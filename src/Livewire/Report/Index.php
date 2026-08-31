<?php

namespace Nawasara\Hibah\Livewire\Report;

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Nawasara\Hibah\Exports\ApprovedProposalExport;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Services\DuplicateDetector;
use Nawasara\Hibah\Services\HibahReporter;

class Index extends Component
{
    #[Url]
    /**
     * Peruntukan yang dilaporkan — dari segmen rute, bukan pilihan.
     *
     * Tiap menu punya laporannya sendiri, memuat datanya sendiri (§7).
     */
    public string $purpose = '';

    public string $tab = 'tahun'; // tahun | opd | triwulan | duplikat

    #[Url]
    public string $tahunFilter = '';

    /** Cross-year toggle for the duplicate report. */
    public bool $crossYear = true;

    /**
     * When true, only rows with a populated address are eligible for
     * duplicate detection (avoids false positives from common names like
     * "MDT MIFTAHUL HUDA"). When false, falls back to name-only grouping —
     * useful for years where OPD left the address column blank (e.g. 2025).
     */
    public bool $requireAddress = true;

    /**
     * Id usulan dalam kelompok duplikat yang sedang inspected duplicate group.
     * Null/empty = modal closed. We snapshot the IDs at click time rather
     * than re-derive from name/address keys, so the modal stays consistent
     * even when the user toggles crossYear/tahunFilter while it's open.
     *
     * @var list<int>
     */
    public array $detailIds = [];

    public ?string $detailName = null;

    /**
     * Peruntukan diambil dari segmen rute.
     *
     * Tanpa ini `$purpose` kosong dan laporannya memuat SELURUH peruntukan —
     * staf bansos akan melihat angka hibah di layar laporannya.
     */
    public function mount(string $purpose): void
    {
        $resolved = ApprovedProposal::purposeFromSegment($purpose);

        abort_if($resolved === null, 404);

        $this->purpose = $resolved;
    }

    #[Computed]
    public function tahunOptions(): array
    {
        return ApprovedProposal::query()
            ->where('purpose', $this->purpose)
            ->select('fiscal_year')->distinct()->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')->mapWithKeys(fn ($t) => [(string) $t => (string) $t])->all();
    }

    #[Computed]
    public function perTahun()
    {
        return app(HibahReporter::class)->perTahun($this->purpose);
    }

    #[Computed]
    public function perOpd()
    {
        return app(HibahReporter::class)->perOpd(
            $this->tahunFilter !== '' ? (int) $this->tahunFilter : null,
            $this->purpose,
        );
    }

    #[Computed]
    public function perTriwulan(): array
    {
        return app(HibahReporter::class)->perTriwulan(
            $this->tahunFilter !== '' ? (int) $this->tahunFilter : null,
            $this->purpose,
        );
    }

    #[Computed]
    public function duplikat()
    {
        return app(DuplicateDetector::class)->detect(
            crossYear: $this->crossYear,
            tahun: $this->tahunFilter !== '' ? (int) $this->tahunFilter : null,
            requireAddress: $this->requireAddress,
            purpose: $this->purpose,
        );
    }

    /**
     * Deteksi duplikat TIDAK berlaku untuk Bantuan Keuangan.
     *
     * Blade memakai ini untuk tidak merender tabnya sama sekali. Tab kosong
     * terbaca "sudah dicek, aman" padahal artinya "tidak pernah dicek";
     * tab yang tidak ada tidak menjanjikan apa-apa.
     */
    public function showsDuplicates(): bool
    {
        return $this->purpose !== ApprovedProposal::PURPOSE_BK;
    }

    public function export()
    {
        $this->authorize('hibah.report.export');

        $tahun = $this->tahunFilter !== '' ? (int) $this->tahunFilter : null;

        $filename = sprintf(
            '%s-%s-%s.xlsx',
            ApprovedProposal::segmentFromPurpose($this->purpose) ?? 'bantuan',
            $tahun ?? 'semua',
            now()->format('Ymd_His'),
        );

        return Excel::download(
            new ApprovedProposalExport(purpose: $this->purpose, fiscalYear: $tahun),
            $filename,
        );
    }

    /**
     * Open the per-duplicate-group detail modal. The auditor sees the
     * top-level row "MDT MIFTAHUL HUDA · 33×" then drills into the
     * baris usulan satu per satu to verify what's actually shared.
     *
     * Accepts row index (int) — names can contain quote/slash chars that
     * break wire:click syntax after Blade encoding. We resolve index →
     * group → ids here and snapshot the ids so the modal isn't affected
     * by subsequent filter toggles.
     */
    public function viewDetail(int $index): void
    {
        $row = $this->duplikat->get($index);
        if (! $row) {
            return;
        }

        $this->detailIds = $row['ids'];
        $this->detailName = $row['nama'];
        // Modal nawasara-ui listen ke 'modal-open:<id>' (id di event NAME,
        // bukan payload).
        $this->dispatch('modal-open:hibah-duplikat-detail');
    }

    public function closeDetail(): void
    {
        $this->detailIds = [];
        $this->detailName = null;
    }

    /**
     * Pengajuan rows for the currently inspected duplicate group, loaded
     * from the snapshot IDs set at click time.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Pengajuan>
     */
    #[Computed]
    public function duplikatDetail()
    {
        if (empty($this->detailIds)) {
            return collect();
        }

        return ApprovedProposal::query()
            ->with(['opd:id,name', 'kategori:id,nama'])
            ->whereIn('id', $this->detailIds)
            ->orderBy('tahun')
            ->orderBy('id')
            ->get();
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.report.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
