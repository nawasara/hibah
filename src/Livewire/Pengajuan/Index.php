<?php

namespace Nawasara\Hibah\Livewire\Pengajuan;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Hibah\Models\Kategori;
use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Registry\Models\Opd;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tahunFilter = '';

    #[Url]
    public string $statusFilter = '';

    /**
     * Multi-select filters — typed `array` to match the filter-panel /
     * filter-group Alpine payload (which dispatches array values).
     *
     * @var array<int, int|string>
     */
    #[Url]
    public array $opdFilter = [];

    /** @var array<int, int|string> */
    #[Url]
    public array $kategoriFilter = [];

    public int $perPage = 25;

    public function updated($property): void
    {
        // Any filter change resets pagination to page 1.
        if (in_array($property, ['search', 'tahunFilter', 'statusFilter', 'opdFilter', 'kategoriFilter'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'tahunFilter', 'statusFilter', 'opdFilter', 'kategoriFilter']);
        $this->resetPage();
    }

    /**
     * Distinct years present in the (scoped) data — drives the year filter
     * dropdown. The registry ScopedToOpd trait already limits this to the
     * operator's OPD; admins see every year.
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

    /**
     * OPD list scoped to those that actually have pengajuan in the current
     * visible set — avoids surfacing every OPD in the registry (~14 here,
     * could be hundreds in other deployments) when only a handful are
     * relevant. Restricted users see nothing here too (consistent with
     * the table being empty).
     */
    #[Computed]
    public function opdOptions(): array
    {
        $opdIds = Pengajuan::query()->select('opd_id')->distinct()->pluck('opd_id');

        return Opd::query()
            ->whereIn('id', $opdIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    #[Computed]
    public function kategoriOptions(): array
    {
        return Kategori::query()
            ->where('aktif', true)
            ->orderBy('nama')
            ->pluck('nama', 'id')
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
            ->when(! empty($this->opdFilter), fn ($q) => $q->whereIn('opd_id', $this->opdFilter))
            ->when(! empty($this->kategoriFilter), fn ($q) => $q->whereIn('kategori_id', $this->kategoriFilter))
            ->latest('id')
            ->paginate($this->perPage);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.pengajuan.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
