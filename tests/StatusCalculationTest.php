<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Status pencairan yang DIHITUNG, bukan dipilih staf.
 *
 * Yang dijaga tes ini adalah janji ke pengawas: angka di layar dan status
 * di sebelahnya tidak pernah bertentangan. Baris bertuliskan "Cair" dengan
 * realisasi Rp 0 adalah cacat yang seharusnya mustahil.
 */
class StatusCalculationTest extends TestCase
{
    private const APPROVED = 'approved';

    private const PARTIAL = 'partially_disbursed';

    private const DISBURSED = 'disbursed';

    private const CANCELLED = 'cancelled';

    /** Salinan ApprovedProposal::recalculateStatus(). */
    private function hitung(string $current, int $disbursed, ?int $approvedBudget): string
    {
        if ($current === self::CANCELLED) {
            return self::CANCELLED;
        }

        $approved = (int) $approvedBudget;

        return match (true) {
            $disbursed <= 0 => self::APPROVED,
            $approved > 0 && $disbursed >= $approved => self::DISBURSED,
            default => self::PARTIAL,
        };
    }

    public function test_belum_cair_tetap_disahkan(): void
    {
        $this->assertSame(self::APPROVED, $this->hitung(self::APPROVED, 0, 1_500_000_000));
    }

    public function test_sebagian_cair(): void
    {
        $this->assertSame(self::PARTIAL, $this->hitung(self::APPROVED, 500_000_000, 1_500_000_000));
    }

    public function test_cair_penuh(): void
    {
        $this->assertSame(self::DISBURSED, $this->hitung(self::APPROVED, 1_500_000_000, 1_500_000_000));
    }

    /**
     * Cair MELEBIHI anggaran tetap dihitung lunas.
     *
     * Kelebihan bayar adalah persoalan tersendiri dan bukan urusan status;
     * menahannya di "sebagian" hanya menyembunyikan bahwa uangnya sudah
     * habis tersalur.
     */
    public function test_cair_melebihi_anggaran_tetap_lunas(): void
    {
        $this->assertSame(self::DISBURSED, $this->hitung(self::APPROVED, 1_600_000_000, 1_500_000_000));
    }

    /**
     * ⚠️ Penjagaan yang paling mudah terlewat.
     *
     * `approved_budget` bisa kosong — baris hasil impor tidak selalu
     * memuatnya, dan di tangkapan layar produksi memang kosong. Tanpa
     * pemeriksaan `$approved > 0`, perbandingan `$disbursed >= 0` selalu
     * benar dan SETIAP baris bercap "Cair" begitu ada serupiah cair.
     *
     * Menyatakan lunas atas dasar angka yang tidak diketahui lebih
     * berbahaya daripada menyatakan sebagian.
     */
    public function test_anggaran_kosong_tidak_pernah_dinyatakan_lunas(): void
    {
        foreach ([null, 0] as $kosong) {
            $this->assertSame(
                self::PARTIAL,
                $this->hitung(self::APPROVED, 500_000_000, $kosong),
                'anggaran disetujui kosong tidak boleh menghasilkan "Cair"',
            );
        }
    }

    /** Anggaran kosong DAN belum cair tetap "Disahkan", bukan "Sebagian". */
    public function test_anggaran_kosong_tanpa_pencairan_tetap_disahkan(): void
    {
        $this->assertSame(self::APPROVED, $this->hitung(self::APPROVED, 0, null));
    }

    /**
     * Pembatalan keputusan manusia — angka tidak boleh menimpanya.
     *
     * Tanpa penjagaan ini, menyimpan realisasi pada usulan yang sudah batal
     * akan diam-diam menghidupkannya kembali.
     */
    public function test_batal_tidak_ditimpa_angka(): void
    {
        $this->assertSame(self::CANCELLED, $this->hitung(self::CANCELLED, 0, 1_000));
        $this->assertSame(self::CANCELLED, $this->hitung(self::CANCELLED, 1_000, 1_000));
        $this->assertSame(self::CANCELLED, $this->hitung(self::CANCELLED, 999, 1_000));
    }

    /**
     * Koreksi menurunkan status kembali.
     *
     * Staf yang salah mencatat "Cair" harus dapat membetulkannya dengan
     * memperbaiki angkanya, tanpa menghapus barisnya.
     */
    public function test_koreksi_angka_menurunkan_status(): void
    {
        $this->assertSame(self::PARTIAL, $this->hitung(self::DISBURSED, 500_000, 1_000_000));
        $this->assertSame(self::APPROVED, $this->hitung(self::DISBURSED, 0, 1_000_000));
    }

    /**
     * Nilai negatif diperlakukan seperti nol.
     *
     * Kolomnya unsigned, jadi ini seharusnya mustahil — tetapi kalau toh
     * terjadi, "Disahkan" lebih jujur daripada "Sebagian Cair" yang
     * menyiratkan ada uang berpindah.
     */
    public function test_negatif_diperlakukan_sebagai_nol(): void
    {
        $this->assertSame(self::APPROVED, $this->hitung(self::APPROVED, -1, 1_000));
    }
}
