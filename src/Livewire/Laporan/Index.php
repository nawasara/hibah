<?php

namespace Nawasara\Hibah\Livewire\Laporan;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Nawasara\Hibah\Exports\PengajuanExport;
use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Hibah\Services\DuplicateDetector;
use Nawasara\Hibah\Services\HibahReporter;

class Index extends Component
{
    #[Url]
    public string $tab = 'tahun'; // tahun | opd | triwulan | duplikat

    #[Url]
    public string $tahunFilter = '';

    /** Cross-year toggle for the duplicate report. */
    public bool $crossYear = true;

    #[Computed]
    public function tahunOptions(): array
    {
        return Pengajuan::query()
            ->select('tahun')->distinct()->orderByDesc('tahun')
            ->pluck('tahun')->mapWithKeys(fn ($t) => [(string) $t => (string) $t])->all();
    }

    #[Computed]
    public function perTahun()
    {
        return app(HibahReporter::class)->perTahun();
    }

    #[Computed]
    public function perOpd()
    {
        return app(HibahReporter::class)->perOpd($this->tahunFilter !== '' ? (int) $this->tahunFilter : null);
    }

    #[Computed]
    public function perTriwulan(): array
    {
        return app(HibahReporter::class)->perTriwulan($this->tahunFilter !== '' ? (int) $this->tahunFilter : null);
    }

    #[Computed]
    public function duplikat()
    {
        return app(DuplicateDetector::class)->detect(
            crossYear: $this->crossYear,
            tahun: $this->tahunFilter !== '' ? (int) $this->tahunFilter : null,
        );
    }

    public function export()
    {
        $this->authorize('hibah.laporan.export');

        $tahun = $this->tahunFilter !== '' ? (int) $this->tahunFilter : null;
        $filename = 'hibah-'.($tahun ?? 'semua').'-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new PengajuanExport($tahun), $filename);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.laporan.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
