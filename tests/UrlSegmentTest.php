<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pemetaan segmen URL ke peruntukan.
 *
 * Segmen dipakai — bukan query string — karena `WorkspaceManager::current()`
 * mencocokkan `request()->path()` dan sidebar memakai `url()->current()`,
 * dan keduanya membuang query string. Dengan `?purpose=bansos` ketiga menu
 * akan menyala bersamaan tanpa ada satu baris pun yang salah menurut
 * kodenya.
 */
class UrlSegmentTest extends TestCase
{
    private const SEGMENTS = [
        'hibah' => 'hibah',
        'bansos' => 'bansos',
        'bantuan-keuangan' => 'bk',
    ];

    private const CHILDREN = [
        'hibah' => ['uang', 'barang'],
        'bansos' => ['uang', 'barang'],
        'bantuan-keuangan' => ['umum', 'khusus'],
    ];

    private function purposeFrom(?string $segment): ?string
    {
        return self::SEGMENTS[$segment] ?? null;
    }

    private function segmentFrom(?string $purpose): ?string
    {
        return array_search($purpose, self::SEGMENTS, true) ?: null;
    }

    private function isValidPair(?string $purpose, ?string $child): bool
    {
        return in_array($child, self::CHILDREN[$purpose] ?? [], true);
    }

    /**
     * URL terbaca manusia, basis data ringkas.
     *
     * `bantuan-keuangan` di URL, `bk` di kolom — satu-satunya pemetaan yang
     * tidak sepele, dan yang paling mungkin terlupa saat menyusun tautan.
     */
    public function test_bantuan_keuangan_dipetakan_ke_bk(): void
    {
        $this->assertSame('bk', $this->purposeFrom('bantuan-keuangan'));
        $this->assertSame('bantuan-keuangan', $this->segmentFrom('bk'));
    }

    public function test_hibah_dan_bansos_sama_di_kedua_sisi(): void
    {
        $this->assertSame('hibah', $this->purposeFrom('hibah'));
        $this->assertSame('bansos', $this->purposeFrom('bansos'));
    }

    /** Bolak-balik harus kembali ke nilai semula. */
    public function test_pemetaan_bolak_balik_konsisten(): void
    {
        foreach (self::SEGMENTS as $segment => $purpose) {
            $this->assertSame($segment, $this->segmentFrom($this->purposeFrom($segment)));
        }
    }

    /**
     * Segmen karangan menghasilkan null, bukan menebak.
     *
     * Pemanggilnya (Detail::mount) menjadikannya 404 — bukan daftar kosong
     * yang terbaca seperti "belum ada data".
     */
    public function test_segmen_karangan_null(): void
    {
        $this->assertNull($this->purposeFrom('bk'));          // nilai DB, bukan segmen
        $this->assertNull($this->purposeFrom('hibah-uang'));
        $this->assertNull($this->purposeFrom(''));
        $this->assertNull($this->purposeFrom(null));
    }

    /** Pasangan yang masuk akal diterima. */
    public function test_pasangan_sah(): void
    {
        $this->assertTrue($this->isValidPair('hibah', 'uang'));
        $this->assertTrue($this->isValidPair('hibah', 'barang'));
        $this->assertTrue($this->isValidPair('bansos', 'uang'));
        $this->assertTrue($this->isValidPair('bantuan-keuangan', 'umum'));
        $this->assertTrue($this->isValidPair('bantuan-keuangan', 'khusus'));
    }

    /**
     * Pasangan silang ditolak.
     *
     * BK tidak punya bentuk barang, dan hibah/bansos tidak punya sub-jenis
     * umum/khusus. Membiarkannya lolos menghasilkan halaman yang selalu
     * kosong — dan staf menyimpulkan datanya hilang.
     */
    public function test_pasangan_silang_ditolak(): void
    {
        $this->assertFalse($this->isValidPair('bantuan-keuangan', 'uang'));
        $this->assertFalse($this->isValidPair('bantuan-keuangan', 'barang'));
        $this->assertFalse($this->isValidPair('hibah', 'umum'));
        $this->assertFalse($this->isValidPair('bansos', 'khusus'));
        $this->assertFalse($this->isValidPair('karangan', 'uang'));
    }
}
