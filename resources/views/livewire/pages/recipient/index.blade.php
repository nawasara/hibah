<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Bantuan Daerah', 'url' => '#'],
                ['label' => 'Penerima'],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Penerima"
            description="Satu baris per penerima — lintas hibah, bansos, dan bantuan keuangan."
            :count="$this->total" />

        <div class="space-y-2 mb-4">
            <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <x-nawasara-ui::filter-panel
                        label="Filter"
                        :state="[
                            'typeFilter' => $typeFilter,
                            'purposeFilter' => $purposeFilter,
                            'yearFilter' => $yearFilter,
                        ]"
                        :labels="[
                            'typeFilter' => $this->typeOptions,
                            'purposeFilter' => $this->purposeOptions,
                            'yearFilter' => $this->yearOptions,
                        ]">

                        <x-nawasara-ui::filter-group
                            label="Jenis Penerima" model="typeFilter"
                            :items="$this->typeOptions" icon="lucide-users" />

                        <x-nawasara-ui::filter-group
                            label="Peruntukan" model="purposeFilter"
                            :items="$this->purposeOptions" icon="lucide-hand-coins" />

                        <x-nawasara-ui::filter-group
                            label="Tahun" model="yearFilter"
                            :items="$this->yearOptions" icon="lucide-calendar" />
                    </x-nawasara-ui::filter-panel>
                </div>

                <x-nawasara-ui::search-input model="search" placeholder="Cari nama atau alamat penerima..." />
            </div>

            {{-- ⚠️ WAJIB: filter-panel meneleportasikan chip ke sini. --}}
            <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
        </div>

        @if ($this->rows->isEmpty())
            @if ($this->hasActiveFilter())
                <x-nawasara-ui::empty-state
                    icon="lucide-search-x"
                    title="Tidak ada yang cocok"
                    description="Ubah kata kunci atau saringannya.">
                    <x-nawasara-ui::button color="neutral" wire:click="resetFilters">
                        Bersihkan saringan
                    </x-nawasara-ui::button>
                </x-nawasara-ui::empty-state>
            @else
                <x-nawasara-ui::empty-state
                    icon="lucide-users-round"
                    title="Belum ada penerima"
                    description="Penerima terdaftar sendiri saat usulan dicatat atau diimpor." />
            @endif
        @else
            <x-nawasara-ui::table
                :headers="['Penerima', 'Jenis', 'Peruntukan', 'Kali Menerima', 'Total Anggaran']">

                {{-- ⚠️ WAJIB <x-slot:table> — tanpa ini barisnya kosong tanpa galat. --}}
                <x-slot:table>
                    @foreach ($this->rows as $row)
                        <tr wire:key="recipient-{{ $row->id }}">
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-neutral-800 dark:text-neutral-100">
                                    {{ $row->name }}
                                </div>
                                @if ($row->address)
                                    <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                        {{ \Illuminate\Support\Str::limit($row->address, 70) }}
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                                {{ $row->typeLabel() }}
                            </td>

                            <td class="px-4 py-3 text-sm">
                                {{-- Penerima yang muncul di lebih dari satu
                                     peruntukan langsung terlihat di sini —
                                     tidak terbaca dari daftar usulan mana pun. --}}
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($this->purposesFor($row) as $purpose)
                                        <x-nawasara-ui::badge color="neutral">{{ $purpose }}</x-nawasara-ui::badge>
                                    @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-3 text-sm text-right text-neutral-700 dark:text-neutral-200 tabular-nums">
                                {{ $row->proposals_count }}×
                            </td>

                            <td class="px-4 py-3 text-sm text-right font-medium text-emerald-700 dark:text-emerald-400 tabular-nums">
                                Rp {{ number_format((int) $row->total_budget, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </x-slot:table>
            </x-nawasara-ui::table>

            <div class="mt-4">
                {{ $this->rows->links() }}
            </div>
        @endif
    </x-nawasara-ui::page.container>
</div>
