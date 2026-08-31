<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tiap view yang dirujuk kode harus benar-benar ada.
 *
 * Rujukan view yang mati **tidak menghasilkan galat apa pun** sampai
 * halamannya dibuka — lolos lint, lolos `view:cache` (yang mengkompilasi
 * berkas yang ADA, bukan memeriksa yang dirujuk), dan lolos seluruh smoke
 * test. Yang menemukannya adalah pengguna, dengan layar 500.
 *
 * Persis itu yang terjadi saat folder `laporan/` diganti `report/`:
 * `Report\Index::render()` masih menunjuk `livewire.pages.laporan.index`,
 * dan baru ketahuan setelah deploy ke produksi.
 */
class ViewReferenceTest extends TestCase
{
    private function packageRoot(): string
    {
        return dirname(__DIR__);
    }

    /**
     * @return array<string, string> rujukan => jalur berkas yang diharapkan
     */
    private function referencedViews(): array
    {
        $found = [];
        $src = $this->packageRoot().'/src';

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                '/nawasara-hibah::([a-z0-9._\-]+)/i',
                (string) file_get_contents($file->getPathname()),
                $m,
            );

            foreach ($m[1] as $view) {
                $found[$view] = $this->packageRoot()
                    .'/resources/views/'
                    .str_replace('.', '/', $view)
                    .'.blade.php';
            }
        }

        return $found;
    }

    public function test_setiap_view_yang_dirujuk_ada(): void
    {
        $views = $this->referencedViews();

        $this->assertNotEmpty($views, 'tidak ada rujukan view terdeteksi — pola pencariannya mungkin rusak');

        foreach ($views as $view => $path) {
            $this->assertFileExists(
                $path,
                "view '{$view}' dirujuk kode tetapi berkasnya tidak ada",
            );
        }
    }

    /**
     * Sebaliknya juga: blade halaman yang tidak dirujuk siapa pun.
     *
     * Bukan galat — bisa saja disertakan blade lain — tetapi sisa berkas
     * setelah rename biasanya muncul begini, dan lebih baik terlihat.
     */
    public function test_tidak_ada_blade_halaman_yatim(): void
    {
        $referenced = array_values($this->referencedViews());
        $pages = glob($this->packageRoot().'/resources/views/livewire/pages/*/*.blade.php') ?: [];

        $orphans = [];

        foreach ($pages as $page) {
            $normalised = str_replace('\\', '/', $page);

            $isReferenced = false;

            foreach ($referenced as $ref) {
                if (str_replace('\\', '/', $ref) === $normalised) {
                    $isReferenced = true;
                    break;
                }
            }

            if (! $isReferenced) {
                $orphans[] = basename(dirname($page)).'/'.basename($page);
            }
        }

        $this->assertSame([], $orphans, 'blade halaman tidak dirujuk kode mana pun');
    }
}
