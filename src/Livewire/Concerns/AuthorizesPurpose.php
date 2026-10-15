<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Concerns;

use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Pemeriksaan permission per peruntukan untuk setiap aksi component.
 *
 * Audit keamanan 15 Oktober 2026, temuan S2: middleware rute hanya berjalan
 * saat halaman pertama dibuka. Aksi Livewire berikutnya (muat ulang tabel,
 * ekspor, hapus, simpan) dikirim ke /livewire/update dan TIDAK melewati
 * middleware itu lagi, sehingga isolasi peruntukan sepenuhnya bergantung pada
 * properti component. Properti itu kini #[Locked], dan trait ini memeriksa
 * ulang izinnya di tiap aksi sebagai lapis kedua.
 *
 * Permission disusun dari nilai `purpose` yang dipakai menyaring query,
 * bukan dari segmen URL yang disimpan terpisah, supaya yang diperiksa dan
 * yang ditampilkan tidak mungkin berbeda.
 */
trait AuthorizesPurpose
{
    /** Nilai `purpose` basis data (hibah | bansos | bk) milik component ini. */
    abstract protected function purposeValue(): ?string;

    protected function authorizePurpose(string $action): void
    {
        $permission = ApprovedProposal::permissionFor($this->purposeValue(), $action);

        abort_if($permission === null, 404);

        $this->authorize($permission);
    }
}
