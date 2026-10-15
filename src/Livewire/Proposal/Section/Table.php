<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal\Section;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Hibah\Exports\ApprovedProposalExport;
use Nawasara\Hibah\Livewire\Concerns\AuthorizesPurpose;
use Nawasara\Hibah\Models\ApprovedProposal;

/**
 * Daftar usulan untuk satu menu.
 *
 * `purpose` dan `form` datang dari rute, bukan dari saringan — keduanya
 * sudah ditentukan menu yang dibuka. Yang tersisa disaring staf: tahun,
 * OPD, status, jenis penerima, dan (khusus BK) sub-jenisnya.
 *
 * Kelima properti konteks di bawah #[Locked] (audit keamanan 15 Oktober
 * 2026, temuan S2). Tanpa kunci, staf bansos dapat mengganti `purpose`
 * menjadi `hibah` dari peramban, lalu melihat, mengekspor, atau menghapus
 * usulan hibah: middleware rute tidak ikut berjalan pada aksi Livewire.
 */
class Table extends Component
{
    use AuthorizesPurpose;
    use WithPagination;
    use WithSudo;

    #[Locked]
    public string $purpose = '';

    #[Locked]
    public ?string $form = null;

    #[Locked]
    public ?string $bkType = null;

    #[Locked]
    public string $purposeSegment = '';

    #[Locked]
    public string $segment = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $yearFilter = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $recipientTypeFilter = '';

    /** Hanya dipakai di menu Bantuan Keuangan · Khusus (ADD / PD). */
    #[Url]
    public string $bkTypeFilter = '';

    public int $perPage = 25;

    public function mount(
        string $purpose,
        ?string $form,
        ?string $bkType,
        string $purposeSegment,
        string $segment,
    ): void {
        // Parameter datang dari component induk, tetapi tetap dicocokkan:
        // komponen ini dapat di-mount di tempat lain, dan pasangan yang
        // tidak sah lebih baik 404 daripada menyaring dengan nilai karangan.
        abort_unless(
            ApprovedProposal::isValidSegmentPair($purposeSegment, $segment)
                && ApprovedProposal::purposeFromSegment($purposeSegment) === $purpose,
            404,
        );

        $this->purpose = $purpose;
        $this->form = $form;
        $this->bkType = $bkType;
        $this->purposeSegment = $purposeSegment;
        $this->segment = $segment;
    }

    public function updated(string $property): void
    {
        // Tiap perubahan saringan kembali ke halaman 1 — tanpa ini, hasil
        // yang menyusut meninggalkan pengguna di halaman yang sudah kosong.
        if (in_array($property, [
            'search', 'yearFilter', 'statusFilter',
            'recipientTypeFilter', 'bkTypeFilter',
        ], true)) {
            $this->resetPage();
        }
    }

    /**
     * Query dasar — SELALU ter-scope ke menu yang dibuka.
     *
     * Gerbang permission di rute menghentikan orang membuka URL peruntukan
     * lain, tetapi tidak menyaring apa yang muncul di sini. Keduanya perlu.
     */
    protected function baseQuery()
    {
        // Setiap pemakai baseQuery (tabel, opsi tahun, hapus) melewati
        // pemeriksaan izin lihat di sini, bukan hanya di middleware rute.
        $this->authorizePurpose('view');

        return ApprovedProposal::query()
            ->where('purpose', $this->purpose)
            ->when($this->form, fn ($q) => $q->where('form', $this->form))
            ->when(
                $this->bkType === 'umum',
                fn ($q) => $q->where('bk_type', 'umum'),
            )
            ->when(
                $this->bkType === 'khusus',
                // 'khusus' memuat ADD dan DD sekaligus — catatan diskusi
                // menuliskannya sebagai satu kelompok.
                fn ($q) => $q->whereIn('bk_type', ApprovedProposal::BK_SPECIAL_TYPES),
            );
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->with('opd:id,name')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w
                    ->where('recipient_name', 'like', $term)
                    ->orWhere('recipient_address', 'like', $term)
                    ->orWhere('decree', 'like', $term));
            })
            ->when($this->yearFilter !== '', fn ($q) => $q->where('fiscal_year', (int) $this->yearFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->recipientTypeFilter !== '', fn ($q) => $q->where('recipient_type', $this->recipientTypeFilter))
            ->when($this->bkTypeFilter !== '', fn ($q) => $q->where('bk_type', $this->bkTypeFilter))
            ->orderByDesc('fiscal_year')
            ->orderBy('recipient_name')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function total(): int
    {
        return $this->rows()->total();
    }

    /** @return array<string, string> */
    #[Computed]
    public function yearOptions(): array
    {
        return $this->baseQuery()
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
            ->all();
    }

    /**
     * Jenis penerima yang MUNGKIN di menu ini, bukan seluruh daftar.
     *
     * Di Bansos Uang hanya ada satu (Perorangan), jadi menawarkan lima
     * pilihan saringan yang empat di antaranya tidak pernah menghasilkan
     * apa pun hanya membuang waktu staf.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function recipientTypeOptions(): array
    {
        return ApprovedProposal::recipientOptions($this->purpose, $this->form);
    }

    /** @return array<string, string> */
    #[Computed]
    public function statusOptions(): array
    {
        return ApprovedProposal::STATUSES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function bkTypeOptions(): array
    {
        // Hanya relevan di menu Khusus; 'umum' tidak ditawarkan di sana.
        return array_intersect_key(
            ApprovedProposal::BK_TYPES,
            array_flip(ApprovedProposal::BK_SPECIAL_TYPES),
        );
    }

    /** Judul mengikuti menu, mis. "Hibah Uang" atau "Bantuan Keuangan Umum". */
    public function pageTitle(): string
    {
        if ($this->purpose === ApprovedProposal::PURPOSE_BK) {
            return $this->segment === 'umum'
                ? 'Bantuan Keuangan Umum'
                : 'Bantuan Keuangan Khusus';
        }

        return sprintf(
            '%s %s',
            ApprovedProposal::PURPOSES[$this->purpose] ?? '',
            ApprovedProposal::FORMS[$this->form] ?? '',
        );
    }

    public function hasActiveFilter(): bool
    {
        return $this->search !== ''
            || $this->yearFilter !== ''
            || $this->statusFilter !== ''
            || $this->recipientTypeFilter !== ''
            || $this->bkTypeFilter !== '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'yearFilter', 'statusFilter', 'recipientTypeFilter', 'bkTypeFilter']);
        $this->resetPage();
    }

    /**
     * Menghapus baris yang memang keliru — salah ketik, ganda.
     *
     * Berbeda dari membatalkan: yang batal tetap tercatat karena SK-nya
     * ada. Yang dihapus adalah baris yang seharusnya tidak pernah ada;
     * menyimpannya sebagai "batal" membuat daftar penuh sampah.
     */
    #[RequiresSudo(reason: 'menghapus usulan')]
    public function delete(int $id): void
    {
        // Permission dari `purpose` yang menyaring query, bukan dari segmen
        // (audit keamanan 15 Oktober 2026, S2). Sudo karena hapus permanen
        // (AGENTS.md §6, S6).
        $this->authorizePurpose('update');

        // baseQuery memastikan hanya baris milik menu ini yang terhapus —
        // id dari peruntukan lain tidak ditemukan, bukan terhapus diam-diam.
        $this->baseQuery()->whereKey($id)->firstOrFail()->delete();

        unset($this->rows, $this->total);

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Usulan dihapus.']);
    }

    /**
     * Unduh daftar ini sebagai Excel — DENGAN saringan yang sedang aktif.
     *
     * Mengekspor seluruh peruntukan mengabaikan pekerjaan yang baru saja
     * dilakukan staf untuk menyaring, dan berkasnya jadi jauh lebih besar
     * daripada yang mereka minta. Yang diekspor adalah apa yang terlihat.
     */
    public function export()
    {
        $this->authorize('hibah.report.export');

        // Izin ekspor berlaku lintas peruntukan, jadi izin lihat menu ini
        // juga diperiksa: tanpa itu ekspor menjadi jalan membaca peruntukan
        // yang menunya tidak boleh dibuka (audit keamanan 15 Oktober 2026, S2).
        $this->authorizePurpose('view');

        $filename = sprintf(
            '%s-%s-%s.xlsx',
            ApprovedProposal::segmentFromPurpose($this->purpose),
            $this->segment,
            now()->format('Ymd_His'),
        );

        return Excel::download(
            new ApprovedProposalExport(
                purpose: $this->purpose,
                form: $this->form,
                fiscalYear: $this->yearFilter !== '' ? (int) $this->yearFilter : null,
                status: $this->statusFilter !== '' ? $this->statusFilter : null,
                bkType: $this->bkTypeFilter !== '' ? $this->bkTypeFilter : null,
                recipientType: $this->recipientTypeFilter !== '' ? $this->recipientTypeFilter : null,
                search: $this->search,
            ),
            $filename,
        );
    }

    #[On('proposal-saved')]
    public function refreshRows(): void
    {
        unset($this->rows, $this->total, $this->yearOptions);
        $this->resetPage();
    }

    protected function purposeValue(): ?string
    {
        return $this->purpose;
    }

    public function render()
    {
        $this->authorizePurpose('view');

        return view('nawasara-hibah::livewire.pages.proposal.section.table');
    }
}
