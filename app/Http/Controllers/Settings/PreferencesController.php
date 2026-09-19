<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PreferencesController extends Controller
{
    /**
     * Render the preferences page with the user's categories.
     */
    public function edit(Request $request): InertiaResponse
    {
        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'kind' => $category->kind,
                'color' => $category->color,
            ]);

        return Inertia::render('settings/Preferences', [
            'categories' => $categories,
        ]);
    }

    /**
     * Update the user's settings (density, start section, etc.).
     */
    public function update(UpdateSettingsRequest $request): Response
    {
        $user = $request->user();
        $validated = $request->validated();

        $settings = array_merge($user->settings ?? [], $validated);
        $user->settings = $settings;
        $user->save();

        return response()->noContent();
    }
}
