<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Nawasara\Hibah\Http\Middleware\EnsurePurposePermission;
use Nawasara\Hibah\Livewire\Import\Index as ImportIndex;
use Nawasara\Hibah\Livewire\Proposal\Detail as ProposalDetail;
use Nawasara\Hibah\Livewire\Proposal\Form as ProposalForm;
use Nawasara\Hibah\Livewire\Proposal\Index as ProposalIndex;
use Nawasara\Hibah\Livewire\Report\Index as ReportIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
| Rute nawasara/hibah — satu host (hibah.ponorogo.go.id hanyalah alias
| DNS/tunnel ke container yang sama, bukan konteks routing terpisah).
| Semua di balik auth + permission Spatie; TIDAK ada halaman publik.
| Pemisahan data per-OPD ditegakkan trait ScopedToOpd pada model, bukan di
| sini. Keanggotaan user↔OPD dikelola nawasara/registry.
|
| ── Kenapa peruntukan jadi SEGMEN, bukan query string ──
|
| WorkspaceManager::current() mencocokkan dengan request()->path(), dan
| sidebar memakai url()->current(). Keduanya MEMBUANG query string, jadi
| `?purpose=bansos` akan membuat ketiga menu menyala bersamaan — tanpa satu
| baris pun yang salah menurut kodenya.
|
|   hibah/hibah/uang                 hibah/bansos/barang
|   hibah/bantuan-keuangan/umum      hibah/bantuan-keuangan/khusus
|
| Segmen pertama `hibah` adalah nama PAKET; yang kedua peruntukannya. Jadi
| `hibah/hibah/uang` memang terlihat janggal, dan itu diterima: mengubah
| salah satunya lebih mahal daripada kejanggalannya.
*/

Route::middleware(['web', 'auth'])->prefix('hibah')->group(function () {

    // ── Tiga menu, satu berkas rute ──────────────────────────────
    //
    // whereIn menolak segmen karangan di lapisan rute, jadi URL yang
    // dikarang mendapat 404 — bukan daftar kosong yang terbaca seperti
    // "belum ada data". Pasangan yang tidak masuk akal (bansos/khusus,
    // bantuan-keuangan/barang) ditolak component lewat isValidSegmentPair().
    Route::prefix('{purpose}')
        ->whereIn('purpose', ['hibah', 'bansos', 'bantuan-keuangan'])
        // Permission-nya bergantung segmen, jadi tidak dapat ditulis statis
        // di sini — middleware ini menyusunnya dari {purpose}.
        ->middleware(EnsurePurposePermission::class.':view')
        ->group(function () {

            // ⚠️ Laporan dideklarasikan LEBIH DULU daripada `{segment}`.
            // Rute Laravel dicocokkan berurutan, jadi tanpa urutan ini
            // 'laporan' akan terbaca sebagai nilai {segment}. whereIn di
            // bawah kebetulan menolaknya, tetapi mengandalkan itu berarti
            // menambah satu nilai segmen kelak dapat mematikan laporan.
            Route::get('laporan', ReportIndex::class)
                ->name('hibah.reports.index');

            Route::get('{segment}', ProposalIndex::class)
                ->whereIn('segment', ['uang', 'barang', 'umum', 'khusus'])
                ->name('hibah.proposals.index');

            Route::get('{segment}/create', ProposalForm::class)
                ->whereIn('segment', ['uang', 'barang', 'umum', 'khusus'])
                ->middleware(EnsurePurposePermission::class.':create')
                ->name('hibah.proposals.create');

            Route::get('{segment}/{proposal}/edit', ProposalForm::class)
                ->whereIn('segment', ['uang', 'barang', 'umum', 'khusus'])
                ->middleware(EnsurePurposePermission::class.':update')
                ->name('hibah.proposals.edit');

            Route::get('{segment}/{proposal}', ProposalDetail::class)
                ->whereIn('segment', ['uang', 'barang', 'umum', 'khusus'])
                ->name('hibah.proposals.detail');
        });

    // ── Pengaturan ───────────────────────────────────────────────
    Route::get('import', ImportIndex::class)
        ->middleware(PermissionMiddleware::using('hibah.import'))
        ->name('hibah.import.index');
});
