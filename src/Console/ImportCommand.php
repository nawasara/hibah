<?php

namespace Nawasara\Hibah\Console;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Nawasara\Hibah\Imports\PengajuanImport;

/**
 * Import historical hibah data from the OPD's yearly Excel form.
 *
 *   php artisan hibah:import "path/to/FORM 2024.xlsx" 2024
 *   php artisan hibah:import "path/to/FORM 2025.csv" 2025 --csv
 *
 * The Excel layout (confirmed identical across 2024/2026):
 *   row 1  — main headers (No, Tahun, Kategori, ... Keterangan)
 *   row 2  — sub-headers for the Realisasi block (Triwulan I-IV)
 *   row 3+ — data
 *
 * Because of the two-row header, we DON'T use WithHeadingRow — the import
 * maps by zero-based column index instead (see PengajuanImport).
 *
 * --csv streams a pre-converted CSV row-by-row via fgetcsv (importCsv),
 * which is the ONLY memory-safe path for the big files. The 2025 xlsx
 * (34 MB → 267 MB unzipped) OOMs PhpSpreadsheet, so always feed its CSV
 * with --csv. See CLAUDE.md §13.c.
 */
class ImportCommand extends Command
{
    protected $signature = 'hibah:import {file : Path to the .xlsx (or .csv with --csv) file} {tahun : Year to stamp on imported rows} {--csv : Stream a pre-converted CSV row-by-row (memory-safe; use for large files)} {--dry : Parse and report without writing}';

    protected $description = 'Import historical hibah/bansos data from an OPD Excel form';

    public function handle(): int
    {
        $file = $this->argument('file');
        $tahun = (int) $this->argument('tahun');
        $dry = (bool) $this->option('dry');
        $csv = (bool) $this->option('csv');

        if (! is_file($file)) {
            $this->error("File tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $mode = $csv ? 'CSV stream' : 'xlsx';
        $this->info(($dry ? '[DRY RUN] ' : '')."Importing {$file} ({$mode}) as tahun {$tahun}...");

        $import = new PengajuanImport($tahun, $dry);

        if ($csv) {
            $import->importCsv($file);
        } else {
            Excel::import($import, $file);
        }

        $this->newLine();
        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Baris dibaca', $import->read],
                ['Dilewati (kosong/invalid)', $import->skipped],
                ['Pengajuan dibuat', $import->created],
                ['Realisasi diisi', $import->realisasiWritten],
                ['OPD baru dibuat', $import->opdCreated],
            ],
        );

        if ($dry) {
            $this->warn('DRY RUN — tidak ada data yang ditulis.');
        } else {
            $this->info('Import selesai.');
        }

        return self::SUCCESS;
    }
}
