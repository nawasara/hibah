<?php

namespace Nawasara\Hibah\Exports;

use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Nawasara\Hibah\Models\Pengajuan;

/**
 * Exports the (scoped, filtered) pengajuan list to Excel. Mirrors the
 * source Excel column order so the output is familiar to OPD staff.
 * OpdScope on the model still applies — operators export only their OPD.
 */
class PengajuanExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected ?int $tahun = null,
        protected ?string $status = null,
    ) {}

    public function query()
    {
        return Pengajuan::query()
            ->with(['opd:id,name', 'kategori:id,nama'])
            ->when($this->tahun, fn ($q) => $q->where('tahun', $this->tahun))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderBy('tahun')
            ->orderBy('opd_id');
    }

    public function headings(): array
    {
        return [
            'Tahun', 'OPD', 'Kategori', 'Peruntukan', 'Pengusul', 'Dapil',
            'Program', 'Kegiatan', 'Sub Kegiatan', 'Nama Penerima', 'Alamat Penerima',
            'Anggaran Usulan', 'Anggaran Disetujui', 'SK Kepala Daerah',
            'Status', 'Anggaran Belum Cair', 'Alasan Belum Cair', 'Keterangan',
        ];
    }

    /** @param  Pengajuan  $p */
    public function map($p): array
    {
        return [
            $p->tahun,
            $p->opd?->name,
            $p->kategori?->nama,
            strtoupper($p->peruntukan),
            $p->pengusul,
            $p->dapil,
            $p->program,
            $p->kegiatan,
            $p->sub_kegiatan,
            $p->nama_penerima,
            $p->alamat_penerima,
            $p->anggaran_sebelum,
            $p->anggaran_disetujui,
            $p->sk_kepala_daerah,
            $p->statusLabel(),
            $p->anggaran_belum_cair,
            $p->alasan_belum_cair,
            $p->keterangan,
        ];
    }
}
