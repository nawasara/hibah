<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => \Nawasara\Hibah\Models\ApprovedProposal::PURPOSES[$proposal->purpose] ?? '', 'url' => '#'],
                [
                    'label' => 'Daftar',
                    'url' => route('hibah.proposals.index', [
                        'purpose' => $purpose, 'segment' => $segment,
                    ]),
                ],
                ['label' => 'Detail'],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            :title="$proposal->recipient_name"
            :description="'Usulan disahkan · tahun anggaran '.$proposal->fiscal_year">

            <x-nawasara-ui::button
                color="neutral"
                icon="lucide-pencil"
                href="{{ route('hibah.proposals.edit', [
                    'purpose' => $purpose, 'segment' => $segment, 'proposal' => $proposal->id,
                ]) }}"
                wire:navigate>
                Ubah
            </x-nawasara-ui::button>
        </x-nawasara-ui::page-header>

        {{-- Tiap panel component sendiri dengan satu tombol simpan.
             Sebelumnya keempatnya satu kelas, dan mengunggah bukti monev
             me-render ulang seluruh halaman termasuk tabel yang tak berubah. --}}
        <div class="space-y-4">
            <livewire:nawasara-hibah.proposal.section.summary
                :proposal="$proposal" :key="'summary-'.$proposal->id" />

            <livewire:nawasara-hibah.proposal.section.disbursement
                :proposal="$proposal" :key="'disbursement-'.$proposal->id" />

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <livewire:nawasara-hibah.proposal.section.monev
                    :proposal="$proposal" :key="'monev-'.$proposal->id" />

                <livewire:nawasara-hibah.proposal.section.cancel
                    :proposal="$proposal" :key="'cancel-'.$proposal->id" />
            </div>

            <livewire:nawasara-hibah.proposal.section.status-history
                :proposal="$proposal" :key="'history-'.$proposal->id" />
        </div>
    </x-nawasara-ui::page.container>
</div>
