<div>
    @php
        $statusColor = [
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_APPROVED => 'neutral',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_PARTIALLY_DISBURSED => 'warning',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_DISBURSED => 'success',
            \Nawasara\Hibah\Models\ApprovedProposal::STATUS_CANCELLED => 'danger',
        ];
        $projected = $this->projectedStatus;
    @endphp

    <x-nawasara-ui::page.card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-neutral-800 dark:text-neutral-100">
                Realisasi per Triwulan
            </h3>

            <div class="flex items-center gap-3 text-sm">
                <span class="text-neutral-500 dark:text-neutral-400">Total</span>
                <span class="font-semibold text-neutral-800 dark:text-neutral-100 tabular-nums">
                    Rp {{ number_format($this->total, 0, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Status DIHITUNG dari angka di bawah, bukan dipilih. Ditampilkan
             sebelum disimpan supaya staf melihat akibatnya — bukan
             menemukannya berubah sesudahnya. --}}
        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg border border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50 px-3 py-2">
            <span class="text-xs text-neutral-500 dark:text-neutral-400">
                Status setelah disimpan:
            </span>
            <x-nawasara-ui::badge :color="$statusColor[$projected] ?? 'neutral'">
                {{ \Nawasara\Hibah\Models\ApprovedProposal::STATUSES[$projected] ?? $projected }}
            </x-nawasara-ui::badge>

            @if ($proposal->isCancelled())
                <span class="text-xs text-rose-600 dark:text-rose-400">
                    Usulan dibatalkan — pulihkan dulu sebelum mencatat realisasi.
                </span>
            @elseif (! $proposal->approved_budget)
                <span class="text-xs text-amber-700 dark:text-amber-400">
                    Anggaran disetujui belum diisi, jadi status berhenti di "Sebagian Cair".
                </span>
            @endif
        </div>

        <form wire:submit="save">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach (\Nawasara\Hibah\Models\Disbursement::QUARTERS as $quarter => $label)
                    <div>
                        <x-nawasara-ui::form.money
                            :label="$label"
                            wire:model.live.debounce.400ms="amounts.{{ $quarter }}"
                            :disabled="$proposal->isCancelled()" />
                        @error('amounts.'.$quarter)
                            <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            {{-- Kelebihan bayar ditolak saat simpan, dan dikatakan di sini
                 supaya terlihat sebelum tombolnya ditekan. --}}
            @if ($this->overspend > 0)
                <div class="mt-4 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 dark:border-rose-800 dark:bg-rose-900/30">
                    <p class="text-sm text-rose-800 dark:text-rose-300">
                        Total realisasi melebihi anggaran disetujui sebesar
                        <span class="font-semibold tabular-nums">
                            Rp {{ number_format($this->overspend, 0, ',', '.') }}
                        </span>.
                    </p>
                    <p class="mt-0.5 text-xs text-rose-700 dark:text-rose-400">
                        Periksa kembali angkanya — biasanya kelebihan satu nol.
                    </p>
                </div>
            @endif

            @error('amounts')
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Sisa DIHITUNG, bukan diketik. Sebelumnya kolom isian, dan
                     hasilnya dua angka yang bertentangan di layar yang sama:
                     total realisasi bergerak saat triwulan diisi, sementara
                     "belum dicairkan" tetap pada angka lama. --}}
                <div>
                    <x-nawasara-ui::form.label value="Belum Dicairkan" />
                    <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-right dark:border-neutral-700 dark:bg-neutral-800/60">
                        <span class="text-sm font-medium tabular-nums text-neutral-800 dark:text-neutral-100">
                            @if ($this->remaining === null)
                                <span class="text-xs font-normal italic text-amber-700 dark:text-amber-400">
                                    anggaran disetujui belum diisi
                                </span>
                            @else
                                Rp {{ number_format($this->remaining, 0, ',', '.') }}
                            @endif
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                        Anggaran disetujui dikurangi total realisasi.
                    </p>
                </div>

                <div>
                    <x-nawasara-ui::form.input
                        type="text"
                        label="Alasan Belum Dicairkan"
                        wire:model="undisbursed_reason"
                        :disabled="$proposal->isCancelled()" />
                </div>
            </div>

            <div class="mt-4">
                <x-nawasara-ui::button
                    type="submit"
                    color="primary"
                    :disabled="$proposal->isCancelled()">
                    Simpan Realisasi
                </x-nawasara-ui::button>
            </div>
        </form>
    </x-nawasara-ui::page.card>
</div>
