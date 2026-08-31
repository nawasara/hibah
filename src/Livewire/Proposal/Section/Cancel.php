<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\StatusHistory;

/**
 * Pembatalan usulan — satu-satunya perubahan status yang dinyatakan manusia.
 *
 * Semua status lain dihitung dari realisasi. Pembatalan tidak meninggalkan
 * jejak angka, jadi tidak ada yang bisa menyimpulkannya.
 *
 * ⚠️ `cancelled` BUKAN "ditolak". Ditolak berarti tidak pernah disahkan;
 * dibatalkan berarti sudah sah lalu dicabut. Menyatukan keduanya membuat
 * catatan bertentangan dengan SK yang sungguh ada.
 */
class Cancel extends Component
{
    public ApprovedProposal $proposal;

    /**
     * Alasan WAJIB, bukan opsional.
     *
     * Usulan batal tanpa keterangan akan ditanyakan saat audit, dan yang
     * mengetahui alasannya sudah lupa. Biaya mengetiknya sekarang jauh
     * lebih kecil daripada merekonstruksinya setahun kemudian.
     */
    public string $reason = '';

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
    }

    public function cancel(): void
    {
        $this->authorize('hibah.approved-proposal.update');

        $this->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], [
            'reason' => 'Alasan pembatalan',
        ]);

        $before = $this->proposal->status;

        DB::transaction(function () use ($before): void {
            $this->proposal->status = ApprovedProposal::STATUS_CANCELLED;
            $this->proposal->save();

            StatusHistory::create([
                'approved_proposal_id' => $this->proposal->getKey(),
                'from_status' => $before,
                'to_status' => ApprovedProposal::STATUS_CANCELLED,
                'by_user_id' => auth()->id(),
                'notes' => $this->reason,
                'created_at' => now(),
            ]);
        });

        $this->reset('reason');
        $this->proposal->unsetRelation('disbursements');
        $this->proposal->refresh();

        $this->dispatch('modal-close:hibah-proposal-cancel');
        $this->dispatch('proposal-status-changed');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Usulan dibatalkan.']);
    }

    /**
     * Mencabut pembatalan.
     *
     * Setelah dipulihkan, angka realisasi kembali menentukan statusnya —
     * jadi status yang tepat dihitung, bukan dikembalikan ke nilai lama
     * yang mungkin sudah tidak cocok dengan angkanya.
     */
    public function restore(): void
    {
        $this->authorize('hibah.approved-proposal.update');

        $before = $this->proposal->status;

        DB::transaction(function () use ($before): void {
            // Lepas dari 'cancelled' dulu, karena recalculateStatus()
            // sengaja menolak menimpa pembatalan.
            $this->proposal->status = ApprovedProposal::STATUS_APPROVED;
            $this->proposal->recalculateStatus();
            $this->proposal->save();

            StatusHistory::create([
                'approved_proposal_id' => $this->proposal->getKey(),
                'from_status' => $before,
                'to_status' => $this->proposal->status,
                'by_user_id' => auth()->id(),
                'notes' => 'Pembatalan dicabut.',
                'created_at' => now(),
            ]);
        });

        $this->proposal->unsetRelation('disbursements');
        $this->proposal->refresh();

        $this->dispatch('proposal-status-changed');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Usulan dipulihkan.']);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.cancel');
    }
}
