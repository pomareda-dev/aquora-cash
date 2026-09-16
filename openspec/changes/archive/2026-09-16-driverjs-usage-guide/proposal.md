# Proposal: Driver.js Usage Guide

> **Review artifact.** Section 4 contains the full user-facing tour copy in Spanish (UI copy). Review it before approving implementation.

## Intent

New users land on the Dashboard with no explanation of the app's model (real vs. projected movements, budgets, reconciliation, simulation mode). Existing empty states only prompt creation — they never explain *why* a section matters.

This change adds a first-login, multi-page guided tour that walks the user through the five core sections and the shared shell, then never shows again unless explicitly restarted from a Help button.

## Scope

### In Scope

- `driver.js` tour covering, in order: **dashboard → movimientos → cuentas → categorías → recurrentes**, plus the shared shell (sidebar nav, user menu, simulation toggle) and a Help restart entry point.
- Automatic start on first login; manual restart from a Help button.
- Server-side completion flag in the existing `users.settings` JSON column. **No migration.**
- A Simulation-mode step that explains the toggle and its banner without duplicating the banner.
- `data-tour` anchor convention across pages and shell components.
- Pest feature tests for the flag round-trip; frontend verified by `npm run build` + `vue-tsc`.

### Out of Scope

- Vitest / Vue Test Utils / any new JS test toolchain.
- An i18n layer. All tour copy is hardcoded Spanish, matching the app.
- Tours for Deudas, Metas, Proyección, settings pages, auth pages.
- Cross-device sync beyond the `users.settings` flag; per-user tour analytics.
- Any change to `SimulationBanner.vue` content or behavior.

## Capabilities

### New Capabilities

- `user-onboarding-tour`: first-login guided tour — trigger rules, segment ordering across Inertia pages, `data-tour` anchor contract, restart behavior, and completion persistence.

### Modified Capabilities

- `settings`: `UpdateSettingsRequest` MUST accept and validate the new `onboarding` key inside `users.settings`; `useSettings` MUST expose and round-trip it. Existing theme/density/debt rules are unchanged.

## Approach

Adopt the exploration's **Option C** (global launcher + per-page hooks). No evidence was found that it fails; the concrete constraints below are handled inside it, not against it.

- **`useTour()` composable** (`resources/js/composables/useTour.ts`) — module-scoped reactive singleton: `isActive`, `armed`, `currentSegment`, `start()`, `restart()`, `finish()`, `stepIndex`. Wraps a single `driver.js` instance. Browser-only code lives behind `onMounted` + `typeof window !== 'undefined'`.
- **`TourLauncher.vue`** — mounted once in `AppSidebarLayout.vue`. Renders nothing. Owns the driver instance, the segment sequencing, the `router.visit()` hand-off between pages, and the completion write.
- **`usePageTour(segment)`** — one call per page in `onMounted()`. Registers that page's steps with the launcher and asks it to run the segment if the tour is armed. One line per page.
- **`tourSteps.ts`** — all step definitions (selector, Spanish copy, side, preconditions) in one reviewable file. Pages stay thin; copy is reviewable in one place.
- **Anchor convention** — `data-tour="<segment>.<element>"` (e.g. `dashboard.metrics`, `movimientos.create`). `driver.js` receives `[data-tour="…"]` selectors. Targets are existing elements; `Card` and `ResponsiveTable` both have single roots, so the attribute falls through without component changes.
- **Route-change reaction** — the launcher watches `usePage().component` (Inertia component name: `Dashboard`, `Movimientos/Index`, `Cuentas/Index`, `Categorias/Index`, `Recurrentes/Index`) rather than URLs. On segment completion it destroys the driver, sets `advancing = true`, and calls `router.visit(nextRoute)`. The next page mounts, `usePageTour` registers its steps, the launcher resumes.
- **Start behavior** — if the user is not on a tour page, the launcher navigates to the Dashboard first; if on a tour page, that page's segment runs, then the remaining segments in canonical order.
- **Keyboard-shortcut conflict** — the app binds `ArrowLeft`/`ArrowRight`/`N` globally, which collides with driver.js arrow-key navigation. Pages already accept `isDialogOpen`; pass `isDialogOpen: () => isTourActive()` so app shortcuts are suppressed while the tour runs.

## Guide Content (core deliverable)

Segments run in order. `data-tour` values are the anchor to add; **Title** and **Description** are Spanish UI copy.

### Segment 0 — Shared shell (runs on Dashboard)

| # | Anchor | Target file | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|-------------|------|-------------|------------------|--------------|
| 1 | `shell.sidebar` | `AppSidebar.vue` (`<Sidebar>`) | right | Tu menú principal | Desde aquí llegas a todas las secciones: Tablero, Movimientos, Categorías, Cuentas, Recurrentes, Deudas, Metas y Proyección. | Desktop: sidebar expanded. Mobile: sidebar `Sheet` open, or skipped (see Risks) |
| 2 | `nav.movimientos` | `NavMain.vue` (item) | right | Movimientos | Aquí registras tus ingresos y gastos, y revisas tu saldo real y proyectado del mes. | Sidebar visible |
| 3 | `nav.cuentas` | `NavMain.vue` (item) | right | Cuentas | Administra tus cuentas y concilia sus saldos contra el balance real de movimientos. | Sidebar visible |
| 4 | `nav.categorias` | `NavMain.vue` (item) | right | Categorías y presupuestos | Organiza tus movimientos por categoría y define un presupuesto mensual para cada una. | Sidebar visible |
| 5 | `nav.recurrentes` | `NavMain.vue` (item) | right | Recurrentes | Crea plantillas de ingresos y gastos que se repiten cada mes; el sistema proyecta sus cuotas automáticamente. | Sidebar visible |
| 6 | `shell.userMenu` | `NavUser.vue` (trigger) | right | Tu cuenta | Desde aquí accedes a tu configuración y cierras sesión. | Sidebar visible |
| 7 | `shell.simulationToggle` | `AppSidebarHeader.vue` (button) | bottom | Modo Simulación | Actívalo para probar escenarios sin afectar tu balance real. Cuando está activo verás una franja ámbar arriba con las opciones para revertir el escenario o guardarlo como real. | None |
| 8 | `shell.help` | `AppSidebarHeader.vue` (**new** Help button) | bottom | Guía de uso | ¿Te perdiste? Pulsa aquí para volver a ver esta guía cuando quieras. | None |

### Segment 1 — Dashboard (`Dashboard.vue`)

| # | Anchor | Target | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|--------|------|-------------|------------------|--------------|
| 9 | `dashboard.header` | header block (L278) | bottom | Tablero | Este es tu resumen financiero del mes: todo lo importante de un vistazo. | None |
| 10 | `dashboard.monthNav` | month nav row (L284) | bottom | Navegación por mes | Muévete entre meses con las flechas — o con las teclas ← y → — y vuelve al mes actual con «Hoy». | None |
| 11 | `dashboard.metrics` | 4-card grid (L337) | bottom | Indicadores del mes | Balance actual, ingresos, gastos y la proyección a fin de mes, calculados con tus movimientos. | None |
| 12 | `dashboard.budget` | Resumen de presupuesto Card | top | Resumen de presupuesto | Cuánto llevas gastado de cada categoría con presupuesto. La barra se pone ámbar al 75 % y roja si superas el límite. | None |
| 13 | `dashboard.reconciliation` | Mini conciliación Card | top | Mini conciliación | Compara el saldo de tus cuentas con el balance real de movimientos. Si todo cuadra, verás «Conciliado». | None |
| 14 | `dashboard.debts` | Deudas activas Card | top | Deudas activas | El progreso de cada deuda, cuánto te falta por pagar y cuándo vence la próxima cuota. | None |
| 15 | `dashboard.goals` | Metas Card | top | Metas | Cuánto tienes apartado para tus metas y cuánto te queda disponible de verdad. | None |
| 16 | `dashboard.upcoming` | Próximos movimientos Card | top | Próximos movimientos | Lo que se viene en los próximos 7 días, incluyendo lo que proyectan tus recurrentes. | None |
| 17 | `dashboard.chart` | Balance del mes Card | top | Balance del mes | La evolución de tu balance día a día durante el mes seleccionado. | None |

→ `router.visit(movimientos.index())`

### Segment 2 — Movimientos (`Movimientos/Index.vue`)

| # | Anchor | Target | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|--------|------|-------------|------------------|--------------|
| 18 | `movimientos.header` | header block (L328) | bottom | Movimientos | Registra y consulta tus ingresos y egresos. | None |
| 19 | `movimientos.monthNav` | month nav (L335) | bottom | Mes | Cambia de mes con las flechas — o con ← y →. «Hoy» te devuelve al mes actual. | None |
| 20 | `movimientos.create` | «Nuevo movimiento» button (L370) | bottom | Nuevo movimiento | Crea un ingreso o un gasto. Atajo: pulsa N. En modo Simulación el botón se vuelve ámbar y el movimiento no afecta tu balance real. | None |
| 21 | `movimientos.actuales` | «Actuales» Card (L385) | top | Movimientos actuales | Lo que ya ocurrió este mes, con su saldo acumulado. Puedes reordenarlos arrastrando el ícono de la izquierda. | `v-if="!isFutureMonth"` — skipped in future months |
| 22 | `movimientos.proyectados` | «Proyectados» Card (L499) | top | Movimientos proyectados | Lo que todavía no ocurre: cuotas de recurrentes y movimientos futuros, con el saldo estimado. | `v-if="!isPastMonth"` — skipped in past months |
| 23 | `movimientos.summary` | summary block (L600) | top | Resumen del mes | Ingresos, gastos, neto y balance final del mes, calculados solo con movimientos reales. | `v-if="realMovements.length > 0"` — skipped when empty |

→ `router.visit(cuentas.index())`

### Segment 3 — Cuentas (`Cuentas/Index.vue`)

| # | Anchor | Target | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|--------|------|-------------|------------------|--------------|
| 24 | `cuentas.header` | header block (L163) | bottom | Cuentas | Administra tus cuentas y saldos, y concilia contra el balance real. | None |
| 25 | `cuentas.create` | «Nueva cuenta» button (L171) | bottom | Nueva cuenta | Registra una cuenta nueva indicando su tipo y su saldo inicial. | None |
| 26 | `cuentas.table` | `ResponsiveTable` (L178) | top | Tus cuentas | Tipo, saldo y si la cuenta entra o no en la conciliación. El total aparece al pie. Puedes reordenarlas arrastrando. | None |
| 27 | `cuentas.reconciliation` | Conciliación Card (L283) | top | Conciliación | Compara el total de tus cuentas con el balance real. Si la diferencia es cero la insignia dice «Conciliado»; si no, «Descuadre». | None |

→ `router.visit(categorias.index())`

### Segment 4 — Categorías (`Categorias/Index.vue`)

| # | Anchor | Target | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|--------|------|-------------|------------------|--------------|
| 28 | `categorias.header` | header block (L220) | bottom | Categorías y presupuestos | Gestiona tus categorías y controla tu presupuesto mensual. | None |
| 29 | `categorias.monthNav` | month nav (L226) | bottom | Mes del presupuesto | El progreso de cada categoría corresponde al mes que elijas aquí. | None |
| 30 | `categorias.create` | «Nueva categoría» button (L262) | bottom | Nueva categoría | Crea una categoría de ingreso o de gasto y, si quieres, asígnale un presupuesto mensual. | None |
| 31 | `categorias.table` | `ResponsiveTable` (L269) | top | Tus categorías | Cada fila muestra el tipo, el presupuesto del mes y una barra de progreso: verde por debajo del 75 %, ámbar al 75 % y roja si superas el límite. | None |

→ `router.visit(recurrentes.index())`

### Segment 5 — Recurrentes (`Recurrentes/Index.vue`)

| # | Anchor | Target | Side | Title (ES) | Description (ES) | Precondition |
|---|--------|--------|------|-------------|------------------|--------------|
| 32 | `recurrentes.header` | header block (L175) | bottom | Transacciones recurrentes | Gestiona tus plantillas de ingresos y gastos periódicos. | None |
| 33 | `recurrentes.regenerate` | «Regenerar proyecciones» (L183) | bottom | Regenerar proyecciones | Vuelve a generar los movimientos proyectados de tus plantillas. Úsalo si cambiaste una plantilla y quieres ver el efecto en los próximos meses. | None |
| 34 | `recurrentes.create` | «Nueva plantilla» button (L192) | bottom | Nueva plantilla | Define un ingreso o gasto que se repite cada mes: monto, día, categoría y rango de vigencia. | None |
| 35 | `recurrentes.table` | `ResponsiveTable` (L206) | top | Tus plantillas | Monto, categoría, día de cobro, vigencia y si está activa. Las plantillas inactivas no generan proyecciones. | None |
| 36 | — (no target) | final centered popover | — | ¡Listo! | Ya conoces lo esencial de tu caja diaria. Puedes volver a ver esta guía cuando quieras desde el botón de ayuda (?). | None |

On step 36 completion (or dismissal) → persist `onboarding`.

## Persistence Design

Shape written into `users.settings`:

```json
{
  "onboarding": {
    "completed_at": "2026-09-15T12:00:00Z",
    "version": 1
  }
}
```

- **Read:** `useSettings().settings.onboarding.completed_at`. Tour runs when `null`/absent, or when `onboarding.version < TOUR_VERSION`.
- **Write:** on tour end (finish **or** dismiss), `updateSettings({ onboarding: { completed_at: new Date().toISOString(), version: TOUR_VERSION } })`. `PreferencesController::update()` shallow-merges it into `users.settings`, so the existing theme/density keys are preserved.
- **Restart:** the Help button calls `restart()` → in-memory re-arm + `router.visit(dashboard())` + start. It does **not** clear the server flag; completion is rewritten at the end. This avoids an extra round-trip and avoids leaving users stuck in a permanently "incomplete" state if they abandon the tour.
- **Re-trigger for everyone:** bump `TOUR_VERSION`.

Required changes:

- `UpdateSettingsRequest::rules()` MUST add `onboarding` (`array`, nullable) plus `onboarding.completed_at` (`nullable`, `date`) and `onboarding.version` (`nullable`, `integer`, `min:1`). Without this, the existing `settings update ignores unknown keys` behavior silently drops the flag.
- `UserSettings` interface MUST add `onboarding: { completed_at: string | null; version: number }`, **and** `defaults` MUST include it. `updateSettings()` only syncs keys already present on the local `settings` object (`if (key in settings)`), so a missing default makes the local sync a silent no-op.
- `hydrateSettings()` does a shallow `{ ...defaults, ...raw }`. A partial server object (`onboarding` present but `version` absent) would wipe the default `version`. Normalize `onboarding` with a nested merge in `hydrateSettings()`.

## Dependency Decision

Add `driver.js` (MIT, no runtime dependencies, ~5 KB gzipped).

`pnpm` is the effective package manager (`pnpm-lock.yaml` is the install source of truth; `node_modules/.pnpm` exists). `pnpm` does **not** update `package-lock.json`.

```bash
pnpm add driver.js
npm install --package-lock-only   # re-sync the tracked npm lockfile
```

Both `pnpm-lock.yaml` and `package-lock.json` MUST be committed in the same change. Stylesheet import: `import 'driver.js/dist/driver.css'` in `resources/js/composables/useTour.ts` (or `app.ts`).

## Testing Strategy

**Backend (Pest, TDD-first)** — extend `tests/Feature/Settings/PreferencesTest.php`:

1. `settings update persists the onboarding flag` — PUT `onboarding: {completed_at, version}` → `assertNoContent()`, assert both nested values.
2. `settings update rejects an invalid onboarding version` — `version: 0` → `assertSessionHasErrors('onboarding.version')`.
3. `settings update rejects an invalid onboarding completion date` — non-date string → `assertSessionHasErrors('onboarding.completed_at')`.
4. `settings update preserves the onboarding flag when updating other settings` — set `onboarding`, then PUT `theme`, assert `onboarding` survives.
5. `settings update re-arms the onboarding flag` — PUT `completed_at: null` → stored as `null`.

Run: `php artisan test --compact --filter=Preferences`.

**Frontend boundary (explicit):** the Vue/driver.js layer is **not** unit-tested in this change. Verification is `npm run build` and `npm run types:check` (`vue-tsc --noEmit`), plus manual smoke of the tour. This is a deliberate, stated deviation from `strict_tdd: true` for the UI layer, consistent with the project's current absence of a JS test harness and the confirmed decision not to add Vitest.

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| SSR / hydration crash — `driver.js` touches `document` at import | Med | Import lazily inside `onMounted`; guard `typeof window !== 'undefined'`; `build:ssr` still passes |
| Keyboard conflict — app binds `←`/`→`/`N` globally; driver.js uses arrows for step navigation | **High** | Pass `isDialogOpen: () => isTourActive()` to `useKeyboardShortcuts` on every tour page |
| Dark mode / theme styling — driver.js ships light-only defaults; app has 8 themes + `.dark` | High | Override driver.js CSS custom properties with app tokens (`--popover`, `--foreground`, `--border`, `--primary`) and add `.dark` overrides in `resources/css/app.css` |
| z-index vs reka-ui portals — Dialog/Sheet/DropdownMenu render at `z-50`; driver.js owns its own fixed nodes | Med | Set an explicit driver z-index above reka-ui's and close any open reka-ui overlay before starting a step; verify against the installed driver.js version |
| Mobile sidebar is a `Sheet` — nav-item anchors do not exist until it opens | High | Desktop-only shell nav steps; on mobile replace steps 2–6 with a single `shell.sidebarTrigger` step, or open the Sheet (`setOpenMobile(true)`) for the segment and close it after |
| Accessibility — focus management, keyboard reachability, `prefers-reduced-motion`, screen-reader announcements | Med | Disable driver.js animation under `prefers-reduced-motion`; ensure popover buttons are labelled; verify Esc/Enter/arrows; do not trap focus permanently |
| No stable selectors today — UI relies on CSS classes and wrapper components | **High (certain)** | The `data-tour` convention is the mitigation and is a first-class deliverable of this change |
| Lockfile drift — two lockfiles tracked, `pnpm` is effective | Med | `pnpm add` + `npm install --package-lock-only` in one commit; both files reviewed together |
| `hydrateSettings` shallow merge drops `onboarding.version` | Med | Explicit nested normalization in `useSettings.ts` |
| `updateSettings` key-presence guard silently no-ops the flag | Med | Add `onboarding` to `UserSettings` defaults; covered by the test suite on the backend and by manual smoke on the frontend |
| Cross-page navigation fragility — Inertia swaps destroy DOM mid-tour | Med | One driver instance, segment-scoped; destroy before `router.visit()`; resume keyed on `usePage().component`, not URL |
| Copy drift — no i18n layer, Spanish strings hardcoded | Low | All copy centralized in `tourSteps.ts` for single-file review |

## Affected Files

**New**
- `resources/js/composables/useTour.ts` — driver wrapper, singleton state, segment sequencing
- `resources/js/composables/usePageTour.ts` — per-page registration hook
- `resources/js/components/tour/TourLauncher.vue` — launcher mounted in the layout
- `resources/js/components/tour/tourSteps.ts` — all step definitions and Spanish copy

**Modified**
- `resources/js/layouts/app/AppSidebarLayout.vue` — mount `<TourLauncher />`
- `resources/js/components/AppSidebar.vue` — `data-tour="shell.sidebar"`, nav tour ids
- `resources/js/components/NavMain.vue` — bind `data-tour` per item
- `resources/js/components/AppSidebarHeader.vue` — simulation-toggle anchor + new Help button (`shell.help`)
- `resources/js/components/NavUser.vue` — `data-tour="shell.userMenu"`
- `resources/js/pages/Dashboard.vue` — 9 anchors + `usePageTour('dashboard')`
- `resources/js/pages/Movimientos/Index.vue` — 6 anchors + `usePageTour('movimientos')`
- `resources/js/pages/Cuentas/Index.vue` — 4 anchors + `usePageTour('cuentas')`
- `resources/js/pages/Categorias/Index.vue` — 4 anchors + `usePageTour('categorias')`
- `resources/js/pages/Recurrentes/Index.vue` — 4 anchors + `usePageTour('recurrentes')`
- `resources/js/types/navigation.ts` — optional `tourId` on `NavItem`
- `resources/js/composables/useSettings.ts` — `onboarding` type, default, nested hydration
- `resources/css/app.css` — driver.js theme/dark overrides
- `app/Http/Requests/Settings/UpdateSettingsRequest.php` — `onboarding` rules
- `tests/Feature/Settings/PreferencesTest.php` — 5 new tests
- `package.json`, `pnpm-lock.yaml`, `package-lock.json` — `driver.js`

## Review-Size Forecast

**This change plausibly exceeds the 400-changed-line review budget.** Authored estimate: ~550–650 lines (`tourSteps.ts` ~180, `useTour.ts` ~160, `TourLauncher.vue` ~120, CSS ~40, anchors across 10 components/pages ~80, tests ~50, settings plumbing ~15, lockfiles ~35 generated). Line numbers in Section 4 are approximate and will shift as anchors are added.

Natural slice boundaries, each autonomous and independently verifiable:

| Slice | Content | Est. lines |
|-------|---------|-----------|
| **PR 1 — Persistence** | `UpdateSettingsRequest`, `useSettings.ts`, 5 Pest tests | ~70 |
| **PR 2 — Tour infrastructure** | `driver.js` + lockfiles, `useTour.ts`, `TourLauncher.vue`, `usePageTour.ts`, CSS overrides, one smoke step | ~280 |
| **PR 3 — Shell + Dashboard** | `tourSteps.ts` (shell + dashboard copy), shell anchors, Help button, Dashboard anchors | ~230 |
| **PR 4–7 — One page each** | movimientos / cuentas / categorías / recurrentes: anchors + copy + `usePageTour` | ~50–80 each |

`Decision needed before apply: Yes` — the delivery strategy must resolve to chained PR slices or an explicit `size:exception` before `sdd-apply` starts.

## Rollback Plan

Low-risk and fully reversible; no schema change, no data migration.

1. Remove the `<TourLauncher />` mount from `AppSidebarLayout.vue` — the tour stops immediately, the `onboarding` key in `users.settings` becomes inert (harmless extra JSON).
2. Revert the `data-tour` attributes and `usePageTour` calls — pure additions, no behavior change on their own.
3. Revert the `onboarding` rules in `UpdateSettingsRequest` and the `UserSettings` addition — the stored key remains but is ignored, matching the existing unknown-key behavior.
4. `pnpm remove driver.js` and re-sync `package-lock.json` if the dependency must go.
5. Per-slice rollback: each PR in the chain is independently revertable; reverting PR 2 alone disables the tour while leaving the persistence flag in place.

## Dependencies

- `driver.js` (new, MIT) — see Dependency Decision.
- Existing `users.settings` JSON column and `PUT /settings` endpoint — no new migration, no new route.
- `useSidebar()` state control (`setOpen`, `setOpenMobile`) for sidebar preconditions.
- `useSettings().updateSettings()` for the completion write.

## Success Criteria

- [ ] A user with `onboarding.completed_at = null` sees the tour automatically after login, starting on the Dashboard.
- [ ] The tour visits all five sections in order and completes on the Recurrentes segment.
- [ ] `users.settings.onboarding` is persisted with `completed_at` and `version` after completion **and** after dismissal.
- [ ] A user with `onboarding.completed_at` set does not see the tour again on login.
- [ ] The Help button restarts the tour from the Dashboard at any time.
- [ ] The Simulation-mode step explains the toggle and the banner without duplicating the banner.
- [ ] The tour renders correctly in light and dark mode across all 8 themes.
- [ ] Arrow keys drive the tour and do not simultaneously navigate months.
- [ ] `php artisan test --compact --filter=Preferences` passes.
- [ ] `npm run build` and `npm run types:check` pass.
- [ ] Delivery is split so no PR exceeds the 400-changed-line budget.
