<?php

namespace App\Http\Controllers;

use App\Models\Movement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProjectionController extends Controller
{
    /**
     * Display the future projection timeline.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $userId = $user->id;
        $today = Carbon::now()->toDateString();

        // Whitelisted per-page values; fall back to 25.
        $perPage = $request->integer('per_page', 25);
        if (! in_array($perPage, [1, 10, 25, 50, 100], true)) {
            $perPage = 25;
        }

        $includeSandbox = (bool) $request->boolean('include_sandbox');

        $baseQuery = $includeSandbox
            ? Movement::withoutSandboxScope()
            : Movement::query();

        $query = $baseQuery->where('user_id', $userId)
            ->where('date', '>', $today)
            ->orderBy('date')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with('category');

        $paginator = (clone $query)->paginate($perPage)->withQueryString();

        $realBalance = (float) Movement::realBalance($userId);

        // Running balance carried INTO this page: real balance + all future
        // movements that fall before this page's first item.
        $carry = $realBalance;
        $offset = $paginator->firstItem() ? $paginator->firstItem() - 1 : 0;

        if ($offset > 0) {
            $carry += (clone $query)
                ->limit($offset)
                ->pluck('amount')
                ->map(fn (string $amount) => (float) $amount)
                ->sum();
        }

        $items = collect($paginator->items())->map(function (Movement $movement) use (&$carry) {
            $carry += (float) $movement->amount;

            return [
                'id' => $movement->id,
                'date' => $movement->date->toDateString(),
                'description' => $movement->description,
                'category_id' => $movement->category_id,
                'category_name' => $movement->category?->name,
                'category_color' => $movement->category?->color,
                'amount' => (float) $movement->amount,
                'source' => $movement->source,
                'is_projected' => (bool) $movement->is_projected,
                'is_sandbox' => (bool) $movement->is_sandbox,
                'running_balance' => $carry,
            ];
        });

        return Inertia::render('Proyeccion/Index', [
            'items' => $items->values()->all(),
            'openingBalance' => $realBalance,
            'horizonMonths' => $user->settings['projection_horizon'] ?? 12,
            'includeSandbox' => $includeSandbox,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
