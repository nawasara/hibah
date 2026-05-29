<?php

namespace Nawasara\Hibah\Livewire\Pengajuan;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Nawasara\Hibah\Models\Pengajuan;

class Detail extends Component
{
    use WithFileUploads;

    public Pengajuan $pengajuan;

    // Status transition form
    public string $targetStatus = '';
    public string $catatan = '';

    // Hasil rapat (diisi saat → disetujui)
    public string $sk_kepala_daerah = '';
    public ?int $anggaran_disetujui = null;

    // Verifikasi awal + monev uploads
    public string $status_verifikasi = '';
    public $buktiVerifikasi = null;
    public $buktiMonev = null;

    // Realisasi per triwulan — keyed 1..4. Bound to the form grid.
    public array $realisasi = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

    // Ringkasan pencairan
    public ?int $anggaran_belum_cair = null;
    public string $alasan_belum_cair = '';

    public function mount(Pengajuan $pengajuan): void
    {
        // Route-model binding already ran the OpdScope, so an operator
        // requesting another OPD's pengajuan gets a 404 before reaching here.
        $this->pengajuan = $pengajuan;
        $this->sk_kepala_daerah = (string) $pengajuan->sk_kepala_daerah;
        $this->anggaran_disetujui = $pengajuan->anggaran_disetujui;
        $this->status_verifikasi = (string) $pengajuan->status_verifikasi;
        $this->anggaran_belum_cair = $pengajuan->anggaran_belum_cair;
        $this->alasan_belum_cair = (string) $pengajuan->alasan_belum_cair;

        // Hydrate the 4-quarter form from existing realisasi rows.
        foreach ($pengajuan->realisasi as $r) {
            $this->realisasi[$r->triwulan] = $r->realisasi_anggaran;
        }
    }

    #[Computed]
    public function totalRealisasi(): int
    {
        return (int) array_sum($this->realisasi);
    }

    #[Computed]
    public function allowedTransitions(): array
    {
        $labels = Pengajuan::statusLabels();

        return collect(Pengajuan::TRANSITIONS[$this->pengajuan->status] ?? [])
            ->mapWithKeys(fn ($s) => [$s => $labels[$s] ?? $s])
            ->all();
    }

    public function changeStatus(): void
    {
        $this->authorize('hibah.pengajuan.update');

        $this->validate([
            'targetStatus' => ['required', 'string'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $this->pengajuan->canTransitionTo($this->targetStatus)) {
            $this->dispatch('toast', ['type' => 'error', 'message' => 'Transisi status tidak diizinkan.']);

            return;
        }

        // Approving requires the board-decision fields.
        if ($this->targetStatus === Pengajuan::STATUS_DISETUJUI) {
            $this->validate([
                'sk_kepala_daerah' => ['required', 'string', 'max:255'],
                'anggaran_disetujui' => ['required', 'integer', 'min:0'],
            ], [], [
                'sk_kepala_daerah' => 'Keputusan Kepala Daerah',
                'anggaran_disetujui' => 'Anggaran disetujui',
            ]);
        }

        $from = $this->pengajuan->status;

        $this->pengajuan->status = $this->targetStatus;
        if ($this->targetStatus === Pengajuan::STATUS_DISETUJUI) {
            $this->pengajuan->sk_kepala_daerah = $this->sk_kepala_daerah;
            $this->pengajuan->anggaran_disetujui = $this->anggaran_disetujui;
        }
        $this->pengajuan->save();

        $this->pengajuan->histori()->create([
            'dari_status' => $from,
            'ke_status' => $this->targetStatus,
            'oleh_user_id' => auth()->id(),
            'catatan' => $this->catatan ?: null,
        ]);

        $this->reset(['targetStatus', 'catatan']);
        $this->pengajuan->refresh();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Status diperbarui.']);
    }

    public function saveVerifikasi(): void
    {
        $this->authorize('hibah.pengajuan.update');

        $rules = ['status_verifikasi' => ['nullable', 'in:ms,tms']];
        $allowed = config('nawasara-hibah.uploads.allowed_mimes');
        $max = (int) config('nawasara-hibah.uploads.max_size_kb');
        if ($this->buktiVerifikasi) {
            $rules['buktiVerifikasi'] = ['file', 'mimes:'.implode(',', $allowed), 'max:'.$max];
        }
        $this->validate($rules);

        if ($this->buktiVerifikasi) {
            $this->pengajuan->bukti_verifikasi_path = $this->storeUpload($this->buktiVerifikasi);
        }
        $this->pengajuan->status_verifikasi = $this->status_verifikasi ?: null;
        $this->pengajuan->save();

        $this->reset('buktiVerifikasi');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Verifikasi disimpan.']);
    }

    public function saveRealisasi(): void
    {
        $this->authorize('hibah.realisasi.update');

        $this->validate([
            'realisasi.1' => ['required', 'integer', 'min:0'],
            'realisasi.2' => ['required', 'integer', 'min:0'],
            'realisasi.3' => ['required', 'integer', 'min:0'],
            'realisasi.4' => ['required', 'integer', 'min:0'],
            'anggaran_belum_cair' => ['nullable', 'integer', 'min:0'],
            'alasan_belum_cair' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ([1, 2, 3, 4] as $tw) {
            $this->pengajuan->realisasi()->updateOrCreate(
                ['triwulan' => $tw],
                ['realisasi_anggaran' => (int) $this->realisasi[$tw], 'updated_by' => auth()->id()],
            );
        }

        $this->pengajuan->update([
            'anggaran_belum_cair' => $this->anggaran_belum_cair,
            'alasan_belum_cair' => $this->alasan_belum_cair ?: null,
        ]);

        $this->pengajuan->refresh();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Realisasi triwulan disimpan.']);
    }

    public function saveMonev(): void
    {
        $this->authorize('hibah.pengajuan.update');

        $allowed = config('nawasara-hibah.uploads.allowed_mimes');
        $max = (int) config('nawasara-hibah.uploads.max_size_kb');
        $this->validate([
            'buktiMonev' => ['required', 'file', 'mimes:'.implode(',', $allowed), 'max:'.$max],
        ]);

        $this->pengajuan->bukti_monev_path = $this->storeUpload($this->buktiMonev);
        $this->pengajuan->save();

        $this->reset('buktiMonev');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Bukti monev disimpan.']);
    }

    protected function storeUpload($file): string
    {
        $disk = config('nawasara-hibah.uploads.disk', 'local');
        $dir = config('nawasara-hibah.uploads.directory', 'hibah');

        // Stored on a private disk (outside webroot) — served later via an
        // auth-gated download route, never a public URL.
        return $file->store($dir.'/'.$this->pengajuan->id, $disk);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.pengajuan.detail')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
