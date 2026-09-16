# User Onboarding Tour Specification

## Purpose

First-login, multi-page guided tour built on `driver.js`. It walks the five core sections plus the shared shell in a fixed order, persists completion server-side in `users.settings`, and is restartable from a Help button. Spec language is English; all tour Title/Description copy is Spanish UI copy and MUST match the proposal's Guide Content verbatim.

## Requirements

### Requirement: Trigger Conditions

The tour MUST auto-run when `settings.onboarding.completed_at` is `null`/absent, OR when `settings.onboarding.version < TOUR_VERSION`. It MUST NOT auto-run when `completed_at` is set and `version >= TOUR_VERSION`. Arming MUST be evaluated once per authenticated page load on the shared layout.

#### Scenario: First login auto-starts the tour
- GIVEN a user with `settings.onboarding.completed_at` absent
- WHEN they log in and land on the Dashboard
- THEN the tour auto-starts on step 1 (`shell.sidebar`)

#### Scenario: Returning user does not see the tour
- GIVEN a user with `onboarding.completed_at` set and `version >= TOUR_VERSION`
- WHEN they load any authenticated page
- THEN the tour MUST NOT start

#### Scenario: Version bump re-triggers the tour
- GIVEN a user with `onboarding.completed_at` set and `version < TOUR_VERSION`
- WHEN they load an authenticated page
- THEN the tour auto-starts

### Requirement: Segment Order and Cross-Page Hand-Off

Segments MUST run in canonical order: `dashboard` → `movimientos` → `cuentas` → `categorias` → `recurrentes` (shell steps 1–8 belong to the dashboard segment). Resumption MUST key on `usePage().component` (`Dashboard`, `Movimientos/Index`, `Cuentas/Index`, `Categorias/Index`, `Recurrentes/Index`), never on URL. On segment completion the launcher MUST destroy the driver instance before `router.visit(nextRoute)`. If the user is not on a tour page, the launcher MUST navigate to the Dashboard first, then run the remaining segments in order; if already on a tour page, that page's segment MUST run first.

#### Scenario: Segments hand off in canonical order
- GIVEN the tour is running on the last Dashboard step
- WHEN the segment completes
- THEN the driver is destroyed, `router.visit(movimientos.index())` runs, and the Movimientos segment resumes on `Movimientos/Index`

#### Scenario: Start from a non-tour page
- GIVEN an armed user is on a non-tour page
- WHEN the tour is armed
- THEN the launcher navigates to the Dashboard and starts with the shell segment

### Requirement: Step Content Contract

The tour MUST define 36 steps. Anchors per segment:

| Segment | Page component | Anchors (in order) |
|---------|----------------|--------------------|
| 0 (shell) | `Dashboard` | `shell.sidebar`, `nav.movimientos`, `nav.cuentas`, `nav.categorias`, `nav.recurrentes`, `shell.userMenu`, `shell.simulationToggle`, `shell.help` |
| 1 | `Dashboard` | `dashboard.header`, `dashboard.monthNav`, `dashboard.metrics`, `dashboard.budget`, `dashboard.reconciliation`, `dashboard.debts`, `dashboard.goals`, `dashboard.upcoming`, `dashboard.chart` |
| 2 | `Movimientos/Index` | `movimientos.header`, `movimientos.monthNav`, `movimientos.create`, `movimientos.actuales`, `movimientos.proyectados`, `movimientos.summary` |
| 3 | `Cuentas/Index` | `cuentas.header`, `cuentas.create`, `cuentas.table`, `cuentas.reconciliation` |
| 4 | `Categorias/Index` | `categorias.header`, `categorias.monthNav`, `categorias.create`, `categorias.table` |
| 5 | `Recurrentes/Index` | `recurrentes.header`, `recurrentes.regenerate`, `recurrentes.create`, `recurrentes.table`, step 36 (no target) |

Each step MUST carry Title/Description verbatim from the proposal §4, a target anchor, and a side. All 36 copy strings MUST be centralized in `tourSteps.ts`. Step 36 MUST render a centered popover with title «¡Listo!» and description «Ya conoces lo esencial de tu caja diaria. Puedes volver a ver esta guía cuando quieras desde el botón de ayuda (?).» The Help button copy MUST be title «Guía de uso» / description «¿Te perdiste? Pulsa aquí para volver a ver esta guía cuando quieras.»

### Requirement: Conditional Steps and Skip Behavior

Steps 21 (`movimientos.actuales`), 22 (`movimientos.proyectados`) and 23 (`movimientos.summary`) target conditionally rendered elements. When a target is absent at step time, the tour MUST skip the step and advance to the next available step; it MUST NOT render an empty or mispositioned popover, and MUST NOT abort the segment. Step 36, having no target, MUST render regardless of page content.

#### Scenario: Conditional step is skipped
- GIVEN the selected month is in the future so `movimientos.actuales` is not rendered
- WHEN the Movimientos segment reaches step 21
- THEN the step is skipped and step 22 runs

### Requirement: Completion Persistence

On tour finish AND on dismissal (close button, Esc, or overlay) the launcher MUST persist `{ onboarding: { completed_at: <ISO-8601 UTC>, version: TOUR_VERSION } }` via `useSettings().updateSettings()`. Persistence MUST NOT remove or overwrite other `users.settings` keys. A dismissed tour MUST be treated as completed (no re-trigger).

#### Scenario: Finish persists completion
- GIVEN the tour is on step 36
- WHEN the user finishes
- THEN `updateSettings` writes `onboarding.completed_at` and `onboarding.version` and other settings keys are preserved

#### Scenario: Dismissal persists completion
- GIVEN the tour is running
- WHEN the user dismisses it with Esc or the close control
- THEN the same completion payload is persisted and the tour does not re-trigger

### Requirement: Restart Semantics

The `shell.help` control MUST call `restart()`, which re-arms the tour in memory, navigates to the Dashboard, and starts the tour. Restart MUST NOT clear the server `onboarding` flag; completion is rewritten at the end. Restart MUST be available at any time from any authenticated page.

#### Scenario: Restart re-arms without clearing the flag
- GIVEN a user with `onboarding.completed_at` set
- WHEN they press the Help button
- THEN the tour starts from the Dashboard and no request clears the stored flag

### Requirement: Anchor Contract

Every highlighted element MUST expose exactly one `data-tour="<segment>.<element>"` attribute (segment and element in lowercase). Anchors MUST be stable across re-renders, theme changes, and Inertia swaps, and MUST be attached to an element present in the DOM at step time. `driver.js` MUST receive `[data-tour="…"]` selectors. Anchor names MUST match the inventory in the Step Content Contract.

#### Scenario: Anchor contract holds
- GIVEN any tour page
- WHEN it renders
- THEN every anchor in the Step Content Contract resolves to exactly one element and `[data-tour]` selectors match

### Requirement: Keyboard Suppression

The app's global `ArrowLeft` / `ArrowRight` / `N` shortcuts MUST NOT fire while the tour is active. Every tour page MUST pass `isDialogOpen: () => isTourActive()` to `useKeyboardShortcuts`. While the tour is active, arrow keys MUST drive step navigation and MUST NOT change the selected month; Esc MUST dismiss.

#### Scenario: Arrow keys drive the tour only
- GIVEN the tour is active on the Movimientos page
- WHEN the user presses `ArrowRight`
- THEN the tour advances one step AND the selected month does not change

### Requirement: Theme and Dark-Mode Rendering

The tour popover and overlay MUST consume app design tokens (`--popover`, `--popover-foreground`, `--foreground`, `--border`, `--primary`) and MUST render correctly under all 8 themes and under `.dark`. The driver layer MUST stack above reka-ui portal content (`z-50`), and any open reka-ui overlay MUST be closed before a step targets it.

#### Scenario: Dark mode and themes render correctly
- GIVEN appearance `dark` and any of the 8 themes
- WHEN a tour step renders
- THEN the popover uses app tokens, is legible, and stacks above reka-ui portals

### Requirement: Accessibility

Focus MUST move into the popover on each step and return to a sensible element on exit. Esc, Enter and arrow keys MUST be operable. Popover controls MUST have accessible labels. Under `prefers-reduced-motion: reduce`, driver animation MUST be disabled. Focus MUST NOT be permanently trapped.

#### Scenario: Reduced motion
- GIVEN `prefers-reduced-motion: reduce`
- WHEN the tour advances
- THEN step transitions occur without animation

### Requirement: Mobile Behavior

On mobile the sidebar renders as a reka-ui `Sheet` whose nav-item anchors do not exist until opened. For the shell segment the tour MUST open the Sheet for steps 2–6 and close it afterwards, or skip those steps; it MUST NOT target a non-existent element. Steps 1, 7 and 8 MUST remain available on mobile.

#### Scenario: Mobile shell segment
- GIVEN a mobile viewport
- WHEN the shell segment runs
- THEN the Sheet is opened for steps 2–6 (or those steps are skipped) and no step targets a missing element

### Requirement: Code-Evidenced Traps

- `UserSettings` and its `defaults` MUST include `onboarding`, otherwise `updateSettings()`'s `if (key in settings)` guard silently no-ops the write.
- `hydrateSettings()` MUST nested-merge `onboarding`, otherwise a partial server object wipes the default `version`.
- `UpdateSettingsRequest` MUST add onboarding rules, otherwise the unknown-key drop discards the flag (see the `settings` delta).
- The `useKeyboardShortcuts` collision MUST be resolved as specified in Keyboard Suppression.

## Out of Scope

- Tours for Deudas, Metas, Proyección, settings, or auth pages.
- i18n layer; copy is hardcoded Spanish.
- Any change to `SimulationBanner.vue`.
- Vitest or any new JS test toolchain.
