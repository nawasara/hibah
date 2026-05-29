<?php

namespace Nawasara\Hibah\Livewire\Operator;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Hibah\Models\Operator;
use Nawasara\Registry\Models\Opd;

class Index extends Component
{
    use WithPagination;

    public string $search = '';

    // Assign form
    public ?int $userId = null;
    public ?int $opdId = null;

    public function mount(): void
    {
        $this->authorize('hibah.operator.manage');
    }

    #[Computed]
    public function rows()
    {
        return Operator::query()
            ->with(['user:id,name,email', 'opd:id,code,name'])
            ->latest('id')
            ->paginate(25);
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Users not yet assigned as an operator — one OPD per operator
     * (unique user_id), so already-linked users drop out of the picker.
     */
    #[Computed]
    public function userOptions(): array
    {
        $assigned = Operator::pluck('user_id')->all();

        return \App\Models\User::query()
            ->whereNotIn('id', $assigned)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->mapWithKeys(fn ($u) => [$u->id => "{$u->name} ({$u->email})"])
            ->all();
    }

    public function assign(): void
    {
        $this->authorize('hibah.operator.manage');

        $this->validate([
            'userId' => ['required', 'exists:users,id', 'unique:nawasara_hibah_operator,user_id'],
            'opdId' => ['required', 'exists:nawasara_registry_opd,id'],
        ], [], [
            'userId' => 'User',
            'opdId' => 'OPD',
        ]);

        Operator::create([
            'user_id' => $this->userId,
            'opd_id' => $this->opdId,
            'aktif' => true,
        ]);

        $this->reset(['userId', 'opdId']);
        $this->dispatch('modal-close:hibah-operator-assign');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Operator ditambahkan.']);
    }

    public function toggleAktif(int $id): void
    {
        $this->authorize('hibah.operator.manage');

        $operator = Operator::findOrFail($id);
        $operator->update(['aktif' => ! $operator->aktif]);

        $this->dispatch('toast', [
            'type' => 'info',
            'message' => $operator->aktif ? 'Operator diaktifkan.' : 'Operator dinonaktifkan.',
        ]);
    }

    public function remove(int $id): void
    {
        $this->authorize('hibah.operator.manage');

        Operator::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Operator dihapus.']);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.operator.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
