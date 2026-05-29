# nawasara/hibah

Manajemen Hibah & Bansos untuk superapp [Nawasara](https://github.com/nawasara).
Operator OPD meng-entry usulan hibah, mencatat keputusan rapat (SK kepala
daerah + anggaran disetujui), dan melacak realisasi per triwulan. Admin-Hibah
mengelola operator dan master kategori, serta memantau seluruh OPD.

## Aktor

| Aktor | Tugas | Scope |
|---|---|---|
| **Operator OPD** | Entry data, ubah status, update realisasi, export | OPD sendiri |
| **Admin-Hibah** | Kelola operator OPD + master kategori, monitor lintas OPD | Semua OPD |

Persetujuan terjadi di **rapat di luar sistem**; aplikasi **mencatat hasil**,
bukan memutuskan. Tidak ada workflow approval berjenjang.

## Lifecycle

```
diajukan → disetujui / ditolak → selesai
```

Saat disetujui: isi SK kepala daerah + anggaran disetujui. Lalu update
realisasi per triwulan (1-4).

## Arsitektur

- Satu container & image dengan Nawasara (port 7100).
- `hibah.ponorogo.go.id` = **alias akses** (DNS CNAME + Cloudflare Tunnel),
  bukan halaman publik. Buka host → redirect `/login`.
- Keamanan = Spatie permission `hibah.*` + scope OPD (`OpdScope`).

## Permissions

```
hibah.pengajuan.view / create / update
hibah.realisasi.update
hibah.laporan.view / export
hibah.kategori.manage      # admin
hibah.operator.manage      # admin
```

Scope OPD bukan permission — di-enforce via global scope berdasarkan OPD
operator. Admin bypass scope.

## Setup

```bash
composer require nawasara/hibah
php artisan migrate
php artisan db:seed --class="Nawasara\Hibah\Database\Seeders\PermissionSeeder"
```

Import data historis:

```bash
php artisan hibah:import "FORM APLIKASI HIBAH BANSOS TAHUN 2024.xlsx" 2024
```

## License

MIT.
