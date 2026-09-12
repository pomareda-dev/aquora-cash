<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\GoalContributionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProjectionController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\SandboxDebtController;
use App\Http\Controllers\SandboxGoalContributionController;
use App\Http\Controllers\SandboxMovementController;
use App\Http\Controllers\SandboxRecurringController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('movimientos', [MovementController::class, 'index'])->name('movimientos.index');
    Route::post('movimientos', [MovementController::class, 'store'])->name('movimientos.store');
    Route::patch('movimientos/reorder', [MovementController::class, 'reorder'])->name('movimientos.reorder');
    Route::put('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.update');
    Route::patch('movimientos/{movement}', [MovementController::class, 'update'])->name('movimientos.patch');
    Route::delete('movimientos/{movement}', [MovementController::class, 'destroy'])->name('movimientos.destroy');

    Route::get('categorias', [CategoryController::class, 'index'])->name('categorias.index');
    Route::post('categorias', [CategoryController::class, 'store'])->name('categorias.store');
    Route::patch('categorias/reorder', [CategoryController::class, 'reorder'])->name('categorias.reorder');
    Route::put('categorias/{category}', [CategoryController::class, 'update'])->name('categorias.update');
    Route::patch('categorias/{category}', [CategoryController::class, 'update'])->name('categorias.patch');
    Route::delete('categorias/{category}', [CategoryController::class, 'destroy'])->name('categorias.destroy');
    Route::get('cuentas', [AccountController::class, 'index'])->name('cuentas.index');
    Route::post('cuentas', [AccountController::class, 'store'])->name('cuentas.store');
    Route::patch('cuentas/reorder', [AccountController::class, 'reorder'])->name('cuentas.reorder');
    Route::put('cuentas/{account}', [AccountController::class, 'update'])->name('cuentas.update');
    Route::patch('cuentas/{account}', [AccountController::class, 'update'])->name('cuentas.patch');
    Route::delete('cuentas/{account}', [AccountController::class, 'destroy'])->name('cuentas.destroy');

    // Recurring transactions
    Route::get('recurrentes', [RecurringTransactionController::class, 'index'])->name('recurrentes.index');
    Route::post('recurrentes', [RecurringTransactionController::class, 'store'])->name('recurrentes.store');
    Route::put('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurrentes.update');
    Route::patch('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'update'])->name('recurrentes.patch');
    Route::delete('recurrentes/{recurringTransaction}', [RecurringTransactionController::class, 'destroy'])->name('recurrentes.destroy');
    Route::post('recurrentes/regenerate', [RecurringTransactionController::class, 'regenerate'])->name('recurrentes.regenerate');

    // Debts
    Route::resource('deudas', DebtController::class)
        ->except(['edit', 'create'])
        ->parameters(['deudas' => 'debt']);
    Route::post('deudas/{debt}/payoff', [DebtController::class, 'payoff'])->name('deudas.payoff');

    // Goals
    Route::resource('metas', GoalController::class)
        ->except(['create', 'edit', 'show'])
        ->parameters(['metas' => 'goal']);
    Route::post('metas/{goal}/aportes', [GoalContributionController::class, 'store'])
        ->name('metas.aportes.store');
    Route::delete('metas/{goal}/aportes/{contribution}', [GoalContributionController::class, 'destroy'])
        ->name('metas.aportes.destroy');

    // Projection view
    Route::get('proyeccion', [ProjectionController::class, 'index'])->name('proyeccion.index');

    // Sandbox — movements
    Route::post('simulacion/movimientos', [SandboxMovementController::class, 'store'])->name('simulacion.movimientos.store');
    Route::put('simulacion/movimientos/{id}', [SandboxMovementController::class, 'update'])->name('simulacion.movimientos.update');
    Route::patch('simulacion/movimientos/{id}', [SandboxMovementController::class, 'update'])->name('simulacion.movimientos.patch');
    Route::delete('simulacion/movimientos/{id}', [SandboxMovementController::class, 'destroy'])->name('simulacion.movimientos.destroy');

    // Sandbox — recurring
    Route::post('simulacion/recurrentes', [SandboxRecurringController::class, 'store'])->name('simulacion.recurrentes.store');
    Route::put('simulacion/recurrentes/{id}', [SandboxRecurringController::class, 'update'])->name('simulacion.recurrentes.update');
    Route::patch('simulacion/recurrentes/{id}', [SandboxRecurringController::class, 'update'])->name('simulacion.recurrentes.patch');
    Route::delete('simulacion/recurrentes/{id}', [SandboxRecurringController::class, 'destroy'])->name('simulacion.recurrentes.destroy');
    Route::post('simulacion/recurrentes/regenerate', [SandboxRecurringController::class, 'regenerate'])->name('simulacion.recurrentes.regenerate');

    // Sandbox — debts
    Route::post('simulacion/deudas', [SandboxDebtController::class, 'store'])->name('simulacion.deudas.store');
    Route::put('simulacion/deudas/{id}', [SandboxDebtController::class, 'update'])->name('simulacion.deudas.update');
    Route::patch('simulacion/deudas/{id}', [SandboxDebtController::class, 'update'])->name('simulacion.deudas.patch');
    Route::delete('simulacion/deudas/{id}', [SandboxDebtController::class, 'destroy'])->name('simulacion.deudas.destroy');
    Route::post('simulacion/deudas/{id}/payoff', [SandboxDebtController::class, 'payoff'])->name('simulacion.deudas.payoff');

    // Sandbox — goal contributions (the goal is real; the contribution is sandbox)
    Route::post('simulacion/metas/{goal}/aportes', [SandboxGoalContributionController::class, 'store'])->name('simulacion.metas.aportes.store');
    Route::delete('simulacion/metas/{goal}/aportes/{contribution}', [SandboxGoalContributionController::class, 'destroy'])->name('simulacion.metas.aportes.destroy');
});

require __DIR__.'/settings.php';
