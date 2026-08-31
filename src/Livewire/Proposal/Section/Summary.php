<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Livewire\Attributes\On;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Panel "Data Usulan" — baca saja.
 *
 * Component, bukan partial blade, karena ia menampilkan **status** yang
 * berubah saat panel realisasi menyimpan. Sebagai partial ia akan
 * menampilkan status lama sampai halaman dimuat ulang, dan staf mengira
 * simpanannya gagal.
 */
class Summary extends Component
{
    public ApprovedProposal $proposal;

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
    }

    /**
     * Muat ulang usulan setelah realisasi disimpan di panel sebelah.
     *
     * ⚠️ `refresh()` saja TIDAK cukup: ia menyegarkan atribut tetapi
     * MEMPERTAHANKAN relasi yang sudah termuat, jadi jumlah realisasi tetap
     * angka lama — bento menampilkan "Sebagian Cair" pada usulan yang baru
     * saja lunas.
     *
     * Diambil ulang dari basis data, bukan sekadar disegarkan, karena
     * Livewire menghidupkan kembali model ini dari snapshot permintaan
     * sebelumnya — dan snapshot itu memuat status lama.
     */
    #[On('proposal-status-changed')]
    public function refreshProposal(): void
    {
        $this->proposal = ApprovedProposal::withoutGlobalScopes()
            ->findOrFail($this->proposal->getKey());
    }

    /**
     * Angka yang ditonjolkan di hero.
     *
     * `approved_budget` yang jatuh ke `budget_after` lalu `budget_before` —
     * urutan yang sama dengan daftar dan laporan, supaya satu usulan tidak
     * pernah menunjukkan angka berbeda di tiga halaman.
     */
    public function headlineBudget(): int
    {
        return (int) ($this->proposal->approved_budget
            ?? $this->proposal->budget_after
            ?? $this->proposal->budget_before
            ?? 0);
    }

    /**
     * Jumlah realisasi — SELALU lewat query, bukan relasi termuat.
     *
     * Relasi yang termuat saat halaman pertama dirender tidak ikut berubah
     * ketika panel realisasi menyimpan, dan `sum()` atasnya mengembalikan
     * angka sebelum penyimpanan.
     */
    public function disbursedTotal(): int
    {
        return (int) $this->proposal->disbursements()
            ->toBase()
            ->sum('disbursed_amount');
    }

    /** Sisa yang belum cair — 0 bila anggarannya belum diketahui. */
    public function remaining(): int
    {
        $approved = (int) $this->proposal->approved_budget;

        return $approved > 0 ? max(0, $approved - $this->disbursedTotal()) : 0;
    }

    /** Persentase pencairan, 0 bila anggaran disetujui belum diisi. */
    public function disbursedPercent(): int
    {
        $approved = (int) $this->proposal->approved_budget;

        if ($approved <= 0) {
            return 0;
        }

        return (int) min(100, round($this->disbursedTotal() / $approved * 100));
    }

    /**
     * Rupiah ringkas untuk kartu sempit — "Rp 1,5 M".
     *
     * Angka penuh tetap dipakai di hero yang punya ruang; kartu pendamping
     * yang sempit membutuhkan versi ini supaya tidak membungkus baris.
     */
    public function compactRupiah(?int $n): string
    {
        if ($n === null) {
            return '—';
        }

        if ($n === 0) {
            return 'Rp 0';
        }

        $abs = abs($n);

        [$div, $suffix] = match (true) {
            $abs >= 1_000_000_000 => [1_000_000_000, ' M'],
            $abs >= 1_000_000 => [1_000_000, ' Jt'],
            $abs >= 1_000 => [1_000, ' Rb'],
            default => [1, ''],
        };

        $val = $n / $div;

        $str = $val == (int) $val
            ? number_format($val, 0, ',', '.')
            : number_format($val, 1, ',', '.');

        return 'Rp '.$str.$suffix;
    }

    /**
     * Label peruntukan + bentuk yang dibaca staf, mis. "Hibah · Uang".
     *
     * Diturunkan dari konstanta, bukan diketik ulang di blade — daftar yang
     * disalin ke view perlahan berbeda dari sumbernya.
     */
    public function purposeLabel(): string
    {
        $purpose = ApprovedProposal::PURPOSES[$this->proposal->purpose] ?? $this->proposal->purpose;
        $form = ApprovedProposal::FORMS[$this->proposal->form] ?? $this->proposal->form;

        return "{$purpose} · {$form}";
    }

    public function recipientTypeLabel(): string
    {
        return ApprovedProposal::RECIPIENT_TYPES[$this->proposal->recipient_type]
            ?? $this->proposal->recipient_type;
    }

    /** Null untuk hibah & bansos — hanya BK yang punya sub-jenis. */
    public function bkTypeLabel(): ?string
    {
        if ($this->proposal->bk_type === null) {
            return null;
        }

        return ApprovedProposal::BK_TYPES[$this->proposal->bk_type]
            ?? strtoupper($this->proposal->bk_type);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.summary');
    }
}
