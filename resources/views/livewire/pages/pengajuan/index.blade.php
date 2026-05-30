<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Pengajuan']]" />
    </x-slot>

    @php $counts = $this->statusCounts; @endphp

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Pengajuan Hibah & Bansos"
            description="Daftar usulan hibah/bansos. Operator melihat data OPD-nya; admin melihat seluruh OPD."
            :count="$this->rows->total() . ' total'">
            @can('hibah.pengajuan.create')
                <x-nawasara-ui::button color="primary" :href="route('hibah.pengajuan.create')" wire:navigate>
                    <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                    Tambah Pengajuan
                </x-nawasara-ui::button>
            @endcan
        </x-nawasara-ui::page-header>

        {{-- Status summary --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-4">
            <x-nawasara-ui::stat-card compact label="Diajukan" :value="$counts['diajukan'] ?? 0" color="warning"
                icon="lucide-file-clock" />
            <x-nawasara-ui::stat-card compact label="Disetujui" :value="$counts['disetujui'] ?? 0" color="success"
                icon="lucide-circle-check" />
            <x-nawasara-ui::stat-card compact label="Ditolak" :value="$counts['ditolak'] ?? 0" color="danger" icon="lucide-circle-x" />
            <x-nawasara-ui::stat-card compact label="Selesai" :value="$counts['selesai'] ?? 0" color="info" icon="lucide-flag" />
        </div>

        {{-- Toolbar --}}
        @php
            $statusLabels = \Nawasara\Hibah\Models\Pengajuan::statusLabels();
            $opdOptions = $this->opdOptions;
            $kategoriOptions = $this->kategoriOptions;
            $tahunOptions = $this->tahunOptions;
        @endphp

        {{-- Toolbar (WHM-style): satu tombol Filter membuka panel dengan
             rail kategori (Tahun / Status / OPD / Kategori) di kiri + items
             di kanan. Tahun & Status single-select (tidak di $multiple),
             OPD & Kategori multi-select. Chip aktif teleport ke
             [data-filter-chips] di bawah. --}}
        <div class="space-y-2 mb-4">
            <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-nawasara-ui::filter-panel label="Filter" :state="[
                        'tahunFilter' => $tahunFilter,
                        'statusFilter' => $statusFilter,
                        'opdFilter' => $opdFilter,
                        'kategoriFilter' => $kategoriFilter,
                    ]" :multiple="['opdFilter', 'kategoriFilter']" :labels="[
                        'tahunFilter' => $tahunOptions,
                        'statusFilter' => $statusLabels,
                        'opdFilter' => $opdOptions,
                        'kategoriFilter' => $kategoriOptions,
                    ]"
                        :dimensions="[
                            'tahunFilter' => 'Tahun',
                            'statusFilter' => 'Status',
                            'opdFilter' => 'OPD',
                            'kategoriFilter' => 'Kategori',
                        ]">
                        <x-nawasara-ui::filter-group label="Tahun" model="tahunFilter" :items="$tahunOptions"
                            icon="lucide-calendar" />
                        <x-nawasara-ui::filter-group label="Status" model="statusFilter" :items="$statusLabels"
                            icon="lucide-circle-check" />
                        <x-nawasara-ui::filter-group label="OPD" model="opdFilter" :items="$opdOptions"
                            icon="lucide-building-2" />
                        <x-nawasara-ui::filter-group label="Kategori" model="kategoriFilter" :items="$kategoriOptions"
                            icon="lucide-tags" />
                    </x-nawasara-ui::filter-panel>
                </div>

                <x-nawasara-ui::search-input model="search" placeholder="Cari penerima, pengusul, program..." />
            </div>

            {{-- Filter-chip teleport target. wire:ignore: chips dikelola
                 Alpine sepenuhnya — Livewire morph akan menghapus children
                 teleported kalau tidak di-ignore. --}}
            <div wire:ignore data-filter-chips></div>

            @if ($search !== '')
                <div class="flex flex-wrap items-center gap-2">
                    <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
                </div>
            @endif
        </div>

        {{-- Table --}}
        <x-nawasara-ui::table stickyLast :headers="['Tahun', 'OPD', 'Penerima', 'Kategori', 'Anggaran usulan', 'Status', '']">
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
                            <x-nawasara-ui::icon-button icon="eye" tooltip="Detail" placement="left"
                                :href="route('hibah.pengajuan.detail', $row)" wire:navigate />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline icon="lucide-file-x" title="Belum ada pengajuan"
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
