<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal;

use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Kerangka halaman daftar — tipis dengan sengaja.
 *
 * Tidak memuat query, saringan, maupun paginasi; semuanya di
 * Section\Table. Yang di sini hanya menerjemahkan segmen rute jadi konteks
 * dan menyusun remah roti.
 */
class Index extends Component
{
    public string $purposeSegment = '';

    public string $segment = '';

    public string $purpose = '';

    public ?string $form = null;

    public ?string $bkType = null;

    public function mount(string $purpose, string $segment): void
    {
        abort_unless(ApprovedProposal::isValidSegmentPair($purpose, $segment), 404);

        $this->purposeSegment = $purpose;
        $this->segment = $segment;
        $this->purpose = ApprovedProposal::purposeFromSegment($purpose);

        // Segmen kedua berarti hal yang berbeda tergantung peruntukan:
        // bentuk untuk hibah/bansos, sub-jenis untuk BK. Diterjemahkan di
        // sini supaya Table menerima nilai yang siap dipakai menyaring.
        if ($this->purpose === ApprovedProposal::PURPOSE_BK) {
            $this->form = ApprovedProposal::FORM_UANG;
            $this->bkType = $segment;   // 'umum' | 'khusus'
        } else {
            $this->form = $segment;     // 'uang' | 'barang'
        }
    }

    /** Judul halaman mengikuti menu, mis. "Hibah Uang" atau "Umum". */
    public function title(): string
    {
        if ($this->purpose === ApprovedProposal::PURPOSE_BK) {
            return $this->segment === 'umum' ? 'Bantuan Keuangan Umum' : 'Bantuan Keuangan Khusus';
        }

        return sprintf(
            '%s %s',
            ApprovedProposal::PURPOSES[$this->purpose],
            ApprovedProposal::FORMS[$this->form],
        );
    }

    public function workspaceLabel(): string
    {
        return ApprovedProposal::PURPOSES[$this->purpose] ?? '';
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
