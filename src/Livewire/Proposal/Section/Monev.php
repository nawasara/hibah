<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Bukti monitoring & evaluasi.
 *
 * ⚠️ Sengaja TIDAK ikut menentukan status. Monev membuktikan bantuannya
 * dipakai sebagaimana mestinya; realisasi mencatat uangnya berpindah.
 * Keduanya boleh berbeda — dana cair penuh dengan monev belum diunggah
 * adalah keadaan wajar di pertengahan tahun, dan justru itu yang dicari
 * pengawas. Menjadikan "Cair" bergantung pada berkas monev akan menahan
 * status yang sudah benar hanya karena lampirannya belum masuk.
 *
 * Component terpisah supaya unggahan berkas tidak me-render ulang seluruh
 * halaman detail — termasuk tabel realisasi dan riwayat yang tidak berubah.
 */
class Monev extends Component
{
    use WithFileUploads;

    public ApprovedProposal $proposal;

    public $file = null;

    public function mount(ApprovedProposal $proposal): void
    {
        $this->proposal = $proposal;
    }

    public function save(): void
    {
        $this->authorize('hibah.approved-proposal.update');

        $allowed = (array) config('nawasara-hibah.uploads.allowed_mimes', ['pdf', 'jpg', 'png']);
        $max = (int) config('nawasara-hibah.uploads.max_size_kb', 5120);

        $this->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', $allowed), 'max:'.$max],
        ], [], [
            'file' => 'Bukti monev',
        ]);

        $this->proposal->monev_proof_path = $this->store($this->file);
        $this->proposal->save();

        $this->reset('file');

        $this->dispatch('toast', [
            'type' => 'success',
            'message' => 'Bukti monev disimpan.',
        ]);
    }

    /**
     * Disimpan di disk privat (di luar webroot) — dilayani lewat rute
     * unduhan bergerbang auth, tidak pernah lewat URL publik.
     */
    protected function store(TemporaryUploadedFile $file): string
    {
        $disk = config('nawasara-hibah.uploads.disk', 'local');
        $dir = config('nawasara-hibah.uploads.directory', 'hibah');

        return $file->store($dir.'/'.$this->proposal->getKey(), $disk);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.section.monev');
    }
}
