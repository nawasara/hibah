<?php

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Realisasi extends Model
{
    protected $table = 'nawasara_hibah_realisasi';

    protected $guarded = [];

    protected $casts = [
        'triwulan' => 'integer',
        'realisasi_anggaran' => 'integer',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(Pengajuan::class, 'pengajuan_id');
    }
}
