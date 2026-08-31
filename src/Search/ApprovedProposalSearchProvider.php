<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Search;

use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Search\Contracts\SearchProvider;

/**
 * Penyedia hasil untuk palet ⌘K.
 *
 * ⚠️ Rutenya sekarang **bersegmen** — `hibah/{purpose}/{segment}/{id}` —
 * jadi tautannya tidak dapat disusun dari id saja. Sebelum v0.2.0 provider
 * ini memanggil `route('hibah.pengajuan.detail', $id)`; rute itu sudah tidak
 * ada, dan galatnya muncul **saat pencarian dijalankan**, bukan saat boot.
 * Artinya ia lolos seluruh smoke test dan baru meledak ketika staf pertama
 * menekan ⌘K.
 */
class ApprovedProposalSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'hibah-approved-proposal';
    }

    public function label(): string
    {
        return 'Usulan Disahkan';
    }

    /**
     * Satu permission untuk seluruh hasil.
     *
     * Palet menyaring per penyedia, bukan per baris, jadi awalan `.view`
     * yang paling longgar dipakai di sini — penyaringan sungguhan tetap
     * terjadi di query lewat ScopedToOpd, dan rute detailnya digerbang
     * permission per-peruntukan.
     */
    public function permission(): ?string
    {
        return 'hibah.approved-proposal.view';
    }

    public function search(string $term, int $limit): array
    {
        // ScopedToOpd memasang global scope, jadi query ini sudah tersaring
        // ke OPD pengguna (role privileged melihat semua; yang tanpa
        // membership tidak melihat apa pun).
        return ApprovedProposal::query()
            ->with('opd:id,name')
            ->where(function ($q) use ($term) {
                $q->where('recipient_name', 'like', "%{$term}%")
                    ->orWhere('recipient_address', 'like', "%{$term}%")
                    ->orWhere('decree', 'like', "%{$term}%");
            })
            ->orderByDesc('fiscal_year')
            ->limit($limit)
            ->get()
            ->map(fn (ApprovedProposal $p) => [
                'label' => $p->recipient_name,
                'sublabel' => $this->sublabel($p),
                'url' => $this->detailUrl($p),
            ])
            ->all();
    }

    private function sublabel(ApprovedProposal $p): string
    {
        $parts = array_filter([
            $p->fiscal_year,
            ApprovedProposal::PURPOSES[$p->purpose] ?? null,
            $p->opd->name ?? null,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * Tautan ke halaman detail di menu yang benar.
     *
     * Peruntukan menentukan segmen pertama, bentuk (atau sub-jenis BK)
     * menentukan yang kedua. Usulan hibah harus membuka detail di bawah
     * menu Hibah — bukan di menu lain yang akan menolaknya dengan 404.
     */
    private function detailUrl(ApprovedProposal $p): string
    {
        $purposeSegment = ApprovedProposal::segmentFromPurpose($p->purpose) ?? 'hibah';

        // BK tanpa sub-jenis dianggap 'umum', bukan 'khusus': khusus berarti
        // ADD/PD yang ditetapkan, sedangkan yang kosong justru belum
        // dikhususkan. Menebak sebaliknya membuka menu yang tidak memuatnya.
        $childSegment = $p->purpose === ApprovedProposal::PURPOSE_BK
            ? (in_array($p->bk_type, ['add', 'pd'], true) ? 'khusus' : 'umum')
            : $p->form;

        return route('hibah.proposals.detail', [
            'purpose' => $purposeSegment,
            'segment' => $childSegment,
            'proposal' => $p->getKey(),
        ]);
    }
}
