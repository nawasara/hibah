<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Template impor kosong.
 *
 * ⚠️ **Berkas inilah yang menentukan apakah data kiriman OPD cocok dengan
 * skema.** Urutan kolomnya harus persis sama dengan peta indeks
 * ApprovedProposalImport — template yang meleset satu kolom menghasilkan
 * seluruh berkas tertolak, dan yang menemukannya adalah petugas OPD, bukan
 * kita.
 *
 * Perubahan v0.2.0 yang wajib disebarkan SEBELUM OPD diminta mengisi:
 *
 *   Kolom 2   Kategori (teks bebas)  → Peruntukan (Hibah/Bansos/BK)
 *   Kolom 9   Peruntukan             → Bentuk (Uang/Barang)
 *   Kolom 18  Verifikasi MS/TMS      → Jenis Penerima
 *   Kolom 27  —                      → Jenis BK (umum/ADD)
 *
 * Berkas berformat lama tidak akan salah tafsir diam-diam: nilainya tidak
 * cocok dengan daftar yang sah, jadi barisnya ditolak dengan alasan yang
 * menyebut sel mana yang perlu dibetulkan.
 *
 * Catatan: formulir asli OPD punya header dua baris (utama + sub-header
 * Triwulan). Template ini memipihkannya jadi satu baris dengan kolom
 * "Realisasi TW I..IV" eksplisit — lebih sederhana bagi petugas, dan logika
 * lewati-header di importer (memeriksa apakah sel Tahun berupa angka)
 * menangani kedua bentuk.
 */
class TemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Bantuan Daerah';
    }

    public function headings(): array
    {
        // Indeks 0..27 — posisi yang sama dengan yang dibaca importer.
        return [
            'No',                          // 0  (diabaikan importer)
            'Tahun',                       // 1
            'Peruntukan',                  // 2  Hibah / Bansos / Bantuan Keuangan
            'Pengusul',                    // 3
            'Dapil DPRD',                  // 4
            'Lintas Dapil',                // 5  isi "Lintas Dapil" atau kosong
            'Kamus Usulan',                // 6
            'Keputusan Kepala Daerah',     // 7  SK yang mengesahkan
            'Tanggal Proposal',            // 8  format: YYYY-MM-DD
            'Bentuk',                      // 9  Uang / Barang
            'Nama OPD',                    // 10
            'Program',                     // 11
            'Kegiatan',                    // 12
            'Sub Kegiatan',                // 13
            'Nama Penerima',               // 14 WAJIB
            'Alamat Penerima',             // 15
            'Anggaran Sebelum Perubahan',  // 16 angka, tanpa titik/Rp
            'Anggaran Setelah Perubahan',  // 17
            'Jenis Penerima',              // 18 lihat catatan di bawah
            'Realisasi TW I',              // 19
            'Realisasi TW II',             // 20
            'Realisasi TW III',            // 21
            'Realisasi TW IV',             // 22
            'Anggaran Belum Dicairkan',    // 23
            'Alasan Belum Dicairkan',      // 24
            'Bukti Monev',                 // 25 (diunggah di aplikasi — kolom diabaikan)
            'Keterangan',                  // 26
            'Jenis BK',                    // 27 hanya untuk Bantuan Keuangan
        ];
    }

    /**
     * Baris contoh — satu per peruntukan.
     *
     * Tiga contoh, bukan satu, karena aturan jenis penerimanya BERBEDA per
     * peruntukan dan itu yang paling sering salah diisi. Bansos uang hanya
     * boleh ke perorangan, sementara bansos barang justru paling luas —
     * memperlihatkannya lebih meyakinkan daripada menuliskan aturannya.
     */
    public function array(): array
    {
        return [
            [
                1, 2026, 'Hibah', 'Komisi A', 'Dapil 1', '',
                'Belanja Program - Pembangunan Ruang Kelas',
                '100.3.3.2/ARH/156/405.27/2026', '2026-01-15', 'Uang',
                'DINAS PENDIDIKAN',
                'Program Pendidikan', 'Kegiatan X', 'Pembangunan Ruang Kelas Baru',
                'MI Contoh 01', 'Jl. Contoh No. 1 Ds. Contoh',
                200000000, 200000000, 'Lembaga',
                0, 0, 0, 0, '', '', '', 'CONTOH — hapus baris ini', '',
            ],
            [
                2, 2026, 'Bansos', '', '', '',
                'Bantuan sosial untuk keluarga terdampak',
                '100.3.3.2/ARH/157/405.27/2026', '2026-02-01', 'Uang',
                'DINAS SOSIAL',
                'Program Perlindungan Sosial', 'Kegiatan Y', 'Bantuan Langsung',
                'Nama Warga Contoh', 'Ds. Contoh RT 01 RW 02',
                5000000, 5000000, 'Perorangan',
                0, 0, 0, 0, '', '', '', 'CONTOH — bansos UANG hanya ke Perorangan', '',
            ],
            [
                3, 2026, 'Bantuan Keuangan', '', '', '',
                'Alokasi Dana Desa',
                '100.3.3.2/ARH/158/405.27/2026', '2026-01-05', 'Uang',
                'BADAN PENGELOLA KEUANGAN',
                'Program Pemerintahan Desa', 'Kegiatan Z', 'Penyaluran ADD',
                'Pemerintah Desa Contoh', 'Kec. Contoh',
                750000000, 750000000, 'Pemerintah Desa',
                0, 0, 0, 0, '', '', '', 'CONTOH — hapus baris ini', 'ADD',
            ],
        ];
    }

    /**
     * Nilai yang diterima tiap kolom pilihan.
     *
     * Diturunkan dari konstanta model, bukan diketik ulang — daftar yang
     * disalin ke sini akan perlahan berbeda dari yang sungguh divalidasi,
     * dan petugas menyalahkan berkasnya, bukan templatenya.
     *
     * @return array<string, list<string>>
     */
    public static function allowedValues(): array
    {
        return [
            'Peruntukan' => array_values(ApprovedProposal::PURPOSES),
            'Bentuk' => array_values(ApprovedProposal::FORMS),
            'Jenis Penerima' => array_values(ApprovedProposal::RECIPIENT_TYPES),
            'Jenis BK' => array_values(ApprovedProposal::BK_TYPES),
        ];
    }
}
