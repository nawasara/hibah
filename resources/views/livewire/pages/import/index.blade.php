<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Import Data']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Import Data Hibah"
            description="Unggah file Excel data hibah/bansos. Gunakan template agar kolom sesuai." />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                {{-- Step 1: template --}}
                <x-nawasara-ui::page.card>
                    <div class="flex items-start gap-3">
                        <x-lucide-file-down class="size-5 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">1. Unduh template</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 mb-3">
                                Berisi sheet <span class="font-medium">Data</span> untuk diisi, plus satu sheet
                                referensi per kolom pilihan. Hapus tiga baris contoh sebelum mengisi.
                            </p>
                            <x-nawasara-ui::button color="success" variant="outline" wire:click="downloadTemplate">
                                <x-slot:icon><x-lucide-download class="size-4" /></x-slot:icon>
                                Unduh Template Excel
                            </x-nawasara-ui::button>
                        </div>
                    </div>
                </x-nawasara-ui::page.card>

                {{-- Step 2: upload — struktur sama dengan langkah 1: ikon di
                     kiri, judul dan isi di kanan. Sebelumnya hanya <p>, jadi
                     kedua kartu terlihat seperti dua rancangan berbeda. --}}
                <x-nawasara-ui::page.card>
                    <div class="flex items-start gap-3">
                        <x-lucide-file-up class="size-5 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="flex-1">
                            <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100">2. Unggah &amp; import</p>
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 mb-3">
                                Isi template lalu unggah di sini. Baris yang tidak sesuai ditolak
                                satu per satu — sisanya tetap masuk.
                            </p>

                    <form wire:submit="import" class="space-y-4">
                        <div class="w-40">
                            <x-nawasara-ui::form.input
                                type="number"
                                label="Tahun"
                                min="2000"
                                max="2100"
                                wire:model="tahun" />
                            @error('tahun') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            <p class="text-xs text-neutral-400 mt-1">Semua baris akan distempel tahun ini.</p>
                        </div>

                        <div>
                            <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">File Excel (.xlsx)</label>
                            <input type="file" wire:model="file" accept=".xlsx,.xls" class="text-sm" />
                            @error('file') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            <div wire:loading wire:target="file" class="text-xs text-neutral-400 mt-1">Mengunggah…</div>
                        </div>

                        <x-nawasara-ui::button type="submit" color="primary" loadingTarget="import">
                            <x-slot:icon><x-lucide-upload class="size-4" /></x-slot:icon>
                            Mulai Import
                        </x-nawasara-ui::button>
                    </form>
                        </div>
                    </div>
                </x-nawasara-ui::page.card>

                {{-- Result --}}
                @if ($result)
                    <x-nawasara-ui::page.card>
                        <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100 mb-3">Hasil Import</p>
                        <dl class="grid grid-cols-2 md:grid-cols-5 gap-3 text-sm">
                            <div><dt class="text-xs text-neutral-500">Baris dibaca</dt><dd class="font-semibold">{{ $result['read'] }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Dilewati</dt><dd class="font-semibold">{{ $result['skipped'] }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Pengajuan dibuat</dt><dd class="font-semibold text-emerald-600">{{ $result['created'] }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Realisasi</dt><dd class="font-semibold">{{ $result['realisasi'] }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">OPD baru</dt><dd class="font-semibold">{{ $result['opdCreated'] }}</dd></div>
                        </dl>
                    </x-nawasara-ui::page.card>
                @endif
            </div>

            {{-- Help --}}
            <div>
                <x-nawasara-ui::page.card>
                    <p class="text-sm font-medium text-neutral-800 dark:text-neutral-100 mb-2">Catatan</p>
                    <ul class="text-xs text-neutral-500 dark:text-neutral-400 space-y-2 list-disc pl-4">
                        <li><strong>Nama Penerima</strong> wajib diisi — baris tanpa nama dilewati.</li>
                        <li><strong>Nama OPD</strong> dicocokkan dengan data OPD; bila belum ada, dibuat otomatis.</li>
                        <li><strong>Peruntukan</strong>: HIBAH, BANSOS, atau BK.</li>
                        <li><strong>Anggaran</strong>: angka saja (boleh "Rp 200.000.000" — titik/Rp diabaikan).</li>
                        <li>Baris dengan <strong>SK Kepala Daerah</strong> terisi langsung berstatus <em>disetujui</em>.</li>
                        <li>Maksimal 50 MB per file.</li>
                    </ul>
                </x-nawasara-ui::page.card>
            </div>
        </div>
    </x-nawasara-ui::page.container>
</div>
