<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[
                ['label' => 'Hibah & Bansos', 'url' => '#'],
                ['label' => 'Pengajuan', 'url' => route('hibah.pengajuan.index')],
                ['label' => 'Detail'],
            ]" />
    </x-slot>

    @php
        /**
         * Compact a rupiah amount for tight stat-card slots.
         *
         *   < 1 ribu    → "Rp 500"
         *   ≥ 1 ribu    → "Rp 850 Rb"      (1 desimal jika tidak bulat)
         *   ≥ 1 juta    → "Rp 1,2 Jt"
         *   ≥ 1 miliar  → "Rp 1,5 M"
         *
         * Headline panjang ("Rp 1.500.000.000") tetap dipakai di hero tile
         * yang punya ruang; stat-card sempit (label + ikon + value) butuh
         * versi compact agar tidak wrap.
         */
        if (! function_exists('hibah_compact_rp')) {
            function hibah_compact_rp(?int $n): string {
                if ($n === null) return '—';
                if ($n === 0)   return 'Rp 0';
                $abs = abs($n);
                [$div, $suffix] = match (true) {
                    $abs >= 1_000_000_000 => [1_000_000_000, ' M'],
                    $abs >= 1_000_000     => [1_000_000, ' Jt'],
                    $abs >= 1_000         => [1_000, ' Rb'],
                    default               => [1, ''],
                };
                $val = $n / $div;
                // 1 desimal jika tidak bulat; format pakai koma (Indo).
                $str = $val == (int) $val
                    ? number_format($val, 0, ',', '.')
                    : number_format($val, 1, ',', '.');
                return 'Rp '.$str.$suffix;
            }
        }

        // Map status -> gradient pair for the hero tile so the headline
        // colour reflects lifecycle: green for approved/done, amber when
        // still under review, rose when rejected. The gradient is what
        // separates this tile visually from the supporting stat-cards.
        $heroGradient = match ($pengajuan->status) {
            \Nawasara\Hibah\Models\Pengajuan::STATUS_DISETUJUI,
            \Nawasara\Hibah\Models\Pengajuan::STATUS_SELESAI
                => 'from-emerald-500 to-emerald-700 dark:from-emerald-700 dark:to-emerald-900',
            \Nawasara\Hibah\Models\Pengajuan::STATUS_DITOLAK
                => 'from-rose-500 to-rose-700 dark:from-rose-700 dark:to-rose-900',
            default
                => 'from-amber-500 to-amber-700 dark:from-amber-700 dark:to-amber-900',
        };

        // Anggaran headline picks the most meaningful number for the
        // current lifecycle stage. Disetujui beats sebelum once a decision
        // exists; otherwise show the proposal amount.
        $heroAmount = $pengajuan->anggaran_disetujui ?? $pengajuan->anggaran_sebelum;
        $heroLabel = $pengajuan->anggaran_disetujui !== null
            ? 'Anggaran Disetujui'
            : 'Anggaran Usulan';

        $totalRealisasi = $this->totalRealisasi;
        $realisasiCount = collect($realisasi)->filter(fn ($v) => (int) $v > 0)->count();
    @endphp

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

        {{-- Bento hero row: big gradient tile (3/6) + 2x2 stat grid (3/6) --}}
        <div class="grid grid-cols-1 lg:grid-cols-6 gap-4 mb-4">
            {{-- Hero tile: gradient + big headline number --}}
            <div class="lg:col-span-3">
                <div class="relative h-full overflow-hidden rounded-2xl bg-gradient-to-br {{ $heroGradient }} p-6 text-white shadow-sm">
                    {{-- top-right: pulsing status pill + icon --}}
                    <div class="absolute right-4 top-4 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 backdrop-blur-sm px-2.5 py-1 text-xs font-medium text-white">
                            <span class="size-1.5 rounded-full bg-white animate-pulse"></span>
                            {{ strtoupper($pengajuan->statusLabel()) }}
                        </span>
                        <span class="inline-flex size-9 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm">
                            <x-lucide-wallet class="size-4 text-white" />
                        </span>
                    </div>

                    <p class="text-xs font-semibold uppercase tracking-wider text-white/80">{{ $heroLabel }}</p>

                    <p class="mt-8 text-5xl font-bold leading-none tracking-tight">
                        Rp {{ number_format($heroAmount, 0, ',', '.') }}
                    </p>
                    <p class="mt-2 text-sm text-white/85">
                        {{ $pengajuan->opd?->name ?? '—' }} &middot; Tahun {{ $pengajuan->tahun }}
                    </p>

                    @if ($pengajuan->anggaran_disetujui !== null && $pengajuan->anggaran_sebelum > 0 && $pengajuan->anggaran_disetujui !== $pengajuan->anggaran_sebelum)
                        <p class="mt-6 inline-flex items-center gap-1 rounded-full bg-white/15 px-2.5 py-1 text-xs text-white/90">
                            <x-lucide-arrow-up-down class="size-3" />
                            Usulan: Rp {{ number_format($pengajuan->anggaran_sebelum, 0, ',', '.') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- 2x2 stat grid (col-span-3 split into 2 cols) --}}
            <div class="lg:col-span-3 grid grid-cols-2 gap-3">
                <x-nawasara-ui::stat-card
                    label="Anggaran Usulan"
                    :value="hibah_compact_rp($pengajuan->anggaran_sebelum)"
                    color="primary"
                    icon="lucide-trending-up"
                    description="Sebelum perubahan" />

                <x-nawasara-ui::stat-card
                    label="Total Realisasi"
                    :value="hibah_compact_rp($totalRealisasi)"
                    color="info"
                    icon="lucide-coins"
                    :description="$realisasiCount . ' triwulan terisi'" />

                <x-nawasara-ui::stat-card
                    label="Belum Dicairkan"
                    :value="hibah_compact_rp($pengajuan->anggaran_belum_cair)"
                    color="warning"
                    icon="lucide-circle-alert" />

                <x-nawasara-ui::stat-card
                    label="Verifikasi"
                    :value="$pengajuan->status_verifikasi ? strtoupper($pengajuan->status_verifikasi) : '—'"
                    :color="$pengajuan->status_verifikasi === 'ms' ? 'success' : ($pengajuan->status_verifikasi === 'tms' ? 'danger' : 'neutral')"
                    icon="lucide-shield-check"
                    :description="$pengajuan->bukti_verifikasi_path ? 'Bukti tersedia' : 'Belum diverifikasi'" />
            </div>
        </div>

        {{-- Row 2: Data usulan + Ubah status --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="lg:col-span-2">
                <x-nawasara-ui::page.card>
                    <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-4 flex items-center gap-1.5">
                        <x-lucide-file-text class="size-4" /> Data Usulan
                    </p>
                    <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-3 text-sm">
                        @php
                            $kv = [
                                'OPD'        => $pengajuan->opd?->name,
                                'Tahun'      => $pengajuan->tahun,
                                'Kategori'   => $pengajuan->kategori?->nama,
                                'Peruntukan' => ucfirst($pengajuan->peruntukan),
                                'Pengusul'   => $pengajuan->pengusul,
                                'Dapil'      => trim(($pengajuan->dapil ?? '').($pengajuan->lintas_dapil ? ' (lintas)' : '')) ?: null,
                            ];
                        @endphp
                        @foreach ($kv as $label => $value)
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                                <dd class="text-neutral-800 dark:text-neutral-100 mt-0.5">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach

                        <div class="col-span-2 md:col-span-3 pt-3 border-t border-neutral-100 dark:border-neutral-700">
                            <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Program / Kegiatan</dt>
                            <dd class="text-neutral-800 dark:text-neutral-100 mt-0.5">
                                {{ $pengajuan->program ?: '—' }}{{ $pengajuan->sub_kegiatan ? ' › '.$pengajuan->sub_kegiatan : '' }}
                            </dd>
                        </div>

                        <div class="col-span-2 md:col-span-3">
                            <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Alamat Penerima</dt>
                            <dd class="text-neutral-800 dark:text-neutral-100 mt-0.5">{{ $pengajuan->alamat_penerima ?: '—' }}</dd>
                        </div>

                        <div class="col-span-2 md:col-span-3">
                            <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">SK Kepala Daerah</dt>
                            <dd class="text-neutral-800 dark:text-neutral-100 mt-0.5">{{ $pengajuan->sk_kepala_daerah ?: '—' }}</dd>
                        </div>
                    </dl>
                </x-nawasara-ui::page.card>
            </div>

            @can('hibah.pengajuan.update')
            <div>
                <x-nawasara-ui::page.card class="h-full">
                    <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3 flex items-center gap-1.5">
                        <x-lucide-workflow class="size-4" /> Ubah Status
                    </p>
                    @if (! empty($this->allowedTransitions))
                        <form wire:submit="changeStatus" class="space-y-3">
                            <x-nawasara-ui::form.select
                                wire:model.live="targetStatus"
                                placeholder="— pilih status baru —"
                                :options="$this->allowedTransitions" />
                            @error('targetStatus') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror

                            @if ($targetStatus === \Nawasara\Hibah\Models\Pengajuan::STATUS_DISETUJUI)
                                <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 space-y-2
                                            dark:bg-emerald-900/20 dark:border-emerald-800">
                                    <x-nawasara-ui::form.input
                                        type="text"
                                        label="SK Kepala Daerah"
                                        wire:model="sk_kepala_daerah" />
                                    @error('sk_kepala_daerah') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror

                                    <div x-data="{
                                        format(n) { const num=parseInt(n,10); return (!num||isNaN(num))?'':num.toLocaleString('id-ID'); },
                                        sync(e) {
                                            const digits=(e.target.value||'').replace(/\D/g,'');
                                            const num=digits===''?0:parseInt(digits,10);
                                            this.$wire.set('anggaran_disetujui', num);
                                            e.target.value=this.format(num);
                                        },
                                        init() { this.$nextTick(() => { this.$refs.rp.value=this.format(this.$wire.get('anggaran_disetujui')); }); },
                                    }">
                                        <x-nawasara-ui::form.label value="Anggaran Disetujui" />
                                        <input type="text" inputmode="numeric"
                                            x-ref="rp"
                                            x-on:input="sync($event)"
                                            class="py-3 px-4 block w-full border border-gray-300 rounded-md text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-700/80 outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100" />
                                        @error('anggaran_disetujui') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endif

                            <x-nawasara-ui::form.textarea
                                wire:model="catatan"
                                :rows="2"
                                placeholder="Catatan (opsional)" />

                            <x-nawasara-ui::button type="submit" color="primary" class="w-full">Terapkan</x-nawasara-ui::button>
                        </form>
                    @else
                        <div class="rounded-lg bg-neutral-50 dark:bg-neutral-900/40 px-3 py-4 text-center">
                            <p class="text-xs text-neutral-500 dark:text-neutral-400">
                                Tidak ada transisi yang diizinkan dari status saat ini.
                            </p>
                        </div>
                    @endif
                </x-nawasara-ui::page.card>
            </div>
            @endcan
        </div>

        {{-- Row 3: Realisasi triwulan (relevan setelah disetujui) --}}
        @if (in_array($pengajuan->status, [\Nawasara\Hibah\Models\Pengajuan::STATUS_DISETUJUI, \Nawasara\Hibah\Models\Pengajuan::STATUS_SELESAI]))
        <div class="mb-4">
            <x-nawasara-ui::page.card>
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 flex items-center gap-1.5">
                        <x-lucide-calendar-days class="size-4" /> Realisasi per Triwulan
                    </p>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400">
                        Total <strong class="text-neutral-700 dark:text-neutral-200">Rp {{ number_format($totalRealisasi, 0, ',', '.') }}</strong>
                    </span>
                </div>

                @can('hibah.realisasi.update')
                {{--
                    Rupiah mask via inline Alpine x-data (object literal).
                    User lihat "1.500.000.000" sambil ketik; Livewire tetap
                    simpan integer murni via wire:model. Inline (bukan
                    Alpine.data() registered component) supaya tidak
                    tergantung urutan script vs alpine:init — pernah bikin
                    section hilang karena `hibahRupiah is not defined` saat
                    render pertama.
                --}}
                <form wire:submit="saveRealisasi" class="space-y-3"
                    x-data="{
                        format(n) {
                            const num = parseInt(n, 10);
                            if (!num || isNaN(num)) return '';
                            return num.toLocaleString('id-ID');
                        },
                        sync(event, model) {
                            const digits = (event.target.value || '').replace(/\D/g, '');
                            const num = digits === '' ? 0 : parseInt(digits, 10);
                            this.$wire.set(model, num);
                            event.target.value = this.format(num);
                        },
                        init() {
                            this.$nextTick(() => {
                                this.$root.querySelectorAll('[data-rp]').forEach((el) => {
                                    const model = el.dataset.rp;
                                    el.value = this.format(this.$wire.get(model));
                                });
                            });
                        },
                    }">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        @foreach ([1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'] as $tw => $label)
                            <div>
                                <x-nawasara-ui::form.label :value="$label" />
                                <input type="text" inputmode="numeric"
                                    data-rp="realisasi.{{ $tw }}"
                                    x-on:input="sync($event, 'realisasi.{{ $tw }}')"
                                    class="py-3 px-4 block w-full border border-gray-300 rounded-md text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-700/80 outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100" />
                                @error('realisasi.'.$tw) <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div>
                            <x-nawasara-ui::form.label value="Belum Dicairkan" />
                            <input type="text" inputmode="numeric"
                                data-rp="anggaran_belum_cair"
                                x-on:input="sync($event, 'anggaran_belum_cair')"
                                class="py-3 px-4 block w-full border border-gray-300 rounded-md text-sm focus:border-transparent focus:ring-2 focus:ring-emerald-700/80 outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100" />
                        </div>
                        {{-- Wrapper div mandatory: form.input renders label +
                             input as siblings (no outer div), which would
                             explode the 2-col grid into 3 items. --}}
                        <div>
                            <x-nawasara-ui::form.input
                                type="text"
                                label="Alasan"
                                wire:model="alasan_belum_cair" />
                        </div>
                    </div>
                    <x-nawasara-ui::button type="submit" color="primary" variant="outline">Simpan Realisasi</x-nawasara-ui::button>
                </form>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        @foreach ([1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'] as $tw => $label)
                            <div>
                                <dt class="text-[11px] uppercase tracking-wide text-neutral-500 dark:text-neutral-400">{{ $label }}</dt>
                                <dd class="text-neutral-800 dark:text-neutral-100 mt-0.5">Rp {{ number_format($realisasi[$tw] ?? 0, 0, ',', '.') }}</dd>
                            </div>
                        @endforeach
                    </div>
                @endcan
            </x-nawasara-ui::page.card>
        </div>
        @endif

        {{-- Row 4: Verifikasi + Monev sebelahan --}}
        @can('hibah.pengajuan.update')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <x-nawasara-ui::page.card>
                <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3 flex items-center gap-1.5">
                    <x-lucide-shield-check class="size-4" /> Verifikasi Awal
                </p>
                <form wire:submit="saveVerifikasi" class="space-y-3">
                    <x-nawasara-ui::form.select
                        wire:model="status_verifikasi"
                        placeholder="— status —"
                        :options="['ms' => 'MS (Memenuhi Syarat)', 'tms' => 'TMS (Tidak Memenuhi Syarat)']" />
                    {{-- File input native: nawasara-ui has no file-input component. --}}
                    <input type="file" wire:model="buktiVerifikasi"
                        class="block w-full text-xs text-neutral-700 file:mr-3 file:rounded file:border-0 file:bg-neutral-100 file:px-2 file:py-1 file:text-xs file:font-medium hover:file:bg-neutral-200
                               dark:text-neutral-300 dark:file:bg-neutral-700 dark:hover:file:bg-neutral-600" />
                    @error('buktiVerifikasi') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    @if ($pengajuan->bukti_verifikasi_path)
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 truncate">✓ {{ basename($pengajuan->bukti_verifikasi_path) }}</p>
                    @endif
                    <x-nawasara-ui::button type="submit" color="primary" variant="outline">Simpan Verifikasi</x-nawasara-ui::button>
                </form>
            </x-nawasara-ui::page.card>

            <x-nawasara-ui::page.card>
                <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3 flex items-center gap-1.5">
                    <x-lucide-clipboard-check class="size-4" /> Bukti Monev
                </p>
                <form wire:submit="saveMonev" class="space-y-3">
                    <input type="file" wire:model="buktiMonev"
                        class="block w-full text-xs text-neutral-700 file:mr-3 file:rounded file:border-0 file:bg-neutral-100 file:px-2 file:py-1 file:text-xs file:font-medium hover:file:bg-neutral-200
                               dark:text-neutral-300 dark:file:bg-neutral-700 dark:hover:file:bg-neutral-600" />
                    @error('buktiMonev') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    @if ($pengajuan->bukti_monev_path)
                        <p class="text-[11px] text-emerald-600 dark:text-emerald-400 truncate">✓ {{ basename($pengajuan->bukti_monev_path) }}</p>
                    @endif
                    <x-nawasara-ui::button type="submit" color="primary" variant="outline">Unggah Monev</x-nawasara-ui::button>
                </form>
            </x-nawasara-ui::page.card>
        </div>
        @endcan

        {{-- Row 5: Riwayat status full-width --}}
        <x-nawasara-ui::page.card>
            <p class="text-sm font-semibold text-neutral-800 dark:text-neutral-100 mb-3 flex items-center gap-1.5">
                <x-lucide-history class="size-4" /> Riwayat Status
            </p>
            <ol class="relative border-l border-neutral-200 dark:border-neutral-700 ml-2 space-y-3">
                @forelse ($pengajuan->histori()->latest('id')->get() as $h)
                    <li class="ml-4 relative">
                        <div class="absolute -left-[1.4rem] mt-1.5 w-2.5 h-2.5 rounded-full bg-emerald-400 dark:bg-emerald-500 ring-2 ring-white dark:ring-neutral-700"></div>
                        <p class="text-sm text-neutral-800 dark:text-neutral-100">
                            @if ($h->dari_status)
                                <span class="text-neutral-500 dark:text-neutral-400">{{ ucfirst($h->dari_status) }}</span>
                                <span class="text-neutral-400">→</span>
                            @endif
                            <strong>{{ ucfirst($h->ke_status) }}</strong>
                        </p>
                        @if ($h->catatan)
                            <p class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">{{ $h->catatan }}</p>
                        @endif
                        <p class="text-[11px] text-neutral-400 dark:text-neutral-500 mt-0.5">{{ $h->created_at?->diffForHumans() }}</p>
                    </li>
                @empty
                    <li class="ml-4 text-sm text-neutral-500 dark:text-neutral-400">Belum ada riwayat.</li>
                @endforelse
            </ol>
        </x-nawasara-ui::page.card>
    </x-nawasara-ui::page.container>
</div>
