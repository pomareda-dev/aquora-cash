<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\RecurringTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ProjectionService
{
    /**
     * Default horizon in months when no end_month is set.
     */
    public const DEFAULT_HORIZON_MONTHS = 12;

    /**
     * Generate projected movements for all active recurring templates of a user.
     * Skips dates already having a movement for the same (recurring_id, date) pair.
     * Only generates FUTURE movements (date > today).
     *
     * @return int Number of movements generated.
     */
    public function generateForUser(int $userId, ?int $horizonMonths = null): int
    {
        $horizonMonths ??= self::DEFAULT_HORIZON_MONTHS;
        $today = Carbon::now()->startOfDay();
        $templates = RecurringTransaction::where('user_id', $userId)
            ->where('active', true)
            ->get();

        $generated = 0;

        DB::transaction(function () use ($templates, $userId, $horizonMonths, $today, &$generated): void {
            foreach ($templates as $template) {
                $generated += $this->generateForTemplate($template, $userId, $horizonMonths, $today);
            }
        });

        return $generated;
    }

    /**
     * Delete all existing projected source=recurring movements for the user
     * and regenerate. Realized recurring movements (is_projected=false) are
     * preserved — they represent actual money movement and must never be lost.
     *
     * @return int Number of movements generated.
     */
    public function regenerateForUser(int $userId, ?int $horizonMonths = null): int
    {
        $horizonMonths ??= self::DEFAULT_HORIZON_MONTHS;

        $generated = 0;

        // Wrap delete + generate in ONE transaction so a generation failure
        // rolls back the delete too (nested savepoint semantics apply since
        // generateForUser opens its own DB::transaction).
        DB::transaction(function () use ($userId, $horizonMonths, &$generated): void {
            Movement::where('user_id', $userId)
                ->where('source', 'recurring')
                ->where('is_projected', true)
                ->delete();

            $generated = $this->generateForUser($userId, $horizonMonths);
        });

        return $generated;
    }

    /**
     * Generate sandbox projected movements for all active sandbox recurring
     * templates of a user.
     *
     * @return int Number of movements generated.
     */
    public function generateSandboxForUser(int $userId, ?int $horizonMonths = null): int
    {
        $horizonMonths ??= self::DEFAULT_HORIZON_MONTHS;
        $today = Carbon::now()->startOfDay();
        $templates = RecurringTransaction::withoutSandboxScope()
            ->where('user_id', $userId)
            ->where('is_sandbox', true)
            ->where('active', true)
            ->get();

        $generated = 0;

        DB::transaction(function () use ($templates, $userId, $horizonMonths, $today, &$generated): void {
            foreach ($templates as $template) {
                $generated += $this->generateForTemplate($template, $userId, $horizonMonths, $today, true);
            }
        });

        return $generated;
    }

    /**
     * Delete all existing sandbox projected source=recurring movements for
     * the user and regenerate from sandbox templates.
     *
     * @return int Number of movements generated.
     */
    public function regenerateSandboxForUser(int $userId, ?int $horizonMonths = null): int
    {
        $horizonMonths ??= self::DEFAULT_HORIZON_MONTHS;

        $generated = 0;

        DB::transaction(function () use ($userId, $horizonMonths, &$generated): void {
            Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->where('source', 'recurring')
                ->where('is_projected', true)
                ->delete();

            $generated = $this->generateSandboxForUser($userId, $horizonMonths);
        });

        return $generated;
    }

    /**
     * Generate movements for a single template.
     *
     * @param  bool  $isSandbox  When true, bypasses LiveScope for idempotent
     *                           checks and sort-order computation, and marks
     *                           the generated movement as sandbox.
     */
    private function generateForTemplate(
        RecurringTransaction $template,
        int $userId,
        int $horizonMonths,
        Carbon $today,
        bool $isSandbox = false,
    ): int {
        /** @var Carbon $startMonth */
        $startMonth = $template->start_month;
        $endMonth = $template->end_month ?? Carbon::now()->addMonthsNoOverflow($horizonMonths);
        $dayOfMonth = $template->day_of_month;
        $amount = $template->amount;
        $description = $template->name;
        $categoryId = $template->category_id;

        // Determine the effective start: max(today's month, template.start_month)
        $cursor = Carbon::parse($startMonth->format('Y-m-01'));
        $endCursor = Carbon::parse($endMonth->format('Y-m-01'));

        // Ensure we don't start before today's month
        $todayMonthStart = $today->copy()->startOfMonth();
        if ($cursor->lessThan($todayMonthStart)) {
            $cursor = $todayMonthStart;
        }

        $generated = 0;

        while ($cursor->lessThanOrEqualTo($endCursor)) {
            // Build date: day_of_month, clamped to month's last day
            $lastDayOfMonth = $cursor->copy()->endOfMonth()->day;
            $clampedDay = min($dayOfMonth, $lastDayOfMonth);
            $movementDate = Carbon::createFromDate(
                $cursor->year,
                $cursor->month,
                $clampedDay,
            )->startOfDay();

            // Only generate FUTURE movements (date > today)
            if ($movementDate->greaterThan($today)) {
                // Check for existing movement for this (recurring_id, date) — idempotent.
                // Sandbox templates must bypass LiveScope to see sandbox rows.
                $existingQuery = $isSandbox
                    ? Movement::withoutSandboxScope()
                    : Movement::query();

                $existing = $existingQuery
                    ->where('user_id', $userId)
                    ->where('recurring_id', $template->id)
                    ->whereDate('date', $movementDate->toDateString())
                    ->exists();

                if (! $existing) {
                    $sortOrder = $isSandbox
                        ? Movement::nextSandboxSortOrder(
                            $userId,
                            $movementDate->toDateString(),
                            true,
                        )
                        : Movement::nextSortOrder(
                            $userId,
                            $movementDate->toDateString(),
                            true,
                        );

                    $attributes = [
                        'user_id' => $userId,
                        'date' => $movementDate->toDateString(),
                        'description' => $description,
                        'category_id' => $categoryId,
                        'amount' => $amount,
                        'source' => 'recurring',
                        'recurring_id' => $template->id,
                        'is_projected' => true,
                        'sort_order' => $sortOrder,
                    ];

                    if ($isSandbox) {
                        $attributes['is_sandbox'] = true;
                    }

                    // forceCreate bypasses $fillable (user_id is intentionally
                    // not mass-assignable; the relationship create path is used
                    // by controllers, but this service only has the user id).
                    Movement::forceCreate($attributes);

                    $generated++;
                }
            }

            $cursor->addMonthNoOverflow();
        }

        return $generated;
    }
}
