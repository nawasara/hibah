<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Ekspor daftar usulan (yang sudah tersaring dan ter-scope) ke Excel.
 *
 * OpdScope pada model tetap berlaku — operator hanya mengekspor OPD-nya.
 *
 * Kolom `Kategori` yang dulu ada diganti tiga kolom terstruktur:
 * Peruntukan, Bentuk, Jenis Penerima. Satu nama teks bebas tidak dapat
 * mewakili ketiganya, dan itulah yang dulu melahirkan "HIBAH UANG KEPADA
 * KOPERASI" — bentuk dan penerima terpadatkan jadi satu.
 */
class ApprovedProposalExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?string $purpose = null,
        protected ?string $form = null,
        protected ?int $fiscalYear = null,
        protected ?string $status = null,
    ) {}

    public function query()
    {
        return ApprovedProposal::query()
            ->with('opd:id,name')
            ->when($this->purpose, fn ($q) => $q->where('purpose', $this->purpose))
            ->when($this->form, fn ($q) => $q->where('form', $this->form))
            ->when($this->fiscalYear, fn ($q) => $q->where('fiscal_year', $this->fiscalYear))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('fiscal_year')
            ->orderBy('opd_id');
    }

    public function headings(): array
    {
        return [
            'Tahun', 'OPD',
            'Peruntukan', 'Bentuk', 'Jenis Penerima', 'Jenis BK',
            'Pengusul', 'Dapil',
            'Program', 'Kegiatan', 'Sub Kegiatan',
            'Nama Penerima', 'Alamat Penerima',
            'Anggaran Usulan', 'Anggaran Disetujui', 'SK Kepala Daerah',
            'Status', 'Anggaran Belum Cair', 'Alasan Belum Cair', 'Keterangan',
        ];
    }

    /** @param  ApprovedProposal  $p */
    public function map($p): array
    {
        return [
            $p->fiscal_year,
            $p->opd?->name,

            // Label yang dibaca manusia, bukan nilai basis data — berkas ini
            // dibuka staf OPD, bukan dibaca mesin.
            ApprovedProposal::PURPOSES[$p->purpose] ?? $p->purpose,
            ApprovedProposal::FORMS[$p->form] ?? $p->form,
            ApprovedProposal::RECIPIENT_TYPES[$p->recipient_type] ?? $p->recipient_type,

            // Kosong untuk hibah & bansos. Cadangan strtoupper menjaga
            // nilai tak dikenal tetap terbaca alih-alih jadi sel kosong.
            $p->bk_type === null
                ? null
                : (ApprovedProposal::BK_TYPES[$p->bk_type] ?? strtoupper($p->bk_type)),

            $p->proposer,
            $p->dapil,
            $p->program,
            $p->activity,
            $p->sub_activity,
            $p->recipient_name,
            $p->recipient_address,
            $p->budget_before,
            $p->approved_budget,
            $p->decree,
            $p->statusLabel(),
            $p->undisbursed_budget,
            $p->undisbursed_reason,
            $p->notes,
        ];
    }
}
