<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal;

use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Kerangka halaman detail — **tipis dengan sengaja**.
 *
 * Tidak memuat satu pun aksi simpan. Tiap panel adalah component sendiri
 * dengan satu tombol simpan, karena versi sebelumnya (199 baris PHP, 414
 * baris blade) menangani empat urusan sekaligus: ubah status, verifikasi,
 * realisasi, monev. Selain sulit dibaca, tiap unggahan berkas me-render
 * ulang seluruh halaman termasuk tabel yang tidak berubah.
 *
 * Yang tersisa di sini: memuat model, menyediakan remah roti, dan menyusun
 * panel-panelnya.
 */
class Detail extends Component
{
    public ApprovedProposal $proposal;

    /**
     * Peruntukan berasal dari segmen rute, bukan dari model.
     *
     * Dipakai untuk menyusun remah roti dan tautan kembali ke daftar yang
     * benar — staf yang membuka detail dari menu Bansos harus kembali ke
     * Bansos, bukan ke daftar gabungan yang tidak ada.
     */
    public string $purpose = '';

    public string $segment = '';

    public function mount(string $purpose, string $segment, ApprovedProposal $proposal): void
    {
        // Pasangan segmen yang tidak masuk akal (bansos/khusus,
        // bantuan-keuangan/barang) ditolak, bukan ditampilkan sebagai
        // halaman kosong.
        abort_unless(ApprovedProposal::isValidSegmentPair($purpose, $segment), 404);

        // Gerbang ganda: middleware rute sudah memeriksa permission, dan ini
        // memastikan usulannya memang milik menu yang dibuka. Tanpa ini,
        // menebak id di URL bansos akan menampilkan usulan hibah.
        abort_unless(
            $proposal->purpose === ApprovedProposal::purposeFromSegment($purpose),
            404,
        );

        $this->purpose = $purpose;
        $this->segment = $segment;
        $this->proposal = $proposal;
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.detail')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
