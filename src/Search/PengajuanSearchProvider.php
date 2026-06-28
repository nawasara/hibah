<?php

namespace Nawasara\Hibah\Search;

use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Search\Contracts\SearchProvider;

class PengajuanSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'hibah-pengajuan';
    }

    public function label(): string
    {
        return 'Pengajuan Hibah';
    }

    public function permission(): ?string
    {
        return 'hibah.pengajuan.view';
    }

    public function search(string $term, int $limit): array
    {
        // ScopedToOpd applies a global scope, so this query is already filtered
        // to the current user's OPD (privileged roles see all; non-members see
        // nothing). No extra tenant handling needed here.
        return Pengajuan::query()
            ->with('opd:id,name')
            ->search($term)
            ->orderByDesc('tahun')
            ->limit($limit)
            ->get()
            ->map(fn (Pengajuan $p) => [
                'label' => $p->nama_penerima,
                'sublabel' => trim(($p->tahun ? $p->tahun.' · ' : '').($p->opd->name ?? ''), ' ·'),
                // Deep-link straight to the record's detail page.
                'url' => route('hibah.pengajuan.detail', $p->getKey()),
            ])
            ->all();
    }
}
