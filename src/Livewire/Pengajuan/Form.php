<?php

namespace Nawasara\Hibah\Livewire\Pengajuan;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Hibah\Models\Kategori;
use Nawasara\Hibah\Models\Pengajuan;
use Nawasara\Registry\Models\Opd;
use Nawasara\Registry\Support\MembershipResolver;

class Form extends Component
{
    public ?int $pengajuanId = null;

    // Klasifikasi
    public ?int $opd_id = null;
    public int $tahun;
    public ?int $kategori_id = null;
    public string $peruntukan = 'hibah';

    // Identitas usulan — string props are nullable because the underlying
    // columns are nullable and historical import rows have null values. A
    // non-null string type would TypeError on $this->fill() during edit.
    public ?string $pengusul = null;
    public ?string $dapil = null;
    public bool $lintas_dapil = false;
    public ?string $kamus_usulan = null;
    public ?string $tanggal_proposal = null;

    // Program
    public ?string $program = null;
    public ?string $kegiatan = null;
    public ?string $sub_kegiatan = null;

    // Penerima — nama_penerima required by validation, but keep nullable type
    // so an empty hydrate doesn't crash before validation runs.
    public ?string $nama_penerima = null;
    public ?string $alamat_penerima = null;

    // Anggaran usulan
    public int $anggaran_sebelum = 0;
    public ?int $anggaran_setelah = null;

    public ?string $keterangan = null;

    public function mount(?Pengajuan $pengajuan = null): void
    {
        $this->tahun = (int) date('Y');

        // Edit route binds {pengajuan}; create route passes nothing (null model).
        if ($pengajuan && $pengajuan->exists) {
            $model = $pengajuan; // OpdScope already applied during route binding
            $this->pengajuanId = $model->id;
            $this->fill($model->only([
                'opd_id', 'tahun', 'kategori_id', 'peruntukan', 'pengusul', 'dapil',
                'lintas_dapil', 'kamus_usulan', 'program', 'kegiatan', 'sub_kegiatan',
                'nama_penerima', 'alamat_penerima', 'anggaran_sebelum', 'anggaran_setelah',
                'keterangan',
            ]));
            $this->tanggal_proposal = $model->tanggal_proposal?->format('Y-m-d');
        } else {
            // Operator: lock to their own OPD. Admin: free to choose.
            $this->opd_id = $this->operatorOpdId();
        }
    }

    protected function operatorOpdId(): ?int
    {
        // OPD membership now lives in the registry (cross-package), resolved
        // via MembershipResolver. Member → their OPD id; admin/privileged → null.
        return app(MembershipResolver::class)->opdIdFor(auth()->user());
    }

    #[Computed]
    public function isOperator(): bool
    {
        return $this->operatorOpdId() !== null;
    }

    #[Computed]
    public function opdOptions(): array
    {
        return Opd::orderBy('name')->pluck('name', 'id')->all();
    }

    #[Computed]
    public function kategoriOptions(): array
    {
        return Kategori::aktif()->orderBy('nama')->pluck('nama', 'id')->all();
    }

    protected function rules(): array
    {
        return [
            'opd_id' => ['required', 'exists:nawasara_registry_opd,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'kategori_id' => ['nullable', 'exists:nawasara_hibah_kategori,id'],
            'peruntukan' => ['required', 'in:hibah,bansos,bk'],
            'pengusul' => ['nullable', 'string', 'max:255'],
            'dapil' => ['nullable', 'string', 'max:255'],
            'lintas_dapil' => ['boolean'],
            'kamus_usulan' => ['nullable', 'string'],
            'tanggal_proposal' => ['nullable', 'date'],
            'program' => ['nullable', 'string', 'max:255'],
            'kegiatan' => ['nullable', 'string', 'max:255'],
            'sub_kegiatan' => ['nullable', 'string', 'max:255'],
            'nama_penerima' => ['required', 'string', 'max:255'],
            'alamat_penerima' => ['nullable', 'string'],
            'anggaran_sebelum' => ['required', 'integer', 'min:0'],
            'anggaran_setelah' => ['nullable', 'integer', 'min:0'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    public function save()
    {
        $this->authorize($this->pengajuanId ? 'hibah.pengajuan.update' : 'hibah.pengajuan.create');

        // Operators can never write to another OPD, regardless of form input.
        if ($operatorOpd = $this->operatorOpdId()) {
            $this->opd_id = $operatorOpd;
        }

        $data = $this->validate();

        if ($this->pengajuanId) {
            $model = Pengajuan::findOrFail($this->pengajuanId);
            $model->update($data);
        } else {
            $data['status'] = Pengajuan::STATUS_DIAJUKAN;
            $data['created_by'] = auth()->id();
            $model = Pengajuan::create($data);

            $model->histori()->create([
                'dari_status' => null,
                'ke_status' => Pengajuan::STATUS_DIAJUKAN,
                'oleh_user_id' => auth()->id(),
                'catatan' => 'Pengajuan dibuat.',
            ]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Pengajuan tersimpan.']);

        return redirect()->route('hibah.pengajuan.detail', $model);
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.pengajuan.form')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
