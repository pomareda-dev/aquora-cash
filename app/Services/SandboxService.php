<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use App\Models\RecurringTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    /**
     * Delete all sandbox rows for the user (children → parents).
     */
    public static function revert(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            // 1. Delete sandbox movements (manual, debt movements, projected)
            Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->delete();

            // 2. Delete sandbox goal contributions for this user's goals
            GoalContribution::withoutSandboxScope()
                ->where('is_sandbox', true)
                ->whereHas('goal', fn (Builder $q) => $q->where('user_id', $userId))
                ->delete();

            // 3. Delete sandbox debts
            Debt::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->delete();

            // 4. Delete sandbox recurring templates
            RecurringTransaction::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->delete();
        });
    }

    /**
     * Promote all sandbox rows to real (permanent) state.
     */
    public static function commit(int $userId): void
    {
        if (! self::hasSandboxRows($userId)) {
            abort(422, 'No hay un escenario de simulación activo.');
        }

        DB::transaction(function () use ($userId): void {
            // 1. Delete sandbox projected movements (will be regenerated from real templates)
            Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->where('source', 'recurring')
                ->where('is_projected', true)
                ->delete();

            // 2. Promote sandbox manual movements to real
            Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->where('source', 'manual')
                ->update(['is_sandbox' => false]);

            // 3. Promote sandbox recurring templates to real
            RecurringTransaction::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->update(['is_sandbox' => false]);

            // 4. Promote sandbox debts to real
            Debt::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->update(['is_sandbox' => false]);

            // 5. Promote past goal contributions; delete future ones
            $goalIds = GoalContribution::withoutSandboxScope()
                ->where('is_sandbox', true)
                ->whereHas('goal', fn (Builder $q) => $q->where('user_id', $userId))
                ->pluck('goal_id')
                ->unique();

            if ($goalIds->isNotEmpty()) {
                // Promote contributions with date <= today
                GoalContribution::withoutSandboxScope()
                    ->where('is_sandbox', true)
                    ->whereIn('goal_id', $goalIds)
                    ->where('date', '<=', now()->toDateString())
                    ->update(['is_sandbox' => false]);

                // Delete future sandbox contributions
                GoalContribution::withoutSandboxScope()
                    ->where('is_sandbox', true)
                    ->whereIn('goal_id', $goalIds)
                    ->delete();

                // 6. Sync completion for each affected goal
                foreach ($goalIds as $goalId) {
                    Goal::find($goalId)?->syncCompletion();
                }
            }

            // 7. Regenerate real projections from all active real templates
            $user = User::find($userId);
            $horizon = $user->settings['projection_horizon'] ?? ProjectionService::DEFAULT_HORIZON_MONTHS;
            app(ProjectionService::class)->regenerateForUser($userId, $horizon);
        });
    }
}
