# Design: Driver.js Usage Guide

## Technical Approach

Option C hybrid: a module-scoped `useTour()` singleton owns the driver.js instance and segment state; `TourLauncher.vue` (mounted once in `AppSidebarLayout.vue`) orchestrates cross-page sequencing; each page calls `usePageTour(segment)` in `onMounted()`. All 36 step definitions and Spanish copy live in `tourSteps.ts`. Anchors use `data-tour="<segment>.<element>"` selectors.

## Architecture Decisions

### Decision: Mobile shell segment — open the Sheet

| Option | Tradeoff | Decision |
|--------|----------|----------|
| **(a) Open Sheet programmatically** | Real anchors for steps 2–6; consistent UX; requires `setOpenMobile(true)` + close after segment | **Selected** |
| (b) Desktop-only nav + fallback step | Simpler; skips 5 steps on mobile; degraded experience | Rejected |

**Rationale:** `SidebarProvider.vue` exposes `useSidebar()` with `isMobile: Ref<boolean>`, `openMobile: Ref<boolean>`, and `setOpenMobile(value: boolean)`. The mobile `Sidebar.vue` renders a reka-ui `Sheet` controlled by `openMobile` (line 33). The tour launcher can call `setOpenMobile(true)` before the shell nav steps and `setOpenMobile(false)` after step 6. This gives real DOM anchors on mobile without modifying the Sidebar component. The existing `router.on('navigate')` handler in `AppSidebar.vue` already closes the Sheet on navigation, so the tour's `router.visit()` hand-off naturally cleans up.

**Mechanism:** In `TourLauncher.vue`, before emitting shell steps 2–6, check `isMobile.value`. If true: `setOpenMobile(true)`, wait for next tick (anchors render inside the Sheet portal), then highlight. After step 6: `setOpenMobile(false)`. Steps 1 (`shell.sidebar`), 7 (`shell.simulationToggle`), and 8 (`shell.help`) target elements that exist on both viewports.

### Decision: Step 36 target-less centered popover

| Option | Tradeoff | Decision |
|--------|----------|----------|
| **(a) driver.js modal (no `element`)** | Native support; clean; no extra DOM | **Selected** |
| (b) Invisible offscreen anchor | Works everywhere; extra DOM node; visual hack | Fallback only |

**Rationale:** driver.js v1.x makes `element` optional on step definitions. When omitted, the step renders as a **modal** — a centered popover with no highlight rectangle. This is confirmed by the migration guide ("element: Optional, if not provided, step will be shown as modal") and the `highlight()` API which accepts `popover` without `element`. Since driver.js is not yet installed, `sdd-apply` must verify this behavior immediately after install. If the installed version behaves differently, the fallback is a `<div data-tour="recurrentes.finale" class="fixed inset-0 pointer-events-none" />` mounted by the launcher.

## Data Flow

```
User logs in
    │
    ▼
AppSidebarLayout mounts
    │
    ▼
TourLauncher.onMounted()
    ├─ reads settings.onboarding from Inertia shared props
    ├─ if completed_at is null OR version < TOUR_VERSION → arm
    └─ if not on a tour page → router.visit(dashboard()) → arm on mount

    ┌──────────────────────────────────────────────────┐
    │  Tour armed → current segment = 'shell'          │
    │  usePageTour('dashboard') registers shell+dash   │
    │  Launcher highlights shell steps 1-8             │
    │  (mobile: setOpenMobile(true) for steps 2-6)     │
    │  After step 8 → segment = 'dashboard'            │
    │  Highlight dashboard steps 9-17                  │
    │  After step 17 → destroy driver                  │
    │  router.visit(movimientos.index())               │
    └──────────────────────────────────────────────────┘
    │
    ▼  (repeats per page)
    ┌──────────────────────────────────────────────────┐
    │  Movimientos mounts → usePageTour('movimientos') │
    │  Launcher resumes segment 2, steps 18-23         │
    │  → router.visit(cuentas.index())                 │
    │  Cuentas → steps 24-27 → router.visit(...)       │
    │  Categorías → steps 28-31 → router.visit(...)    │
    │  Recurrentes → steps 32-36                       │
    │  Step 36 (modal) → finish/dismiss                │
    │  → updateSettings({ onboarding: {...} })          │
    └──────────────────────────────────────────────────┘
```

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `resources/js/composables/useTour.ts` | Create | Singleton: `isActive`, `armed`, `currentSegment`, `stepIndex`, `start()`, `restart()`, `finish()`. Wraps driver.js instance. Lazy import in `onMounted`. |
| `resources/js/composables/usePageTour.ts` | Create | `usePageTour(segment)` — registers steps with launcher, asks it to run if armed. One-liner per page. |
| `resources/js/components/tour/TourLauncher.vue` | Create | Mounted in layout. Owns driver instance lifecycle, segment sequencing, `router.visit()` hand-off, mobile Sheet control, completion write. |
| `resources/js/components/tour/tourSteps.ts` | Create | All 36 step definitions: `{ anchor, title, description, side, precondition?, mobileSkip? }`. Single reviewable file. |
| `resources/js/layouts/app/AppSidebarLayout.vue` | Modify | Mount `<TourLauncher />` after `<Toaster />`. |
| `resources/js/components/AppSidebar.vue` | Modify | Add `data-tour="shell.sidebar"` on `<Sidebar>`. Add `data-tour` on nav items via `NavItem.tourId`. |
| `resources/js/components/NavMain.vue` | Modify | Bind `:data-tour="item.tourId"` on each `<SidebarMenuItem>`. |
| `resources/js/components/AppSidebarHeader.vue` | Modify | Add `data-tour="shell.simulationToggle"` on simulation button. Add Help button with `data-tour="shell.help"`. |
| `resources/js/components/NavUser.vue` | Modify | Add `data-tour="shell.userMenu"` on the trigger. |
| `resources/js/pages/Dashboard.vue` | Modify | 9 `data-tour` anchors + `usePageTour('dashboard')` + `isDialogOpen: () => isTourActive()`. |
| `resources/js/pages/Movimientos/Index.vue` | Modify | 6 anchors + `usePageTour('movimientos')` + `isDialogOpen` guard. |
| `resources/js/pages/Cuentas/Index.vue` | Modify | 4 anchors + `usePageTour('cuentas')`. |
| `resources/js/pages/Categorias/Index.vue` | Modify | 4 anchors + `usePageTour('categorias')` + `isDialogOpen` guard. |
| `resources/js/pages/Recurrentes/Index.vue` | Modify | 4 anchors + `usePageTour('recurrentes')`. |
| `resources/js/types/navigation.ts` | Modify | Add optional `tourId?: string` to `NavItem`. |
| `resources/js/composables/useSettings.ts` | Modify | Add `onboarding` to `UserSettings`, `defaults`, nested hydration. |
| `resources/css/app.css` | Modify | Driver.js CSS custom-property overrides + `.dark` + 8 themes. |
| `app/Http/Requests/Settings/UpdateSettingsRequest.php` | Modify | Add `onboarding` validation rules. |
| `tests/Feature/Settings/PreferencesTest.php` | Modify | 5 new Pest tests. |
| `package.json`, `pnpm-lock.yaml`, `package-lock.json` | Modify | `driver.js` dependency. |

## Interfaces / Contracts

### `useTour.ts` — Singleton State

```typescript
import type { Driver, DriveStep } from 'driver.js';

export type TourSegment = 'shell' | 'dashboard' | 'movimientos' | 'cuentas' | 'categorias' | 'recurrentes';

export interface TourState {
  isActive: boolean;        // driver is currently highlighting
  armed: boolean;           // tour should run (not yet completed)
  currentSegment: TourSegment | null;
  stepIndex: number;        // global step index (0-35)
}

export const TOUR_VERSION = 1;

// Module-scoped reactive singleton
export function useTour(): {
  isActive: Readonly<boolean>;
  armed: Readonly<boolean>;
  currentSegment: Readonly<TourSegment | null>;
  stepIndex: Readonly<number>;
  isTourActive: () => boolean;    // for useKeyboardShortcuts
  start: () => void;              // begin from current position
  restart: () => void;            // re-arm + navigate to dashboard + start
  finish: () => void;             // persist + cleanup
  advance: (segment: TourSegment) => void;  // internal: register + run segment
  setSteps: (segment: TourSegment, steps: DriveStep[]) => void;
  destroy: () => void;            // cleanup driver instance
}
```

### `usePageTour.ts` — Per-Page Hook

```typescript
import type { TourSegment } from './useTour';

/**
 * Call in onMounted() of each tour page.
 * Registers segment steps and triggers the launcher if armed.
 */
export function usePageTour(segment: TourSegment): void;
```

### `UserSettings` — Extended Interface

```typescript
export interface UserSettings {
  // ... existing keys unchanged ...
  onboarding: {
    completed_at: string | null;
    version: number;
  };
}
```

### `UpdateSettingsRequest` — Added Rules

```php
'onboarding' => ['nullable', 'array'],
'onboarding.completed_at' => ['nullable', 'date'],
'onboarding.version' => ['nullable', 'integer', 'min:1'],
```

## Anchor Inventory

### Shell segment (runs on Dashboard)

| Anchor | Component | Binding |
|--------|-----------|---------|
| `shell.sidebar` | `AppSidebar.vue` → `<Sidebar>` | `data-tour="shell.sidebar"` on the `<Sidebar>` root |
| `nav.movimientos` | `NavMain.vue` → `<SidebarMenuItem>` | `:data-tour="item.tourId"` where `tourId: 'nav.movimientos'` |
| `nav.cuentas` | `NavMain.vue` | `tourId: 'nav.cuentas'` |
| `nav.categorias` | `NavMain.vue` | `tourId: 'nav.categorias'` |
| `nav.recurrentes` | `NavMain.vue` | `tourId: 'nav.recurrentes'` |
| `shell.userMenu` | `NavUser.vue` | `data-tour="shell.userMenu"` on trigger element |
| `shell.simulationToggle` | `AppSidebarHeader.vue` | `data-tour="shell.simulationToggle"` on `<Button>` |
| `shell.help` | `AppSidebarHeader.vue` | `data-tour="shell.help"` on new Help `<Button>` |

### Dashboard segment

| Anchor | Target | Binding |
|--------|--------|---------|
| `dashboard.header` | Header `<div>` | `data-tour="dashboard.header"` |
| `dashboard.monthNav` | Month nav row | `data-tour="dashboard.monthNav"` |
| `dashboard.metrics` | 4-card grid wrapper | `data-tour="dashboard.metrics"` |
| `dashboard.budget` | Budget Card | `data-tour="dashboard.budget"` on `<Card>` root |
| `dashboard.reconciliation` | Reconciliation Card | `data-tour="dashboard.reconciliation"` |
| `dashboard.debts` | Debts Card | `data-tour="dashboard.debts"` |
| `dashboard.goals` | Goals Card | `data-tour="dashboard.goals"` |
| `dashboard.upcoming` | Upcoming Card | `data-tour="dashboard.upcoming"` |
| `dashboard.chart` | Chart Card | `data-tour="dashboard.chart"` |

### Movimientos segment

| Anchor | Target | Binding |
|--------|--------|---------|
| `movimientos.header` | Header `<div>` | `data-tour="movimientos.header"` |
| `movimientos.monthNav` | Month nav | `data-tour="movimientos.monthNav"` |
| `movimientos.create` | «Nuevo movimiento» button | `data-tour="movimientos.create"` |
| `movimientos.actuales` | «Actuales» Card | `data-tour="movimientos.actuales"` (conditional) |
| `movimientos.proyectados` | «Proyectados» Card | `data-tour="movimientos.proyectados"` (conditional) |
| `movimientos.summary` | Summary block | `data-tour="movimientos.summary"` (conditional) |

### Cuentas segment

| Anchor | Target | Binding |
|--------|--------|---------|
| `cuentas.header` | Header | `data-tour="cuentas.header"` |
| `cuentas.create` | «Nueva cuenta» button | `data-tour="cuentas.create"` |
| `cuentas.table` | ResponsiveTable | `data-tour="cuentas.table"` |
| `cuentas.reconciliation` | Reconciliation Card | `data-tour="cuentas.reconciliation"` |

### Categorías segment

| Anchor | Target | Binding |
|--------|--------|---------|
| `categorias.header` | Header | `data-tour="categorias.header"` |
| `categorias.monthNav` | Month nav | `data-tour="categorias.monthNav"` |
| `categorias.create` | «Nueva categoría» button | `data-tour="categorias.create"` |
| `categorias.table` | ResponsiveTable | `data-tour="categorias.table"` |

### Recurrentes segment

| Anchor | Target | Binding |
|--------|--------|---------|
| `recurrentes.header` | Header | `data-tour="recurrentes.header"` |
| `recurrentes.regenerate` | «Regenerar» button | `data-tour="recurrentes.regenerate"` |
| `recurrentes.create` | «Nueva plantilla» button | `data-tour="recurrentes.create"` |
| `recurrentes.table` | ResponsiveTable | `data-tour="recurrentes.table"` |
| *(step 36 — no target)* | Modal popover | `element: undefined` → driver.js centered modal |

## Code-Evidenced Traps → Concrete Fixes

### Trap 1: `useKeyboardShortcuts` collision

**Evidence:** Dashboard (L183) binds `ArrowLeft`/`ArrowRight` globally with no `isDialogOpen`. Movimientos (L111) and Categorías (L176) bind arrows + `N` with `isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value`.

**Fix:** Each tour page appends `isTourActive` to the existing guard:

```typescript
// Dashboard.vue — currently has NO isDialogOpen option
useKeyboardShortcuts([...], {
  isDialogOpen: () => isTourActive(),
});

// Movimientos/Index.vue — extend existing guard
useKeyboardShortcuts([...], {
  isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value || isTourActive(),
});

// Categorias/Index.vue — same pattern
useKeyboardShortcuts([...], {
  isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value || isTourActive(),
});
```

Cuentas and Recurrentes do not use `useKeyboardShortcuts` — no change needed.

### Trap 2: `useSettings.updateSettings()` key-presence guard

**Evidence:** `useSettings.ts` L134: `if (key in settings)` — only syncs keys present on the local reactive object. Current `UserSettings` has no `onboarding` key, so `updateSettings({ onboarding: ... })` would silently skip the local sync.

**Fix:** Add `onboarding` to `UserSettings` interface AND `defaults`:

```typescript
// useSettings.ts
export interface UserSettings {
  // ... existing ...
  onboarding: { completed_at: string | null; version: number };
}

const defaults: UserSettings = {
  // ... existing ...
  onboarding: { completed_at: null, version: 0 },
};
```

### Trap 3: `hydrateSettings()` shallow merge

**Evidence:** `useSettings.ts` L50: `return { ...defaults, ...raw } as UserSettings`. A partial server object `{ onboarding: { completed_at: "..." } }` (no `version`) would spread over the default, wiping `version: 0`.

**Fix:** Nested merge for `onboarding` in `hydrateSettings()`:

```typescript
function hydrateSettings(raw: Record<string, unknown> | null | undefined): UserSettings {
  const base = { ...defaults, ...raw } as UserSettings;
  if (raw?.onboarding) {
    base.onboarding = { ...defaults.onboarding, ...(raw.onboarding as Record<string, unknown>) };
  }
  return base;
}
```

### Trap 4: `UpdateSettingsRequest` whitelist

**Evidence:** `UpdateSettingsRequest.php` L26-33: explicit `rules()` array — unknown keys are silently dropped by Laravel's validation. No `onboarding` rule exists.

**Fix:** Add three rules:

```php
'onboarding' => ['nullable', 'array'],
'onboarding.completed_at' => ['nullable', 'date'],
'onboarding.version' => ['nullable', 'integer', 'min:1'],
```

## driver.js Integration

### Lazy Import + SSR Guard

```typescript
// useTour.ts — inside start() or onMounted of TourLauncher
let driverInstance: any = null;

async function ensureDriver() {
  if (typeof window === 'undefined') return null;
  if (!driverInstance) {
    const { driver } = await import('driver.js');
    await import('driver.js/dist/driver.css');
    driverInstance = driver({
      showProgress: true,
      progressText: '{{current}} de {{total}}',
      nextBtnText: 'Siguiente',
      prevBtnText: 'Anterior',
      doneBtnText: 'Listo',
      showButtons: ['next', 'previous', 'close'],
      allowClose: true,
      overlayClickNext: false,
      stagePadding: 4,
      stageRadius: 8,
      animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      onDestroyStarted: () => {
        // persist on dismiss (close button, overlay click)
        persistCompletion();
        driverInstance?.destroy();
      },
    });
  }
  return driverInstance;
}
```

### Theming — CSS Custom-Property Overrides

In `resources/css/app.css`, after the existing base layer:

```css
/* driver.js theme integration */
.driver-popover {
  background: var(--popover);
  color: var(--popover-foreground);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  box-shadow: var(--shadow-lg);
  font-family: var(--font-sans);
}

.driver-popover-title {
  font-weight: 600;
  color: var(--foreground);
}

.driver-popover-description {
  color: var(--muted-foreground);
}

.driver-popover-btn {
  background: var(--primary);
  color: var(--primary-foreground);
  border-radius: var(--radius-sm);
  border: none;
  padding: 0.375rem 0.75rem;
  font-size: 0.875rem;
  cursor: pointer;
}

.driver-popover-btn:hover {
  opacity: 0.9;
}

.driver-popover-btn-secondary {
  background: var(--secondary);
  color: var(--secondary-foreground);
}

.driver-popover-close-btn {
  color: var(--muted-foreground);
}

.driver-popover-close-btn:hover {
  color: var(--foreground);
}

.driver-overlay {
  opacity: 0.5;
}

/* Dark mode — inherits from .dark on <html> via CSS variables above */
/* All 8 themes inherit via .theme-xxx selectors setting the same --popover/--foreground/etc. tokens */
```

### z-index Strategy

reka-ui portals (Dialog, Sheet, DropdownMenu) render at `z-50` (Tailwind `z-index: 50`). Driver.js creates its own fixed overlay/popover. Set driver's z-index above reka-ui:

```css
.driver-overlay,
.driver-popover {
  z-index: 100 !important;
}
```

Before a step targets an element inside a reka-ui overlay (none currently in the tour), close any open overlay first. The existing sidebar Sheet close on `router.on('navigate')` handles this for navigation steps.

### `prefers-reduced-motion`

Pass `animate: false` to the driver config when `window.matchMedia('(prefers-reduced-motion: reduce)').matches` is true (shown above in `ensureDriver()`).

## Help Button

**Location:** `AppSidebarHeader.vue`, in the `ml-auto` flex container, before the simulation toggle.

**Icon:** `CircleHelp` from `@lucide/vue`.

**Label:** Tooltip «Guía de uso» (matching the tour copy for step 8).

**Wiring:** Calls `useTour().restart()`. The `restart()` function sets `armed = true`, navigates to Dashboard via `router.visit(dashboard())`, and the launcher re-starts on mount.

```vue
<Tooltip>
  <TooltipTrigger as-child>
    <Button
      variant="outline"
      size="icon"
      class="h-9 w-9"
      data-tour="shell.help"
      @click="restart"
    >
      <CircleHelp class="size-5" />
    </Button>
  </TooltipTrigger>
  <TooltipContent>Guía de uso</TooltipContent>
</Tooltip>
```

## Accessibility

- **Focus:** On each step, driver.js moves focus to the popover. On destroy/exit, focus returns to the previously focused element (driver.js default behavior).
- **Keyboard:** Esc closes the tour (triggers `onDestroyStarted` → persist). Enter/arrow keys navigate steps (driver.js built-in). The `isDialogOpen` guard suppresses app-level shortcuts.
- **Labels:** All popover buttons have text content (`nextBtnText`, `prevBtnText`, `doneBtnText`). The close button has an `aria-label` via driver.js defaults.
- **Non-trapping:** driver.js does not trap focus permanently — Tab moves through popover controls and into the overlay close button.
- **Reduced motion:** `animate: false` under `prefers-reduced-motion: reduce`.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Feature (Pest) | `onboarding` flag persists via PUT /settings | `assertNoContent()` + assert nested values in `users.settings` |
| Feature (Pest) | Invalid `onboarding.version` rejected | `version: 0` → `assertSessionHasErrors('onboarding.version')` |
| Feature (Pest) | Invalid `onboarding.completed_at` rejected | non-date → `assertSessionHasErrors('onboarding.completed_at')` |
| Feature (Pest) | Theme update preserves `onboarding` | Set onboarding, PUT theme, assert onboarding survives |
| Feature (Pest) | Re-arm with `completed_at: null` | PUT `completed_at: null` → stored as null |
| Build | Frontend compiles cleanly | `npm run build` |
| Type check | No TypeScript errors | `npm run types:check` (`vue-tsc --noEmit`) |

Run: `php artisan test --compact --filter=Preferences`

## Threat Matrix

N/A — no routing, shell, subprocess, VCS/PR automation, executable-file classification, or process-integration boundary.

## Migration / Rollout

No migration required. The `users.settings` JSON column already exists. The `onboarding` key is additive — existing rows without it are treated as "not completed" (the default `completed_at: null` in the frontend).

## Dependency Step

```bash
pnpm add driver.js
npm install --package-lock-only   # re-sync tracked npm lockfile
```

Both `pnpm-lock.yaml` and `package-lock.json` MUST be committed together.

## Slice Boundaries for Delivery

| PR | Content | Est. lines | Independently verifiable |
|----|---------|-----------|--------------------------|
| **PR 1 — Persistence** | `UpdateSettingsRequest` rules, `useSettings.ts` (type + default + hydration), 5 Pest tests | ~70 | `php artisan test --compact --filter=Preferences` |
| **PR 2 — Tour infrastructure** | `driver.js` + lockfiles, `useTour.ts`, `TourLauncher.vue`, `usePageTour.ts`, CSS overrides, z-index, reduced-motion, one smoke step | ~280 | `npm run build` + `npm run types:check` + manual smoke |
| **PR 3 — Shell + Dashboard** | `tourSteps.ts` (shell + dashboard copy), shell anchors (AppSidebar, NavMain, NavUser, AppSidebarHeader), Help button, Dashboard anchors, keyboard guard | ~230 | Full shell + dashboard segment walkthrough |
| **PR 4 — Movimientos** | `tourSteps.ts` (movimientos copy), 6 anchors, `usePageTour('movimientos')`, keyboard guard | ~70 | Movimientos segment walkthrough |
| **PR 5 — Cuentas** | `tourSteps.ts` (cuentas copy), 4 anchors, `usePageTour('cuentas')` | ~50 | Cuentas segment walkthrough |
| **PR 6 — Categorías** | `tourSteps.ts` (categorías copy), 4 anchors, `usePageTour('categorias')`, keyboard guard | ~60 | Categorías segment walkthrough |
| **PR 7 — Recurrentes** | `tourSteps.ts` (recurrentes copy), 4 anchors, `usePageTour('recurrentes')`, step 36 modal | ~60 | Full tour end-to-end |

Each PR is independently revertable. PR 2 disables the tour if reverted alone, leaving the persistence flag inert.

## Open Questions

None — all ORs resolved.
