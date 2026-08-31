<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Panel harus melepas relasi termuat sebelum menyegarkan.
 *
 * `Model::refresh()` menyegarkan atribut tetapi **mempertahankan relasi yang
 * sudah termuat**. Jadi setelah realisasi disimpan, `$proposal->disbursements`
 * masih berisi koleksi sebelum penyimpanan — dan bento menampilkan
 * "Sebagian Cair" pada usulan yang baru saja lunas.
 *
 * Gejalanya menipu karena angka di panel realisasi berubah (ia menghitung
 * dari masukan formulir), sementara petak dana di atasnya tidak. Terlihat
 * seperti status yang salah hitung, padahal datanya yang basi.
 *
 * Tes ini memeriksa kodenya, bukan menjalankan Livewire — cukup untuk
 * menahan pola yang kembali menyelinap saat berkas ini disunting lagi.
 */
class StaleRelationTest extends TestCase
{
    private function source(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__).'/src/Livewire/Proposal/Section/'.$file);
    }

    /**
     * Tiap panel yang memanggil refresh() harus melepas relasinya lebih dulu.
     */
    public function test_panel_melepas_relasi_sebelum_refresh(): void
    {
        foreach (['Disbursement.php', 'Cancel.php'] as $file) {
            $src = $this->source($file);

            $refreshCount = substr_count($src, '$this->proposal->refresh()');
            $unsetCount = substr_count($src, "unsetRelation('disbursements')");

            $this->assertGreaterThanOrEqual(
                $refreshCount,
                $unsetCount,
                "{$file}: ada {$refreshCount} panggilan refresh() tetapi hanya {$unsetCount} unsetRelation — "
                .'relasi yang tertinggal membuat jumlah realisasi terbaca angka lama',
            );
        }
    }

    /**
     * Summary MENGAMBIL ULANG, bukan sekadar refresh.
     *
     * Livewire menghidupkan model dari snapshot permintaan sebelumnya, dan
     * snapshot itu memuat status lama. refresh() memperbarui atribut dari
     * basis data, tetapi komponen ini juga perlu lepas dari relasi yang ikut
     * terbawa — mengambilnya ulang menyelesaikan keduanya sekaligus.
     */
    public function test_summary_mengambil_ulang_dari_basis_data(): void
    {
        $src = $this->source('Summary.php');

        $this->assertStringContainsString(
            'ApprovedProposal::withoutGlobalScopes()',
            $src,
            'Summary harus mengambil ulang usulan, bukan hanya me-refresh objek yang ada',
        );

        $this->assertStringContainsString('findOrFail($this->proposal->getKey())', $src);
    }

    /**
     * Blade TIDAK boleh memanggil fungsi by-reference pada #[Computed].
     *
     * `reset()`, `end()`, `array_shift()` dan sejenisnya menggeser pointer
     * internal array, jadi PHP menuntut sebuah REFERENSI. Properti
     * terkomputasi Livewire dihasilkan lewat __get() dan tidak dapat
     * direferensikan, sehingga hasilnya:
     *
     *   Indirect modification of overloaded property ... has no effect
     *
     * Ia melempar saat halaman DIBUKA, bukan saat dikompilasi — jadi lolos
     * view:cache dan baru muncul di layar pengguna.
     */
    public function test_blade_tidak_memanggil_fungsi_by_reference_pada_computed(): void
    {
        $dir = dirname(__DIR__).'/resources/views';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        $offenders = [];

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $src = (string) file_get_contents($file->getPathname());

            if (preg_match('/(reset|end|array_shift|array_pop|sort|usort|next|prev)\(\$this->/', $src, $m)) {
                $offenders[] = $file->getFilename().': '.$m[1].'($this->...)';
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'fungsi by-reference pada properti terkomputasi melempar saat halaman dibuka',
        );
    }

    /**
     * Sisa DIHITUNG, bukan kolom isian.
     *
     * Sebelumnya "Belum Dicairkan" diketik tangan, dan hasilnya dua angka
     * yang bertentangan di layar yang sama: total realisasi 4.900.000 di
     * sebelah "belum dicairkan 4.800.000". Yang satu bergerak saat triwulan
     * diisi, yang satu tidak, dan tidak ada cara membaca mana yang benar.
     */
    public function test_sisa_dihitung_bukan_diketik(): void
    {
        $src = $this->source('Disbursement.php');

        $this->assertStringContainsString('public function remaining(): ?int', $src);
        $this->assertStringContainsString('$this->proposal->undisbursed_budget = $this->remaining()', $src);

        // Properti masukan harus SUDAH TIDAK ADA.
        $this->assertStringNotContainsString('public ?int $undisbursed_budget', $src);
        $this->assertStringNotContainsString("'undisbursed_budget' => ['nullable'", $src);
    }

    /**
     * Realisasi melebihi anggaran DITOLAK, bukan sekadar diperingatkan.
     *
     * Angka yang melampaui SK hampir selalu salah ketik nol. Menyimpannya
     * membuat laporan pencairan melebihi pagu, dan itu baru ketahuan saat
     * diperiksa pengawas.
     */
    public function test_kelebihan_bayar_ditolak_saat_simpan(): void
    {
        $src = $this->source('Disbursement.php');

        $this->assertStringContainsString('public function overspend(): int', $src);

        // Harus ada jalan keluar lebih awal — bukan hanya menampilkan pesan.
        $this->assertMatchesRegularExpression(
            '/if \(\$this->overspend\(\) > 0\) \{.*?return;.*?\}/s',
            $src,
            'save() harus berhenti saat kelebihan, bukan meneruskan penyimpanan',
        );
    }

    /**
     * Jumlah realisasi dihitung lewat QUERY, bukan relasi termuat.
     *
     * `$proposal->disbursements->sum(...)` membaca koleksi yang termuat;
     * `$proposal->disbursements()->sum(...)` bertanya ke basis data. Bedanya
     * satu tanda kurung, dan yang pertama diam-diam mengembalikan angka
     * sebelum penyimpanan.
     */
    public function test_jumlah_realisasi_lewat_query(): void
    {
        $src = $this->source('Summary.php');

        // Dicocokkan sebagai POLA, bukan teks persis — menuntut indentasi
        // yang sama membuat tes patah tiap kali berkasnya dirapikan.
        $this->assertMatchesRegularExpression(
            '/disbursements\(\)\s*(->toBase\(\)\s*)?->sum\(/s',
            $src,
            'disbursedTotal harus memakai query builder, bukan koleksi termuat',
        );

        // Bentuk properti (tanpa kurung) tidak boleh dipakai untuk menjumlah.
        $this->assertStringNotContainsString(
            '$this->proposal->disbursements->sum(',
            $src,
        );
    }
}
