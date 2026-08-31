<div>
    @php
        $statusColor = [
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_APPROVED => 'neutral',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_PARTIALLY_DISBURSED => 'warning',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_DISBURSED => 'success',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_CANCELLED => 'danger',
        ];

        // Gradien hero mengikuti keadaan pencairan, bukan sekadar hiasan:
        // warnanya menjawab "sudah cair atau belum" sebelum angkanya dibaca.
        $heroGradient = match ($proposal->status) {
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_DISBURSED
                => 'from-emerald-500 to-emerald-700 dark:from-emerald-700 dark:to-emerald-900',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_CANCELLED
                => 'from-rose-500 to-rose-700 dark:from-rose-700 dark:to-rose-900',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_PARTIALLY_DISBURSED
                => 'from-amber-500 to-amber-600 dark:from-amber-700 dark:to-amber-900',
            default
                => 'from-slate-500 to-slate-700 dark:from-slate-700 dark:to-slate-900',
        };

        $field = fn (?string $v) => $v ?: '—';
        $percent = $this->disbursedPercent();
    @endphp

    {{-- ── Bento: dana di petak besar, pendampingnya di kanan ──
         Angka rupiah adalah hal pertama yang dicari siapa pun yang membuka
         halaman ini, jadi ia mendapat petak terbesar dan kontras tertinggi.
         Yang lain menjelaskannya. --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Petak utama — anggaran disetujui --}}
        <div class="lg:col-span-2 relative overflow-hidden rounded-xl bg-linear-to-br {{ $heroGradient }} p-6 text-white shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-white/70">
                        {{ $proposal->approved_budget ? 'Anggaran Disetujui' : 'Anggaran Usulan' }}
                    </p>

                    <p class="mt-2 text-3xl font-bold tabular-nums sm:text-4xl">
                        Rp {{ number_format($this->headlineBudget(), 0, ',', '.') }}
                    </p>

                    @unless ($proposal->approved_budget)
                        <p class="mt-2 text-xs text-white/80">
                            Anggaran disetujui belum diisi — status tidak akan mencapai "Cair".
                        </p>
                    @endunless
                </div>

                <span class="shrink-0 rounded-full bg-white/20 px-3 py-1 text-xs font-medium backdrop-blur">
                    {{ $proposal->statusLabel() }}
                </span>
            </div>

            {{-- Bilah kemajuan hanya bila anggarannya diketahui; tanpa itu
                 persentasenya tidak punya penyebut dan hanya menyesatkan. --}}
            @if ($proposal->approved_budget)
                <div class="mt-5">
                    <div class="flex items-center justify-between text-xs text-white/80">
                        <span>Cair {{ $percent }}%</span>
                        <span class="tabular-nums">
                            Rp {{ number_format($this->disbursedTotal(), 0, ',', '.') }}
                        </span>
                    </div>

                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-white/25">
                        <div class="h-full rounded-full bg-white transition-all"
                            style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Petak pendamping --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-1">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    Sudah Cair
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">
                    {{ $this->compactRupiah($this->disbursedTotal()) }}
                </p>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
                <p class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                    Sisa
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums text-neutral-800 dark:text-neutral-100">
                    {{ $proposal->approved_budget ? $this->compactRupiah($this->remaining()) : '—' }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Rincian usulan ── --}}
    <x-nawasara-ui::page.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                Data Usulan
            </h3>

            <div class="flex items-center gap-2">
                <x-nawasara-ui::badge color="info">{{ $this->purposeLabel() }}</x-nawasara-ui::badge>

                <x-nawasara-ui::badge :color="$statusColor[$proposal->status] ?? 'neutral'">
                    {{ $proposal->statusLabel() }}
                </x-nawasara-ui::badge>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">OPD</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($proposal->opd->name ?? null) }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Tahun</dt>
                <dd class="mt-1 tabular-nums text-neutral-800 dark:text-neutral-100">{{ $proposal->fiscal_year }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Jenis Penerima</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $this->recipientTypeLabel() }}
                    @if ($this->bkTypeLabel())
                        <span class="text-neutral-500 dark:text-neutral-400">· {{ $this->bkTypeLabel() }}</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Pengusul</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($proposal->proposer) }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Dapil</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $field($proposal->dapil) }}
                    @if ($proposal->cross_dapil)
                        <span class="text-xs text-neutral-500 dark:text-neutral-400">(lintas dapil)</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Tanggal Proposal</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $proposal->proposed_at?->translatedFormat('d F Y') ?? '—' }}
                </dd>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Program / Kegiatan</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">
                    {{ $field($proposal->program) }}
                    @if ($proposal->activity)
                        <span class="text-neutral-500 dark:text-neutral-400"> › </span>{{ $proposal->activity }}
                    @endif
                    @if ($proposal->sub_activity)
                        <span class="text-neutral-500 dark:text-neutral-400"> › </span>{{ $proposal->sub_activity }}
                    @endif
                </dd>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Alamat Penerima</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($proposal->recipient_address) }}</dd>
            </div>

            <div class="sm:col-span-2 lg:col-span-3">
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">SK Kepala Daerah</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $field($proposal->decree) }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Anggaran Usulan</dt>
                <dd class="mt-1 tabular-nums text-neutral-800 dark:text-neutral-100">
                    Rp {{ number_format((int) $proposal->budget_before, 0, ',', '.') }}
                </dd>
            </div>

            @if ($proposal->budget_after)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Setelah Perubahan</dt>
                    <dd class="mt-1 tabular-nums text-neutral-800 dark:text-neutral-100">
                        Rp {{ number_format((int) $proposal->budget_after, 0, ',', '.') }}
                    </dd>
                </div>
            @endif

            @if ($proposal->notes)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Keterangan</dt>
                    <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $proposal->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-nawasara-ui::page.card>
</div>
