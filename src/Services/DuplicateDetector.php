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
     * @return Collection<int, array{
     *   nama: string, alamat: ?string, count: int,
     *   tahun: list<int>, total_anggaran: int, ids: list<int>
     * }>
     */
    public function detect(bool $crossYear = true, ?int $tahun = null): Collection
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
            'anggaran_sebelum', 'anggaran_disetujui',
        ]);

        // Skip rows tanpa alamat: tanpa alamat tidak mungkin tahu apakah
        // dua row dengan nama sama itu memang penerima yang sama atau
        // entitas berbeda yang kebetulan satu nama (mis. "MDT MIFTAHUL
        // HUDA" yang dipakai banyak madrasah). Lebih baik tidak flag
        // daripada salah flag. Auditor harus isi alamat dulu kalau mau
        // dicek.
        $withAddress = $rows->filter(fn (Pengajuan $p) => ! empty($p->alamat_penerima_normalized));

        // Group by NORMALIZED name + NORMALIZED address.
        $grouped = $withAddress->groupBy(fn (Pengajuan $p) => $p->nama_penerima_normalized.'|'.$p->alamat_penerima_normalized);

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
                        fn (Pengajuan $p) => $p->anggaran_disetujui ?? $p->anggaran_sebelum
                    ),
                    'ids' => $group->pluck('id')->all(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }
}
