<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Peta kolom template HARUS sama dengan yang dibaca importer.
 *
 * Template adalah berkas yang menentukan bentuk data kiriman OPD. Kalau
 * urutan kolomnya meleset satu saja, seluruh berkas tertolak — dan yang
 * menemukannya petugas OPD setelah mengisi ratusan baris, bukan kita.
 *
 * Tes ini membandingkan judul kolom template dengan indeks yang benar-benar
 * dibaca ApprovedProposalImport, keduanya diambil dari berkas sumbernya.
 */
class TemplateColumnMapTest extends TestCase
{
    /** Indeks yang dibaca importer, dari peta di importRow(). */
    private const IMPORTER_READS = [
        1 => 'Tahun',
        2 => 'Peruntukan',
        3 => 'Pengusul',
        4 => 'Dapil DPRD',
        5 => 'Lintas Dapil',
        6 => 'Kamus Usulan',
        7 => 'Keputusan Kepala Daerah',
        8 => 'Tanggal Proposal',
        9 => 'Bentuk',
        10 => 'Nama OPD',
        11 => 'Program',
        12 => 'Kegiatan',
        13 => 'Sub Kegiatan',
        14 => 'Nama Penerima',
        15 => 'Alamat Penerima',
        16 => 'Anggaran Sebelum Perubahan',
        17 => 'Anggaran Setelah Perubahan',
        18 => 'Jenis Penerima',
        19 => 'Realisasi TW I',
        20 => 'Realisasi TW II',
        21 => 'Realisasi TW III',
        22 => 'Realisasi TW IV',
        23 => 'Anggaran Belum Dicairkan',
        24 => 'Alasan Belum Dicairkan',
        26 => 'Keterangan',
        27 => 'Jenis BK',
    ];

    /** @return list<string> */
    private function templateHeadings(): array
    {
        $src = (string) file_get_contents(dirname(__DIR__).'/src/Exports/Sheets/DataSheet.php');

        preg_match('/public function headings\(\): array\s*\{(.*?)\n    \}/s', $src, $m);
        $this->assertNotEmpty($m, 'blok headings() tidak ditemukan');

        preg_match_all("/'([^']+)',\s*\/\/\s*(\d+)/", $m[1], $rows, PREG_SET_ORDER);

        $out = [];

        foreach ($rows as $row) {
            $out[(int) $row[2]] = $row[1];
        }

        ksort($out);

        return $out;
    }

    public function test_tiap_kolom_yang_dibaca_importer_ada_di_template(): void
    {
        $template = $this->templateHeadings();

        $this->assertNotEmpty($template, 'judul kolom template tidak terbaca');

        foreach (self::IMPORTER_READS as $index => $label) {
            $this->assertArrayHasKey(
                $index,
                $template,
                "importer membaca indeks {$index} ({$label}) tetapi template tidak punya kolom di situ",
            );

            $this->assertSame(
                $label,
                $template[$index],
                "indeks {$index}: importer mengharapkan '{$label}', template menulis '{$template[$index]}'",
            );
        }
    }

    /**
     * Kolom sumbu TIDAK boleh bertabrakan dengan realisasi.
     *
     * Jenis BK sempat ditaruh di indeks 19 — yang sudah dipakai Realisasi
     * TW I. Nominal triwulan pertama akan terbaca sebagai jenis BK dan
     * sebaliknya, tanpa galat apa pun.
     */
    public function test_kolom_sumbu_tidak_bertabrakan_dengan_realisasi(): void
    {
        $realisasi = [19, 20, 21, 22];
        $sumbu = ['Peruntukan' => 2, 'Bentuk' => 9, 'Jenis Penerima' => 18, 'Jenis BK' => 27];

        foreach ($sumbu as $label => $index) {
            $this->assertNotContains($index, $realisasi, "{$label} (indeks {$index}) bentrok dengan kolom realisasi");
        }

        $this->assertSame(array_values($sumbu), array_unique(array_values($sumbu)));
    }

    /**
     * Sheet Data harus bernama persis "Data".
     *
     * Importer memilih sheet lewat NAMA, bukan urutan
     * (ApprovedProposalImport::sheets()). Mengganti namanya membuat importer
     * tidak menemukan apa pun — dan hasilnya "0 baris dibaca", bukan galat.
     */
    public function test_nama_sheet_data_cocok_dengan_yang_dicari_importer(): void
    {
        $sheet = (string) file_get_contents(dirname(__DIR__).'/src/Exports/Sheets/DataSheet.php');
        $import = (string) file_get_contents(dirname(__DIR__).'/src/Imports/ApprovedProposalImport.php');

        $this->assertMatchesRegularExpression("/return 'Data';/", $sheet);
        $this->assertMatchesRegularExpression("/'Data' => \\\$this/", $import);
    }

    /**
     * Tiap kolom pilihan punya sheet referensi bernama SAMA.
     *
     * Kalau namanya berbeda, petugas harus menebak sheet mana yang berlaku
     * untuk kolom yang sedang diisi — dan itu persis pekerjaan yang ingin
     * dihapus dengan memecah master jadi satu sheet per kolom.
     */
    public function test_tiap_kolom_pilihan_punya_sheet_bernama_sama(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__).'/src/Exports/TemplateExport.php');

        foreach (['Peruntukan', 'Bentuk', 'Jenis Penerima', 'Jenis BK', 'Nama OPD'] as $column) {
            $this->assertStringContainsString(
                "new ReferenceSheet('{$column}'",
                $template,
                "kolom '{$column}' tidak punya sheet referensi bernama sama",
            );
        }
    }

    /**
     * Sheet OPD memuat KODE dan NAMA.
     *
     * Kode lebih pendek dan lebih jarang salah ketik, tetapi sebagiannya
     * masih hasil generate lama yang terpotong (BADAN_KESATUAN_BANGS) —
     * jadi nama tetap ditampilkan sebagai jalan keluar, dan importer
     * menerima keduanya.
     */
    public function test_sheet_opd_memuat_kode_dan_nama(): void
    {
        $template = (string) file_get_contents(dirname(__DIR__).'/src/Exports/TemplateExport.php');
        $import = (string) file_get_contents(dirname(__DIR__).'/src/Imports/ApprovedProposalImport.php');

        $this->assertStringContainsString("new ReferenceSheet('Nama OPD', ['Kode', 'Nama']", $template);

        // Importer harus mencoba kode DAN nama.
        $this->assertStringContainsString("where('code', Str::upper(\$value))", $import);
        $this->assertStringContainsString("where('name', \$value)", $import);
    }

    /**
     * Importer HARUS membatasi diri ke satu sheet.
     *
     * Tanpa WithMultipleSheets, Maatwebsite membaca SELURUH sheet dengan
     * import yang sama — jadi sheet Master ikut diproses sebagai baris
     * usulan. Diam, tetapi salah.
     */
    public function test_importer_membatasi_sheet(): void
    {
        $import = (string) file_get_contents(dirname(__DIR__).'/src/Imports/ApprovedProposalImport.php');

        $this->assertStringContainsString('WithMultipleSheets', $import);
        $this->assertStringContainsString('public function sheets(): array', $import);
    }
}
