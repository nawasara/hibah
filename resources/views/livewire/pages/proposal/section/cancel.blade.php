<div>
    <x-nawasara-ui::page.card>
        <h3 class="mb-4 text-sm font-semibold text-neutral-800 dark:text-neutral-100">
            Pembatalan
        </h3>

        @if ($proposal->isCancelled())
            <div class="mb-3 rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/30 px-3 py-2">
                <p class="text-sm text-rose-800 dark:text-rose-300">
                    Usulan ini dibatalkan. Alasannya tercatat di Riwayat Status.
                </p>
            </div>

            <p class="mb-3 text-xs text-neutral-500 dark:text-neutral-400">
                Setelah dipulihkan, status kembali dihitung dari realisasi —
                bukan dikembalikan ke nilai sebelumnya, yang mungkin sudah
                tidak cocok dengan angkanya.
            </p>

            <x-nawasara-ui::button color="neutral" wire:click="restore">
                Pulihkan Usulan
            </x-nawasara-ui::button>
        @else
            <p class="mb-3 text-sm text-neutral-500 dark:text-neutral-400">
                Membatalkan usulan yang sudah disahkan lalu dicabut. Berbeda
                dari <span class="font-medium">menghapus</span>, yang untuk
                baris keliru — salah ketik atau ganda.
            </p>

            <x-nawasara-ui::button
                color="danger"
                x-on:click="$dispatch('open-modal', { id: 'hibah-proposal-cancel' })">
                Batalkan Usulan
            </x-nawasara-ui::button>

            <x-nawasara-ui::modal id="hibah-proposal-cancel" title="Batalkan Usulan">
                <p class="mb-3 text-sm text-neutral-600 dark:text-neutral-300">
                    Usulan tetap tercatat karena SK-nya ada — hanya ditandai
                    batal. Alasannya masuk ke Riwayat Status bersama nama Anda.
                </p>

                <x-nawasara-ui::form.textarea
                    label="Alasan Pembatalan"
                    wire:model="reason"
                    :rows="3"
                    hint="Wajib diisi. Usulan batal tanpa keterangan akan ditanyakan saat audit." />

                @error('reason')
                    <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <x-slot name="footer">
                    {{-- ⚠️ wire:click, BUKAN tombol submit: footer modal
                         dirender di LUAR div konten, jadi tombolnya lolos
                         dari <form> dan wire:submit tidak pernah menyala. --}}
                    <x-nawasara-ui::button
                        color="neutral"
                        x-on:click="$dispatch('close-modal', 'hibah-proposal-cancel')">
                        Batal
                    </x-nawasara-ui::button>

                    <x-nawasara-ui::button color="danger" wire:click="cancel">
                        Ya, Batalkan
                    </x-nawasara-ui::button>
                </x-slot>
            </x-nawasara-ui::modal>
        @endif
    </x-nawasara-ui::page.card>
</div>
