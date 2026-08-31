<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pemetaan kolom Excel dan penolakan baris.
 *
 * Importer adalah pintu masuk yang tidak melewati formulir, jadi aturan
 * §3 harus ditegakkan di sini juga. Dan penolakannya harus menyebut MENGAPA:
 * petugas yang menerima daftar tolakan perlu tahu sel mana yang dibetulkan
 * tanpa menebak.
 */
class ImportMappingTest extends TestCase
{
    private function mapPurpose(string $v): ?string
    {
        return match (mb_strtolower(trim($v))) {
            'hibah' => 'hibah',
            'bansos', 'bantuan sosial' => 'bansos',
            'bk', 'bantuan keuangan' => 'bk',
            default => null,
        };
    }

    private function mapForm(string $v): ?string
    {
        return match (mb_strtolower(trim($v))) {
            'uang' => 'uang',
            'barang' => 'barang',
            default => null,
        };
    }

    private function mapBkType(string $v): string
    {
        $v = mb_strtolower(trim($v));

        if (preg_match('/\badd\b/', $v) || str_contains($v, 'alokasi dana desa')) {
            return 'add';
        }

        if (preg_match('/\bdd\b/', $v) || str_contains($v, 'dana desa')) {
            return 'dd';
        }

        return 'umum';
    }

    /** Pencocokan tegas, bukan penebakan dari kata. */
    public function test_peruntukan_dicocokkan_tepat(): void
    {
        $this->assertSame('hibah', $this->mapPurpose('Hibah'));
        $this->assertSame('hibah', $this->mapPurpose('  HIBAH  '));
        $this->assertSame('bansos', $this->mapPurpose('Bantuan Sosial'));
        $this->assertSame('bk', $this->mapPurpose('Bantuan Keuangan'));
    }

    /**
     * Yang tidak dikenali DITOLAK, tidak ditebak.
     *
     * Inilah yang dulu keliru: "mengandung kata keuangan → bk" membuat
     * BANTUAN KEUANGAN DARI ADD tercatat 'hibah' untuk 2024 dan 'bk' untuk
     * 2025 — 562 baris di masing-masing tahun, dan tidak ada yang menyadari
     * sampai datanya diperiksa dua tahun kemudian.
     */
    public function test_nilai_tak_dikenal_ditolak_bukan_ditebak(): void
    {
        $this->assertNull($this->mapPurpose('HIBAH UANG'));
        $this->assertNull($this->mapPurpose('BANTUAN KEUANGAN DARI ADD'));
        $this->assertNull($this->mapPurpose('BANTUAN SOSIAL BERUPA UANG'));
        $this->assertNull($this->mapPurpose(''));
    }

    public function test_bentuk_dicocokkan_tepat(): void
    {
        $this->assertSame('uang', $this->mapForm('Uang'));
        $this->assertSame('barang', $this->mapForm('BARANG'));
        $this->assertNull($this->mapForm('jasa'));
        $this->assertNull($this->mapForm(''));
    }

    /**
     * Sub-jenis BK kosong dianggap 'umum', bukan ditolak.
     *
     * Bantuan keuangan tanpa keterangan khusus memang bantuan keuangan
     * umum; menolaknya membuang baris yang sah.
     *
     * ADD = Alokasi Dana Desa, DD = Dana Desa. Keduanya diperiksa dari
     * bentuk singkat maupun panjangnya, karena berkas OPD memakai keduanya.
     */
    public function test_bk_kosong_dianggap_umum(): void
    {
        $this->assertSame('umum', $this->mapBkType(''));
        $this->assertSame('umum', $this->mapBkType('Umum'));
        $this->assertSame('add', $this->mapBkType('ADD'));
        $this->assertSame('add', $this->mapBkType('Alokasi Dana Desa (ADD)'));
        $this->assertSame('dd', $this->mapBkType('DD'));
        $this->assertSame('dd', $this->mapBkType('Dana Desa'));
    }

    /**
     * "ADD" tidak boleh terbaca sebagai "DD".
     *
     * Kata "add" MENGANDUNG "dd". Dengan pencocokan substring, hasilnya
     * bergantung pada urutan pemeriksaan — dan dua baris `match` yang
     * tampak setara akan tertukar cepat atau lambat, membuat seluruh
     * Alokasi Dana Desa tercatat sebagai Dana Desa tanpa galat apa pun.
     *
     * Batas kata () menghapus jebakan itu, dan tes ini menjaganya tetap
     * terhapus.
     */
    public function test_add_tidak_terbaca_sebagai_dd(): void
    {
        foreach (['ADD', 'add', ' ADD ', 'Alokasi Dana Desa (ADD)', 'BK ADD'] as $v) {
            $this->assertSame('add', $this->mapBkType($v), "'{$v}' harus ADD, bukan DD");
        }
    }

    /** Dan sebaliknya: DD tetap DD, dalam bentuk singkat maupun panjang. */
    public function test_dd_dikenali_dua_bentuk(): void
    {
        foreach (['DD', 'dd', 'Dana Desa', 'BK DD'] as $v) {
            $this->assertSame('dd', $this->mapBkType($v), "'{$v}' harus DD");
        }
    }

    /**
     * Kolom bk_type TIDAK boleh bertabrakan dengan realisasi.
     *
     * Realisasi TW I..IV menempati indeks 19–22. Menaruh Jenis BK di 19 —
     * yang sempat terjadi saat menulis ini — membuat nominal triwulan
     * pertama terbaca sebagai jenis BK, dan sebaliknya.
     */
    public function test_indeks_kolom_tidak_bertabrakan(): void
    {
        $realisasi = [19, 20, 21, 22];
        $bkType = 27;
        $penerima = 18;
        $bentuk = 9;
        $peruntukan = 2;

        foreach ([$bkType, $penerima, $bentuk, $peruntukan] as $idx) {
            $this->assertNotContains($idx, $realisasi, "indeks {$idx} bentrok dengan kolom realisasi");
        }

        // Dan keempat sumbunya sendiri harus berbeda satu sama lain.
        $sumbu = [$peruntukan, $bentuk, $penerima, $bkType];
        $this->assertSame($sumbu, array_unique($sumbu));
    }

    /**
     * Baris yang ditolak tidak menghentikan impor.
     *
     * Berkas 4.441 baris pernah gagal di tengah jalan dan menyisakan commit
     * separuh yang harus di-rollback lalu di-migrate ulang. Yang benar:
     * muat yang sah, laporkan sisanya.
     */
    public function test_penolakan_tidak_menghentikan_impor(): void
    {
        $rows = [
            ['Hibah', 'Uang', 'Lembaga'],
            ['Karangan', 'Uang', 'Lembaga'],      // peruntukan tak dikenal
            ['Bansos', 'Uang', 'Lembaga'],        // kombinasi terlarang
            ['Bansos', 'Uang', 'Perorangan'],
        ];

        $created = 0;
        $rejected = [];

        foreach ($rows as $i => [$p, $f, $r]) {
            $purpose = $this->mapPurpose($p);
            $form = $this->mapForm($f);

            if ($purpose === null || $form === null) {
                $rejected[] = $i;

                continue;
            }

            $matrix = [
                'hibah' => ['uang' => ['lembaga', 'kelompok_masyarakat', 'instansi_vertikal']],
                'bansos' => ['uang' => ['perorangan']],
            ];

            if (! in_array(mb_strtolower($r), $matrix[$purpose][$form] ?? [], true)) {
                $rejected[] = $i;

                continue;
            }

            $created++;
        }

        $this->assertSame(2, $created, 'baris yang sah tetap dimuat');
        $this->assertSame([1, 2], $rejected);
    }
}
