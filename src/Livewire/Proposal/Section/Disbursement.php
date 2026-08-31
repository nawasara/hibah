<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Disbursement as DisbursementModel;
use Nawasara\Hibah\Models\StatusHistory;

/**
 * Realisasi pencairan per triwulan.
 *
 * Component ini **mengubah status usulan**, bukan sekadar mencatat angka:
 * jumlah realisasi dibandingkan anggaran disetujui menentukan
 * approved / partially_disbursed / disbursed.
 *
 * Karena itu penyimpanannya satu transaksi — angka, status, dan jejak
 * riwayatnya harus tersimpan bersama atau tidak sama sekali. Status yang
 * tersimpan tanpa riwayatnya meninggalkan lompatan yang tidak dapat
 * dijelaskan saat diaudit.
 */
class Disbursement extends Component
{
    public ApprovedProposal $proposal;

    /** @var array<int, int> triwulan => nominal */
    public array $amounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

    public ?int $undisbursed_budget = null;

    public string $undisbursed_reason = '';

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
        $this->undisbursed_budget = $proposal->undisbursed_budget;
        $this->undisbursed_reason = (string) $proposal->undisbursed_reason;

        foreach ($proposal->disbursements as $row) {
            $this->amounts[$row->quarter] = $row->disbursed_amount;
        }
    }

    #[Computed]
    public function total(): int
    {
        return (int) array_sum($this->amounts);
    }

    /**
     * Status yang AKAN berlaku setelah disimpan.
     *
     * Ditampilkan di panel supaya staf melihat akibat angkanya sebelum
     * menekan simpan — bukan menemukannya berubah sesudahnya.
     */
    #[Computed]
    public function projectedStatus(): string
    {
        if ($this->proposal->isCancelled()) {
            return ApprovedProposal::STATUS_CANCELLED;
        }

        $approved = (int) $this->proposal->approved_budget;
        $total = $this->total();

        return match (true) {
            $total <= 0 => ApprovedProposal::STATUS_APPROVED,
            $approved > 0 && $total >= $approved => ApprovedProposal::STATUS_DISBURSED,
            default => ApprovedProposal::STATUS_PARTIALLY_DISBURSED,
        };
    }

    protected function rules(): array
    {
        return [
            'amounts.1' => ['required', 'integer', 'min:0'],
            'amounts.2' => ['required', 'integer', 'min:0'],
            'amounts.3' => ['required', 'integer', 'min:0'],
            'amounts.4' => ['required', 'integer', 'min:0'],
            'undisbursed_budget' => ['nullable', 'integer', 'min:0'],
            'undisbursed_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function save(): void
    {
        $this->authorize('hibah.disbursement.update');

        // Usulan yang batal tidak menerima pencatatan baru. Tanpa ini,
        // angka yang masuk akan tersimpan pada baris yang sudah dicabut —
        // dan meski statusnya tidak berubah, catatannya jadi menyesatkan.
        if ($this->proposal->isCancelled()) {
            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Usulan sudah dibatalkan. Pulihkan dulu sebelum mencatat realisasi.',
            ]);

            return;
        }

        $this->validate();

        $before = $this->proposal->status;

        DB::transaction(function () use ($before): void {
            foreach (array_keys(DisbursementModel::QUARTERS) as $quarter) {
                $this->proposal->disbursements()->updateOrCreate(
                    ['quarter' => $quarter],
                    [
                        'disbursed_amount' => (int) $this->amounts[$quarter],
                        'updated_by' => auth()->id(),
                    ],
                );
            }

            $this->proposal->undisbursed_budget = $this->undisbursed_budget;
            $this->proposal->undisbursed_reason = $this->undisbursed_reason ?: null;

            // Relasi sudah dimuat sebelum penyimpanan di atas; tanpa
            // memuatnya ulang, sum() menjumlah angka lama.
            $this->proposal->unsetRelation('disbursements');

            if ($this->proposal->recalculateStatus()) {
                StatusHistory::create([
                    'approved_proposal_id' => $this->proposal->getKey(),
                    'from_status' => $before,
                    'to_status' => $this->proposal->status,
                    'by_user_id' => auth()->id(),
                    // Jejaknya menyebut sebabnya, karena tidak ada manusia
                    // yang memilih status ini.
                    'notes' => 'Perubahan otomatis dari pencatatan realisasi.',
                    'created_at' => now(),
                ]);
            }

            $this->proposal->save();
        });

        $this->proposal->refresh();

        // Panel lain menampilkan status dan riwayat — keduanya baru saja
        // berubah, dan tanpa kabar ini staf melihat keadaan lama lalu
        // mengira simpanannya gagal.
        $this->dispatch('proposal-status-changed');

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Realisasi triwulan disimpan.',
        ]);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.disbursement');
    }
}
