<?php

namespace App\Http\Controllers;

use App\Services\SandboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SandboxController extends Controller
{
    /**
     * Discard all sandbox rows for the authenticated user.
     */
    public function revert(Request $request): RedirectResponse
    {
        SandboxService::revert($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Escenario de simulación revertido correctamente.',
        ]);

        return back();
    }

    /**
     * Promote all sandbox rows to real (permanent) state.
     */
    public function commit(Request $request): RedirectResponse
    {
        SandboxService::commit($request->user()->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Escenario guardado como real correctamente.',
        ]);

        return back();
    }
}
