<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Hibah & Bansos', 'url' => '#'],
                ['label' => 'Pengajuan', 'url' => route('hibah.pengajuan.index')],
                ['label' => 'Detail'],
            ]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header :title="$pengajuan->nama_penerima">
            <x-nawasara-ui::badge :color="$pengajuan->statusColor()">{{ $pengajuan->statusLabel() }}</x-nawasara-ui::badge>
            @can('hibah.pengajuan.update')
                <x-nawasara-ui::button :href="route('hibah.pengajuan.edit', $pengajuan)" wire:navigate
                    color="neutral" variant="outline">
                    <x-slot:icon><x-lucide-pencil class="size-4" /></x-slot:icon>
                    Edit
                </x-nawasara-ui::button>
            @endcan
        </x-nawasara-ui::page-header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left: detail data --}}
            <div class="lg:col-span-2 space-y-4">
                <x-nawasara-ui::page.card>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Data Usulan</h3>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-xs text-neutral-500">OPD</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->opd?->name ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Tahun</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->tahun }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Kategori</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->kategori?->nama ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Peruntukan</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ ucfirst($pengajuan->peruntukan) }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Pengusul</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->pengusul ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Dapil</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->dapil ?? '—' }}{{ $pengajuan->lintas_dapil ? ' (lintas)' : '' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-neutral-500">Program / Kegiatan</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->program ?? '—' }}{{ $pengajuan->sub_kegiatan ? ' › '.$pengajuan->sub_kegiatan : '' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-neutral-500">Alamat Penerima</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->alamat_penerima ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Anggaran Usulan</dt><dd class="text-neutral-800 dark:text-neutral-100">Rp {{ number_format($pengajuan->anggaran_sebelum, 0, ',', '.') }}</dd></div>
                        <div><dt class="text-xs text-neutral-500">Anggaran Disetujui</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->anggaran_disetujui !== null ? 'Rp '.number_format($pengajuan->anggaran_disetujui, 0, ',', '.') : '—' }}</dd></div>
                        <div class="col-span-2"><dt class="text-xs text-neutral-500">SK Kepala Daerah</dt><dd class="text-neutral-800 dark:text-neutral-100">{{ $pengajuan->sk_kepala_daerah ?? '—' }}</dd></div>
                    </dl>
                </x-nawasara-ui::page.card>

                {{-- Realisasi per triwulan — relevan setelah disetujui --}}
                @if (in_array($pengajuan->status, [\Nawasara\Hibah\Models\Pengajuan::STATUS_DISETUJUI, \Nawasara\Hibah\Models\Pengajuan::STATUS_SELESAI]))
                <x-nawasara-ui::page.card>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">Realisasi Anggaran per Triwulan</h3>
                        <span class="text-xs text-neutral-500">
                            Total realisasi: <strong>Rp {{ number_format($this->totalRealisasi, 0, ',', '.') }}</strong>
                            @if ($pengajuan->anggaran_disetujui)
                                / Rp {{ number_format($pengajuan->anggaran_disetujui, 0, ',', '.') }}
                            @endif
                        </span>
                    </div>

                    @can('hibah.realisasi.update')
                    <form wire:submit="saveRealisasi" class="space-y-4">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            @foreach ([1 => 'Triwulan I', 2 => 'Triwulan II', 3 => 'Triwulan III', 4 => 'Triwulan IV'] as $tw => $label)
                                <div>
                                    <label class="block text-xs text-neutral-600 dark:text-neutral-300 mb-1">{{ $label }}</label>
                                    <input type="number" min="0" wire:model="realisasi.{{ $tw }}"
                                        class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                                    @error('realisasi.'.$tw) <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-neutral-600 dark:text-neutral-300 mb-1">Anggaran Belum Dicairkan</label>
                                <input type="number" min="0" wire:model="anggaran_belum_cair"
                                    class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-600 dark:text-neutral-300 mb-1">Alasan Belum/Tidak Dicairkan</label>
                                <input type="text" wire:model="alasan_belum_cair"
                                    class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                            </div>
                        </div>

                        <x-nawasara-ui::button type="submit" color="primary" variant="outline">Simpan Realisasi</x-nawasara-ui::button>
                    </form>
                    @else
                        {{-- read-only view --}}
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                            @foreach ([1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'] as $tw => $label)
                                <div>
                                    <dt class="text-xs text-neutral-500">{{ $label }}</dt>
                                    <dd class="text-neutral-800 dark:text-neutral-100">Rp {{ number_format($realisasi[$tw] ?? 0, 0, ',', '.') }}</dd>
                                </div>
                            @endforeach
                        </div>
                    @endcan
                </x-nawasara-ui::page.card>
                @endif

                {{-- Verifikasi awal --}}
                @can('hibah.pengajuan.update')
                <x-nawasara-ui::page.card>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Verifikasi Awal</h3>
                    <form wire:submit="saveVerifikasi" class="space-y-3">
                        <div class="flex items-center gap-3">
                            <select wire:model="status_verifikasi"
                                class="rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                                <option value="">— status —</option>
                                <option value="ms">MS (Memenuhi Syarat)</option>
                                <option value="tms">TMS (Tidak Memenuhi Syarat)</option>
                            </select>
                            <input type="file" wire:model="buktiVerifikasi" class="text-sm" />
                        </div>
                        @error('buktiVerifikasi') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        @if ($pengajuan->bukti_verifikasi_path)
                            <p class="text-xs text-emerald-600">Bukti tersimpan: {{ basename($pengajuan->bukti_verifikasi_path) }}</p>
                        @endif
                        <x-nawasara-ui::button type="submit" color="primary" variant="outline">Simpan Verifikasi</x-nawasara-ui::button>
                    </form>
                </x-nawasara-ui::page.card>

                {{-- Monev --}}
                <x-nawasara-ui::page.card>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4">Bukti Monev Pelaksanaan</h3>
                    <form wire:submit="saveMonev" class="space-y-3">
                        <input type="file" wire:model="buktiMonev" class="text-sm" />
                        @error('buktiMonev') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        @if ($pengajuan->bukti_monev_path)
                            <p class="text-xs text-emerald-600">Bukti tersimpan: {{ basename($pengajuan->bukti_monev_path) }}</p>
                        @endif
                        <x-nawasara-ui::button type="submit" color="primary" variant="outline">Unggah Monev</x-nawasara-ui::button>
                    </form>
                </x-nawasara-ui::page.card>
                @endcan
            </div>

            {{-- Right: status + histori --}}
            <div class="space-y-4">
                @can('hibah.pengajuan.update')
                @if (! empty($this->allowedTransitions))
                <x-nawasara-ui::page.card>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3">Ubah Status</h3>
                    <form wire:submit="changeStatus" class="space-y-3">
                        <select wire:model.live="targetStatus"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                            <option value="">— pilih status baru —</option>
                            @foreach ($this->allowedTransitions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('targetStatus') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror

                        {{-- Hasil rapat fields, only when approving --}}
                        @if ($targetStatus === \Nawasara\Hibah\Models\Pengajuan::STATUS_DISETUJUI)
                            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 p-3 space-y-2">
                                <div>
                                    <label class="block text-xs text-neutral-600 dark:text-neutral-300 mb-1">Keputusan Kepala Daerah (No. SK)</label>
                                    <input type="text" wire:model="sk_kepala_daerah"
                                        class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                                    @error('sk_kepala_daerah') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs text-neutral-600 dark:text-neutral-300 mb-1">Anggaran Disetujui</label>
                                    <input type="number" wire:model="anggaran_disetujui" min="0"
                                        class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm" />
                                    @error('anggaran_disetujui') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <textarea wire:model="catatan" rows="2" placeholder="Catatan (opsional)"
                            class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm"></textarea>

                        <x-nawasara-ui::button type="submit" color="primary">Terapkan</x-nawasara-ui::button>
                    </form>
                </x-nawasara-ui::page.card>
                @endif
                @endcan

                <x-nawasara-ui::page.card>
                    <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3">Riwayat Status</h3>
                    <ol class="relative border-l border-neutral-200 dark:border-neutral-700 ml-2 space-y-4">
                        @forelse ($pengajuan->histori()->latest('id')->get() as $h)
                            <li class="ml-4">
                                <div class="absolute -left-1.5 mt-1 w-3 h-3 rounded-full bg-neutral-300 dark:bg-neutral-600"></div>
                                <p class="text-sm text-neutral-800 dark:text-neutral-100">
                                    {{ $h->dari_status ? ucfirst($h->dari_status).' → ' : '' }}{{ ucfirst($h->ke_status) }}
                                </p>
                                @if ($h->catatan)
                                    <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $h->catatan }}</p>
                                @endif
                                <p class="text-xs text-neutral-400">{{ $h->created_at?->diffForHumans() }}</p>
                            </li>
                        @empty
                            <li class="ml-4 text-sm text-neutral-500">Belum ada riwayat.</li>
                        @endforelse
                    </ol>
                </x-nawasara-ui::page.card>
            </div>
        </div>
    </x-nawasara-ui::page.container>
</div>
