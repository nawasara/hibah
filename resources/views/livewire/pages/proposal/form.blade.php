<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => \Nawasara\Hibah\Models\ApprovedProposal::PURPOSES[$purpose] ?? '', 'url' => '#'],
                [
                    'label' => 'Daftar',
                    'url' => route('hibah.proposals.index', [
                        'purpose' => $purposeSegment, 'segment' => $segment,
                    ]),
                ],
                ['label' => $this->pageTitle()],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            :title="$this->pageTitle()"
            description="Usulan yang dicatat di sini sudah disahkan — status pencairannya dihitung dari realisasi." />

        {{-- ⚠️ wire:submit, BUKAN wire:submit.prevent. Di Livewire 3 yang
             kedua diam-diam rusak dan form jatuh ke GET. --}}
        <form wire:submit="save" class="space-y-6">

            <x-nawasara-ui::page.card>
                <div class="mb-4 flex items-center gap-2">
                    <x-nawasara-ui::badge color="info">
                        {{ \Nawasara\Hibah\Models\ApprovedProposal::PURPOSES[$purpose] }}
                        ·
                        {{ \Nawasara\Hibah\Models\ApprovedProposal::FORMS[$form] }}
                    </x-nawasara-ui::badge>

                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        Ditentukan oleh menu yang dibuka — tidak perlu dipilih.
                    </span>
                </div>

                {{-- Tiga kolom: OPD, Tahun, Jenis Penerima sebaris. Dua kolom
                     menyisakan Jenis Penerima sendirian di baris kedua, dan
                     kolom tunggal selebar setengah layar terbaca seperti ada
                     yang belum dimuat. --}}
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {{-- ⚠️ form.input tidak punya wrapper <div> sendiri; label
                         dan input keluar sebagai sibling. Di dalam grid harus
                         dibungkus, kalau tidak grid menghitung 3 item bukan 2. --}}
                    <div>
                        <x-nawasara-ui::form.select
                            label="OPD"
                            wire:model="opd_id"
                            :options="$this->opdOptions"
                            placeholder="— pilih OPD —" />
                        @error('opd_id')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.input
                            type="number"
                            label="Tahun Anggaran"
                            wire:model="fiscal_year" />
                        @error('fiscal_year')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        @if (count($this->recipientOptions) === 1)
                            {{-- Satu-satunya yang sah — ditampilkan, tidak
                                 ditanyakan. Bansos Uang hanya ke Perorangan. --}}
                            <x-nawasara-ui::form.label value="Jenis Penerima" />
                            <div class="mt-1 flex items-center gap-2">
                                {{-- ⚠️ Jangan `reset()`: fungsi itu menggeser
                                     pointer internal array, jadi ia butuh
                                     REFERENSI — dan properti #[Computed]
                                     tidak dapat direferensikan.
                                     `array_values()[0]` membaca tanpa
                                     mengubah apa pun. --}}
                                <x-nawasara-ui::badge color="neutral">
                                    {{ array_values($this->recipientOptions)[0] ?? '—' }}
                                </x-nawasara-ui::badge>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400">
                                    satu-satunya yang berlaku untuk
                                    {{ strtolower(\Nawasara\Hibah\Models\ApprovedProposal::PURPOSES[$purpose]) }}
                                    {{ strtolower(\Nawasara\Hibah\Models\ApprovedProposal::FORMS[$form]) }}
                                </span>
                            </div>
                        @else
                            <x-nawasara-ui::form.select
                                label="Jenis Penerima"
                                wire:model="recipient_type"
                                :options="$this->recipientOptions"
                                placeholder="— pilih —" />
                            @error('recipient_type')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    @if ($purpose === \Nawasara\Hibah\Models\ApprovedProposal::PURPOSE_BK)
                        <div>
                            <x-nawasara-ui::form.select
                                label="Jenis Bantuan Keuangan"
                                wire:model="bk_type"
                                :options="$this->bkTypeOptions"
                                placeholder="— pilih —" />
                            @error('bk_type')
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            </x-nawasara-ui::page.card>

            <x-nawasara-ui::page.card>
                <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    Penerima
                </h3>

                <div class="space-y-4">
                    {{-- Cari penerima yang sudah terdaftar sebelum mengetik
                         nama baru.

                         Ada seratus penerima di basis data dan sebagian
                         namanya berulang dengan ejaan berbeda — "AGUS
                         MUSTOFA" tercatat tiga kali. Tanpa pencarian, tiap
                         pengisian berpotensi melahirkan penerima baru yang
                         seharusnya sama, dan riwayat penerimaannya terpecah
                         tanpa ada yang menyadari. --}}
                    <div>
                        <x-nawasara-ui::form.input
                            type="text"
                            label="Cari Penerima Terdaftar"
                            placeholder="Ketik minimal 3 huruf nama atau alamat..."
                            wire:model.live.debounce.400ms="recipientSearch" />

                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Opsional — lewati bila penerimanya belum pernah tercatat.
                        </p>

                        @if (mb_strlen(trim($recipientSearch)) >= 3)
                            @if ($this->recipientMatches->isEmpty())
                                <p class="mt-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800/60 dark:text-neutral-300">
                                    Tidak ada yang cocok — isi nama dan alamatnya di bawah,
                                    penerima baru terdaftar sendiri saat disimpan.
                                </p>
                            @else
                                <ul class="mt-2 divide-y divide-gray-200 overflow-hidden rounded-md border border-gray-200 dark:divide-neutral-700 dark:border-neutral-700">
                                    @foreach ($this->recipientMatches as $match)
                                        <li wire:key="match-{{ $match->id }}">
                                            <button type="button"
                                                wire:click="useRecipient({{ $match->id }})"
                                                class="flex w-full items-start justify-between gap-3 bg-white px-3 py-2 text-left transition hover:bg-emerald-50 dark:bg-neutral-800 dark:hover:bg-emerald-900/20">
                                                <span>
                                                    <span class="block text-sm font-medium text-neutral-800 dark:text-neutral-100">
                                                        {{ $match->name }}
                                                    </span>
                                                    <span class="block text-xs text-neutral-500 dark:text-neutral-400">
                                                        {{ $match->address ?: 'belum ada alamat' }}
                                                        · {{ $match->typeLabel() }}
                                                    </span>
                                                </span>

                                                <span class="shrink-0 text-xs tabular-nums text-neutral-500 dark:text-neutral-400">
                                                    {{ $match->proposals_count }}× menerima
                                                </span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @endif
                    </div>

                    <div>
                        <x-nawasara-ui::form.input
                            type="text"
                            label="Nama Penerima"
                            wire:model="recipient_name" />
                        @error('recipient_name')
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.textarea
                            label="Alamat Penerima"
                            wire:model="recipient_address"
                            :rows="2"
                            hint="Dipakai untuk mendeteksi penerima ganda — nama saja tidak cukup." />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            <x-nawasara-ui::page.card>
                <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    Asal Usulan
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div><x-nawasara-ui::form.input type="text" label="Pengusul" wire:model="proposer" /></div>
                    <div><x-nawasara-ui::form.input type="text" label="Dapil DPRD" wire:model="dapil" /></div>

                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.checkbox wire:model="cross_dapil" label="Lintas Dapil" />
                    </div>

                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.textarea label="Kamus Usulan" wire:model="proposal_dictionary" :rows="2" />
                    </div>

                    <div><x-nawasara-ui::form.input type="date" label="Tanggal Proposal" wire:model="proposed_at" /></div>

                    <div>
                        <x-nawasara-ui::form.input type="text" label="SK Kepala Daerah" wire:model="decree" />
                        <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                            Nomor keputusan yang mengesahkan usulan ini.
                        </p>
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    Nomenklatur Anggaran
                </h3>
                <p class="mb-4 text-xs text-neutral-500 dark:text-neutral-400">
                    Salin apa adanya dari dokumen anggaran — ketiganya bertingkat:
                    program memuat kegiatan, kegiatan memuat sub kegiatan.
                </p>

                <div class="space-y-4">
                    <div><x-nawasara-ui::form.textarea label="Program" wire:model="program" :rows="2" /></div>
                    <div><x-nawasara-ui::form.textarea label="Kegiatan" wire:model="activity" :rows="2" /></div>
                    <div><x-nawasara-ui::form.textarea label="Sub Kegiatan" wire:model="sub_activity" :rows="2" /></div>
                </div>
            </x-nawasara-ui::page.card>

            <x-nawasara-ui::page.card>
                <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                    Anggaran
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-nawasara-ui::form.money label="Anggaran Sebelum Perubahan" wire:model="budget_before" />
                    </div>
                    <div>
                        <x-nawasara-ui::form.money label="Anggaran Setelah Perubahan" wire:model="budget_after" />
                    </div>
                    <div>
                        <x-nawasara-ui::form.money label="Anggaran Disetujui" wire:model="approved_budget" />
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400">
                            Menentukan kapan status menjadi "Cair". Bila kosong,
                            status berhenti di "Sebagian Cair".
                        </p>
                    </div>

                    <div class="md:col-span-3">
                        <x-nawasara-ui::form.textarea label="Keterangan" wire:model="notes" :rows="2" />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            <div class="flex items-center justify-end gap-2">
                <x-nawasara-ui::button
                    color="neutral"
                    href="{{ route('hibah.proposals.index', ['purpose' => $purposeSegment, 'segment' => $segment]) }}"
                    wire:navigate>
                    Batal
                </x-nawasara-ui::button>

                <x-nawasara-ui::button type="submit" color="primary">
                    Simpan
                </x-nawasara-ui::button>
            </div>
        </form>
    </x-nawasara-ui::page.container>
</div>
