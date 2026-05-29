<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Operator OPD']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header
            title="Operator OPD"
            description="Tautkan user ke OPD. Operator hanya bisa mengelola data hibah OPD-nya sendiri."
            :count="$this->rows->total().' operator'">
            <x-nawasara-ui::button color="primary"
                x-on:click="$dispatch('open-modal', 'hibah-operator-assign')">
                <x-slot:icon><x-lucide-user-plus class="size-4" /></x-slot:icon>
                Tambah Operator
            </x-nawasara-ui::button>
        </x-nawasara-ui::page-header>

        <x-nawasara-ui::table stickyLast :headers="['Nama', 'Email', 'OPD', 'Status', '']">
            <x-slot:table>
                @forelse ($this->rows as $op)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $op->user?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm text-neutral-600 dark:text-neutral-300">{{ $op->user?->email ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $op->opd?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5">
                            @if ($op->aktif)
                                <x-nawasara-ui::badge color="success">Aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="inline-flex items-center gap-1">
                                <x-nawasara-ui::icon-button
                                    :icon="$op->aktif ? 'toggle-right' : 'toggle-left'"
                                    :tooltip="$op->aktif ? 'Nonaktifkan' : 'Aktifkan'"
                                    wire:click="toggleAktif({{ $op->id }})" />
                                <x-nawasara-ui::icon-button icon="trash-2" tooltip="Hapus"
                                    wire:click="remove({{ $op->id }})"
                                    wire:confirm="Hapus operator {{ $op->user?->name }}?" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline
                                icon="lucide-users"
                                title="Belum ada operator"
                                description="Tambahkan operator dan tautkan ke OPD-nya." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>

        <div class="mt-4">{{ $this->rows->links() }}</div>

        {{-- Assign modal --}}
        <x-nawasara-ui::modal id="hibah-operator-assign" title="Tambah Operator" maxWidth="md">
            <form wire:submit="assign" class="space-y-4">
                <div>
                    <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">User</label>
                    <select wire:model="userId"
                        class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                        <option value="">— pilih user —</option>
                        @foreach ($this->userOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('userId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm text-neutral-600 dark:text-neutral-300 mb-1">OPD</label>
                    <select wire:model="opdId"
                        class="w-full rounded-lg border-gray-300 dark:border-neutral-600 dark:bg-neutral-800 text-sm">
                        <option value="">— pilih OPD —</option>
                        @foreach ($this->opdOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('opdId') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                </div>
            </form>

            <x-slot:footer>
                <x-nawasara-ui::button color="primary" wire:click="assign">Simpan</x-nawasara-ui::button>
                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="$dispatch('close-modal', 'hibah-operator-assign')">Batal</x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
