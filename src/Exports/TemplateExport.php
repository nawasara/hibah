<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Nawasara\Hibah\Exports\Sheets\DataSheet;
use Nawasara\Hibah\Exports\Sheets\ReferenceSheet;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Registry\Models\Opd;

/**
 * Template impor — satu sheet isian, lalu satu sheet per master.
 *
 * Petugas mengisi sheet **Data**, dan setiap kolom pilihan punya sheet
 * referensinya sendiri dengan nama yang sama. Tidak ada penyaringan yang
 * perlu dilakukan sendiri: buka sheet bernama "Jenis Penerima", salin
 * nilainya.
 *
 * ⚠️ **Berkas inilah yang menentukan apakah data kiriman OPD cocok dengan
 * skema.** Urutan kolom di DataSheet harus persis sama dengan peta indeks
 * ApprovedProposalImport; template yang meleset satu kolom menggagalkan
 * seluruh berkas, dan yang menemukannya petugas OPD.
 *
 * Importer memilih sheet berdasarkan NAMA ("Data"), bukan urutan — lihat
 * ApprovedProposalImport::sheets(). Menambah sheet referensi aman;
 * MENGGANTI NAMA sheet Data membuat importer tidak menemukan apa pun.
 */
class TemplateExport implements WithMultipleSheets
{
    /** @return list<object> */
    public function sheets(): array
    {
        return [
            new DataSheet,

            new ReferenceSheet('Peruntukan', ['Nilai'], $this->simple(ApprovedProposal::PURPOSES)),

            new ReferenceSheet('Bentuk', ['Nilai', 'Keterangan'], [
                ['Uang', ''],
                ['Barang', 'Bantuan Keuangan selalu Uang'],
            ]),

            new ReferenceSheet('Jenis Penerima', ['Nilai', 'Berlaku Untuk'], $this->recipientRows()),

            new ReferenceSheet('Jenis BK', ['Nilai', 'Keterangan'], [
                ['Umum', 'Bantuan keuangan tanpa peruntukan khusus'],
                ['ADD', 'Alokasi Dana Desa'],
                ['DD', 'Dana Desa'],
            ]),

            new ReferenceSheet('Nama OPD', ['Kode', 'Nama'], $this->opdRows()),
        ];
    }

    /**
     * @param  array<string, string>  $map
     * @return list<list<string>>
     */
    private function simple(array $map): array
    {
        return array_map(static fn ($label) => [$label], array_values($map));
    }

    /**
     * Jenis penerima + pasangan peruntukan/bentuk yang membolehkannya.
     *
     * Dibaca dari matriks yang sama dengan validasi, jadi keterangan di
     * template tidak dapat berbeda dari yang sungguh diterima — dan aturan
     * inilah yang paling sering salah diisi.
     *
     * @return list<list<string>>
     */
    private function recipientRows(): array
    {
        $rows = [];

        foreach (ApprovedProposal::RECIPIENT_TYPES as $key => $label) {
            $allowed = [];

            foreach (ApprovedProposal::VALID_RECIPIENTS as $purpose => $forms) {
                foreach ($forms as $form => $types) {
                    if (in_array($key, $types, true)) {
                        $allowed[] = ApprovedProposal::PURPOSES[$purpose].' '.ApprovedProposal::FORMS[$form];
                    }
                }
            }

            $rows[] = [$label, $allowed === [] ? '—' : implode(', ', $allowed)];
        }

        return $rows;
    }

    /**
     * Kode DAN nama, karena importer menerima keduanya.
     *
     * Kode lebih pendek dan lebih jarang salah ketik, tetapi sebagian masih
     * hasil generate lama yang terpotong (`BADAN_KESATUAN_BANGS`) — jadi
     * namanya tetap ditampilkan sebagai jalan keluar.
     *
     * @return list<list<string>>
     */
    private function opdRows(): array
    {
        try {
            return Opd::query()
                ->orderBy('name')
                ->get(['code', 'name'])
                ->map(static fn ($o) => [(string) $o->code, (string) $o->name])
                ->all();
        } catch (\Throwable) {
            // Tabel registry belum ada (mis. template dibuat di lingkungan
            // baru). Sheet lain tetap berguna.
            return [];
        }
    }
}
