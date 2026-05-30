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
                            <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">Rp {{ number_format($row['usulan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-emerald-600 dark:text-emerald-400">Rp {{ number_format($row['disetujui'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-blue-600 dark:text-blue-400">Rp {{ number_format($row['realisasi'], 0, ',', '.') }}</td>
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
                            <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">Rp {{ number_format($row['usulan'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-sm text-emerald-600 dark:text-emerald-400">Rp {{ number_format($row['disetujui'], 0, ',', '.') }}</td>
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

            {{-- stickyLast: kolom action selalu kelihatan di edge kanan.
                 Tooltip pakai placement="left" supaya keluar ke arah kiri
                 (kolom anggaran) — tidak ke-clip oleh sticky stacking
                 context yang akan membungkus tooltip kalau placement
                 default ('top') atau 'right'. --}}
            <x-nawasara-ui::table stickyLast :headers="['Nama Penerima', 'Alamat', 'Jumlah', 'Tahun', 'Total Anggaran', '']">
                <x-slot:table>
                    @forelse ($this->duplikat as $idx => $row)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                            <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $row['nama'] }}</td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300 max-w-[280px] truncate">{{ $row['alamat'] ?? '—' }}</td>
                            <td class="px-4 py-2.5">
                                <x-nawasara-ui::badge color="danger">{{ $row['count'] }}× </x-nawasara-ui::badge>
                            </td>
                            <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ implode(', ', $row['tahun']) }}</td>
                            <td class="px-4 py-2.5 text-sm text-neutral-700 dark:text-neutral-200">Rp {{ number_format($row['total_anggaran'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right">
                                {{-- @click (Alpine): buka modal SEKETIKA dengan skeleton.
                                     Tidak menunggu round-trip ke server.
                                     wire:click (Livewire): jalan paralel, ambil rows
                                     duplikat di server; saat selesai, viewDetail()
                                     dispatch modal-open:<id> (tanpa loading) → modal
                                     switch dari skeleton ke konten.

                                     Pattern dari nawasara-audit (activity-log detail). --}}
                                <x-nawasara-ui::icon-button
                                    icon="list"
                                    tooltip="Lihat {{ $row['count'] }} pengajuan"
                                    placement="left"
                                    x-on:click="$dispatch('open-modal', { id: 'hibah-duplikat-detail', loading: true })"
                                    wire:click="viewDetail({{ $idx }})" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6"><x-nawasara-ui::empty-state inline icon="lucide-shield-check" title="Tidak ada duplikat" description="Tidak ditemukan penerima dengan nama+alamat yang sama." variant="celebrate" /></td></tr>
                    @endforelse
                </x-slot:table>
            </x-nawasara-ui::table>

            {{-- Detail modal — list semua pengajuan dalam group duplikat ini.
                 Dibuka via viewDetail() yang juga dispatch open-modal event;
                 modal Alpine-managed (`id` mode), bukan wire:model, supaya
                 close instan tidak menunggu server roundtrip. --}}
            <x-nawasara-ui::modal
                id="hibah-duplikat-detail"
                :title="$detailName ? 'Pengajuan: '.$detailName : 'Detail Duplikat'"
                maxWidth="3xl">
                @if (! empty($detailIds) && $this->duplikatDetail->isNotEmpty())
                    <p class="text-xs text-neutral-500 dark:text-neutral-400 mb-3">
                        {{ $this->duplikatDetail->count() }} pengajuan dengan nama penerima yang ter-normalisasi sama.
                        Klik baris untuk membuka detail pengajuan.
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide border-b border-neutral-200 dark:border-neutral-700">
                                    <th class="text-left py-2 px-2">Tahun</th>
                                    <th class="text-left py-2 px-2">OPD</th>
                                    <th class="text-left py-2 px-2">Kategori</th>
                                    <th class="text-left py-2 px-2">Alamat</th>
                                    <th class="text-right py-2 px-2">Anggaran</th>
                                    <th class="text-left py-2 px-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->duplikatDetail as $p)
                                    <tr class="border-b border-neutral-100 dark:border-neutral-800 hover:bg-neutral-50 dark:hover:bg-neutral-700/40 cursor-pointer"
                                        wire:click="$dispatch('navigate-to', { url: @js(route('hibah.pengajuan.detail', $p)) })"
                                        onclick="window.location.href = @js(route('hibah.pengajuan.detail', $p))">
                                        <td class="py-2 px-2 text-neutral-700 dark:text-neutral-200">{{ $p->tahun }}</td>
                                        <td class="py-2 px-2 text-neutral-800 dark:text-neutral-100">{{ $p->opd?->name ?? '—' }}</td>
                                        <td class="py-2 px-2 text-neutral-600 dark:text-neutral-300">{{ $p->kategori?->nama ?? '—' }}</td>
                                        <td class="py-2 px-2 text-neutral-600 dark:text-neutral-300 max-w-[200px] truncate" title="{{ $p->alamat_penerima }}">
                                            {{ $p->alamat_penerima ?? '—' }}
                                        </td>
                                        <td class="py-2 px-2 text-right text-neutral-700 dark:text-neutral-200 whitespace-nowrap">
                                            Rp {{ number_format($p->anggaran_disetujui ?? $p->anggaran_sebelum, 0, ',', '.') }}
                                        </td>
                                        <td class="py-2 px-2">
                                            <x-nawasara-ui::badge :color="$p->statusColor()">{{ $p->statusLabel() }}</x-nawasara-ui::badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="border-t-2 border-neutral-200 dark:border-neutral-700">
                                    <td colspan="4" class="py-2 px-2 text-right font-medium text-neutral-700 dark:text-neutral-200">Total</td>
                                    <td class="py-2 px-2 text-right font-bold text-neutral-800 dark:text-neutral-100 whitespace-nowrap">
                                        Rp {{ number_format($this->duplikatDetail->sum(fn ($p) => $p->anggaran_disetujui ?? $p->anggaran_sebelum), 0, ',', '.') }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif

                <x-slot:footer>
                    <x-nawasara-ui::button color="neutral" variant="outline"
                        x-on:click="$dispatch('close-modal', 'hibah-duplikat-detail')"
                        wire:click="closeDetail">Tutup</x-nawasara-ui::button>
                </x-slot:footer>
            </x-nawasara-ui::modal>
        @endif
    </x-nawasara-ui::page.container>
</div>
