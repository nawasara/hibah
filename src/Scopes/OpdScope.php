<?php

namespace Nawasara\Hibah\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Nawasara\Hibah\Models\Operator;

/**
 * Restricts hibah queries to the authenticated operator's OPD.
 *
 * Rules:
 *   - No authenticated user (console, queue, tests) → no restriction.
 *   - User with `hibah.pengajuan.view` but NO operator row → admin-hibah,
 *     sees everything (bypass).
 *   - User WITH an operator row → locked to that opd_id.
 *
 * This is the single enforcement point for per-OPD data isolation. It runs
 * on every query against models that apply it, so a forgotten where-clause
 * in a Livewire component can't leak another OPD's data.
 *
 * Bypass deliberately when needed (e.g. cross-OPD duplicate report for
 * admins) via Model::withoutGlobalScope(OpdScope::class) — and gate that
 * call behind an admin permission check.
 */
class OpdScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $operator = Operator::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('aktif', true)
            ->first();

        // Admin-hibah (no operator row) sees all OPD.
        if (! $operator) {
            return;
        }

        $builder->where($model->getTable().'.opd_id', $operator->opd_id);
    }
}
