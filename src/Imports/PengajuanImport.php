<?php

namespace Nawasara\Hibah\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Nawasara\Hibah\Models\Kategori;
use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Registry\Models\Opd;

/**
 * Maps the OPD yearly Excel form into pengajuan + realisasi rows.
 *
 * Column index map (0-based, confirmed against 2024/2026 files):
 *   1  Tahun           10 Nama OPD          19 Realisasi TW I
 *   2  Kategori        11 Program           20 Realisasi TW II
 *   3  Pengusul        12 Kegiatan          21 Realisasi TW III
 *   4  Dapil           13 Sub Kegiatan      22 Realisasi TW IV
 *   5  Lintas Dapil    14 Nama Penerima     23 Anggaran Belum Cair
 *   6  Kamus Usulan    15 Alamat Penerima   24 Alasan
 *   7  SK Kepala Dae.  16 Anggaran Sebelum  25 Bukti Monev (skip — file)
 *   8  Tgl Proposal    17 Anggaran Setelah  26 Keterangan
 *   9  Peruntukan      18 Verifikasi (MS/TMS)
 *
 * Two header rows (main + triwulan sub-header) → data starts at index 2
 * within each chunk's collection. We detect & skip header rows by checking
 * whether the Tahun cell is numeric.
 *
 * Runs with OpdScope bypassed: import is an admin/console operation that
 * must write across all OPD regardless of who triggered it.
 */
class PengajuanImport implements ToCollection, WithChunkReading
{
    public int $read = 0;
    public int $skipped = 0;
    public int $created = 0;
    public int $realisasiWritten = 0;
    public int $opdCreated = 0;

    /** Cache OPD + Kategori lookups to avoid a query per row. */
    protected array $opdCache = [];
    protected array $kategoriCache = [];

    public function __construct(
        protected int $tahun,
        protected bool $dry = false,
    ) {}

    public function chunkSize(): int
    {
        return 500;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->importRow($row->values()->all());
        }
    }

    /**
     * Map + persist a single row given a 0-indexed cell array. Shared by the
     * Excel (collection) path and the CSV streaming path so column mapping
     * lives in exactly one place.
     */
    public function importRow(array $cells): void
    {
        $this->read++;

        $namaPenerima = trim((string) ($cells[14] ?? ''));
        $tahunCell = $cells[1] ?? null;

        // Skip header rows + blank rows. A real data row has a numeric year
        // and a recipient name.
        if ($namaPenerima === '' || ! is_numeric($tahunCell)) {
            $this->skipped++;

            return;
        }

        if ($this->dry) {
            $this->created++;
            $this->countRealisasi($cells);

            return;
        }

        $opdId = $this->resolveOpd(trim((string) ($cells[10] ?? '')));
        $kategoriId = $this->resolveKategori(trim((string) ($cells[2] ?? '')));

        $pengajuan = Pengajuan::withoutGlobalScopes()->create([
            'opd_id' => $opdId,
            'tahun' => $this->tahun,
            'kategori_id' => $kategoriId,
            'peruntukan' => $this->mapPeruntukan((string) ($cells[9] ?? '')),
            'pengusul' => $this->str($cells[3] ?? null),
            'dapil' => $this->str($cells[4] ?? null),
            'lintas_dapil' => $this->bool($cells[5] ?? null),
            'kamus_usulan' => $this->str($cells[6] ?? null),
            'sk_kepala_daerah' => $this->str($cells[7] ?? null),
            'tanggal_proposal' => $this->date($cells[8] ?? null),
            'program' => $this->str($cells[11] ?? null),
            'kegiatan' => $this->str($cells[12] ?? null),
            'sub_kegiatan' => $this->str($cells[13] ?? null),
            'nama_penerima' => $namaPenerima,
            'alamat_penerima' => $this->str($cells[15] ?? null),
            'anggaran_sebelum' => $this->money($cells[16] ?? null),
            'anggaran_setelah' => $this->moneyNullable($cells[17] ?? null),
            'status_verifikasi' => $this->mapVerifikasi((string) ($cells[18] ?? '')),
            'anggaran_belum_cair' => $this->moneyNullable($cells[23] ?? null),
            'alasan_belum_cair' => $this->str($cells[24] ?? null),
            'keterangan' => $this->str($cells[26] ?? null),
            // SK present → treat as approved historically.
            'status' => $this->str($cells[7] ?? null)
                ? Pengajuan::STATUS_DISETUJUI
                : Pengajuan::STATUS_DIAJUKAN,
        ]);
        $this->created++;

        $this->writeRealisasi($pengajuan, $cells);
    }

    /**
     * Stream a CSV (already extracted from a huge xlsx) row-by-row. Memory-
     * safe for files with hundreds of thousands of rows because fgetcsv
     * reads one line at a time.
     */
    public function importCsv(string $path): void
    {
        $fh = fopen($path, 'r');
        if ($fh === false) {
            throw new \RuntimeException("Cannot open CSV: {$path}");
        }
        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            $this->importRow($row);
        }
        fclose($fh);
    }

    /**
     * Stream a large xlsx straight without going through CSV. Uses pure
     * XMLReader (no SimpleXMLElement / readOuterXML), which is the only
     * reliable way for huge files — Maatwebsite/PhpSpreadsheet OOMs on
     * any non-trivial xlsx (the 2025 hibah file: 34 MB → 267 MB unzipped
     * → 4+ GB RAM, then fatal). The prior xlsx_to_csv converter that
     * combined readOuterXML() with $reader->next() silently DROPPED rows
     * (3,846 real → 1,924 written) — never use that pattern again.
     *
     * When $csvPath is provided, also writes each row to a CSV at that
     * path while it streams (auditor can sanity-check the conversion
     * out-of-band). Defaults to a sibling .csv next to the source xlsx.
     */
    public function importLargeXlsx(string $path, ?string $csvPath = null): void
    {
        if (! is_file($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $csvPath ??= preg_replace('/\.xlsx$/i', '.csv', $path);

        $tmp = sys_get_temp_dir().'/hibah_xlsx_'.uniqid();
        @mkdir($tmp, 0777, true);

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open xlsx: {$path}");
        }
        $zip->extractTo($tmp);
        $zip->close();

        $csv = fopen($csvPath, 'w');
        if ($csv === false) {
            throw new \RuntimeException("Cannot write CSV: {$csvPath}");
        }

        try {
            $strings = $this->loadSharedStrings($tmp);
            $sheets = glob($tmp.'/xl/worksheets/*.xml');
            if (! $sheets) {
                throw new \RuntimeException('No worksheet found in xlsx');
            }
            $this->streamSheetIntoImport($sheets[0], $strings, $csv);
        } finally {
            fclose($csv);
            $this->rrmdir($tmp);
        }
    }

    /**
     * @return list<string>
     */
    protected function loadSharedStrings(string $extractedRoot): array
    {
        $strings = [];
        $path = $extractedRoot.'/xl/sharedStrings.xml';
        if (! is_file($path)) {
            return $strings;
        }

        $r = new \XMLReader;
        $r->open($path);
        $cur = '';
        $inSi = false;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'si') {
                $inSi = true;
                $cur = '';
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'si') {
                $strings[] = $cur;
                $inSi = false;
            } elseif ($inSi && $r->nodeType === \XMLReader::TEXT) {
                $cur .= $r->value;
            }
        }
        $r->close();

        return $strings;
    }

    /**
     * @param  list<string>  $strings
     * @param  resource|null  $csvHandle  Optional fopen handle — every parsed row is also written here as CSV.
     */
    protected function streamSheetIntoImport(string $sheetPath, array $strings, $csvHandle = null): void
    {
        $colIdx = static function (string $ref): int {
            $col = preg_replace('/[0-9]+/', '', $ref);
            $n = 0;
            for ($i = 0, $len = strlen($col); $i < $len; $i++) {
                $n = $n * 26 + (ord($col[$i]) - 64);
            }

            return $n - 1;
        };

        $r = new \XMLReader;
        $r->open($sheetPath);

        $inRow = false;
        $inCell = false;
        $inV = false;
        $cellRef = '';
        $cellType = '';
        $cellVal = '';
        $rowCells = [];

        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT) {
                if ($r->localName === 'row') {
                    $inRow = true;
                    $rowCells = [];
                } elseif ($inRow && $r->localName === 'c') {
                    $inCell = true;
                    $cellRef = $r->getAttribute('r') ?? '';
                    $cellType = $r->getAttribute('t') ?? '';
                    $cellVal = '';
                } elseif ($inCell && $r->localName === 'v') {
                    $inV = true;
                }
            } elseif ($r->nodeType === \XMLReader::TEXT && $inV) {
                $cellVal .= $r->value;
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT) {
                if ($r->localName === 'v') {
                    $inV = false;
                } elseif ($r->localName === 'c') {
                    $inCell = false;
                    if ($cellVal !== '') {
                        $val = $cellType === 's'
                            ? ($strings[(int) $cellVal] ?? '')
                            : $cellVal;
                        $rowCells[$colIdx($cellRef)] = $val;
                    }
                } elseif ($r->localName === 'row') {
                    $inRow = false;
                    if (empty($rowCells)) {
                        continue;
                    }

                    // Build dense 0..max array so importRow() sees column
                    // indices line up with PengajuanImport column map.
                    $maxIdx = max(array_keys($rowCells));
                    $line = [];
                    for ($i = 0; $i <= $maxIdx; $i++) {
                        $line[$i] = $rowCells[$i] ?? '';
                    }
                    if ($csvHandle !== null) {
                        fputcsv($csvHandle, $line, ',', '"', '\\');
                    }
                    $this->importRow($line);
                }
            }
        }
        $r->close();
    }

    protected function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f) : unlink($f);
        }
        rmdir($dir);
    }

    protected function resolveOpd(string $name): int
    {
        $name = $name !== '' ? $name : 'TIDAK DIKETAHUI';

        if (isset($this->opdCache[$name])) {
            return $this->opdCache[$name];
        }

        $opd = Opd::where('name', $name)->first();
        if (! $opd) {
            $opd = Opd::create([
                'code' => Str::upper(Str::slug(Str::limit($name, 20, ''), '_')) ?: 'OPD_'.Str::random(4),
                'name' => $name,
            ]);
            $this->opdCreated++;
        }

        return $this->opdCache[$name] = $opd->id;
    }

    protected function resolveKategori(string $nama): ?int
    {
        if ($nama === '') {
            return null;
        }

        if (isset($this->kategoriCache[$nama])) {
            return $this->kategoriCache[$nama];
        }

        $kategori = Kategori::firstOrCreate(['nama' => Str::upper($nama)], ['aktif' => true]);

        return $this->kategoriCache[$nama] = $kategori->id;
    }

    protected function writeRealisasi(Pengajuan $pengajuan, array $cells): void
    {
        foreach ([1 => 19, 2 => 20, 3 => 21, 4 => 22] as $tw => $idx) {
            $val = $this->moneyNullable($cells[$idx] ?? null);
            if ($val === null || $val === 0) {
                continue;
            }
            $pengajuan->realisasi()->create([
                'triwulan' => $tw,
                'realisasi_anggaran' => $val,
            ]);
            $this->realisasiWritten++;
        }
    }

    protected function countRealisasi(array $cells): void
    {
        foreach ([19, 20, 21, 22] as $idx) {
            if ($this->moneyNullable($cells[$idx] ?? null)) {
                $this->realisasiWritten++;
            }
        }
    }

    // --- cell coercion helpers -------------------------------------------

    protected function str($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    protected function bool($v): bool
    {
        return Str::contains(Str::lower((string) $v), 'lintas');
    }

    protected function money($v): int
    {
        return $this->moneyNullable($v) ?? 0;
    }

    /**
     * Parse a money cell to int (or null when empty/unparseable).
     *
     * Source data is messy — OPD staff sometimes paste multi-line breakdowns
     * like "1. Rp.20.748.000\n2. Rp.20.748.000" into the anggaran column.
     * Naively stripping non-digits then concatenates everything into a huge
     * number that overflows BIGINT (2024 had 54 such rows, all saturated at
     * INT64 max). Instead, find the FIRST numeric token (allowing thousands
     * separators), strip its separators, and stop there. Multi-line entries
     * are still partially wrong (we keep only the first quarter's amount),
     * but at least the value is plausible and doesn't poison sum() / chart
     * rendering downstream.
     */
    protected function moneyNullable($v): ?int
    {
        $s = (string) $v;
        if (trim($s) === '') {
            return null;
        }

        // Match the first run of digits, allowing dot/comma/space separators
        // between them (so "Rp 200.000.000,00" → "200000000"). Stops at the
        // first newline or text character past the trailing separator.
        if (! preg_match('/[0-9][0-9\.\,\s]*/', $s, $m)) {
            return null;
        }
        $clean = preg_replace('/[^0-9]/', '', $m[0]);
        if ($clean === '') {
            return null;
        }

        // Guard against any value that, even after first-token extraction,
        // still exceeds BIGINT range. Treat that as garbage input → null.
        if (strlen($clean) > 15) { // > 999 trillion: clearly malformed
            return null;
        }

        return (int) $clean;
    }

    protected function date($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        // Excel serial date (numeric) → Y-m-d.
        if (is_numeric($v)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
            } catch (\Throwable $e) {
                return null;
            }
        }
        try {
            return \Carbon\Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function mapPeruntukan(string $v): string
    {
        $v = Str::lower($v);

        return match (true) {
            Str::contains($v, 'bansos') => 'bansos',
            Str::contains($v, 'bk') || Str::contains($v, 'keuangan') => 'bk',
            default => 'hibah',
        };
    }

    protected function mapVerifikasi(string $v): ?string
    {
        $v = Str::upper(trim($v));

        return match (true) {
            Str::contains($v, 'TMS') => 'tms',
            Str::contains($v, 'MS') => 'ms',
            default => null,
        };
    }
}
