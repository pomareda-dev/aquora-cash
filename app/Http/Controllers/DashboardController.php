<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Debt;
use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with monthly financial overview.
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $month = $request->query('month');
        $selectedMonth = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m', $month)
            : Carbon::now();

        $includeSandbox = (bool) $request->boolean('include_sandbox');

        $monthStart = $selectedMonth->copy()->startOfMonth()->toDateString();
        $monthEnd = $selectedMonth->copy()->endOfMonth()->toDateString();
        $today = Carbon::now()->toDateString();

        // ─── Card: Real balance up to today ───
        $realBalance = (float) Movement::realBalance($userId);

        // ─── Card: Income this month (real) ───
        $monthIncome = (float) Movement::where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('amount', '>', 0)
            ->where('is_projected', false)
            ->sum('amount');

        // ─── Card: Expense this month (real, absolute value) ───
        $monthExpenseRaw = (float) Movement::where('user_id', $userId)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('amount', '<', 0)
            ->where('is_projected', false)
            ->sum('amount');

        $monthExpense = abs($monthExpenseRaw);

        // ─── Card: Projected end of month ───
        $futureSum = (float) Movement::where('user_id', $userId)
            ->where('date', '>', $today)
            ->where('date', '<=', $monthEnd)
            ->sum('amount');

        $projectedEndOfMonth = $realBalance + $futureSum;

        // ─── Budget overview: top 5 expense categories with limit ───
        $categoriesWithLimit = Category::where('user_id', $userId)
            ->where('kind', 'expense')
            ->whereNotNull('monthly_limit')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Spent = net spending for expense categories (abs of negative net, 0 otherwise).
        // Using SUM(amount) instead of SUM(ABS(amount)) so refunds/income in the
        // same category properly offset spending for budget progress.
        $spentByCategory = Movement::where('user_id', $userId)
            ->whereIn('category_id', $categoriesWithLimit->pluck('id'))
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->where('date', '<=', $today)
            ->where('is_projected', false)
            ->groupBy('category_id')
            ->selectRaw('category_id, SUM(amount) as net')
            ->pluck('net', 'category_id');

        $budgetOverview = $categoriesWithLimit
            ->map(fn (Category $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
                'monthly_limit' => (float) $cat->monthly_limit,
                'spent' => max(0, -(float) ($spentByCategory->get($cat->id) ?? 0)),
            ])
            ->sortByDesc('spent')
            ->take(5)
            ->values()
            ->all();

        // ─── Mini reconciliation ───
        $totalAccounts = (float) Account::where('user_id', $userId)
            ->where('exclude_from_reconciliation', false)
            ->sum('balance');

        $difference = round($totalAccounts - $realBalance, 2);
        $reconciled = abs($difference) <= 0.01;

        // ─── Upcoming projected movements (next 7 days) ───
        $nextWeekEnd = Carbon::now()->addDays(7)->toDateString();

        $upcoming = Movement::where('user_id', $userId)
            ->where('date', '>', $today)
            ->where('date', '<=', $nextWeekEnd)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('category')
            ->get()
            ->map(fn (Movement $movement) => [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_name' => $movement->category?->name,
                'amount' => (float) $movement->amount,
                'is_projected' => (bool) $movement->is_projected,
            ]);

        // ─── Chart: daily running balance for the selected month ───
        $openingBalance = (float) Movement::openingBalance(
            $selectedMonth->copy()->startOfMonth(),
            $userId
        );

        $monthMovements = Movement::forMonth($selectedMonth)
            ->where('user_id', $userId)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // Build daily accumulation map
        $dailyAmounts = [];
        foreach ($monthMovements as $movement) {
            $dateStr = $movement->date->toDateString();
            $dailyAmounts[$dateStr] = ($dailyAmounts[$dateStr] ?? 0) + (float) $movement->amount;
        }

        $totalDays = (int) $selectedMonth->copy()->endOfMonth()->format('d');
        $running = $openingBalance;
        $dailyBalances = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $dateStr = $selectedMonth->copy()->startOfMonth()->addDays($day - 1)->toDateString();
            if (isset($dailyAmounts[$dateStr])) {
                $running += $dailyAmounts[$dateStr];
            }
            $dailyBalances[] = [
                'date' => $dateStr,
                'balance' => round($running, 2),
            ];
        }

        // ─── Active debts summary (for the debts card) ───
        $activeDebts = Debt::where('user_id', $userId)
            ->whereNull('closed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $debtsOverview = $activeDebts
            ->map(function (Debt $debt) use ($today): array {
                $nextInstallment = collect($debt->payment_dates)
                    ->filter(fn (string $date): bool => $date > $today)
                    ->sort()
                    ->first();

                return [
                    'id' => $debt->id,
                    'name' => $debt->name,
                    'remaining' => (float) $debt->remaining,
                    'paid_installments' => $debt->paid_installments,
                    'installments_count' => $debt->installments_count,
                    'rate_factor' => $debt->rate_factor,
                    'next_date' => $nextInstallment,
                    'next_amount' => (float) $debt->installment_amount,
                ];
            })
            ->values()
            ->all();

        // ─── Active goals summary (for the goals card) ───
        $apartado = Goal::apartadoAmount($userId);

        $activeGoals = Goal::where('user_id', $userId)
            ->whereNull('completed_at')
            ->withSum('contributions', 'amount')
            ->orderBy('created_at', 'desc')
            ->get();

        $goalsOverview = $activeGoals
            ->map(function (Goal $goal): array {
                return [
                    'id' => $goal->id,
                    'name' => $goal->name,
                    'target_amount' => (float) $goal->target_amount,
                    'progress_amount' => (float) $goal->progress_amount,
                    'percent' => $goal->percent,
                    'remaining_amount' => (float) $goal->remaining_amount,
                    'target_date' => $goal->target_date?->toDateString(),
                    'days_to_target' => $goal->target_date
                        ? now()->startOfDay()->diffInDays($goal->target_date->copy()->startOfDay(), false)
                        : null,
                ];
            })
            ->values()
            ->all();

        $goalsSummary = [
            'apartado' => $apartado,
            'available_real' => round($realBalance - $apartado, 2),
            'active_count' => $activeGoals->count(),
        ];

        // ─── Simulated values (only when includeSandbox) ───
        $simulatedCards = null;
        $chartDataSimulated = null;
        $simulatedBudgetOverview = null;
        $simulatedDebtsOverview = null;
        $simulatedGoalsOverview = null;
        $simulatedGoalsSummary = null;
        $simulatedUpcoming = null;
        $differenceWithSandbox = null;

        if ($includeSandbox) {
            // Cards with sandbox
            $simRealBalance = (float) Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('date', '<=', $today)
                ->where('is_projected', false)
                ->sum('amount');

            $simMonthIncome = (float) Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('date', '<=', $today)
                ->where('amount', '>', 0)
                ->where('is_projected', false)
                ->sum('amount');

            $simMonthExpense = abs((float) Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('date', '<=', $today)
                ->where('amount', '<', 0)
                ->where('is_projected', false)
                ->sum('amount'));

            $simFutureSum = (float) Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('date', '>', $today)
                ->where('date', '<=', $monthEnd)
                ->sum('amount');

            $simProjectedEndOfMonth = $simRealBalance + $simFutureSum;

            $simulatedCards = [
                'realBalance' => $simRealBalance,
                'monthIncome' => $simMonthIncome,
                'monthExpense' => $simMonthExpense,
                'projectedEndOfMonth' => $simProjectedEndOfMonth,
            ];

            // Chart: simulated daily balances (real + sandbox)
            $allMonthMovements = Movement::withoutSandboxScope()
                ->forMonth($selectedMonth)
                ->where('user_id', $userId)
                ->orderBy('date')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $simDailyAmounts = [];
            foreach ($allMonthMovements as $movement) {
                $dateStr = $movement->date->toDateString();
                $simDailyAmounts[$dateStr] = ($simDailyAmounts[$dateStr] ?? 0) + (float) $movement->amount;
            }

            $simOpeningBalance = (float) Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('date', '<', $monthStart)
                ->where('is_projected', false)
                ->sum('amount');

            $totalDays = (int) $selectedMonth->copy()->endOfMonth()->format('d');
            $simRunning = $simOpeningBalance;
            $chartDataSimulated = [];

            for ($day = 1; $day <= $totalDays; $day++) {
                $dateStr = $selectedMonth->copy()->startOfMonth()->addDays($day - 1)->toDateString();
                if (isset($simDailyAmounts[$dateStr])) {
                    $simRunning += $simDailyAmounts[$dateStr];
                }
                $chartDataSimulated[] = [
                    'date' => $dateStr,
                    'balance' => round($simRunning, 2),
                ];
            }

            // Debts: include sandbox debts
            $sandboxDebts = Debt::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('is_sandbox', true)
                ->whereNull('closed_at')
                ->get();

            $simulatedDebtsOverview = $sandboxDebts
                ->map(function (Debt $debt) use ($today): array {
                    $nextInstallment = collect($debt->payment_dates)
                        ->filter(fn (string $date): bool => $date > $today)
                        ->sort()
                        ->first();

                    return [
                        'id' => $debt->id,
                        'name' => $debt->name,
                        'remaining' => (float) $debt->remaining,
                        'paid_installments' => $debt->paid_installments,
                        'installments_count' => $debt->installments_count,
                        'rate_factor' => $debt->rate_factor,
                        'next_date' => $nextInstallment,
                        'next_amount' => (float) $debt->installment_amount,
                        'is_sandbox' => true,
                    ];
                })
                ->values()
                ->all();

            // Goals: add simulated_amount per goal
            $simulatedGoalsOverview = array_map(function (array $goal): array {
                $simAmount = (float) GoalContribution::withoutSandboxScope()
                    ->where('goal_id', $goal['id'])
                    ->where('is_sandbox', true)
                    ->sum('amount');

                $goal['simulated_amount'] = $simAmount;

                return $goal;
            }, $goalsOverview);

            $simApartado = (float) GoalContribution::withoutSandboxScope()
                ->where('is_sandbox', true)
                ->whereHas('goal', function ($query) use ($userId): void {
                    $query->where('user_id', $userId)
                        ->whereNull('completed_at');
                })
                ->sum('amount');

            $simulatedGoalsSummary = [
                'apartado' => $goalsSummary['apartado'],
                'available_real' => $goalsSummary['available_real'],
                'active_count' => $goalsSummary['active_count'],
                'apartado_with_sandbox' => round($goalsSummary['apartado'] + $simApartado, 2),
            ];

            // Budget: include sandbox spending
            $simSpentByCategory = Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->whereIn('category_id', $categoriesWithLimit->pluck('id'))
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->where('date', '<=', $today)
                ->where('is_projected', false)
                ->groupBy('category_id')
                ->selectRaw('category_id, SUM(amount) as net')
                ->pluck('net', 'category_id');

            $simulatedBudgetOverview = $categoriesWithLimit
                ->map(fn (Category $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'color' => $cat->color,
                    'monthly_limit' => (float) $cat->monthly_limit,
                    'spent' => max(0, -(float) ($simSpentByCategory->get($cat->id) ?? 0)),
                ])
                ->sortByDesc('spent')
                ->take(5)
                ->values()
                ->all();

            // Upcoming: include sandbox
            $simUpcoming = Movement::withoutSandboxScope()
                ->where('user_id', $userId)
                ->where('date', '>', $today)
                ->where('date', '<=', $nextWeekEnd)
                ->orderBy('date')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with('category')
                ->get()
                ->map(fn (Movement $movement) => [
                    'id' => $movement->id,
                    'date' => $movement->date->toDateString(),
                    'description' => $movement->description,
                    'category_name' => $movement->category?->name,
                    'amount' => (float) $movement->amount,
                    'is_projected' => (bool) $movement->is_projected,
                    'is_sandbox' => (bool) $movement->is_sandbox,
                ]);

            $simulatedUpcoming = $simUpcoming->values()->all();

            // Reconciliation: difference with sandbox
            $differenceWithSandbox = round($totalAccounts - $simRealBalance, 2);
        }

        return Inertia::render('Dashboard', [
            'cards' => [
                'realBalance' => $realBalance,
                'monthIncome' => $monthIncome,
                'monthExpense' => $monthExpense,
                'projectedEndOfMonth' => $projectedEndOfMonth,
            ],
            'budgetOverview' => $budgetOverview,
            'reconciliation' => [
                'totalAccounts' => $totalAccounts,
                'realBalance' => $realBalance,
                'difference' => $difference,
                'reconciled' => $reconciled,
            ],
            'debtsOverview' => $debtsOverview,
            'goalsOverview' => $goalsOverview,
            'goalsSummary' => $goalsSummary,
            'upcomingProjections' => $upcoming->values()->all(),
            'chartData' => $dailyBalances,
            'selectedMonth' => $selectedMonth->format('Y-m'),
            'currentMonth' => Carbon::now()->format('Y-m'),
            'includeSandbox' => $includeSandbox,
            'simulatedCards' => $simulatedCards,
            'chartDataSimulated' => $chartDataSimulated,
            'simulatedBudgetOverview' => $simulatedBudgetOverview,
            'simulatedDebtsOverview' => $simulatedDebtsOverview,
            'simulatedGoalsOverview' => $simulatedGoalsOverview,
            'simulatedGoalsSummary' => $simulatedGoalsSummary,
            'simulatedUpcoming' => $simulatedUpcoming,
            'differenceWithSandbox' => $differenceWithSandbox,
        ]);
    }
}
