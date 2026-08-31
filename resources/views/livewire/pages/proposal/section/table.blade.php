<div>
    @php
        $statusColor = [
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_APPROVED => 'neutral',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_PARTIALLY_DISBURSED => 'warning',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_DISBURSED => 'success',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_CANCELLED => 'danger',
        ];
    @endphp

    <x-nawasara-ui::page-header
        :title="$this->pageTitle()"
        description="Usulan yang sudah disahkan dan tercatat dalam SK."
        :count="$this->total">

        <x-nawasara-ui::button
            color="primary"
            icon="lucide-plus"
            href="{{ route('hibah.proposals.create', ['purpose' => $purposeSegment, 'segment' => $segment]) }}"
            wire:navigate>
            Tambah Usulan
        </x-nawasara-ui::button>
    </x-nawasara-ui::page-header>

    {{-- Toolbar — bentuk baku halaman daftar (§1a AGENTS.md).
         filter-panel meneleportasikan chip ke [data-filter-chips] di bawah,
         supaya chip yang membungkus tidak mengganggu tata letak toolbar. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="[
                        'yearFilter' => $yearFilter,
                        'statusFilter' => $statusFilter,
                        'recipientTypeFilter' => $recipientTypeFilter,
                        'bkTypeFilter' => $bkTypeFilter,
                    ]"
                    :labels="[
                        'yearFilter' => $this->yearOptions,
                        'statusFilter' => $this->statusOptions,
                        'recipientTypeFilter' => $this->recipientTypeOptions,
                        'bkTypeFilter' => $this->bkTypeOptions,
                    ]">

                    <x-nawasara-ui::filter-group
                        label="Tahun" model="yearFilter"
                        :items="$this->yearOptions" icon="lucide-calendar" />

                    <x-nawasara-ui::filter-group
                        label="Status" model="statusFilter"
                        :items="$this->statusOptions" icon="lucide-circle-dot" />

                    {{-- Hanya jenis penerima yang MUNGKIN di menu ini.
                         Di Bansos Uang cuma ada satu, dan menawarkan lima
                         pilihan yang empat di antaranya selalu kosong hanya
                         membuang waktu staf. --}}
                    @if (count($this->recipientTypeOptions) > 1)
                        <x-nawasara-ui::filter-group
                            label="Jenis Penerima" model="recipientTypeFilter"
                            :items="$this->recipientTypeOptions" icon="lucide-users" />
                    @endif

                    {{-- ADD / PD — hanya di menu Bantuan Keuangan · Khusus. --}}
                    @if ($bkType === 'khusus')
                        <x-nawasara-ui::filter-group
                            label="Jenis BK" model="bkTypeFilter"
                            :items="$this->bkTypeOptions" icon="lucide-landmark" />
                    @endif
                </x-nawasara-ui::filter-panel>
            </div>

            {{-- ⚠️ prop `model=`, BUKAN wire:model — salah satu ini membuat
                 kotaknya tidak terikat apa pun, tanpa galat. --}}
            <x-nawasara-ui::search-input model="search" placeholder="Cari penerima, alamat, atau SK..." />

            <div class="flex items-center gap-2 shrink-0">
                {{-- Unduh mengikuti saringan yang sedang aktif — lihat
                     Table::export(). --}}
                @can('hibah.report.export')
                    <x-nawasara-ui::icon-button
                        icon="download" tooltip="Unduh Excel"
                        placement="left" wire:click="export" />
                @endcan

                <x-nawasara-ui::icon-button
                    icon="refresh-cw" tooltip="Muat ulang"
                    placement="left" wire:click="refreshRows" />
            </div>
        </div>

        {{-- ⚠️ WAJIB: filter-panel meneleportasikan chip ke sini. Tanpanya
             chip hilang dan staf tidak tahu saringan apa yang sedang aktif. --}}
        <div data-filter-chips class="flex flex-wrap items-center gap-2"></div>
    </div>

    @if ($this->rows->isEmpty())
        {{-- DUA empty state, bukan satu: satu pesan untuk keduanya membuat
             staf mencari data yang sebenarnya ada, hanya tersaring. --}}
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
                icon="lucide-inbox"
                title="Belum ada usulan"
                description="Usulan yang sudah disahkan akan muncul di sini setelah diimpor atau ditambahkan." />
        @endif
    @else
        <x-nawasara-ui::table
            stickyLast
            :headers="['Tahun', 'OPD', 'Penerima', 'Jenis Penerima', 'Anggaran', 'Status', '']">

            {{-- ⚠️ WAJIB <x-slot:table>. Tanpa ini header tergambar, badge
                 jumlah menyebut angka yang benar, dan barisnya KOSONG —
                 terlihat seperti "data tidak muncul" padahal data sampai ke
                 blade lalu dibuang (§1c AGENTS.md). --}}
            <x-slot:table>
                @foreach ($this->rows as $row)
                    <tr wire:key="proposal-{{ $row->id }}">
                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200 tabular-nums">
                            {{ $row->fiscal_year }}
                        </td>

                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ $row->opd->name ?? '—' }}
                        </td>

                        <td class="px-4 py-3 text-sm">
                            <div class="font-medium text-neutral-800 dark:text-neutral-100">
                                {{ $row->recipient_name }}
                            </div>

                            {{-- Alamat SELALU punya barisnya, terisi atau
                                 tidak. Menyembunyikannya saat kosong membuat
                                 halaman terlihat berbeda antar peruntukan —
                                 hibah uang punya alamat di 11 dari 45 baris,
                                 sisanya nol — dan itu terbaca seperti kolomnya
                                 hilang, bukan datanya. --}}
                            @if ($row->recipient_address)
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ \Illuminate\Support\Str::limit($row->recipient_address, 60) }}
                                </div>
                            @else
                                <div class="text-xs italic text-neutral-400 dark:text-neutral-500">
                                    belum ada alamat
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm text-neutral-700 dark:text-neutral-200">
                            {{ \Nawasara\Hibah\Models\ApprovedProposal::RECIPIENT_TYPES[$row->recipient_type] ?? $row->recipient_type }}
                            @if ($row->bk_type)
                                <div class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ \Nawasara\Hibah\Models\ApprovedProposal::BK_TYPES[$row->bk_type] ?? strtoupper($row->bk_type) }}
                                </div>
                            @endif
                        </td>

                        <td class="px-4 py-3 text-sm text-right text-neutral-700 dark:text-neutral-200 tabular-nums">
                            Rp {{ number_format((int) ($row->approved_budget ?? $row->budget_before), 0, ',', '.') }}
                        </td>

                        <td class="px-4 py-3 text-sm">
                            <x-nawasara-ui::badge :color="$statusColor[$row->status] ?? 'neutral'">
                                {{ \Nawasara\Hibah\Models\ApprovedProposal::STATUSES[$row->status] ?? $row->status }}
                            </x-nawasara-ui::badge>
                        </td>

                        <td class="px-4 py-3 text-right">
                            {{-- Dropdown 3 titik, bukan tombol berjejer:
                                 tombol berjejer memakan lebar kolom dan tidak
                                 menyisakan tempat bagi aksi ketiga — itu
                                 sebabnya halaman yang memakainya biasanya
                                 kehilangan Hapus. --}}
                            <x-nawasara-ui::dropdown-menu-action :id="$row->id" :items="[
                                [
                                    'type' => 'link',
                                    'label' => 'Detail',
                                    'href' => route('hibah.proposals.detail', [
                                        'purpose' => $purposeSegment,
                                        'segment' => $segment,
                                        'proposal' => $row->id,
                                    ]),
                                    'icon' => 'lucide-eye',
                                    'permission' => 'hibah.'.$purposeSegment.'.view',
                                ],
                                [
                                    'type' => 'link',
                                    'label' => 'Ubah',
                                    'href' => route('hibah.proposals.edit', [
                                        'purpose' => $purposeSegment,
                                        'segment' => $segment,
                                        'proposal' => $row->id,
                                    ]),
                                    'icon' => 'lucide-pencil',
                                    'permission' => 'hibah.'.$purposeSegment.'.update',
                                ],
                                [
                                    'type' => 'click',
                                    'label' => 'Hapus',
                                    'wire:click' => 'delete('.$row->id.')',
                                    'icon' => 'lucide-trash-2',
                                    'confirm' => 'Hapus usulan untuk '.$row->recipient_name.'? Gunakan Batalkan bila usulannya sah tetapi dicabut.',
                                    'permission' => 'hibah.'.$purposeSegment.'.update',
                                ],
                            ]" />
                        </td>
                    </tr>
                @endforeach
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">
            {{ $this->rows->links() }}
        </div>
    @endif
</div>
