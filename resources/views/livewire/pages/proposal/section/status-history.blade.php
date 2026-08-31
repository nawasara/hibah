<div>
    <x-nawasara-ui::page.card>
        <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
            Riwayat Status
        </h3>

        @if ($this->entries->isEmpty())
            <p class="text-sm text-neutral-500 dark:text-neutral-400">
                Belum ada perubahan status. Riwayat terisi sendiri saat
                realisasi disimpan atau usulan dibatalkan.
            </p>
        @else
            <ol class="space-y-3">
                @foreach ($this->entries as $entry)
                    <li wire:key="history-{{ $entry->id }}"
                        class="flex gap-3 border-l-2 border-neutral-200 dark:border-neutral-700 pl-3">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                @if ($entry->fromLabel())
                                    <span class="text-neutral-500 dark:text-neutral-400">
                                        {{ $entry->fromLabel() }}
                                    </span>
                                    <span class="text-neutral-400 dark:text-neutral-500">→</span>
                                @endif

                                <span class="font-medium text-neutral-800 dark:text-neutral-100">
                                    {{ $entry->toLabel() }}
                                </span>
                            </div>

                            @if ($entry->notes)
                                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                                    {{ $entry->notes }}
                                </p>
                            @endif

                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                {{ $entry->byUser->name ?? 'Sistem' }}
                                ·
                                {{ $entry->created_at?->translatedFormat('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-nawasara-ui::page.card>
</div>
