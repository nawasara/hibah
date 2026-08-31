<div>
    <x-nawasara-ui::page.card>
        <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
            Bukti Monev
        </h3>

        @if ($proposal->monev_proof_path)
            <div class="mb-3 flex items-center gap-2 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-900/30 px-3 py-2">
                <span class="text-sm text-emerald-800 dark:text-emerald-300">
                    Berkas sudah diunggah.
                </span>
            </div>
        @else
            <p class="mb-3 text-sm text-neutral-500 dark:text-neutral-400">
                Belum ada berkas. Monev membuktikan bantuannya dipakai
                sebagaimana mestinya — terpisah dari pencairannya, jadi tidak
                memengaruhi status.
            </p>
        @endif

        <form wire:submit="save">
            {{-- Native input: komponen file-input tidak ada di nawasara-ui,
                 dan unggahan Livewire butuh mekanisme berbeda. --}}
            <input
                type="file"
                wire:model="file"
                class="block w-full text-sm text-neutral-700 dark:text-neutral-200
                       file:mr-3 file:rounded-md file:border-0
                       file:bg-neutral-100 dark:file:bg-neutral-700
                       file:px-3 file:py-1.5 file:text-sm
                       file:text-neutral-700 dark:file:text-neutral-200" />

            @error('file')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <div wire:loading wire:target="file" class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                Mengunggah…
            </div>

            <div class="mt-3">
                <x-nawasara-ui::button type="submit" color="primary">
                    Unggah Monev
                </x-nawasara-ui::button>
            </div>
        </form>
    </x-nawasara-ui::page.card>
</div>
