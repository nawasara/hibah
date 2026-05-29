<?php

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Blank import template — column order matches PengajuanImport's index map
 * EXACTLY so a filled-in template imports cleanly. One example row is
 * included to show the expected shape; operators delete it before filling.
 *
 * NOTE: the original OPD form has a two-row header (main + Triwulan
 * sub-header). This template flattens that into a single header row with
 * explicit "Realisasi TW I..IV" columns — simpler for operators AND the
 * import's header-skip logic (numeric-year check) handles either layout.
 */
class TemplateExport implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Hibah';
    }

    public function headings(): array
    {
        // Index 0..26 — same positions PengajuanImport reads.
        return [
            'No',                          // 0  (ignored on import)
            'Tahun',                       // 1
            'Kategori',                    // 2
            'Pengusul',                    // 3
            'Dapil DPRD',                  // 4
            'Lintas Dapil',                // 5  isi "Lintas Dapil" atau kosong
            'Kamus Usulan',                // 6
            'Keputusan Kepala Daerah',     // 7  (SK — isi jika sudah disetujui)
            'Tanggal Proposal',            // 8  format: YYYY-MM-DD
            'Peruntukan',                  // 9  HIBAH / BANSOS / BK
            'Nama OPD',                    // 10
            'Program',                     // 11
            'Kegiatan',                    // 12
            'Sub Kegiatan',                // 13
            'Nama Penerima',               // 14 WAJIB
            'Alamat Penerima',             // 15
            'Anggaran Sebelum Perubahan',  // 16 angka, tanpa titik/Rp
            'Anggaran Setelah Perubahan',  // 17
            'Verifikasi (MS/TMS)',         // 18
            'Realisasi TW I',              // 19
            'Realisasi TW II',             // 20
            'Realisasi TW III',            // 21
            'Realisasi TW IV',             // 22
            'Anggaran Belum Dicairkan',    // 23
            'Alasan Belum Dicairkan',      // 24
            'Bukti Monev',                 // 25 (diisi via upload di aplikasi, kolom diabaikan import)
            'Keterangan',                  // 26
        ];
    }

    public function array(): array
    {
        // One example row to illustrate the format. Operators replace it.
        return [
            [
                1, 2026, 'HIBAH UANG', 'Komisi A', 'Dapil 1', '',
                'Belanja Program - Pembangunan Ruang Kelas',
                '', '2026-01-15', 'HIBAH', 'DINAS PENDIDIKAN',
                'Program Pendidikan', 'Kegiatan X', 'Pembangunan Ruang Kelas Baru',
                'MI Contoh 01', 'Jl. Contoh No. 1 Ds. Contoh',
                200000000, '', 'MS',
                0, 0, 0, 0, '', '', 'Contoh — hapus baris ini sebelum mengisi',
            ],
        ];
    }
}
