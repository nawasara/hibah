<?php

namespace Nawasara\Hibah\Livewire\Kategori;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Hibah\Models\Kategori;

class Index extends Component
{
    public ?int $editId = null;
    public string $nama = '';

    public function mount(): void
    {
        $this->authorize('hibah.kategori.manage');
    }

    #[Computed]
    public function rows()
    {
        return Kategori::query()->orderBy('nama')->get();
    }

    public function edit(int $id): void
    {
        $kategori = Kategori::findOrFail($id);
        $this->editId = $kategori->id;
        $this->nama = $kategori->nama;
        $this->dispatch('open-modal', 'hibah-kategori-form');
    }

    public function create(): void
    {
        $this->reset(['editId', 'nama']);
        $this->dispatch('open-modal', 'hibah-kategori-form');
    }

    public function save(): void
    {
        $this->authorize('hibah.kategori.manage');

        $this->validate([
            'nama' => [
                'required', 'string', 'max:255',
                'unique:nawasara_hibah_kategori,nama'.($this->editId ? ','.$this->editId : ''),
            ],
        ]);

        Kategori::updateOrCreate(
            ['id' => $this->editId],
            ['nama' => $this->nama],
        );

        $this->reset(['editId', 'nama']);
        $this->dispatch('close-modal', 'hibah-kategori-form');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Kategori tersimpan.']);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('hibah.kategori.manage');

        $kategori = Kategori::findOrFail($id);
        $kategori->update(['aktif' => ! $kategori->aktif]);

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => $kategori->aktif ? 'Kategori diaktifkan.' : 'Kategori dinonaktifkan.',
        ]);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.kategori.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
