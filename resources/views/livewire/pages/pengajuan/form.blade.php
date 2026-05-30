<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Hibah & Bansos', 'url' => '#'],
                ['label' => 'Pengajuan', 'url' => route('hibah.pengajuan.index')],
                ['label' => $pengajuanId ? 'Edit' : 'Tambah'],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            :title="$pengajuanId ? 'Edit Pengajuan' : 'Tambah Pengajuan'"
            description="Data usulan hibah/bansos. Keputusan rapat & realisasi diisi setelah pengajuan tersimpan." />

        <form wire:submit="save" class="space-y-6">
            {{-- Klasifikasi --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Klasifikasi</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- OPD: operator-locked (display + hidden), admin select --}}
                    <div>
                        <x-nawasara-ui::form.label value="OPD" />
                        @if ($this->isOperator)
                            <x-nawasara-ui::form.input
                                type="text"
                                disabled
                                :value="$this->opdOptions[$opd_id] ?? '—'" />
                            <input type="hidden" wire:model="opd_id" />
                        @else
                            <x-nawasara-ui::form.select
                                wire:model="opd_id"
                                placeholder="— pilih OPD —"
                                :options="$this->opdOptions" />
                        @endif
                        @error('opd_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.input
                            type="number"
                            label="Tahun"
                            wire:model="tahun" />
                        @error('tahun') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.select
                            label="Kategori"
                            wire:model="kategori_id"
                            placeholder="— pilih kategori —"
                            :options="$this->kategoriOptions" />
                        @error('kategori_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-nawasara-ui::form.select
                            label="Peruntukan"
                            wire:model="peruntukan"
                            :placeholder="null"
                            :options="['hibah' => 'Hibah', 'bansos' => 'Bansos', 'bk' => 'Bantuan Keuangan']" />
                        @error('peruntukan') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Identitas & Program --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Usulan & Program</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-nawasara-ui::form.input type="text" label="Pengusul" wire:model="pengusul" />
                    <x-nawasara-ui::form.input type="text" label="Dapil DPRD" wire:model="dapil" />

                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.checkbox
                            wire:model="lintas_dapil"
                            label="Lintas Dapil" />
                    </div>

                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.textarea
                            label="Kamus Usulan"
                            wire:model="kamus_usulan"
                            :rows="2" />
                    </div>

                    <x-nawasara-ui::form.input type="date" label="Tanggal Proposal" wire:model="tanggal_proposal" />
                    <x-nawasara-ui::form.input type="text" label="Program" wire:model="program" />
                    <x-nawasara-ui::form.input type="text" label="Kegiatan" wire:model="kegiatan" />
                    <x-nawasara-ui::form.input type="text" label="Sub Kegiatan" wire:model="sub_kegiatan" />
                </div>
            </x-nawasara-ui::page.card>

            {{-- Penerima --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Penerima</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-nawasara-ui::form.input
                            type="text"
                            label="Nama Penerima *"
                            wire:model="nama_penerima" />
                        @error('nama_penerima') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                    <x-nawasara-ui::form.input type="text" label="Alamat Penerima" wire:model="alamat_penerima" />
                </div>
            </x-nawasara-ui::page.card>

            {{-- Anggaran usulan --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Anggaran Usulan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-nawasara-ui::form.input
                            type="number"
                            label="Anggaran Sebelum Perubahan *"
                            min="0"
                            wire:model="anggaran_sebelum" />
                        @error('anggaran_sebelum') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                    <x-nawasara-ui::form.input
                        type="number"
                        label="Anggaran Setelah Perubahan"
                        min="0"
                        wire:model="anggaran_setelah" />

                    <div class="md:col-span-2">
                        <x-nawasara-ui::form.textarea
                            label="Keterangan"
                            wire:model="keterangan"
                            :rows="2" />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            <div class="flex items-center gap-3">
                <x-nawasara-ui::button type="submit" color="primary">Simpan</x-nawasara-ui::button>
                <x-nawasara-ui::button :href="route('hibah.pengajuan.index')" color="neutral" variant="outline" wire:navigate>
                    Batal
                </x-nawasara-ui::button>
            </div>
        </form>
    </x-nawasara-ui::page.container>
</div>
