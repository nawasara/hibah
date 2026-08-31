<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Identitas penerima — kapan dua baris orang/lembaga yang SAMA.
 *
 * Salah di sini merusak dua arah sekaligus, dan keduanya sulit ditemukan:
 * terlalu longgar menggabungkan dua madrasah berbeda jadi satu riwayat
 * penerimaan; terlalu ketat membuat satu lembaga tampak sebagai beberapa
 * penerima yang masing-masing "baru pertama menerima".
 */
class RecipientIdentityTest extends TestCase
{
    /** Salinan ApprovedProposal::normalize(). */
    private function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = mb_strtolower(strip_tags($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return mb_substr(trim($value), 0, 191);
    }

    /** Salinan aturan pencocokan Recipient::findOrCreateFor(). */
    private function sameRecipient(array $a, array $b): bool
    {
        $an = $this->normalize($a[0]);
        $aa = $this->normalize($a[1]);
        $bn = $this->normalize($b[0]);
        $ba = $this->normalize($b[1]);

        // Tanpa alamat tidak ada bukti — jangan digabungkan.
        if ($aa === null || $ba === null) {
            return false;
        }

        return $an === $bn && $aa === $ba;
    }

    /**
     * Perbedaan ejaan dan tanda baca digabungkan.
     *
     * Bentuk nyata dari Excel sumber: kapitalisasi berbeda dan titik di
     * akhir. Tanpa normalisasi, satu madrasah tampil sebagai dua penerima
     * yang masing-masing "baru pertama menerima".
     */
    public function test_ejaan_berbeda_alamat_sama_digabung(): void
    {
        $this->assertTrue($this->sameRecipient(
            ['MI Muhammadiyah 14 Beton', 'Jl. Beton No. 1'],
            ['MI MUHAMMADIYAH 14 BETON.', 'JL. BETON NO. 1'],
        ));
    }

    /**
     * Nama sama + alamat BERBEDA tetap terpisah.
     *
     * "MDT MIFTAHUL HUDA" dipakai banyak madrasah di alamat berbeda.
     * Menggabungkannya akan menyatukan riwayat penerimaan lembaga yang tidak
     * berhubungan — kesalahan yang jauh lebih sulit ditemukan daripada dua
     * baris kembar, karena hasilnya terlihat masuk akal.
     */
    public function test_nama_sama_alamat_beda_tetap_terpisah(): void
    {
        $this->assertFalse($this->sameRecipient(
            ['MDT Miftahul Huda', 'Ds. Satu'],
            ['MDT Miftahul Huda', 'Ds. Dua'],
        ));
    }

    /**
     * Alamat kosong TIDAK pernah digabungkan.
     *
     * Dua nama identik tanpa alamat bisa jadi dua lembaga berbeda. Memilih
     * salah — dan tetap membuat baris terpisah — lebih aman daripada
     * menggabungkan yang tidak terbukti sama.
     */
    public function test_alamat_kosong_tidak_digabung(): void
    {
        $this->assertFalse($this->sameRecipient(
            ['MDT Miftahul Huda', null],
            ['MDT Miftahul Huda', null],
        ));

        $this->assertFalse($this->sameRecipient(
            ['MDT Miftahul Huda', ''],
            ['MDT Miftahul Huda', 'Ds. Satu'],
        ));
    }

    /** Spasi berlebih dan spasi di ujung tidak membedakan. */
    public function test_spasi_tidak_membedakan(): void
    {
        $this->assertTrue($this->sameRecipient(
            ['  MI   Contoh  01 ', 'Jl. Contoh'],
            ['MI Contoh 01', 'Jl.   Contoh  '],
        ));
    }

    /**
     * Nama berbeda di alamat sama tetap terpisah.
     *
     * Satu alamat dapat menampung lebih dari satu lembaga — masjid dan
     * madrasah di halaman yang sama, misalnya.
     */
    public function test_nama_beda_alamat_sama_tetap_terpisah(): void
    {
        $this->assertFalse($this->sameRecipient(
            ['Masjid Al-Ikhlas', 'Ds. Contoh RT 01'],
            ['MDT Al-Ikhlas', 'Ds. Contoh RT 01'],
        ));
    }

    /**
     * Kunci ternormalisasi dipotong 191 karakter, sesuai lebar kolomnya.
     *
     * Nama yang berbagi 191 karakter pertama akan dianggap sama — dapat
     * diterima untuk identitas penerima, dan itulah kompromi yang membuat
     * kolomnya dapat di-index di utf8mb4.
     */
    public function test_dipotong_pada_batas_index(): void
    {
        $this->assertSame(191, mb_strlen((string) $this->normalize(str_repeat('a', 300))));
    }
}
