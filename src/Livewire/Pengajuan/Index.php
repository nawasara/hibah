<?php

namespace Nawasara\Hibah\Livewire\Pengajuan;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Hibah\Models\Pengajuan;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tahunFilter = '';

    #[Url]
    public string $statusFilter = '';

    public int $perPage = 25;

    public function updated($property): void
    {
        // Any filter change resets pagination to page 1.
        if (in_array($property, ['search', 'tahunFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'tahunFilter', 'statusFilter']);
        $this->resetPage();
    }

    /**
     * Distinct years present in the (scoped) data — drives the year filter
     * dropdown. OpdScope already limits this to the operator's OPD.
     */
    #[Computed]
    public function tahunOptions(): array
    {
        return Pengajuan::query()
            ->select('tahun')
            ->distinct()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->mapWithKeys(fn ($t) => [(string) $t => (string) $t])
            ->all();
    }

    #[Computed]
    public function statusCounts(): array
    {
        return Pengajuan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
    }

    #[Computed]
    public function rows()
    {
        return Pengajuan::query()
            ->with(['opd:id,code,name', 'kategori:id,nama'])
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('nama_penerima', 'like', "%{$this->search}%")
                        ->orWhere('pengusul', 'like', "%{$this->search}%")
                        ->orWhere('program', 'like', "%{$this->search}%");
                });
            })
            ->when($this->tahunFilter !== '', fn ($q) => $q->where('tahun', $this->tahunFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.pengajuan.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
