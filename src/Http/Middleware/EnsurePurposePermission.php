<?php

declare(strict_types=1);

namespace Nawasara\Hibah\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nawasara\Hibah\Models\ApprovedProposal;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menggerbang rute dengan permission yang diturunkan dari segmen {purpose}.
 *
 * Permission-nya berbeda per menu — `hibah.hibah.view`, `hibah.bansos.view`,
 * `hibah.bantuan-keuangan.view` — jadi tidak dapat ditulis statis di berkas
 * rute seperti `PermissionMiddleware::using(...)` yang biasa.
 *
 * ⚠️ **Gerbang ini saja TIDAK cukup.** Ia menghentikan orang membuka URL
 * peruntukan lain, tetapi laporan, pencarian, dan ekspor tetap dapat
 * memunculkan barisnya. Penyaringan sesungguhnya terjadi di query
 * (`->where('purpose', ...)`) — sama seperti pola ScopedToOpd yang sudah
 * dipakai paket ini. Keduanya, bukan salah satu.
 */
class EnsurePurposePermission
{
    public function handle(Request $request, Closure $next, string $action = 'view'): Response
    {
        $segment = (string) $request->route('purpose');

        // Segmen sudah dibatasi whereIn di berkas rute, jadi sampai di sini
        // nilainya pasti dikenal. Pemeriksaan ini menjaga kalau kelak ada
        // yang menambah rute tanpa batasan itu.
        if (ApprovedProposal::purposeFromSegment($segment) === null) {
            abort(404);
        }

        $permission = "hibah.{$segment}.{$action}";

        abort_unless($request->user()?->can($permission), 403);

        return $next($request);
    }
}
