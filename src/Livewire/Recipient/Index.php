<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Recipient;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Recipient;

/**
 * Daftar penerima bantuan — LINTAS peruntukan.
 *
 * Berbeda dari daftar usulan: di sini satu baris = satu penerima, bukan satu
 * usulan. Yang dijawab halaman ini adalah pertanyaan yang tidak dapat
 * dijawab daftar usulan — "siapa saja penerimanya, sudah berapa kali, dan
 * berapa totalnya" — termasuk penerima yang menerima dari lebih dari satu
 * peruntukan sekaligus.
 *
 * Itulah alasan halaman ini tidak diletakkan di dalam seksi peruntukan:
 * memecahnya per peruntukan justru menghilangkan pandangan yang membuatnya
 * berguna.
 */
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $typeFilter = '';

    #[Url]
    public string $purposeFilter = '';

    #[Url]
    public string $yearFilter = '';

    public int $perPage = 25;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'purposeFilter', 'yearFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Saringan yang berlaku pada USULAN, bukan pada penerima.
     *
     * Peruntukan dan tahun adalah sifat usulan; penerima hanya punya jenis.
     * Jadi keduanya disaring lewat relasi — dan agregatnya pun harus dihitung
     * dari himpunan yang sama, kalau tidak "3 kali" akan menghitung usulan
     * yang sedang tersaring keluar.
     */
    protected function proposalConstraint(): ?callable
    {
        if ($this->purposeFilter === '' && $this->yearFilter === '') {
            return null;
        }

        // ⚠️ TANPA tipe parameter. Closure ini dipakai tiga tempat yang
        // mengoper jenis berbeda: whereHas memberi Builder, sedangkan
        // withCount dan with memberi relasi (HasMany). Menuliskan
        // `Builder $q` membuat dua yang terakhir melempar TypeError —
        // dan pesannya menunjuk baris pemanggil, bukan sumbernya.
        return function ($q): void {
            $q->when($this->purposeFilter !== '', fn ($w) => $w->where('purpose', $this->purposeFilter))
                ->when($this->yearFilter !== '', fn ($w) => $w->where('fiscal_year', (int) $this->yearFilter));
        };
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $constraint = $this->proposalConstraint();

        // ⚠️ Boolean eksplisit, JANGAN `->when($constraint, ...)`.
        //
        // `when()` memanggil argumen pertamanya bila ia callable, untuk
        // menghitung nilai kondisinya. Jadi closure saringan tadi
        // DIJALANKAN pada query Recipient — menghasilkan
        // `where fiscal_year = 2025` pada tabel yang tidak punya kolom itu.
        $hasConstraint = $constraint !== null;

        return Recipient::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(fn ($w) => $w
                    ->where('name', 'like', $term)
                    ->orWhere('address', 'like', $term));
            })
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))

            // Penerima yang seluruh usulannya tersaring keluar tidak
            // ditampilkan — baris dengan "0 kali, Rp 0" hanya kebisingan.
            ->when($hasConstraint, fn ($q) => $q->whereHas('proposals', $constraint))

            // Tanpa saringan usulan, penerima tanpa usulan sama sekali tetap
            // disembunyikan: ia biasanya sisa dari baris yang dihapus, dan
            // menampilkannya membuat daftar terlihat lebih penuh dari
            // kenyataannya.
            ->when(! $hasConstraint, fn ($q) => $q->has('proposals'))

            ->withCount(['proposals' => fn ($q) => $constraint ? $constraint($q) : $q])
            // ⚠️ coalesce, bukan approved_budget saja: 16 dari 100 baris
            // contoh punya anggaran usulan tetapi belum punya anggaran
            // disetujui, dan menjumlah kolom itu sendirian menampilkan
            // "Rp 0" untuk penerima yang jelas menerima sesuatu.
            //
            // Urutannya sama dengan yang dipakai daftar usulan, supaya angka
            // di dua halaman tidak pernah berbeda untuk baris yang sama.
            ->withSum(
                ['proposals as total_budget' => fn ($q) => $constraint ? $constraint($q) : $q],
                \Illuminate\Support\Facades\DB::raw('COALESCE(approved_budget, budget_after, budget_before, 0)'),
            )
            // Peruntukan dimuat sekali untuk seluruh halaman. Tanpa ini,
            // lencana peruntukan memicu satu query per baris — 25 query
            // tambahan tiap halaman, untuk hiasan.
            ->with(['proposals' => function ($q) use ($constraint) {
                $q->select('id', 'recipient_id', 'purpose');

                if ($constraint) {
                    $constraint($q);
                }
            }])
            ->orderByDesc('proposals_count')
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    #[Computed]
    public function total(): int
    {
        return $this->rows()->total();
    }

    /** @return array<string, string> */
    #[Computed]
    public function typeOptions(): array
    {
        return ApprovedProposal::RECIPIENT_TYPES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function purposeOptions(): array
    {
        return ApprovedProposal::PURPOSES;
    }

    /** @return array<string, string> */
    #[Computed]
    public function yearOptions(): array
    {
        return ApprovedProposal::query()
            ->select('fiscal_year')
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])
            ->all();
    }

    /**
     * Peruntukan yang pernah diterima seorang penerima.
     *
     * Ditampilkan sebagai lencana supaya yang menerima dari lebih dari satu
     * sumber langsung terlihat — itu justru hal yang dicari pengawas, dan
     * tidak terlihat sama sekali dari daftar usulan mana pun.
     *
     * @return list<string>
     */
    public function purposesFor(Recipient $recipient): array
    {
        // Membaca relasi yang SUDAH dimuat, bukan query baru.
        return $recipient->proposals
            ->pluck('purpose')
            ->unique()
            ->map(fn ($p) => ApprovedProposal::PURPOSES[$p] ?? $p)
            ->values()
            ->all();
    }

    public function hasActiveFilter(): bool
    {
        return $this->search !== ''
            || $this->typeFilter !== ''
            || $this->purposeFilter !== ''
            || $this->yearFilter !== '';
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'purposeFilter', 'yearFilter']);
        $this->resetPage();
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.recipient.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
