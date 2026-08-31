<?php

namespace Nawasara\Hibah\Services;

use Illuminate\Support\Collection;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Disbursement;

/**
 * Read-side aggregates for the reporting pages. All queries respect the
 * OpdScope pada ApprovedProposal, so an operator sees their OPD only; admin sees all.
 */
class HibahReporter
{
    /**
     * Total usulan vs disetujui vs realisasi, grouped by year.
     *
     * @return Collection<int, array{tahun:int, jumlah:int, usulan:int, disetujui:int, realisasi:int}>
     */
    public function perTahun(?string $purpose = null): Collection
    {
        // "Usulan" = nominal entered by OPD. The 2024 Excel layout only
        // populated "Anggaran Setelah Perubahan" (post-revision), leaving
        // "Sebelum" zero — coalesce to setelah when sebelum is missing.
        // "Disetujui" prefers the explicit approval column; absent that,
        // fall back to the post-revision figure since SK-bearing rows are
        // marked DISETUJUI on import (lihat ApprovedProposalImport::importRow).
        $base = ApprovedProposal::query()
            // Laporan tiap menu hanya memuat peruntukannya sendiri (§7).
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->selectRaw('fiscal_year, count(*) as jumlah,
                sum(coalesce(nullif(budget_before,0), budget_after, 0)) as usulan,
                sum(coalesce(approved_budget, budget_after, budget_before, 0)) as disetujui')
            ->groupBy('fiscal_year')
            ->orderByDesc('fiscal_year')
            ->get();

        // Total realisasi per tahun lewat tabel anak, ter-scope ke himpunan
        // usulan yang sama (sub-select menjaga OpdScope tetap berlaku).
        $realisasiByYear = Disbursement::query()
            ->join('nawasara_hibah_approved_proposals as p', 'p.id', '=', 'nawasara_hibah_disbursements.approved_proposal_id')
            ->whereIn('p.id', ApprovedProposal::query()
                ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
                ->select('id'))
            ->selectRaw('p.fiscal_year, sum(disbursed_amount) as realisasi')
            ->groupBy('p.fiscal_year')
            // ⚠️ Kunci kedua HARUS sama dengan alias di selectRaw. Sempat
            // tertinggal 'tahun' setelah kolomnya diganti fiscal_year, dan
            // akibatnya kuncinya null — seluruh angka realisasi jadi 0 tanpa
            // galat apa pun.
            ->pluck('realisasi', 'fiscal_year');

        return $base->map(fn ($r) => [
            'tahun' => (int) $r->fiscal_year,
            'jumlah' => (int) $r->jumlah,
            'usulan' => (int) $r->usulan,
            'disetujui' => (int) $r->disetujui,
            'realisasi' => (int) ($realisasiByYear[$r->fiscal_year] ?? 0),
        ]);
    }

    /**
     * Per-OPD breakdown for a given year (or all years).
     *
     * @return Collection<int, array{opd:string, jumlah:int, usulan:int, disetujui:int}>
     */
    public function perOpd(?int $tahun = null, ?string $purpose = null): Collection
    {
        return ApprovedProposal::query()
            ->join('nawasara_registry_opd as o', 'o.id', '=', 'nawasara_hibah_approved_proposals.opd_id')
            ->when($tahun, fn ($q) => $q->where('fiscal_year', $tahun))
            ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
            ->selectRaw('o.name as opd, count(*) as jumlah,
                sum(coalesce(nullif(budget_before,0), budget_after, 0)) as usulan,
                sum(coalesce(approved_budget, budget_after, budget_before, 0)) as disetujui')
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
    public function perTriwulan(?int $tahun = null, ?string $purpose = null): array
    {
        $rows = Disbursement::query()
            ->join('nawasara_hibah_approved_proposals as p', 'p.id', '=', 'nawasara_hibah_disbursements.approved_proposal_id')
            ->whereIn('p.id', ApprovedProposal::query()
                ->when($tahun, fn ($q) => $q->where('fiscal_year', $tahun))
                ->when($purpose, fn ($q) => $q->where('purpose', $purpose))
                ->select('id'))
            ->selectRaw('quarter, sum(disbursed_amount) as total')
            ->groupBy('quarter')
            ->pluck('total', 'quarter');

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
