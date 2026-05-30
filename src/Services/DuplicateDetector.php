<?php

namespace Nawasara\Hibah\Services;

use Illuminate\Support\Collection;
use Nawasara\Hibah\Models\Pengajuan;

/**
 * Finds potential duplicate / double-funded recipients by grouping on the
 * NORMALIZED name + NORMALIZED address (lowercase, punctuation-stripped,
 * space-collapsed — derived on save by Pengajuan::normalize). This catches
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
     *   alamat_penerima_normalized is populated participate — and grouping
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
    public function detect(bool $crossYear = true, ?int $tahun = null, bool $requireAddress = true): Collection
    {
        // OpdScope still applies — an operator sees duplicates only within
        // their own OPD. Admin (no operator row) sees across all OPD, which
        // is the intended cross-OPD double-funding view.
        $query = Pengajuan::query()
            ->whereNotNull('nama_penerima_normalized')
            ->when($tahun, fn ($q) => $q->where('tahun', $tahun));

        $rows = $query->get([
            'id', 'tahun', 'nama_penerima', 'nama_penerima_normalized',
            'alamat_penerima', 'alamat_penerima_normalized',
            'anggaran_sebelum', 'anggaran_setelah', 'anggaran_disetujui',
        ]);

        if ($requireAddress) {
            // Skip rows without alamat: cannot prove same-recipient without
            // it. False-negative is preferred over false-positive.
            $candidates = $rows->filter(fn (Pengajuan $p) => ! empty($p->alamat_penerima_normalized));
            $grouped = $candidates->groupBy(fn (Pengajuan $p) => $p->nama_penerima_normalized.'|'.$p->alamat_penerima_normalized);
        } else {
            // Looser mode for years where OPD didn't populate addresses
            // (2025). Auditor must manually verify each hit. Group by
            // nama only; surface the row whether alamat is null or not.
            $grouped = $rows->groupBy(fn (Pengajuan $p) => $p->nama_penerima_normalized);
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
                    return $group->groupBy('tahun')->contains(fn ($g) => $g->count() >= 2);
                }

                return true;
            })
            ->map(function (Collection $group) {
                $first = $group->first();

                // Semua row di group berbagi alamat ternormalisasi yang sama.
                // Ambil alamat dari row pertama (versi asli, belum ter-
                // normalisasi) supaya auditor lihat label aslinya.
                return [
                    'nama' => $first->nama_penerima,
                    'alamat' => $first->alamat_penerima,
                    'count' => $group->count(),
                    'tahun' => $group->pluck('tahun')->unique()->sort()->values()->all(),
                    'total_anggaran' => (int) $group->sum(
                        fn (Pengajuan $p) => $p->anggaran_disetujui
                            ?? $p->anggaran_setelah
                            ?? $p->anggaran_sebelum
                            ?? 0
                    ),
                    'ids' => $group->pluck('id')->all(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }
}
