<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Registry\Models\Opd;

/**
 * Sheet referensi — nilai yang diterima importer, apa adanya.
 *
 * Tanpa ini petugas OPD menebak ejaan, dan importer menolak baris demi baris
 * dengan alasan yang baru terbaca setelah berkasnya diisi penuh. Menyalin
 * dari sheet ini menghapus seluruh kelas kesalahan itu.
 *
 * ⚠️ Isinya diturunkan dari konstanta model dan tabel OPD — BUKAN diketik
 * ulang. Daftar yang disalin ke sini akan perlahan berbeda dari yang
 * sungguh divalidasi, dan petugas akan menyalahkan berkasnya, bukan
 * templatenya.
 */
class MasterSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Master';
    }

    public function headings(): array
    {
        return ['Kolom', 'Nilai yang Diterima', 'Keterangan'];
    }

    public function array(): array
    {
        $rows = [];

        foreach (ApprovedProposal::PURPOSES as $label) {
            $rows[] = ['Peruntukan', $label, ''];
        }

        foreach (ApprovedProposal::FORMS as $label) {
            $rows[] = ['Bentuk', $label, 'Bantuan Keuangan selalu Uang'];
        }

        // Jenis penerima disertai peruntukan mana yang membolehkannya —
        // aturannya berbeda per pasangan, dan itu yang paling sering salah.
        foreach (ApprovedProposal::RECIPIENT_TYPES as $key => $label) {
            $rows[] = ['Jenis Penerima', $label, $this->allowedFor($key)];
        }

        foreach (ApprovedProposal::BK_TYPES as $key => $label) {
            $rows[] = [
                'Jenis BK',
                $label,
                match ($key) {
                    'add' => 'Alokasi Dana Desa',
                    'dd' => 'Dana Desa',
                    default => 'Bantuan keuangan tanpa peruntukan khusus',
                },
            ];
        }

        $rows[] = ['', '', ''];

        foreach ($this->opdNames() as $name) {
            $rows[] = ['Nama OPD', $name, ''];
        }

        return $rows;
    }

    /**
     * Peruntukan + bentuk mana yang menerima jenis penerima ini.
     *
     * Dibaca dari matriks yang sama dengan validasi, jadi keterangan di
     * template tidak dapat berbeda dari yang sungguh diterima.
     */
    private function allowedFor(string $recipientType): string
    {
        $allowed = [];

        foreach (ApprovedProposal::VALID_RECIPIENTS as $purpose => $forms) {
            foreach ($forms as $form => $types) {
                if (! in_array($recipientType, $types, true)) {
                    continue;
                }

                $allowed[] = sprintf(
                    '%s %s',
                    ApprovedProposal::PURPOSES[$purpose],
                    ApprovedProposal::FORMS[$form],
                );
            }
        }

        return $allowed === [] ? '—' : 'Untuk: '.implode(', ', $allowed);
    }

    /** @return list<string> */
    private function opdNames(): array
    {
        try {
            return Opd::query()->orderBy('name')->pluck('name')->all();
        } catch (\Throwable) {
            // Tabel OPD belum ada (mis. saat template dibuat di lingkungan
            // baru). Sheet tetap berguna untuk kolom lainnya.
            return [];
        }
    }
}
