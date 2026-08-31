<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Livewire\Proposal;

use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Hibah\Models\ApprovedProposal;
use Nawasara\Hibah\Models\Recipient;
use Nawasara\Registry\Models\Opd;

/**
 * Formulir tambah / ubah usulan.
 *
 * `purpose` dan `form` **tidak ditanyakan** — keduanya ditentukan menu yang
 * dibuka. Dua select hilang dari layar, dan pilihan jenis penerima sudah
 * menyempit sesuai aturan §3 sebelum staf melihatnya.
 */
class Form extends Component
{
    public string $purposeSegment = '';

    public string $segment = '';

    public string $purpose = '';

    public string $form = '';

    public ?ApprovedProposal $proposal = null;

    // ── Kolom yang diisi staf ────────────────────────────────────
    //
    // ⚠️ Semuanya nullable kalau kolomnya nullable. Properti bertipe
    // `string` dengan default '' melempar TypeError saat mengisi model
    // hasil impor yang kolomnya null.

    public ?int $opd_id = null;

    public ?int $fiscal_year = null;

    public string $recipient_type = '';

    public ?string $bk_type = null;

    public ?string $recipient_name = '';

    /**
     * Kata kunci pencarian penerima yang sudah terdaftar.
     *
     * Terpisah dari `recipient_name`: yang ini alat bantu, yang itu nilai
     * yang tersimpan. Menyatukannya membuat tiap ketikan memicu pencarian
     * sekaligus mengubah data yang akan disimpan.
     */
    public string $recipientSearch = '';

    public ?string $recipient_address = null;

    public ?string $proposer = null;

    public ?string $dapil = null;

    public bool $cross_dapil = false;

    public ?string $proposal_dictionary = null;

    public ?string $decree = null;

    public ?string $proposed_at = null;

    public ?string $program = null;

    public ?string $activity = null;

    public ?string $sub_activity = null;

    public ?int $budget_before = null;

    public ?int $budget_after = null;

    public ?int $approved_budget = null;

    public ?string $notes = null;

    public function mount(string $purpose, string $segment, ?ApprovedProposal $proposal = null): void
    {
        abort_unless(ApprovedProposal::isValidSegmentPair($purpose, $segment), 404);

        $this->purposeSegment = $purpose;
        $this->segment = $segment;
        $this->purpose = ApprovedProposal::purposeFromSegment($purpose);

        if ($this->purpose === ApprovedProposal::PURPOSE_BK) {
            $this->form = ApprovedProposal::FORM_UANG;
            $this->bk_type = $segment === 'umum' ? 'umum' : 'add';
        } else {
            $this->form = $segment;
        }

        if ($proposal?->exists) {
            abort_unless($proposal->purpose === $this->purpose, 404);

            $this->proposal = $proposal;

            $this->fill($proposal->only([
                'opd_id', 'fiscal_year', 'recipient_type', 'bk_type',
                'recipient_name', 'recipient_address', 'proposer', 'dapil',
                'cross_dapil', 'proposal_dictionary', 'decree',
                'program', 'activity', 'sub_activity',
                'budget_before', 'budget_after', 'approved_budget', 'notes',
            ]));

            $this->proposed_at = $proposal->proposed_at?->format('Y-m-d');
        } else {
            $this->fiscal_year = (int) date('Y');

            // Satu-satunya penerima yang sah diisi otomatis — di Bansos Uang
            // hanya ada Perorangan, jadi menanyakannya cuma menambah klik.
            $options = $this->recipientOptions();

            if (count($options) === 1) {
                $this->recipient_type = array_key_first($options);
            }
        }
    }

    /**
     * Penerima terdaftar yang cocok dengan kata kunci.
     *
     * Ada 100 penerima di basis data dan sebagian namanya berulang dengan
     * ejaan berbeda — "agus mustofa" muncul tiga kali. Tanpa pencarian, tiap
     * pengisian berpotensi melahirkan penerima baru yang seharusnya sama,
     * dan riwayat penerimaannya terpecah tanpa ada yang menyadari.
     *
     * Dibatasi jenis penerima yang sedang dipilih: menawarkan lembaga saat
     * mengisi bansos uang hanya menampilkan pilihan yang akan ditolak
     * validasi.
     *
     * @return \Illuminate\Support\Collection<int, Recipient>
     */
    #[Computed]
    public function recipientMatches()
    {
        $term = trim($this->recipientSearch);

        if (mb_strlen($term) < 3) {
            return collect();
        }

        return Recipient::query()
            ->when($this->recipient_type !== '', fn ($q) => $q->where('type', $this->recipient_type))
            ->where(function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where('name', 'like', $like)->orWhere('address', 'like', $like);
            })
            ->withCount('proposals')
            ->orderByDesc('proposals_count')
            ->limit(8)
            ->get();
    }

    /**
     * Pakai penerima yang sudah ada.
     *
     * Nama dan alamat disalin apa adanya dari master — bukan ditautkan lewat
     * id, karena kolom di usulan menyimpan nama seperti tertulis pada SK-nya
     * sendiri. Penautan tetap terjadi otomatis saat menyimpan, lewat
     * pencocokan nama + alamat di model.
     */
    public function useRecipient(int $id): void
    {
        $recipient = Recipient::find($id);

        if (! $recipient) {
            return;
        }

        $this->recipient_name = $recipient->name;
        $this->recipient_address = $recipient->address;

        // Jenis penerima hanya diikuti bila SAH untuk menu ini. Bila tidak,
        // pilihan yang ada dibiarkan — dan petugas diberi tahu, bukan
        // dibiarkan menemukan kolomnya kosong sendiri.
        if (array_key_exists($recipient->type, $this->recipientOptions())) {
            $this->recipient_type = $recipient->type;
        } else {
            $this->dispatch('toast', [
                'type' => 'info',
                'message' => sprintf(
                    'Nama dan alamat disalin. Jenis penerima "%s" tidak berlaku di menu ini — pilih yang sesuai.',
                    $recipient->typeLabel(),
                ),
            ]);
        }

        $this->recipientSearch = '';
        unset($this->recipientMatches);
    }

    /**
     * Jenis penerima yang sah untuk menu ini.
     *
     * Diturunkan dari aturan yang sama dengan validasi — satu sumber, bukan
     * daftar yang ditulis ulang di blade.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function recipientOptions(): array
    {
        return ApprovedProposal::recipientOptions($this->purpose, $this->form);
    }

    /** @return array<int|string, string> */
    #[Computed]
    public function opdOptions(): array
    {
        return Opd::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function bkTypeOptions(): array
    {
        return ApprovedProposal::BK_TYPES;
    }

    protected function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:nawasara_registry_opd,id'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],

            // Nilai yang sah diturunkan dari matriks, bukan daftar tetap —
            // begitu matriksnya berubah, validasi ini ikut sendiri.
            'recipient_type' => ['required', Rule::in(array_keys($this->recipientOptions()))],

            'bk_type' => [
                Rule::requiredIf($this->purpose === ApprovedProposal::PURPOSE_BK),
                'nullable',
                Rule::in(array_keys(ApprovedProposal::BK_TYPES)),
            ],

            'recipient_name' => ['required', 'string', 'max:5000'],
            'recipient_address' => ['nullable', 'string', 'max:5000'],
            'proposer' => ['nullable', 'string', 'max:2000'],
            'dapil' => ['nullable', 'string', 'max:255'],
            'cross_dapil' => ['boolean'],
            'proposal_dictionary' => ['nullable', 'string', 'max:5000'],
            'decree' => ['nullable', 'string', 'max:5000'],
            'proposed_at' => ['nullable', 'date'],
            'program' => ['nullable', 'string', 'max:5000'],
            'activity' => ['nullable', 'string', 'max:5000'],
            'sub_activity' => ['nullable', 'string', 'max:5000'],
            'budget_before' => ['nullable', 'integer', 'min:0'],
            'budget_after' => ['nullable', 'integer', 'min:0'],
            'approved_budget' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'opd_id' => 'OPD',
            'fiscal_year' => 'Tahun',
            'recipient_type' => 'Jenis penerima',
            'bk_type' => 'Jenis bantuan keuangan',
            'recipient_name' => 'Nama penerima',
            'approved_budget' => 'Anggaran disetujui',
        ];
    }

    public function save(): void
    {
        $this->authorize(
            'hibah.'.$this->purposeSegment.'.'.($this->proposal ? 'update' : 'create'),
        );

        $data = $this->validate();

        // purpose & form BUKAN dari masukan pengguna — dari rute. Menerima
        // keduanya dari formulir akan membuka kombinasi terlarang lewat
        // permintaan yang dikarang.
        $data['purpose'] = $this->purpose;
        $data['form'] = $this->form;

        if ($this->purpose !== ApprovedProposal::PURPOSE_BK) {
            $data['bk_type'] = null;
        }

        if ($this->proposal) {
            $this->proposal->update($data);
        } else {
            $data['created_by'] = auth()->id();
            $data['status'] = ApprovedProposal::STATUS_APPROVED;

            $this->proposal = ApprovedProposal::create($data);
        }

        $this->dispatch('proposal-saved');

        $this->redirectRoute('hibah.proposals.index', [
            'purpose' => $this->purposeSegment,
            'segment' => $this->segment,
        ], navigate: true);
    }

    public function pageTitle(): string
    {
        return ($this->proposal ? 'Ubah' : 'Tambah').' Usulan';
    }

    public function render()
    {
        return view('nawasara-hibah::livewire.pages.proposal.form')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
