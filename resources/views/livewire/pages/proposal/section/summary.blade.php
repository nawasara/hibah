<div>
    @php
        $statusColor = [
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_APPROVED => 'neutral',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_PARTIALLY_DISBURSED => 'warning',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_DISBURSED => 'success',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_CANCELLED => 'danger',
        ];

        $field = fn (?string $v) => $v ?: '—';
    @endphp

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
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100 tabular-nums">{{ $proposal->fiscal_year }}</dd>
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
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Anggaran Disetujui</dt>
                <dd class="mt-1 font-medium text-emerald-700 dark:text-emerald-400 tabular-nums">
                    @if ($proposal->approved_budget)
                        Rp {{ number_format((int) $proposal->approved_budget, 0, ',', '.') }}
                    @else
                        <span class="font-normal text-amber-700 dark:text-amber-400">
                            belum diisi — status tidak akan mencapai "Cair"
                        </span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Anggaran Usulan</dt>
                <dd class="mt-1 text-neutral-800 dark:text-neutral-100 tabular-nums">
                    Rp {{ number_format((int) $proposal->budget_before, 0, ',', '.') }}
                </dd>
            </div>

            @if ($proposal->notes)
                <div class="sm:col-span-2 lg:col-span-3">
                    <dt class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Keterangan</dt>
                    <dd class="mt-1 text-neutral-800 dark:text-neutral-100">{{ $proposal->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-nawasara-ui::page.card>
</div>
