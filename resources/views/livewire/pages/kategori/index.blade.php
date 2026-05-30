<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb :items="[['label' => 'Hibah & Bansos', 'url' => '#'], ['label' => 'Master Kategori']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page-header title="Master Kategori"
            description="Kategori hibah/bansos yang dapat dipilih saat entry pengajuan." :count="$this->rows->count() . ' kategori'">
            <x-nawasara-ui::button color="primary" wire:click="create">
                <x-slot:icon><x-lucide-plus class="size-4" /></x-slot:icon>
                Tambah Kategori
            </x-nawasara-ui::button>
        </x-nawasara-ui::page-header>

        <x-nawasara-ui::table stickyLast :headers="['Nama', 'Status', '']">
            <x-slot:table>
                @forelse ($this->rows as $kat)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                        <td class="px-4 py-2.5 text-sm text-neutral-800 dark:text-neutral-100">{{ $kat->nama }}</td>
                        <td class="px-4 py-2.5">
                            @if ($kat->aktif)
                                <x-nawasara-ui::badge color="success">Aktif</x-nawasara-ui::badge>
                            @else
                                <x-nawasara-ui::badge color="neutral">Nonaktif</x-nawasara-ui::badge>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="inline-flex items-center gap-1">
                                <x-nawasara-ui::icon-button icon="pencil" tooltip="Edit" placement="left"
                                    wire:click="edit({{ $kat->id }})" />
                                <x-nawasara-ui::icon-button :icon="$kat->aktif ? 'toggle-right' : 'toggle-left'" :tooltip="$kat->aktif ? 'Nonaktifkan' : 'Aktifkan'" placement="left"
                                    wire:click="toggleAktif({{ $kat->id }})" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6">
                            <x-nawasara-ui::empty-state inline icon="lucide-tags" title="Belum ada kategori"
                                description="Tambahkan kategori hibah pertama." />
                        </td>
                    </tr>
                @endforelse
            </x-slot:table>
        </x-nawasara-ui::table>

        {{-- Form modal --}}
        <x-nawasara-ui::modal id="hibah-kategori-form" :title="$editId ? 'Edit Kategori' : 'Tambah Kategori'" maxWidth="md">
            <form wire:submit="save">
                <x-nawasara-ui::form.input type="text" label="Nama Kategori" wire:model="nama" autofocus />
                @error('nama')
                    <span class="text-xs text-rose-500">{{ $message }}</span>
                @enderror
            </form>

            <x-slot:footer>
                <x-nawasara-ui::button color="primary" wire:click="save">Simpan</x-nawasara-ui::button>
                <x-nawasara-ui::button color="neutral" variant="outline"
                    x-on:click="$dispatch('close-modal', 'hibah-kategori-form')">Batal</x-nawasara-ui::button>
            </x-slot:footer>
        </x-nawasara-ui::modal>
    </x-nawasara-ui::page.container>
</div>
