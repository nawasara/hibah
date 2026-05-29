<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Hibah\Livewire\Import\Index as ImportIndex;
use Nawasara\Hibah\Livewire\Kategori\Index as KategoriIndex;
use Nawasara\Hibah\Livewire\Laporan\Index as LaporanIndex;
use Nawasara\Hibah\Livewire\Pengajuan\Detail as PengajuanDetail;
use Nawasara\Hibah\Livewire\Pengajuan\Form as PengajuanForm;
use Nawasara\Hibah\Livewire\Pengajuan\Index as PengajuanIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

/*
| Hibah routes — single-host (hibah.ponorogo.go.id is a DNS/tunnel alias
| for the same container, not a separate routing context). All routes sit
| behind auth + Spatie permission; there is NO public/anonymous page.
| Per-OPD data isolation is enforced by the registry ScopedToOpd trait on
| the models, not here. User↔OPD membership is managed in nawasara/registry.
*/

Route::middleware(['web', 'auth'])->prefix('hibah')->group(function () {
    Route::get('pengajuan', PengajuanIndex::class)
        ->middleware(PermissionMiddleware::using('hibah.pengajuan.view'))
        ->name('hibah.pengajuan.index');

    Route::get('pengajuan/create', PengajuanForm::class)
        ->middleware(PermissionMiddleware::using('hibah.pengajuan.create'))
        ->name('hibah.pengajuan.create');

    Route::get('pengajuan/{pengajuan}/edit', PengajuanForm::class)
        ->middleware(PermissionMiddleware::using('hibah.pengajuan.update'))
        ->name('hibah.pengajuan.edit');

    Route::get('pengajuan/{pengajuan}', PengajuanDetail::class)
        ->middleware(PermissionMiddleware::using('hibah.pengajuan.view'))
        ->name('hibah.pengajuan.detail');

    Route::get('laporan', LaporanIndex::class)
        ->middleware(PermissionMiddleware::using('hibah.laporan.view'))
        ->name('hibah.laporan.index');

    Route::get('kategori', KategoriIndex::class)
        ->middleware(PermissionMiddleware::using('hibah.kategori.manage'))
        ->name('hibah.kategori.index');

    Route::get('import', ImportIndex::class)
        ->middleware(PermissionMiddleware::using('hibah.import'))
        ->name('hibah.import.index');
});
