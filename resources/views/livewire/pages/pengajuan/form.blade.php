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
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">OPD</label>
                        @if ($this->isOperator)
                            <input type="text" disabled
                                value="{{ $this->opdOptions[$opd_id] ?? '—' }}"
                                class="w-full rounded-lg border-gray-200 bg-neutral-100 dark:bg-neutral-900 dark:border-neutral-700 text-sm" />
                            <input type="hidden" wire:model="opd_id" />
                        @else
                            <select wire:model="opd_id"
                                class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                                <option value="">— pilih OPD —</option>
                                @foreach ($this->opdOptions as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        @endif
                        @error('opd_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Tahun</label>
                        <input type="number" wire:model="tahun"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                        @error('tahun') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Kategori</label>
                        <select wire:model="kategori_id"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                            <option value="">— pilih kategori —</option>
                            @foreach ($this->kategoriOptions as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </select>
                        @error('kategori_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Peruntukan</label>
                        <select wire:model="peruntukan"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                            <option value="hibah">Hibah</option>
                            <option value="bansos">Bansos</option>
                            <option value="bk">Bantuan Keuangan</option>
                        </select>
                        @error('peruntukan') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Identitas & Program --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Usulan & Program</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Pengusul</label>
                        <input type="text" wire:model="pengusul"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Dapil DPRD</label>
                        <input type="text" wire:model="dapil"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                            <input type="checkbox" wire:model="lintas_dapil"
                                class="rounded border-neutral-300 dark:border-neutral-600 dark:bg-neutral-800" />
                            Lintas Dapil
                        </label>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Kamus Usulan</label>
                        <textarea wire:model="kamus_usulan" rows="2"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Tanggal Proposal</label>
                        <input type="date" wire:model="tanggal_proposal"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Program</label>
                        <input type="text" wire:model="program"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Kegiatan</label>
                        <input type="text" wire:model="kegiatan"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Sub Kegiatan</label>
                        <input type="text" wire:model="sub_kegiatan"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Penerima --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Penerima</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Nama Penerima <span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="nama_penerima"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                        @error('nama_penerima') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Alamat Penerima</label>
                        <input type="text" wire:model="alamat_penerima"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                </div>
            </x-nawasara-ui::page.card>

            {{-- Anggaran usulan --}}
            <x-nawasara-ui::page.card>
                <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Anggaran Usulan</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Anggaran Sebelum Perubahan <span class="text-rose-500">*</span></label>
                        <input type="number" wire:model="anggaran_sebelum" min="0"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                        @error('anggaran_sebelum') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Anggaran Setelah Perubahan</label>
                        <input type="number" wire:model="anggaran_setelah" min="0"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">Keterangan</label>
                        <textarea wire:model="keterangan" rows="2"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm"></textarea>
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
