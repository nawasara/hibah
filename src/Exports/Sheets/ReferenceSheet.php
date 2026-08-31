<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Satu sheet referensi untuk SATU kolom.
 *
 * Sebelumnya semua master ditumpuk di satu sheet berkolom "Kolom | Nilai |
 * Keterangan", dan petugas harus menyaring sendiri baris mana yang berlaku
 * untuk kolom yang sedang diisi. Satu sheet per master menghapus penyaringan
 * itu: buka sheet yang namanya sama dengan kolomnya, salin nilainya.
 *
 * Isinya selalu diturunkan dari konstanta model atau tabel registry — tidak
 * pernah diketik ulang, supaya tidak dapat berbeda dari yang sungguh
 * divalidasi.
 */
class ReferenceSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  string  $sheetTitle  Nama sheet — sebaiknya sama dengan nama kolomnya.
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        private readonly string $sheetTitle,
        private readonly array $headings,
        private readonly array $rows,
    ) {}

    public function title(): string
    {
        // Excel membatasi nama sheet 31 karakter dan menolak beberapa tanda.
        return substr(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $this->sheetTitle), 0, 31);
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
