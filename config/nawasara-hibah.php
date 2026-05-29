<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Host (alias)
    |--------------------------------------------------------------------------
    | hibah.ponorogo.go.id adalah ALIAS akses untuk operator OPD — bukan
    | halaman publik. Buka host ini → redirect ke /login. Semua aktor
    | internal (operator OPD + admin-hibah). Tidak ada landing anonim.
    |
    | Dipakai oleh listener login untuk deteksi "dibuka dari host hibah"
    | (opsional — untuk redirect default ke area hibah setelah login).
    */
    'public_host' => env('HIBAH_PUBLIC_HOST', 'hibah.ponorogo.go.id'),

    /*
    |--------------------------------------------------------------------------
    | Upload Dokumen
    |--------------------------------------------------------------------------
    | Bukti verifikasi awal + bukti monev pelaksanaan. Disimpan di disk
    | privat (di luar webroot) — diakses lewat route ber-auth, bukan URL
    | publik langsung.
    */
    'uploads' => [
        'disk' => env('HIBAH_UPLOAD_DISK', 'local'),
        'directory' => 'hibah',
        'max_size_kb' => env('HIBAH_UPLOAD_MAX_KB', 10240), // 10 MB
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deteksi Duplikat Penerima
    |--------------------------------------------------------------------------
    | Normalisasi nama + alamat penerima sebelum dibandingkan, supaya
    | variasi penulisan ("MI Muhammadiyah" vs "MI MUHAMMADIYAH") tetap
    | terdeteksi sebagai potensi duplikat / double-funding.
    |
    | cross_year: true → bandingkan lintas tahun (default). Penerima yang
    | sama dapat hibah berulang tiap tahun adalah sinyal penting.
    */
    'duplicate' => [
        'cross_year' => env('HIBAH_DUPLICATE_CROSS_YEAR', true),
    ],
];
