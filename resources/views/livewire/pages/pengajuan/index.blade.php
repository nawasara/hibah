<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Pengajuan']]" />
    </x-slot>

    @php $counts = $this->statusCounts; @endphp

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Pengajuan Hibah & Bansos"
            description="Daftar usulan hibah/bansos. Operator melihat data OPD-nya; admin melihat seluruh OPD."
            :count="$this->rows->total().' total'">
            @can('hibah.pengajuan.create')
                <x-nawasara-ui::button color="primary" :href="route('hibah.pengajuan.create')" wire:navigate>
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Pengajuan
                </x-nawasara-ui::button>
            @endcan
        </x-nawasara-ui::page-header>

        {{-- Status summary --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <x-nawasara-ui::stat-card compact label="Diajukan"
                :value="$counts['diajukan'] ?? 0" color="warning" icon="lucide-file-clock" />
            <x-nawasara-ui::stat-card compact label="Disetujui"
                :value="$counts['disetujui'] ?? 0" color="success" icon="lucide-circle-check" />
            <x-nawasara-ui::stat-card compact label="Ditolak"
                :value="$counts['ditolak'] ?? 0" color="danger" icon="lucide-circle-x" />
            <x-nawasara-ui::stat-card compact label="Selesai"
                :value="$counts['selesai'] ?? 0" color="info" icon="lucide-flag" />
        </div>

        {{-- Toolbar --}}
        <div class="flex flex-col md:flex-row md:items-center gap-2 mb-4">
            <x-nawasara-ui::search-input model="search" placeholder="Cari penerima, pengusul, program..." />

            <select wire:model.live="tahunFilter"
                class="rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100 text-sm">
                <option value="">Semua tahun</option>
                @foreach ($this->tahunOptions as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            <select wire:model.live="statusFilter"
                class="rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100 text-sm">
                <option value="">Semua status</option>
                @foreach (\Nawasara\Hibah\Models\Pengajuan::statusLabels() as $val => $label)
                    <option value="{{ $val }}">{{ $label }}</option>
                @endforeach
            </select>

            @if ($search !== '' || $tahunFilter !== '' || $statusFilter !== '')
                <button wire:click="clearFilters"
                    class="text-xs text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300 underline">
                    Reset filter
                </button>
            @endif
        </div>

        {{-- Table --}}
        <x-nawasara-ui::table stickyLast
            :headers="['Tahun', 'OPD', 'Penerima', 'Kategori', 'Anggaran usulan', 'Status', '']">
            <x-slot:table>
                @forelse ($this->rows as $row)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $row->tahun }}</td>
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">
                            {{ $row->opd?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="text-sm text-neutral-800 dark:text-neutral-100">{{ $row->nama_penerima }}</div>
                            @if ($row->program)
                                <div class="text-xs text-neutral-400 truncate max-w-[260px]">{{ $row->program }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $row->kategori?->nama ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200 whitespace-nowrap">
                            Rp {{ number_format($row->anggaran_sebelum, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5">
                            <x-nawasara-ui::badge :color="$row->statusColor()">{{ $row->statusLabel() }}</x-nawasara-ui::badge>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <x-nawasara-ui::icon-button icon="eye" tooltip="Detail"
                                :href="route('hibah.pengajuan.detail', $row)" wire:navigate />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline
                                icon="lucide-file-x"
                                title="Belum ada pengajuan"
                                description="Klik 'Tambah Pengajuan' untuk meng-entry usulan hibah pertama." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">
            {{ $this->rows->links() }}
        </div>
    </x-nawasara-ui::page.container>
</div>
