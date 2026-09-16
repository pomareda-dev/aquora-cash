# Exploration: Driver.js Usage Guide

## Executive Summary

A `driver.js`-powered usage guide is feasible and would complement the existing empty-state patterns, but it requires coordinated changes across the Vue frontend, the user-settings persistence layer, and the test suite. The most robust shape is a **reusable tour composable plus per-page tour definitions**, mounted from the shared layout, with anchor attributes (`data-tour`) added to the key UI elements. The `User.settings` JSON column is the natural place to persist "tour seen" state; no new migration is needed.

## Current State

### Frontend Architecture

- **Entry point:** `resources/js/app.ts` calls `createInertiaApp()` with a layout resolver (`Welcome` → no layout, `auth/*` → `AuthLayout`, `settings/*` → `[AppLayout, SettingsLayout]`, default → `AppLayout`).
- **Shared authenticated layout:** `AppLayout.vue` → `AppSidebarLayout.vue`.
- **Global UI shell:** `AppSidebarLayout.vue` renders `AppSidebar`, `AppSidebarHeader`, `SimulationBanner`, page `<slot>`, and `<Toaster />`.
- **Pages:** all Inertia pages live under `resources/js/pages` (e.g., `Dashboard.vue`, `Movimientos/Index.vue`, `Cuentas/Index.vue`, `Categorias/Index.vue`, `Recurrentes/Index.vue`).
- **State/composables:** `useSettings.ts` reads/writes `auth.user.settings`; `useSandbox.ts` uses `localStorage` for simulation-mode state; `useAppearance.ts` uses `localStorage` for light/dark/system appearance.

A globally-mounted tour component should be wired into `AppSidebarLayout.vue` (or `AppLayout.vue`) so it is present on every authenticated page and can react to route changes.

### Navigation

- **Sidebar declaration:** `resources/js/components/AppSidebar.vue` defines `mainNavItems` using Wayfinder routes (`dashboard()`, `movimientos.index()`, `categorias.index()`, `cuentas.index()`, `recurrentes.index()`, etc.).
- **Sidebar rendering:** `resources/js/components/NavMain.vue` renders the items through shadcn/ui `SidebarMenuButton` components.
- **Header:** `resources/js/components/AppSidebarHeader.vue` contains the `SidebarTrigger`, breadcrumbs, and the Simulation-mode toggle button.
- **Routing:** Wayfinder generates typed route functions under `resources/js/routes/`; the Vite plugin is enabled in `vite.config.ts`.

### Tour Targets

The main user-facing surfaces and the elements a tour should highlight are:

| Page | Elements to highlight | Stable anchors today | Anchors needed |
|------|----------------------|----------------------|----------------|
| **Dashboard** | Month navigator, 4 metric cards, budget overview, mini reconciliation, active debts, goals, upcoming movements, balance chart | None (headings/sections are plain `<div>`/`<Card>`) | `data-tour` on header, month nav, each metric card, budget card, reconciliation card, debts card, goals card, upcoming card, chart card |
| **Movimientos** | Header, month navigator, "Nuevo movimiento" button, "Actuales" table, "Proyectados" table, monthly summary | None | `data-tour` on header, month nav, create button, both tables, summary row |
| **Cuentas** | Header, "Nueva cuenta" button, accounts table, reconciliation panel | None | `data-tour` on header, create button, table, reconciliation card |
| **Categorías** | Header, month navigator, "Nueva categoría" button, categories table with progress bars | None | `data-tour` on header, month nav, create button, table |
| **Recurrentes** | Header, "Regenerar proyecciones" button, "Nueva plantilla" button, templates table | None | `data-tour` on header, regenerate button, create button, table |
| **Shared** | Sidebar navigation, user menu, Simulation toggle | `data-test="sidebar-menu-button"` on user button only | `data-tour` on each nav item, logo, user menu, sandbox toggle |

Today the UI relies almost entirely on CSS classes and component wrappers; **stable `id` or `data-*` selectors must be added** before `driver.js` can reliably target elements across re-renders and theme changes.

### Existing Onboarding / Help / Empty-State Patterns

- **Empty states:** `ResponsiveTable.vue` already renders `<slot name="empty">`; every index page uses it with a "Crear la primera…" call-to-action button.
- **No existing tour or guided onboarding.**
- **Simulation mode:** has its own banner (`SimulationBanner.vue`) and toggle; the tour should explain this feature but not duplicate the banner.

The guide should complement the empty-state CTAs by explaining *why* each section matters, not just prompting creation.

### Dependencies

- `driver.js` is **not installed** (verified in `package.json` and lockfiles).
- **Package manager:** both `package-lock.json` and `pnpm-lock.yaml` are tracked; `pnpm` is installed and `node_modules/.pnpm` exists, so `pnpm` is the effective package manager. Adding `driver.js` should use `pnpm add driver.js` and both lockfiles must be kept in sync.
- **Overlay primitives:** shadcn/ui + reka-ui provide `Dialog`, `Sheet`, `DropdownMenu`, and `Tooltip`, all rendered through `<*Portal>` with `z-50` content. `driver.js` creates its own popover/overlay DOM nodes, so z-index and portal ordering will need explicit handling.

### Persistence of "Tour Already Seen"

- **User model:** `app/Models/User.php` has a nullable `settings` JSON column (cast to `array`) and is already fillable.
- **Settings endpoint:** `App/Http/Controllers/Settings/PreferencesController.php` merges validated settings into `users.settings` via `UpdateSettingsRequest.php`.
- **Frontend settings composable:** `useSettings.ts` exposes `settings` and `updateSettings(partial)` for typed updates.

The most natural persistence is a server-side flag in `users.settings`, e.g.:

```json
{
  "onboarding": {
    "completed_at": "2026-09-15T12:00:00Z",
    "version": 1
  }
}
```

A client-side `localStorage` fallback is possible but makes the feature invisible across devices/browsers and harder to reset centrally.

### Testing

- **Current test stack:** only PHP Pest tests exist (`tests/Feature/**/*.php`, `tests/Unit/**/*.php`). No Vue component tests, no Vitest config, no `.spec.ts`/`.test.ts` files.
- **Strict TDD implication:** because the feature is UI-heavy, tests should cover:
  1. The backend settings endpoint accepts and returns the onboarding flag (`PreferencesTest.php` can be extended).
  2. Optionally, a minimal JavaScript test harness could be added, but that is a larger toolchain decision. The project currently verifies the frontend only through `npm run build` and `vue-tsc`.

A pragmatic path is to extend the existing PHP feature tests for the settings persistence and treat the Vue/driver integration as verified by the build/type-check pipeline until a JS test harness is adopted.

## Approaches

### Option A — Single Global Tour with Cross-Page Navigation

A single `driver.js` instance is created in `AppSidebarLayout.vue`. It defines every step for every page and uses `router.visit()` between pages.

- **Pros:** One file, one tour definition, easy to trigger from any page.
- **Cons:** Inertia page swaps destroy/reattach DOM nodes; driver must be paused/restarted on navigation, step state is fragile, and the file becomes large.
- **Effort:** High.

### Option B — Per-Page Tours with a Reusable Composable

Create `useTour()` composable that wraps `driver.js` with page-aware step registration. Each page component declares its own steps via a `provide`/`inject` pattern or a page-level tour config, and a global `TourLauncher` in `AppSidebarLayout.vue` starts the tour for the current page.

- **Pros:** Steps live next to the components they describe, easier to maintain, no cross-page state juggling, simpler SSR guards.
- **Cons:** More files, need a convention for registering page steps.
- **Effort:** Medium.

### Option C — Hybrid: Global Launcher + Page Tour Hooks

Create a `TourProvider` in the layout and a `usePageTour(steps)` composable that each page calls in `onMounted()`. The provider checks `users.settings.onboarding` and starts the tour once per session, passing the current page's steps to `driver.js`.

- **Pros:** Clean separation, reusable, supports multi-page tours later by chaining hooks, respects page lifecycle.
- **Cons:** Slightly more initial scaffolding than Option A.
- **Effort:** Medium.

## Recommendation

**Option C (hybrid global launcher + page-level `usePageTour` hook)** is recommended. It keeps tour steps co-located with each page, avoids fragile cross-page navigation inside `driver.js`, and provides a clear path to multi-page guided flows later. The launcher should read the onboarding flag from `users.settings` and persist completion via `useSettings().updateSettings()`.

## Risks

1. **SSR / hydration:** `driver.js` is browser-only. The tour must be initialized inside `onMounted()` and guarded with `typeof window !== 'undefined'` to avoid SSR crashes.
2. **Dark mode:** The app toggles the `dark` class on `<html>` and applies theme palette classes (`theme-*`). `driver.js` popovers must be styled against `.dark` and the current theme tokens, or they will render with inconsistent colors.
3. **z-index / portal conflicts:** reka-ui overlays (Dialog, Sheet, DropdownMenu) render in portals with `z-50`. The driver overlay/popover needs a `z-index` above `50` or must close/avoid overlapping open overlays.
4. **Accessibility:** `driver.js` provides basic keyboard navigation, but focus management, reduced-motion (`prefers-reduced-motion`), and screen-reader announcements need explicit verification. The app already supports keyboard shortcuts (`useKeyboardShortcuts.ts`) — the tour should not hijack them unexpectedly.
5. **i18n:** There is no translation layer; all UI strings are hardcoded in Spanish. The tour content must be authored in Spanish to match the rest of the app.
6. **Mobile sidebar:** On mobile the sidebar renders as a `Sheet`. Tour steps targeting sidebar items must wait for the sidebar to be open or avoid mobile sidebar anchors.
7. **Lockfile drift:** Both `package-lock.json` and `pnpm-lock.yaml` are tracked. Installing `driver.js` with one tool and not the other will create inconsistent lockfiles.
8. **Strict TDD with no JS tests:** There is no established JavaScript test harness. The orchestrator should decide whether to add Vitest for the composable or rely on PHP feature tests + build verification.

## Open Questions for the Orchestrator / User

1. Should the tour run **automatically on first login** only, or also be manually restartable from a "Help" button?
2. Is the guide **single-page only** (dashboard walkthrough) or **multi-page** (dashboard → movimientos → cuentas → categorías → recurrentes)?
3. Should completion be stored **server-side** in `users.settings` or **client-side** in `localStorage`?
4. Should the tour include the **Simulation mode** feature as a highlighted step?
5. Do we want to add a **JavaScript test harness** (Vitest + Vue Test Utils) for the tour composable, or keep verification limited to PHP tests and the build pipeline?

## Affected Files (High-Level)

- `resources/js/app.ts` — possible plugin/composable registration.
- `resources/js/layouts/app/AppSidebarLayout.vue` — global `TourProvider`/`TourLauncher` mount point.
- `resources/js/components/AppSidebar.vue` and `NavMain.vue` — add `data-tour` anchors to navigation.
- `resources/js/components/AppSidebarHeader.vue` — add anchor to simulation toggle.
- `resources/js/pages/Dashboard.vue` — add anchors to cards and chart.
- `resources/js/pages/Movimientos/Index.vue`, `Cuentas/Index.vue`, `Categorias/Index.vue`, `Recurrentes/Index.vue` — add anchors and page-level tour steps.
- `resources/js/composables/useSettings.ts` — extend `UserSettings` type with onboarding flag.
- `app/Http/Requests/Settings/UpdateSettingsRequest.php` — allow the onboarding key (or keep it as an unvalidated nested object).
- `tests/Feature/Settings/PreferencesTest.php` — assert onboarding flag persistence.
- `package.json` + `pnpm-lock.yaml` (+ `package-lock.json`) — add `driver.js`.

## Ready for Proposal

**Yes**, with the open questions above resolved. The terrain is mapped, persistence is ready, and the integration point is clear. The next phase should produce a concrete proposal covering scope (single-page vs. multi-page), persistence choice, and testing strategy.
