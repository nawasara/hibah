<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Nawasara\Registry\Concerns\ScopedToOpd;
use Nawasara\Registry\Models\Opd;

/**
 * Usulan bantuan daerah yang SUDAH DISAHKAN.
 *
 * Namanya sengaja bukan `Proposal`. Yang dicatat di sini keputusan yang
 * sudah diambil di luar sistem — kalau namanya "proposal", pengembang
 * berikutnya akan menambahkan alur setujui/tolak kembali, justru karena
 * namanya mengundang itu.
 *
 * Satu tabel memuat hibah, bansos, dan bantuan keuangan. Menunya dipisah
 * tiga, tetapi itu keputusan tampilan; di sini ketiganya hanya dibedakan
 * `purpose`.
 */
class ApprovedProposal extends Model
{
    use ScopedToOpd;

    protected $table = 'nawasara_hibah_approved_proposals';

    protected $guarded = [];

    /**
     * Status awal diisi model, bukan hanya default kolom.
     *
     * Default basis data baru terbaca setelah model di-refresh, jadi tanpa
     * ini `$proposal->status` kosong tepat setelah create() — dan
     * recalculateStatus() yang dipanggil importer akan membandingkan dengan
     * string kosong, lalu menganggapnya berubah setiap kali.
     */
    protected $attributes = [
        'status' => self::STATUS_APPROVED,
    ];

    // ─────────────────────────────────────────────────────────────
    //  Data statis — konstanta, bukan tabel referensi.
    //
    //  Kategori dulu dibuat sebagai tabel supaya staf bisa menambah
    //  sendiri, dan itulah yang melahirkan sembilan kategori dari empat
    //  ("HIBAH UANG" di samping "HIBAH BERUPA UANG"). Nilai di bawah
    //  berasal dari peraturan, bukan selera OPD.
    //
    //  Bentuknya: KUNCI = nilai yang tersimpan di basis data,
    //  NILAI = label yang dibaca staf. Satu tempat, dua kebutuhan.
    // ─────────────────────────────────────────────────────────────

    public const PURPOSE_HIBAH = 'hibah';

    public const PURPOSE_BANSOS = 'bansos';

    public const PURPOSE_BK = 'bk';

    public const PURPOSES = [
        self::PURPOSE_HIBAH => 'Hibah',
        self::PURPOSE_BANSOS => 'Bansos',
        self::PURPOSE_BK => 'Bantuan Keuangan',
    ];

    public const FORM_UANG = 'uang';

    public const FORM_BARANG = 'barang';

    public const FORMS = [
        self::FORM_UANG => 'Uang',
        self::FORM_BARANG => 'Barang',
    ];

    /**
     * Segmen URL → nilai `purpose`.
     *
     * URL memakai `bantuan-keuangan` yang terbaca manusia, basis data
     * menyimpan `bk`. Pemetaannya ditulis SEKALI di sini; menyebarnya ke
     * tiap component adalah cara paling cepat membuat satu di antaranya
     * berbeda diam-diam.
     */
    public const URL_SEGMENTS = [
        'hibah' => self::PURPOSE_HIBAH,
        'bansos' => self::PURPOSE_BANSOS,
        'bantuan-keuangan' => self::PURPOSE_BK,
    ];

    /**
     * Segmen kedua yang sah untuk tiap peruntukan.
     *
     * Hibah dan bansos dipecah menurut bentuk; BK menurut sub-jenisnya.
     * Pasangan di luar ini — `bansos/khusus`, `bantuan-keuangan/barang` —
     * harus 404, bukan menampilkan daftar kosong yang terbaca seperti
     * "belum ada data".
     */
    public const URL_CHILD_SEGMENTS = [
        'hibah' => ['uang', 'barang'],
        'bansos' => ['uang', 'barang'],
        'bantuan-keuangan' => ['umum', 'khusus'],
    ];

    public static function purposeFromSegment(?string $segment): ?string
    {
        return self::URL_SEGMENTS[$segment] ?? null;
    }

    public static function segmentFromPurpose(?string $purpose): ?string
    {
        return array_search($purpose, self::URL_SEGMENTS, true) ?: null;
    }

    public static function isValidSegmentPair(?string $purpose, ?string $child): bool
    {
        return in_array($child, self::URL_CHILD_SEGMENTS[$purpose] ?? [], true);
    }

    public const RECIPIENT_TYPES = [
        'lembaga' => 'Lembaga',
        'kelompok_masyarakat' => 'Kelompok Masyarakat',
        'instansi_vertikal' => 'Instansi Vertikal',
        'perorangan' => 'Perorangan',
        'pemerintah_desa' => 'Pemerintah Desa',
    ];

    /**
     * Sub-jenis Bantuan Keuangan.
     *
     * ⚠️ 'pd' ADA di enum basis data tetapi TIDAK di sini. Kepanjangan dan
     * keberadaannya belum dipastikan — data 2024 dan 2025 hanya memuat ADD.
     * Menawarkan pilihan yang tidak dipahami staf berakhir dengan pilihan
     * yang terisi asal. Menambahkannya kelak cukup satu baris di sini,
     * tanpa migrasi.
     */
    public const BK_TYPES = [
        'umum' => 'Umum',
        'add' => 'ADD',
    ];

    /**
     * Penerima yang sah untuk tiap pasangan (purpose, form).
     *
     * Ditulis sebagai TABEL, bukan rangkaian `if`, karena penerima yang sah
     * ditentukan kedua sumbu sekaligus — bukan salah satunya. Menambah
     * aturan berikutnya jadi menyunting data, bukan menyisipkan cabang.
     *
     * Perhatikan bansos: UANG hanya ke perorangan, sedangkan BARANG justru
     * yang paling luas. Jadi mengganti bentuk dari barang ke uang harus
     * MEMPERSEMPIT pilihan penerima — lihat validRecipients().
     */
    public const VALID_RECIPIENTS = [
        self::PURPOSE_HIBAH => [
            self::FORM_UANG => ['lembaga', 'kelompok_masyarakat', 'instansi_vertikal'],
            self::FORM_BARANG => ['lembaga', 'kelompok_masyarakat', 'instansi_vertikal'],
        ],
        self::PURPOSE_BANSOS => [
            self::FORM_UANG => ['perorangan'],
            self::FORM_BARANG => ['lembaga', 'perorangan', 'kelompok_masyarakat'],
        ],
        self::PURPOSE_BK => [
            // Pemerintah desa PASTI masuk. Apakah BK juga mengalir ke
            // penerima lain belum diketahui — tambahkan di sini bila muncul.
            // Mengunci ini terlalu sempit berarti data sah ditolak importer,
            // dan itu baru ketahuan saat impor gagal.
            self::FORM_UANG => ['pemerintah_desa'],
        ],
    ];

    // ── Status pencairan ─────────────────────────────────────────
    //
    //  DIHITUNG dari realisasi, bukan dipilih staf. Satu-satunya yang
    //  dinyatakan manusia adalah 'cancelled', karena pembatalan tidak
    //  meninggalkan jejak angka.

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIALLY_DISBURSED = 'partially_disbursed';

    public const STATUS_DISBURSED = 'disbursed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_APPROVED => 'Disahkan',
        self::STATUS_PARTIALLY_DISBURSED => 'Sebagian Cair',
        self::STATUS_DISBURSED => 'Cair',
        self::STATUS_CANCELLED => 'Batal',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'cross_dapil' => 'boolean',
            'proposed_at' => 'date',
            'budget_before' => 'integer',
            'budget_after' => 'integer',
            'approved_budget' => 'integer',
            'undisbursed_budget' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    //  Aturan silang
    // ─────────────────────────────────────────────────────────────

    /**
     * Penerima yang sah untuk pasangan ini.
     *
     * @return list<string>
     */
    public static function validRecipients(?string $purpose, ?string $form): array
    {
        return self::VALID_RECIPIENTS[$purpose][$form] ?? [];
    }

    /**
     * Penerima sah beserta labelnya — untuk mengisi `<select>`.
     *
     * @return array<string, string>
     */
    public static function recipientOptions(?string $purpose, ?string $form): array
    {
        return array_intersect_key(
            self::RECIPIENT_TYPES,
            array_flip(self::validRecipients($purpose, $form)),
        );
    }

    public static function isValidCombination(?string $purpose, ?string $form, ?string $recipientType): bool
    {
        return in_array($recipientType, self::validRecipients($purpose, $form), true);
    }

    // ─────────────────────────────────────────────────────────────
    //  Status
    // ─────────────────────────────────────────────────────────────

    /**
     * Hitung ulang status dari realisasi yang tercatat.
     *
     * Tidak menyimpan — pemanggil yang memutuskan kapan menyimpan, supaya
     * perubahan status dan pencatatan riwayatnya terjadi dalam satu
     * transaksi.
     *
     * @return bool true bila statusnya berubah
     */
    public function recalculateStatus(): bool
    {
        // Pembatalan keputusan manusia; angka tidak boleh menimpanya.
        if ($this->status === self::STATUS_CANCELLED) {
            return false;
        }

        $disbursed = (int) $this->disbursements()->sum('disbursed_amount');
        $approved = (int) $this->approved_budget;

        $next = match (true) {
            $disbursed <= 0 => self::STATUS_APPROVED,

            // ⚠️ `$approved > 0` wajib. Baris hasil impor tidak selalu
            // memuat anggaran disetujui, dan tanpa penjagaan ini
            // `$disbursed >= 0` selalu benar — setiap baris akan bercap
            // "Cair" begitu ada serupiah cair. Menyatakan lunas atas dasar
            // angka yang tidak diketahui lebih berbahaya daripada
            // menyatakan sebagian.
            $approved > 0 && $disbursed >= $approved => self::STATUS_DISBURSED,

            default => self::STATUS_PARTIALLY_DISBURSED,
        };

        if ($next === $this->status) {
            return false;
        }

        $this->status = $next;

        return true;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // ─────────────────────────────────────────────────────────────
    //  Normalisasi untuk deteksi duplikat
    // ─────────────────────────────────────────────────────────────

    /**
     * Bentuk yang dapat dibandingkan: huruf kecil, tanpa tanda baca,
     * spasi rapat.
     *
     * Menangkap ketidakkonsistenan nyata di Excel sumber ("MI Muhammadiyah
     * 14 Beton" vs "MI MUHAMMADIYAH 14 BETON.") yang lolos pencocokan
     * persis.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = Str::lower(strip_tags($value));
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return Str::limit(trim($value), 191, '');
    }

    /**
     * Role yang melihat seluruh OPD tanpa baris membership: super-admin
     * global dan tingkat admin hibah.
     *
     * `hibah-operator` sengaja TIDAK di sini — tanpa membership ia tidak
     * melihat apa pun (fail-closed). Dan urutannya penting di
     * MembershipResolver: privileged diperiksa SEBELUM membership, karena
     * admin OPD sering juga jadi PIC OPD-nya sendiri — kalau membership
     * diperiksa duluan, admin ter-scope ke satu OPD dan datanya "terlihat
     * kosong" padahal ada di OPD lain.
     */
    protected static function privilegedRoles(): array
    {
        return ['developer', 'hibah-admin'];
    }

    protected static function booted(): void
    {
        // Scope OPD dipasang trait lewat bootScopedToOpd(), yang dipanggil
        // Laravel sendiri berdasarkan konvensi nama — bukan dari sini.

        // Jaga kolom normalisasi tetap seiring sumbernya — satu sumber
        // kebenaran untuk deteksi duplikat.
        static::saving(function (self $proposal): void {
            $proposal->recipient_name_normalized = self::normalize($proposal->recipient_name);
            $proposal->recipient_address_normalized = self::normalize($proposal->recipient_address);
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Relasi & scope
    // ─────────────────────────────────────────────────────────────

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Disbursement::class, 'approved_proposal_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class, 'approved_proposal_id')
            ->latest('created_at');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /** Saringan yang dipakai tiap menu — lihat config/menu.php. */
    public function scopePurpose(Builder $query, string $purpose): Builder
    {
        return $query->where('purpose', $purpose);
    }

    public function scopeForm(Builder $query, string $form): Builder
    {
        return $query->where('form', $form);
    }

    /**
     * Peruntukan yang diperiksa duplikasinya.
     *
     * Bantuan Keuangan DIKECUALIKAN: ia mengalir ke pemerintah desa, dan
     * desa yang sama memang menerima tiap tahun — itu cara ADD bekerja.
     * Menandainya duplikat berarti menuduh penyaluran yang benar sebagai
     * kejanggalan, dan jumlahnya akan menenggelamkan temuan hibah/bansos
     * yang sungguh perlu ditinjau.
     */
    public function scopeDuplicateCheckable(Builder $query): Builder
    {
        return $query->whereIn('purpose', [self::PURPOSE_HIBAH, self::PURPOSE_BANSOS]);
    }
}
