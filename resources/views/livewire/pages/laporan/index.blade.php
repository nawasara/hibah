<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Laporan']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Laporan Hibah & Bansos"
            description="Rekap per tahun, per OPD, realisasi triwulan, dan deteksi penerima duplikat.">
            @can('hibah.laporan.export')
                @php
                    $tahunItems = ['all' => 'Semua tahun'] + $this->tahunOptions;
                    $tahunLabel = $tahunFilter !== '' ? "Tahun · {$tahunFilter}" : 'Semua tahun';
                @endphp
                <x-nawasara-ui::filter-dropdown
                    :label="$tahunLabel"
                    :items="$tahunItems"
                    model="tahunFilter" />
                <x-nawasara-ui::button wire:click="export" color="success" variant="outline">
                    <x-slot:icon><x-lucide-file-spreadsheet class="size-4" /></x-slot:icon>
                    Export Excel
                </x-nawasara-ui::button>
            @endcan
        </x-nawasara-ui::page-header>

        {{-- Tabs --}}
        <div class="flex gap-1 border-b border-neutral-200 dark:border-neutral-700 mb-4">
            @foreach (['tahun' => 'Per Tahun', 'opd' => 'Per OPD', 'triwulan' => 'Realisasi Triwulan', 'duplikat' => 'Deteksi Duplikat'] as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                        'border-emerald-500 text-emerald-600 dark:text-emerald-400' => $tab === $key,
                        'border-transparent text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300' => $tab !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Per Tahun --}}
        @if ($tab === 'tahun')
            <x-nawasara-ui::table :headers="['Tahun', 'Jumlah', 'Total Usulan', 'Total Disetujui', 'Total Realisasi']">
                <x-slot:table>
                    @forelse ($this->perTahun as $row)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                            <td class="px-4 py-2.5 font-medium text-neutral-800 dark:text-neutral-100">{{ $row['tahun'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $row['jumlah'] }}</td>
                            <td class="px-4 py-2.5 text-sm">Rp {{ number_format($row['usulan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-emerald-600">Rp {{ number_format($row['disetujui'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-blue-600">Rp {{ number_format($row['realisasi'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6"><x-nawasara-ui::empty-state inline icon="lucide-chart-bar" title="Belum ada data" description="Belum ada pengajuan untuk direkap." /></td></tr>
                    @endforelse
                </x-slot:table>
            </x-nawasara-ui::table>

        {{-- Per OPD --}}
        @elseif ($tab === 'opd')
            <x-nawasara-ui::table :headers="['OPD', 'Jumlah', 'Total Usulan', 'Total Disetujui']">
                <x-slot:table>
                    @forelse ($this->perOpd as $row)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                            <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $row['opd'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $row['jumlah'] }}</td>
                            <td class="px-4 py-2.5 text-sm">Rp {{ number_format($row['usulan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-emerald-600">Rp {{ number_format($row['disetujui'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6"><x-nawasara-ui::empty-state inline icon="lucide-building" title="Belum ada data" description="Belum ada pengajuan per OPD." /></td></tr>
                    @endforelse
                </x-slot:table>
            </x-nawasara-ui::table>

        {{-- Realisasi Triwulan --}}
        @elseif ($tab === 'triwulan')
            @php $tw = $this->perTriwulan; @endphp
            <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                <x-nawasara-ui::stat-card compact label="Triwulan I" :value="'Rp '.number_format($tw[1], 0, ',', '.')" color="primary" icon="lucide-calendar" />
                <x-nawasara-ui::stat-card compact label="Triwulan II" :value="'Rp '.number_format($tw[2], 0, ',', '.')" color="primary" icon="lucide-calendar" />
                <x-nawasara-ui::stat-card compact label="Triwulan III" :value="'Rp '.number_format($tw[3], 0, ',', '.')" color="primary" icon="lucide-calendar" />
                <x-nawasara-ui::stat-card compact label="Triwulan IV" :value="'Rp '.number_format($tw[4], 0, ',', '.')" color="primary" icon="lucide-calendar" />
                <x-nawasara-ui::stat-card compact label="Total" :value="'Rp '.number_format($tw['total'], 0, ',', '.')" color="success" icon="lucide-coins" />
            </div>

        {{-- Deteksi Duplikat --}}
        @elseif ($tab === 'duplikat')
            <div class="mb-3 flex items-center gap-3">
                <x-nawasara-ui::form.checkbox
                    wire:model.live="crossYear"
                    label="Bandingkan lintas tahun" />
                <span class="text-xs text-neutral-400">— penerima dengan nama+alamat yang dinormalisasi sama.</span>
            </div>

            <x-nawasara-ui::table :headers="['Nama Penerima', 'Alamat', 'Jumlah', 'Tahun', 'Total Anggaran']">
                <x-slot:table>
                    @forelse ($this->duplikat as $row)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                            <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $row['nama'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300 max-w-[280px] truncate">{{ $row['alamat'] ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <x-nawasara-ui::badge color="danger">{{ $row['count'] }}× </x-nawasara-ui::badge>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ implode(', ', $row['tahun']) }}</td>
                            <td class="px-4 py-2.5 text-sm">Rp {{ number_format($row['total_anggaran'], 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6"><x-nawasara-ui::empty-state inline icon="lucide-shield-check" title="Tidak ada duplikat" description="Tidak ditemukan penerima dengan nama+alamat yang sama." variant="celebrate" /></td></tr>
                    @endforelse
                </x-slot:table>
            </x-nawasara-ui::table>
        @endif
    </x-nawasara-ui::page.container>
</div>
