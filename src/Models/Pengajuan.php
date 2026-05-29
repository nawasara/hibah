<?php

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Nawasara\Registry\Concerns\ScopedToOpd;
use Nawasara\Registry\Models\Opd;

class Pengajuan extends Model
{
    use ScopedToOpd;

    public const STATUS_DIAJUKAN = 'diajukan';

    public const STATUS_DISETUJUI = 'disetujui';

    public const STATUS_DITOLAK = 'ditolak';

    public const STATUS_SELESAI = 'selesai';

    public const PERUNTUKAN = ['hibah', 'bansos', 'bk'];

    /**
     * Allowed status transitions. Empty array = terminal-ish but kept
     * editable (ditolak/selesai can still be corrected by re-opening to
     * diajukan if an operator made a mistake — business allows edits).
     */
    public const TRANSITIONS = [
        self::STATUS_DIAJUKAN => [self::STATUS_DISETUJUI, self::STATUS_DITOLAK],
        self::STATUS_DISETUJUI => [self::STATUS_SELESAI, self::STATUS_DITOLAK],
        self::STATUS_DITOLAK => [self::STATUS_DIAJUKAN],
        self::STATUS_SELESAI => [self::STATUS_DISETUJUI],
    ];

    protected $table = 'nawasara_hibah_pengajuan';

    protected $guarded = [];

    protected $casts = [
        'tahun' => 'integer',
        'lintas_dapil' => 'boolean',
        'tanggal_proposal' => 'date',
        'anggaran_sebelum' => 'integer',
        'anggaran_setelah' => 'integer',
        'anggaran_disetujui' => 'integer',
        'anggaran_belum_cair' => 'integer',
    ];

    /**
     * Roles that may see hibah across all OPD without a membership row:
     * the global super-admin (developer) and the hibah admin tier. Operators
     * (hibah-operator) are NOT here — without a membership they see nothing.
     */
    protected static function privilegedRoles(): array
    {
        return ['developer', 'hibah-admin'];
    }

    protected static function booted(): void
    {
        // OpdScope is applied by the ScopedToOpd trait's boot hook.

        // Keep the normalized recipient columns in sync whenever the source
        // fields change — single source of truth for duplicate detection.
        static::saving(function (Pengajuan $model) {
            if ($model->isDirty('nama_penerima')) {
                $model->nama_penerima_normalized = self::normalize($model->nama_penerima);
            }
            if ($model->isDirty('alamat_penerima')) {
                $model->alamat_penerima_normalized = self::normalize($model->alamat_penerima);
            }
        });
    }

    /**
     * Normalize a recipient name/address for duplicate matching:
     * lowercase, strip punctuation, collapse whitespace, trim. So
     * "MI Muhammadiyah 14 Beton" and "MI MUHAMMADIYAH 14 BETON." match.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = Str::lower($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value); // strip punctuation
        $value = preg_replace('/\s+/u', ' ', $value);             // collapse whitespace
        $value = trim($value);

        // Cap to the indexed column width (191, utf8mb4 index-safe). Long
        // values that share a 191-char prefix still group together — fine for
        // duplicate detection.
        return Str::limit($value, 191, '');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class, 'opd_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function realisasi(): HasMany
    {
        return $this->hasMany(Realisasi::class, 'pengajuan_id');
    }

    public function histori(): HasMany
    {
        return $this->hasMany(StatusHistori::class, 'pengajuan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function totalRealisasi(): int
    {
        return (int) $this->realisasi->sum('realisasi_anggaran');
    }

    /**
     * nawasara-ui badge color token for the current status.
     */
    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DISETUJUI => 'success',
            self::STATUS_DITOLAK => 'danger',
            self::STATUS_SELESAI => 'info',
            default => 'warning', // diajukan
        };
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DIAJUKAN => 'Diajukan',
            self::STATUS_DISETUJUI => 'Disetujui',
            self::STATUS_DITOLAK => 'Ditolak',
            self::STATUS_SELESAI => 'Selesai',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst($this->status);
    }
}
