<?php

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistori extends Model
{
    protected $table = 'nawasara_hibah_status_histori';

    public $timestamps = false; // only created_at, set by DB default

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'oleh_user_id');
    }
}
