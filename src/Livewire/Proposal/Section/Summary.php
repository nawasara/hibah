<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Panel "Data Usulan" — baca saja.
 *
 * Component, bukan partial blade, karena ia menampilkan **status** yang
 * berubah saat panel realisasi menyimpan. Sebagai partial ia akan
 * menampilkan status lama sampai halaman dimuat ulang, dan staf mengira
 * simpanannya gagal.
 */
class Summary extends Component
{
    public ApprovedProposal $proposal;

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
    }

    #[On('proposal-status-changed')]
    public function refreshProposal(): void
    {
        $this->proposal->refresh();
    }

    /**
     * Label peruntukan + bentuk yang dibaca staf, mis. "Hibah · Uang".
     *
     * Diturunkan dari konstanta, bukan diketik ulang di blade — daftar yang
     * disalin ke view perlahan berbeda dari sumbernya.
     */
    public function purposeLabel(): string
    {
        $purpose = ApprovedProposal::PURPOSES[$this->proposal->purpose] ?? $this->proposal->purpose;
        $form = ApprovedProposal::FORMS[$this->proposal->form] ?? $this->proposal->form;

        return "{$purpose} · {$form}";
    }

    public function recipientTypeLabel(): string
    {
        return ApprovedProposal::RECIPIENT_TYPES[$this->proposal->recipient_type]
            ?? $this->proposal->recipient_type;
    }

    /** Null untuk hibah & bansos — hanya BK yang punya sub-jenis. */
    public function bkTypeLabel(): ?string
    {
        if ($this->proposal->bk_type === null) {
            return null;
        }

        return ApprovedProposal::BK_TYPES[$this->proposal->bk_type]
            ?? strtoupper($this->proposal->bk_type);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.summary');
    }
}
