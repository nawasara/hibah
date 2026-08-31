<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Nawasara\Hibah\Exports\Sheets\DataSheet;
use Nawasara\Hibah\Exports\Sheets\MasterSheet;

/**
 * Template impor — dua sheet.
 *
 * **Data** yang diisi petugas, dan **Master** yang memuat nilai sah untuk
 * tiap kolom pilihan. Tanpa sheet Master, petugas menebak ejaan dan importer
 * menolak baris demi baris — dengan alasan yang baru terbaca setelah
 * berkasnya diisi penuh.
 *
 * ⚠️ **Berkas inilah yang menentukan apakah data kiriman OPD cocok dengan
 * skema.** Urutan kolom di DataSheet harus persis sama dengan peta indeks
 * ApprovedProposalImport; template yang meleset satu kolom menggagalkan
 * seluruh berkas, dan yang menemukannya petugas OPD.
 *
 * Importer memilih sheet berdasarkan NAMA ("Data"), bukan urutan — lihat
 * ApprovedProposalImport::sheets(). Jadi menambah sheet baru di template
 * aman, tetapi MENGGANTI NAMA sheet Data akan membuat importer tidak
 * menemukan apa pun.
 */
class TemplateExport implements WithMultipleSheets
{
    /** @return list<object> */
    public function sheets(): array
    {
        return [
            new DataSheet,
            new MasterSheet,
        ];
    }
}
