<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Builder;

class SandboxService
{
    /**
     * Whether any sandbox rows exist for the given user across all 4 entities.
     */
    public static function hasSandboxRows(int $userId): bool
    {
        return Movement::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
            || RecurringTransaction::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
            || Debt::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
            || GoalContribution::withoutSandboxScope()
                ->where('is_sandbox', true)
                ->whereHas('goal', fn (Builder $q) => $q->where('user_id', $userId))
                ->exists();
    }
}
