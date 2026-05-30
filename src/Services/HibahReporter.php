<?php

namespace Nawasara\Hibah\Services;

use Illuminate\Support\Collection;
use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Hibah\Models\Realisasi;

/**
 * Read-side aggregates for the reporting pages. All queries respect the
 * OpdScope on Pengajuan, so an operator sees their OPD only; admin sees all.
 */
class HibahReporter
{
    /**
     * Total usulan vs disetujui vs realisasi, grouped by year.
     *
     * @return Collection<int, array{tahun:int, jumlah:int, usulan:int, disetujui:int, realisasi:int}>
     */
    public function perTahun(): Collection
    {
        // "Usulan" = nominal entered by OPD. The 2024 Excel layout only
        // populated "Anggaran Setelah Perubahan" (post-revision), leaving
        // "Sebelum" zero — coalesce to setelah when sebelum is missing.
        // "Disetujui" prefers the explicit approval column; absent that,
        // fall back to the post-revision figure since SK-bearing rows are
        // marked DISETUJUI on import (see PengajuanImport::importRow).
        $base = Pengajuan::query()
            ->selectRaw('tahun, count(*) as jumlah,
                sum(coalesce(nullif(anggaran_sebelum,0), anggaran_setelah, 0)) as usulan,
                sum(coalesce(anggaran_disetujui, anggaran_setelah, anggaran_sebelum, 0)) as disetujui')
            ->groupBy('tahun')
            ->orderByDesc('tahun')
            ->get();

        // Realisasi total per year via the child table, scoped to the same
        // pengajuan set (sub-select keeps OpdScope honoured).
        $realisasiByYear = Realisasi::query()
            ->join('nawasara_hibah_pengajuan as p', 'p.id', '=', 'nawasara_hibah_realisasi.pengajuan_id')
            ->whereIn('p.id', Pengajuan::query()->select('id'))
            ->selectRaw('p.tahun, sum(realisasi_anggaran) as realisasi')
            ->groupBy('p.tahun')
            ->pluck('realisasi', 'tahun');

        return $base->map(fn ($r) => [
            'tahun' => (int) $r->tahun,
            'jumlah' => (int) $r->jumlah,
            'usulan' => (int) $r->usulan,
            'disetujui' => (int) $r->disetujui,
            'realisasi' => (int) ($realisasiByYear[$r->tahun] ?? 0),
        ]);
    }

    /**
     * Per-OPD breakdown for a given year (or all years).
     *
     * @return Collection<int, array{opd:string, jumlah:int, usulan:int, disetujui:int}>
     */
    public function perOpd(?int $tahun = null): Collection
    {
        return Pengajuan::query()
            ->join('nawasara_registry_opd as o', 'o.id', '=', 'nawasara_hibah_pengajuan.opd_id')
            ->when($tahun, fn ($q) => $q->where('tahun', $tahun))
            ->selectRaw('o.name as opd, count(*) as jumlah,
                sum(coalesce(nullif(anggaran_sebelum,0), anggaran_setelah, 0)) as usulan,
                sum(coalesce(anggaran_disetujui, anggaran_setelah, anggaran_sebelum, 0)) as disetujui')
            ->groupBy('o.name')
            ->orderByDesc('disetujui')
            ->get()
            ->map(fn ($r) => [
                'opd' => $r->opd,
                'jumlah' => (int) $r->jumlah,
                'usulan' => (int) $r->usulan,
                'disetujui' => (int) $r->disetujui,
            ]);
    }

    /**
     * Realisasi summed per quarter for a given year.
     *
     * @return array{1:int, 2:int, 3:int, 4:int, total:int}
     */
    public function perTriwulan(?int $tahun = null): array
    {
        $rows = Realisasi::query()
            ->join('nawasara_hibah_pengajuan as p', 'p.id', '=', 'nawasara_hibah_realisasi.pengajuan_id')
            ->whereIn('p.id', Pengajuan::query()->when($tahun, fn ($q) => $q->where('tahun', $tahun))->select('id'))
            ->selectRaw('triwulan, sum(realisasi_anggaran) as total')
            ->groupBy('triwulan')
            ->pluck('total', 'triwulan');

        $out = [];
        $total = 0;
        foreach ([1, 2, 3, 4] as $tw) {
            $out[$tw] = (int) ($rows[$tw] ?? 0);
            $total += $out[$tw];
        }
        $out['total'] = $total;

        return $out;
    }
}
