<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Matriks penerima yang sah — inti revisi v0.2.0.
 *
 * Salah di sini berarti mencatat bantuan yang melanggar aturannya sendiri:
 * hibah ke perorangan, atau bansos uang ke lembaga. Keduanya baru ketahuan
 * saat diperiksa pengawas, ketika catatannya sudah dipakai.
 *
 * Salinan VALID_RECIPIENTS diletakkan di sini alih-alih memuat modelnya,
 * supaya tes berjalan tanpa Laravel — sama seperti tes paket cctv. Kalau
 * matriks di model berubah tanpa mengubah yang ini, tes gagal, dan itu
 * memang yang diinginkan: perubahan aturan harus disengaja.
 */
class ValidRecipientsTest extends TestCase
{
    /** Salinan ApprovedProposal::VALID_RECIPIENTS. */
    private const MATRIX = [
        'hibah' => [
            'uang' => ['lembaga', 'kelompok_masyarakat', 'instansi_vertikal'],
            'barang' => ['lembaga', 'kelompok_masyarakat', 'instansi_vertikal'],
        ],
        'bansos' => [
            'uang' => ['perorangan'],
            'barang' => ['lembaga', 'perorangan', 'kelompok_masyarakat'],
        ],
        'bk' => [
            'uang' => ['pemerintah_desa'],
        ],
    ];

    /** @return list<string> */
    private function valid(?string $purpose, ?string $form): array
    {
        return self::MATRIX[$purpose][$form] ?? [];
    }

    private function isValid(?string $purpose, ?string $form, ?string $recipient): bool
    {
        return in_array($recipient, $this->valid($purpose, $form), true);
    }

    /**
     * Aturan paling tegas di catatan diskusi: hibah tidak pernah ke
     * perorangan — di KEDUA bentuk, bukan hanya salah satunya.
     */
    public function test_hibah_tidak_pernah_ke_perorangan(): void
    {
        $this->assertFalse($this->isValid('hibah', 'uang', 'perorangan'));
        $this->assertFalse($this->isValid('hibah', 'barang', 'perorangan'));
    }

    /**
     * Bansos UANG hanya ke perorangan.
     *
     * Ini kebalikan dari dugaan yang wajar — bansos terdengar seperti
     * bantuan yang luas penerimanya, dan untuk bentuk barang memang begitu.
     */
    public function test_bansos_uang_hanya_perorangan(): void
    {
        $this->assertSame(['perorangan'], $this->valid('bansos', 'uang'));

        foreach (['lembaga', 'kelompok_masyarakat', 'instansi_vertikal', 'pemerintah_desa'] as $lain) {
            $this->assertFalse(
                $this->isValid('bansos', 'uang', $lain),
                "bansos uang tidak boleh ke {$lain}",
            );
        }
    }

    /** Bansos BARANG justru yang paling luas. */
    public function test_bansos_barang_paling_luas(): void
    {
        foreach (['lembaga', 'perorangan', 'kelompok_masyarakat'] as $sah) {
            $this->assertTrue($this->isValid('bansos', 'barang', $sah));
        }
    }

    /**
     * Mengganti bentuk pada bansos MEMPERSEMPIT pilihan, tidak melebarkan.
     *
     * Inilah yang membuat nilai yang sudah dipilih bisa menjadi tidak sah:
     * staf memilih Barang → Kelompok Masyarakat, lalu mengubah ke Uang.
     * Kalau pilihannya hanya disembunyikan tanpa dikosongkan, tersimpanlah
     * kombinasi terlarang lewat UI yang terlihat benar.
     */
    public function test_bansos_barang_ke_uang_mempersempit(): void
    {
        $barang = $this->valid('bansos', 'barang');
        $uang = $this->valid('bansos', 'uang');

        $this->assertLessThan(count($barang), count($uang));

        // Yang sah untuk uang harus tetap sah untuk barang — bukan himpunan
        // yang berbeda, melainkan bagian darinya.
        $this->assertEmpty(array_diff($uang, $barang));

        // Dan ada yang GUGUR saat berpindah: itu yang harus dikosongkan.
        $gugur = array_diff($barang, $uang);
        $this->assertContains('kelompok_masyarakat', $gugur);
        $this->assertContains('lembaga', $gugur);
    }

    /** Hibah tidak berubah antara uang dan barang. */
    public function test_hibah_sama_di_kedua_bentuk(): void
    {
        $this->assertSame(
            $this->valid('hibah', 'uang'),
            $this->valid('hibah', 'barang'),
        );
    }

    /**
     * BK tidak punya bentuk barang.
     *
     * Bantuan Keuangan selalu uang; tidak ada BK barang. Pasangan yang
     * tidak ada menghasilkan daftar kosong, dan pemanggilnya harus menolak
     * — bukan menampilkan pilihan kosong yang terbaca "belum diisi".
     */
    public function test_bk_tidak_punya_bentuk_barang(): void
    {
        $this->assertSame([], $this->valid('bk', 'barang'));
        $this->assertSame(['pemerintah_desa'], $this->valid('bk', 'uang'));
    }

    /** Pasangan karangan menghasilkan kosong, bukan galat. */
    public function test_pasangan_tak_dikenal_kosong(): void
    {
        $this->assertSame([], $this->valid('hibah', 'jasa'));
        $this->assertSame([], $this->valid('karangan', 'uang'));
        $this->assertSame([], $this->valid(null, null));
        $this->assertFalse($this->isValid(null, null, 'lembaga'));
    }

    /**
     * Tiap penerima di matriks harus ada di enum RECIPIENT_TYPES.
     *
     * Nilai yang hanya ada di matriks lolos validasi aplikasi lalu ditolak
     * basis data — galat yang muncul saat menyimpan, bukan saat mengisi.
     */
    public function test_semua_penerima_ada_di_enum(): void
    {
        $enum = [
            'lembaga', 'kelompok_masyarakat', 'instansi_vertikal',
            'perorangan', 'pemerintah_desa',
        ];

        foreach (self::MATRIX as $purpose => $forms) {
            foreach ($forms as $form => $recipients) {
                foreach ($recipients as $r) {
                    $this->assertContains($r, $enum, "{$purpose}/{$form}: {$r} tidak ada di enum");
                }
            }
        }
    }
}
