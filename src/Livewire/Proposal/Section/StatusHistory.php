<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Jejak perubahan status.
 *
 * Justru karena statusnya kini dihitung otomatis, panel ini penting: tanpa
 * jejaknya, riwayat sebuah usulan melompat dari "Disahkan" ke "Cair" tanpa
 * menyebut siapa dan kapan — dan itulah yang dicari ketika angkanya
 * dipertanyakan.
 *
 * Punya query sendiri, jadi ia component (bukan partial) supaya dapat
 * menyegarkan diri saat realisasi disimpan di panel sebelah.
 */
class StatusHistory extends Component
{
    public ApprovedProposal $proposal;

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
    }

    /**
     * @return Collection<int, \Nawasara\Hibah\Models\StatusHistory>
     */
    #[Computed]
    public function entries(): Collection
    {
        return $this->proposal
            ->statusHistories()
            ->with('byUser:id,name')
            ->get();
    }

    /**
     * Disbursement dan Cancel menyiarkan ini setelah menyimpan.
     *
     * Cukup melupakan cache computed — Livewire me-render ulang dan
     * query-nya berjalan lagi.
     */
    #[On('proposal-status-changed')]
    public function refreshEntries(): void
    {
        $this->proposal->refresh();
        unset($this->entries);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.status-history');
    }
}
