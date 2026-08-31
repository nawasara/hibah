<?php

namespace Nawasara\Hibah\Services;

use Illuminate\Support\Collection;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Finds potential duplicate / double-funded recipients by grouping on the
 * NORMALIZED name + NORMALIZED address (lowercase, punctuation-stripped,
 * space-collapsed — diturunkan saat simpan oleh ApprovedProposal::normalize). This catches
 * the real-world inconsistency in the source Excel ("MI Muhammadiyah 14
 * Beton" vs "MI MUHAMMADIYAH 14 BETON.") that an exact match would miss,
 * tanpa over-flag nama umum seperti "MDT MIFTAHUL HUDA" yang dipakai
 * banyak madrasah di alamat berbeda.
 *
 * Trade-off: alamat dengan typo halus ("Jl." vs "Jln") lolos. Untuk
 * audit anti-double-funding, **same name + same address** adalah sinyal
 * yang lebih bermakna daripada nama-saja yang menghasilkan banyak noise.
 *
 * Cross-year is the default: the same recipient receiving a grant in
 * multiple years is precisely the signal an auditor wants to see.
 */
class DuplicateDetector
{
    /**
     * @param  bool  $requireAddress  When true (default), only rows whose
     *   recipient_address_normalized is populated participate — and grouping
     *   keys on nama+alamat to avoid false positives from common
     *   institution names ("MDT MIFTAHUL HUDA"). When false, falls back to
     *   nama-only grouping so years like 2025 (where OPD staff left
     *   addresses blank in Excel) still surface potential duplicates for
     *   manual review.
     * @return Collection<int, array{
     *   nama: string, alamat: ?string, count: int,
     *   tahun: list<int>, total_anggaran: int, ids: list<int>
     * }>
     */
    public function detect(
        bool $crossYear = true,
        ?int $tahun = null,
        bool $requireAddress = true,
        ?string $purpose = null,
    ): Collection
    {
        // OpdScope still applies — an operator sees duplicates only within
        // their own OPD. Admin (no operator row) sees across all OPD, which
        // is the intended cross-OPD double-funding view.
        $query = ApprovedProposal::query()
            // ⚠️ Bantuan Keuangan DIKECUALIKAN, dan ini ditulis di sini —
            // bukan sebagai saringan di halaman — supaya pemanggil
            // berikutnya (ekspor, pemeriksaan impor) tidak melewatkannya.
            //
            // BK mengalir ke pemerintah desa, dan desa yang sama memang
            // menerima tiap tahun: itu cara ADD bekerja. Menandainya
            // duplikat berarti menuduh penyaluran yang benar sebagai
            // kejanggalan — dan dengan 1.124 baris BK di produksi, temuan
            // hibah/bansos yang sungguh perlu ditinjau akan tenggelam.
            ->duplicateCheckable()
            // Laporan tiap menu memeriksa peruntukannya sendiri. Tanpa ini,
            // laporan Hibah akan menampilkan duplikat bansos.
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->whereNotNull('recipient_name_normalized')
            ->when($tahun, fn ($q) => $q->where('fiscal_year', $tahun));

        $rows = $query->get([
            'id', 'fiscal_year', 'recipient_name', 'recipient_name_normalized',
            'recipient_address', 'recipient_address_normalized',
            'budget_before', 'budget_after', 'approved_budget',
        ]);

        if ($requireAddress) {
            // Skip rows without alamat: cannot prove same-recipient without
            // it. False-negative is preferred over false-positive.
            $candidates = $rows->filter(fn (ApprovedProposal $p) => ! empty($p->recipient_address_normalized));
            $grouped = $candidates->groupBy(fn (ApprovedProposal $p) => $p->recipient_name_normalized.'|'.$p->recipient_address_normalized);
        } else {
            // Looser mode for years where OPD didn't populate addresses
            // (2025). Auditor must manually verify each hit. Group by
            // nama only; surface the row whether alamat is null or not.
            $grouped = $rows->groupBy(fn (ApprovedProposal $p) => $p->recipient_name_normalized);
        }

        return $grouped
            ->filter(function (Collection $group) use ($crossYear) {
                if ($group->count() < 2) {
                    return false;
                }

                // When NOT cross-year, only flag groups where ≥2 rows share
                // the same year (true intra-year duplicate). Cross-year shows
                // every recurring recipient regardless of year spread.
                if (! $crossYear) {
                    return $group->groupBy('fiscal_year')->contains(fn ($g) => $g->count() >= 2);
                }

                return true;
            })
            ->map(function (Collection $group) {
                $first = $group->first();

                // Semua row di group berbagi alamat ternormalisasi yang sama.
                // Ambil alamat dari row pertama (versi asli, belum ter-
                // normalisasi) supaya auditor lihat label aslinya.
                return [
                    'nama' => $first->recipient_name,
                    'alamat' => $first->recipient_address,
                    'count' => $group->count(),
                    'tahun' => $group->pluck('fiscal_year')->unique()->sort()->values()->all(),
                    'total_anggaran' => (int) $group->sum(
                        fn (ApprovedProposal $p) => $p->approved_budget
                            ?? $p->budget_after
                            ?? $p->budget_before
                            ?? 0
                    ),
                    'ids' => $group->pluck('id')->all(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }
}
