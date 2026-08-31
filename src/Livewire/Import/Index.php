<?php

namespace Nawasara\Hibah\Livewire\Import;

use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Nawasara\Hibah\Exports\TemplateExport;
use Nawasara\Hibah\Imports\ApprovedProposalImport;

/**
 * Admin-only bulk import of historical hibah data from an Excel file.
 * Gated behind hibah.import — importing writes across all OPD, so it's an
 * admin-tier action.
 */
class Index extends Component
{
    use WithFileUploads;

    public int $tahun;

    public $file = null;

    // Last-run result summary (null until an import completes).
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('hibah.import');
        $this->tahun = (int) date('Y');
    }

    public function downloadTemplate()
    {
        $this->authorize('hibah.import');

        return Excel::download(new TemplateExport, 'template-import-hibah.xlsx');
    }

    public function import(): void
    {
        $this->authorize('hibah.import');

        $this->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:51200'], // 50 MB
        ], [], [
            'file' => 'File Excel',
        ]);

        $path = $this->file->getRealPath();

        $import = new ApprovedProposalImport($this->tahun, dry: false);
        Excel::import($import, $path);

        $this->result = [
            'read' => $import->read,
            'skipped' => $import->skipped,
            'created' => $import->created,
            'realisasi' => $import->disbursementsWritten,
            'opdCreated' => $import->opdCreated,
        ];

        $this->reset('file');
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => "Import selesai — {$import->created} pengajuan dibuat.",
        ]);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.import.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
