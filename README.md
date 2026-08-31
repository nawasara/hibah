# nawasara/hibah

Pencatatan **bantuan daerah** untuk superapp [Nawasara](https://github.com/nawasara) —
hibah, bantuan sosial, dan bantuan keuangan dari APBD Kabupaten Ponorogo.

Yang dicatat di sini adalah **usulan yang sudah disahkan**: keputusannya
diambil di rapat, di luar sistem, dan aplikasi mencatat hasilnya. Karena itu
tidak ada alur setujui/tolak — yang masih berubah setelah pengesahan adalah
**pencairannya**.

## Status v0.2.0

| Fitur | Status |
|---|---|
| Tiga menu terpisah (Hibah / Bansos / Bantuan Keuangan) | ✅ siap |
| Aturan penerima per peruntukan × bentuk | ✅ siap |
| Status pencairan dihitung dari realisasi | ✅ siap |
| Realisasi per triwulan | ✅ siap |
| Bukti monev | ✅ siap |
| Deteksi penerima ganda (hibah & bansos) | ✅ siap |
| Laporan per peruntukan + ekspor Excel | ✅ siap |
| Impor Excel + template | ✅ siap |
| Scope per-OPD (`nawasara/registry`) | ✅ siap |
| Sub-jenis BK: **PD** | ⏳ nilainya ada di basis data, belum ditawarkan — lihat §Keputusan |

> **v0.2.0 merusak kompatibilitas.** Nama tabel, kolom, class, rute, dan
> permission semuanya berubah, dan migrasinya **menghapus data v0.1.x**.
> Ekspor cadangan CSV sebelum menjalankannya.

## Keputusan yang mudah dibatalkan tanpa tahu alasannya

### 1. Tiga menu di atas satu tabel

Hibah, bansos, dan bantuan keuangan berbagi **satu tabel**, dibedakan kolom
`purpose`. Menunya tetap dipisah tiga karena staf hibah dan staf bansos orang
yang berbeda, dengan berkas dan atasan berbeda. Satu tabel adalah keputusan
penyimpanan; tidak ada alasan itu bocor ke layar mereka.

⚠️ **`purpose` adalah segmen path, bukan query string** —
`hibah/bansos/uang`, bukan `?purpose=bansos`.
`WorkspaceManager::current()` mencocokkan `request()->path()` dan sidebar
memakai `url()->current()`; keduanya **membuang query string**, jadi dengan
query param ketiga menu akan menyala bersamaan tanpa satu baris pun yang
salah menurut kodenya.

### 2. Penerima yang sah ditentukan DUA sumbu, bukan satu

```
Hibah  · Uang/Barang  → Lembaga · Kelompok Masyarakat · Instansi Vertikal
Bansos · Uang         → Perorangan SAJA
Bansos · Barang       → Lembaga · Perorangan · Kelompok Masyarakat
BK     · Uang         → Pemerintah Desa
```

Perhatikan bansos: **uang paling sempit, barang paling luas.** Jadi mengganti
bentuk dari barang ke uang harus **mempersempit** pilihan penerima — dan
nilai yang sudah dipilih bisa menjadi tidak sah. Formulir mengosongkannya;
kalau hanya disembunyikan, tersimpanlah kombinasi terlarang lewat UI yang
terlihat benar.

Aturannya satu tabel di `ApprovedProposal::VALID_RECIPIENTS`, bukan rangkaian
`if` — menambah aturan berikutnya jadi menyunting data.

### 3. Status DIHITUNG, bukan dipilih

```
realisasi 0                     → Disahkan
0 < realisasi < anggaran        → Sebagian Cair
realisasi ≥ anggaran disetujui  → Cair
```

Staf tidak memilih status. Angka triwulan sudah mereka isi, dan menanyakan
status setelahnya membuka kemungkinan keduanya bertentangan — baris
bertuliskan "Cair" dengan realisasi Rp 0.

⚠️ **`approved_budget > 0` wajib diperiksa.** Baris hasil impor tidak selalu
memuatnya, dan tanpa penjagaan itu `$disbursed >= 0` selalu benar — setiap
baris bercap "Cair" begitu ada serupiah cair.

⚠️ **`undisbursed_budget` TIDAK dipakai menghitung.** Diisi tangan dan dapat
basi; jumlah baris `disbursements` yang mencerminkan uang sungguh berpindah.

**Batal satu-satunya yang manual**, karena pembatalan tidak meninggalkan
jejak angka. Dan `cancelled` **bukan** "ditolak": ditolak berarti tidak
pernah disahkan, dibatalkan berarti sudah sah lalu dicabut. Menyatukannya
membuat catatan bertentangan dengan SK yang sungguh ada.

### 4. Deteksi duplikat mengecualikan Bantuan Keuangan

BK mengalir ke pemerintah desa, dan desa yang sama **memang** menerima tiap
tahun — itu cara ADD bekerja. Menandainya duplikat berarti menuduh penyaluran
yang benar sebagai kejanggalan, dan 1.124 baris BK akan menenggelamkan temuan
hibah/bansos yang sungguh perlu ditinjau.

Pengecualiannya ditulis **di dalam detector** (`scopeDuplicateCheckable`),
bukan sebagai saringan halaman, supaya pemanggil berikutnya tidak
melewatkannya. Dan tab Deteksi Duplikat **tidak dirender** di laporan BK: tab
kosong terbaca "sudah dicek, aman" padahal artinya "tidak pernah dicek".

Pengelompokannya **nama + alamat**, bukan nama saja. Baris tanpa alamat
di-skip, bukan dikelompokkan bersama — tanpa alamat tidak ada bukti. Setelah
aturan ini: 1.769 positif palsu turun jadi 8 temuan benar.

### 5. Importer mencocokkan tepat, tidak menebak

`mapPeruntukan()` yang lama menebak dari kata ("mengandung *keuangan* → bk"),
dan tebakannya berubah antar impor: `BANTUAN KEUANGAN DARI ADD` tercatat
`hibah` untuk 2024 dan `bk` untuk 2025 — 562 baris di masing-masing tahun,
tak ada yang menyadari sampai datanya diperiksa dua tahun kemudian.

Sekarang dicocokkan tepat terhadap daftar nilai yang sah. Yang tidak cocok
**ditolak dengan alasan yang menyebut sel mana**, dan impor **tidak berhenti**
— berkas 4.441 baris pernah gagal di tengah jalan dan menyisakan commit
separuh.

⚠️ **`TemplateExport` menentukan bentuk berkas yang dikirim balik OPD.**
Perbarui template **sebelum** meminta data, bukan sesudah.

### 6. `pd` ada di enum, tidak di konstanta

Kolom `bk_type` memuat `'pd'`, tetapi `BK_TYPES` **belum** — kepanjangan dan
keberadaannya belum dipastikan, dan data 2024/2025 hanya memuat ADD.
Menawarkan pilihan yang tidak dipahami staf berakhir dengan pilihan terisi
asal. Menambahkannya kelak cukup satu baris konstanta; menambah nilai enum di
MySQL menulis ulang seluruh tabel.

## Aktor

| Aktor | Tugas | Scope |
|---|---|---|
| **Operator OPD** | Entry usulan, catat realisasi, ekspor | OPD sendiri |
| **Admin-Hibah** | Impor massal, pantau lintas OPD | Semua OPD |

Scope OPD **bukan** permission — ditegakkan global scope lewat
`ScopedToOpd` dari `nawasara/registry`. Role privileged
(`developer`, `hibah-admin`) melihat semua; yang tanpa membership tidak
melihat apa pun (fail-closed).

## Menu

```
BANTUAN DAERAH
  Hibah              → Hibah Uang · Hibah Barang · Laporan
  Bansos             → Bansos Uang · Bansos Barang · Laporan
  Bantuan Keuangan   → Umum · Khusus · Laporan
  Pengaturan Hibah   → Impor Data
```

⚠️ ID workspace **wajib berbeda**. `WorkspaceManager` menggabungkan workspace
ber-ID sama dan mengambil label dari yang termuat lebih dulu secara alfabet.

## Permissions

```
hibah.hibah.view / create / update
hibah.bansos.view / create / update
hibah.bantuan-keuangan.view / create / update
hibah.approved-proposal.view / update
hibah.disbursement.update
hibah.report.export
hibah.import                # admin
```

Digerbang per **peruntukan**, bukan per submenu — staf yang boleh melihat
hibah uang praktis selalu boleh melihat hibah barang. Ketiganya saat ini
diberikan ke role yang sama; dipisah supaya kelak dapat dibelah tanpa
menyentuh skema.

Awalan `hibah.` mengikuti **nama paket**, bukan label workspace — itu yang
membuat permission dapat ditelusuri ke paket asalnya.

## Setup

```bash
composer require nawasara/hibah
php artisan migrate
php artisan db:seed --class="Nawasara\Hibah\Database\Seeders\PermissionSeeder"
```

Tailwind v4 — daftarkan di `resources/css/app.css`:

```css
@source "../../vendor/nawasara/hibah";
```

Tanpa baris itu **semua class Tailwind di blade paket ini tidak ter-compile**:
tampilan kehilangan latar dan tata letaknya pecah, dan yang tersisa hanya
class yang kebetulan juga dipakai paket lain.

Impor data:

```bash
php artisan hibah:import "FORM BANTUAN DAERAH 2026.xlsx" 2026
```

Excel besar (>20 MB) — konversi ke CSV dulu lalu
`ApprovedProposalImport::importCsv()`; PhpSpreadsheet memuat seluruh workbook
ke memori, dan berkas 34 MB pernah menghabiskan 7,6 GB RAM karena 524 ribu
baris kosong ter-styled.

## Arsitektur

- Satu container & image dengan Nawasara (port 7100).
- `hibah.ponorogo.go.id` = **alias akses** (DNS CNAME + Cloudflare Tunnel),
  bukan konteks routing terpisah.
- Component dipecah per urusan: satu component, satu tombol simpan. Halaman
  detail sebelumnya 199 baris PHP + 414 blade menangani empat urusan, dan
  mengunggah bukti monev me-render ulang seluruh halaman.

## Roadmap

- Penerima BK selain pemerintah desa — bila ternyata ada
- Riwayat perubahan anggaran bertahap (kini hanya sebelum/sesudah)
- Laporan lintas peruntukan satu tahun untuk pimpinan

## License

MIT
