<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jejak perubahan status.
 *
 * Dicatat meski status kini dihitung otomatis dari realisasi — justru
 * karena otomatis. Tanpa jejak ini riwayat sebuah usulan melompat dari
 * "Disahkan" ke "Cair" tanpa menyebut siapa dan kapan.
 */
class StatusHistory extends Model
{
    protected $table = 'nawasara_hibah_status_histories';

    protected $guarded = [];

    /** Hanya created_at — baris riwayat tidak pernah disunting. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function approvedProposal(): BelongsTo
    {
        return $this->belongsTo(ApprovedProposal::class, 'approved_proposal_id');
    }

    public function byUser(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'by_user_id');
    }

    public function fromLabel(): ?string
    {
        return $this->from_status === null
            ? null
            : (ApprovedProposal::STATUSES[$this->from_status] ?? $this->from_status);
    }

    public function toLabel(): string
    {
        return ApprovedProposal::STATUSES[$this->to_status] ?? $this->to_status;
    }
}
