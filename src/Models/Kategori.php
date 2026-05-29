<?php

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $table = 'nawasara_hibah_kategori';

    protected $guarded = [];

    protected $casts = [
        'aktif' => 'boolean',
    ];

    public function pengajuan(): HasMany
    {
        return $this->hasMany(Pengajuan::class, 'kategori_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif', true);
    }
}
