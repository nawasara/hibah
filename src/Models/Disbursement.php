<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Realisasi pencairan satu triwulan.
 *
 * Jumlah seluruh baris milik sebuah usulan MENENTUKAN statusnya — lihat
 * ApprovedProposal::recalculateStatus(). Jadi menyimpan baris di sini bukan
 * sekadar mencatat, melainkan mengubah keadaan usulannya.
 */
class Disbursement extends Model
{
    protected $table = 'nawasara_hibah_disbursements';

    protected $guarded = [];

    /** Label triwulan — angka Romawi ditulis apa adanya, bukan dihitung. */
    public const QUARTERS = [
        1 => 'TW I',
        2 => 'TW II',
        3 => 'TW III',
        4 => 'TW IV',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'disbursed_amount' => 'integer',
        ];
    }

    public function approvedProposal(): BelongsTo
    {
        return $this->belongsTo(ApprovedProposal::class, 'approved_proposal_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    public function quarterLabel(): string
    {
        return self::QUARTERS[$this->quarter] ?? "TW {$this->quarter}";
    }
}
