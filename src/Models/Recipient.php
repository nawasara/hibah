<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penerima bantuan — entitas tersendiri, bukan sekadar teks di usulan.
 *
 * ⚠️ Kolom `recipient_name` / `recipient_address` di ApprovedProposal
 * **tetap ada** dan sengaja tidak dihapus. Keduanya menyimpan nama seperti
 * tertulis **pada SK** untuk usulan itu, sementara tabel ini menyimpan
 * identitas penerimanya. Nama di master boleh dirapikan ejaannya; nama di
 * usulan harus tetap sama dengan dokumen yang mengesahkannya, karena itulah
 * yang dicocokkan saat diaudit.
 */
class Recipient extends Model
{
    protected $table = 'nawasara_hibah_recipients';

    protected $guarded = [];

    public function proposals(): HasMany
    {
        return $this->hasMany(ApprovedProposal::class, 'recipient_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $recipient): void {
            $recipient->name_normalized = ApprovedProposal::normalize($recipient->name);
            $recipient->address_normalized = ApprovedProposal::normalize($recipient->address);
        });
    }

    public function typeLabel(): string
    {
        return ApprovedProposal::RECIPIENT_TYPES[$this->type] ?? $this->type;
    }

    /**
     * Cari penerima yang sudah ada, atau buat baru.
     *
     * Dicocokkan lewat kolom ternormalisasi, jadi perbedaan ejaan dan tanda
     * baca tidak melahirkan penerima ganda — persis masalah yang membuat
     * sembilan kategori lahir dari empat.
     *
     * ⚠️ Alamat KOSONG tidak digabungkan. Dua "MDT Miftahul Huda" tanpa
     * alamat bisa jadi dua madrasah berbeda, dan menyatukannya menggabungkan
     * riwayat penerimaan yang tidak berhubungan — kesalahan yang jauh lebih
     * sulit ditemukan daripada dua baris kembar.
     */
    public static function findOrCreateFor(string $name, ?string $address, string $type): self
    {
        $nameNorm = ApprovedProposal::normalize($name);
        $addrNorm = ApprovedProposal::normalize($address);

        if ($nameNorm !== null && $addrNorm !== null) {
            $existing = static::query()
                ->where('name_normalized', $nameNorm)
                ->where('address_normalized', $addrNorm)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        return static::create([
            'name' => $name,
            'address' => $address,
            'type' => $type,
        ]);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
