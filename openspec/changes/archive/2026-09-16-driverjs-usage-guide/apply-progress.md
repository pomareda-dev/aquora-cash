# Apply Progress: Driver.js Usage Guide — PR1 (Settings Persistence) + PR2 (Tour Infrastructure) + PR3 (Shell + Dashboard Segment) + PR4 (Movimientos Segment) + PR5 (Cuentas Segment) + PR6 (Categorías Segment) + PR7 (Recurrentes Segment + Completion Wiring)

- Change: `driverjs-usage-guide`
- Phases: 1 (PR1 — Settings Persistence, tasks 1.1–1.4) + 2 (PR2 — Tour Infrastructure, tasks 2.1–2.5) + 3 (PR3 — Shell + Dashboard Segment, tasks 3.1–3.5) + 4 (PR4 — Movimientos Segment, tasks 4.1–4.3) + 5 (PR5 — Cuentas Segment, tasks 5.1–5.3) + 6 (PR6 — Categorías Segment, tasks 6.1–6.3) + 7 (PR7 — Recurrentes Segment + Completion Wiring, tasks 7.1–7.4) + 8 (Final Verification, tasks 8.1–8.2)
- Mode: Strict TDD
- Batches: 1–8 of 7 chained PRs (stacked-to-main) + final verification, branch `feature/Implement-onboarding-guide` — **COMPLETE (29/29 tasks)**
- Dates: 2026-09-15 (batches 1–4), 2026-09-16 (batches 5–8)

> **Merge protocol**: this file is the cumulative apply-progress for all batches. PR1's, PR2's, PR3's, PR4's, PR5's and PR6's records are preserved verbatim below; PR7's record is appended. See engram topic `sdd/driverjs-usage-guide/apply-progress` for the mirror.

## Completed Tasks

### Batch 1 (PR1 — Settings Persistence)

- [x] 1.1 RED: Append 5 onboarding Pest tests to `tests/Feature/Settings/PreferencesTest.php` (persist, invalid version/date, theme preserves onboarding, re-arm null); run and expect failures.
- [x] 1.2 Implement `onboarding` validation rules in `app/Http/Requests/Settings/UpdateSettingsRequest.php`.
- [x] 1.3 Add `onboarding` type, default, and nested hydration to `resources/js/composables/useSettings.ts`.
- [x] 1.4 GREEN: Run `php artisan test --compact --filter=Preferences`; run Pint on changed PHP.

### Batch 2 (PR2 — Tour Infrastructure)

- [x] 2.1 Install `driver.js` (v1.8.0): `pnpm add driver.js && npm install --package-lock-only`; verified the installed version supports a target-less modal step (`element?:` optional in `DriveStep`; runtime falls back to `#driver-dummy-element` → centered popover); committed `package.json`, `pnpm-lock.yaml`, `package-lock.json` together.
- [x] 2.2 Create `resources/js/composables/useTour.ts` singleton (module-scoped state, lazy driver.js import, `onDestroyStarted` → persist) and `resources/js/composables/usePageTour.ts` hook (registers segment steps + `advance()`).
- [x] 2.3 Create `resources/js/components/tour/TourLauncher.vue` (arm evaluation per REQ-1 on mount; runs the smoke segment) and mount it in `resources/js/layouts/app/AppSidebarLayout.vue` after `<Toaster />`.
- [x] 2.4 Create `resources/js/components/tour/tourSteps.ts` with one target-less smoke step (minimal Spanish placeholder copy, NOT the proposal §4 copy) and add driver.js theme/z-index/reduced-motion overrides to `resources/css/app.css`.
- [x] 2.5 Run `npm run build` (passed) and `npm run types:check` (15 pre-existing errors, 0 new); manual browser smoke of the smoke step + Esc/arrow verification is a **pending manual-verification item for the user** (cannot drive a browser; static launcher-wiring evidence provided — see Work Unit Evidence).

### Batch 3 (PR3 — Shell + Dashboard Segment)

- [x] 3.1 Add shell steps 1–8 and dashboard steps 9–17 to `resources/js/components/tour/tourSteps.ts` from proposal §4 (real Spanish copy, verbatim; smoke placeholder removed).
- [x] 3.2 Add `tourId?: string` to `resources/js/types/navigation.ts`; bind `:data-tour="item.tourId"` in `resources/js/components/NavMain.vue`; add `data-tour="shell.sidebar"` in `resources/js/components/AppSidebar.vue` (plus `tourId` values `nav.movimientos|cuentas|categorias|recurrentes` on the 4 nav items).
- [x] 3.3 Add `data-tour="shell.userMenu"` in `resources/js/components/NavUser.vue`; add `data-tour="shell.simulationToggle"` and a new Help button (`data-tour="shell.help"`, `CircleHelp`, tooltip «Guía de uso`) wired to `restart()` in `resources/js/components/AppSidebarHeader.vue`.
- [x] 3.4 Add 9 dashboard anchors, `usePageTour('dashboard')`, and `isDialogOpen: () => isTourActive()` (REQ-8) in `resources/js/pages/Dashboard.vue`.
- [x] 3.5 Run `npm run build` (✓ 2.75s) and `npm run types:check` (15 pre-existing, 0 new); manual walkthrough of shell + dashboard segments is a **pending manual-verification item for the user** (see return summary — re-arm command + per-segment checklist).

**Launcher flow switch (per design, in PR3 scope):** TourLauncher no longer registers the smoke segment directly; it arms per REQ-1, hands off non-tour pages to the Dashboard, and registers a `setSegmentNextHandler` sequencing hook. `usePageTour('dashboard')` registers shell + dashboard steps; `useTour.advance()` starts the shell segment first on the Dashboard; the handler chains shell → dashboard on the same page, then destroys + `router.visit(movimientos.index().url)` (next slices resume). Mobile: the launcher opens the sidebar Sheet around shell steps 2–6 (`setOpenMobile`), closes it after step 6 and when the tour becomes inactive; `waitForElement: 1000` lets portal-rendered anchors appear before highlighting. Commit `80d10ed`.

### Batch 4 (PR4 — Movimientos Segment)

- [x] 4.1 Add movimientos steps 18–23 to `resources/js/components/tour/tourSteps.ts` from proposal §4 (real Spanish copy, verbatim; `movimientos: []` placeholder replaced).
- [x] 4.2 Add 6 anchors (`movimientos.header|monthNav|create|actuales|proyectados|summary`), `usePageTour('movimientos')`, and extended the existing `isDialogOpen` guard with `isTourActive()` in `resources/js/pages/Movimientos/Index.vue`.
- [x] 4.3 Run `npm run build` (✓ 2.56s) and `npm run types:check` (15 pre-existing, 0 new); conditional-skip behavior for future/past months and empty list verified by executing the real `toDriveSteps()` filter against a stubbed DOM (see Work Unit Evidence); manual browser walkthrough of steps 18–23 is a **pending manual-verification item for the user**.

**REQ-4 conditional-step mechanism (PR4 scope):** `tourSteps.ts` gains an `anchorPresent(anchor)` precondition factory; the three conditional movimientos steps (21 `actuales`, 22 `proyectados`, 23 `summary`) carry a precondition that checks for their own `[data-tour="…"]` node. `toDriveSteps()` now filters steps whose `precondition()` returns false before mapping to `DriveStep`, so an absent target is dropped from the segment (progress counts only available steps) instead of rendering an empty/mispositioned popover or aborting. The driver's global `skipMissingElement` is deliberately left unset — it would also skip the mobile shell step 1 (REQ-11). Because month navigation and the `N` shortcut are suppressed while the tour is active (REQ-8 guard), page state is stable between segment registration and step time.

**Movimientos → Cuentas hand-off (PR4 scope, same precedent as PR3):** `TourLauncher.handleSegmentNext()` gained a `movimientos` branch that destroys the driver and `router.visit(cuentas.index().url)`, mirroring the PR3 dashboard → movimientos branch. PR5–PR7 segments remain empty (`cuentas`, `categorias`, `recurrentes`), so the tour ends at the hand-off until the next slice lands — identical to the intermediate state PR3 shipped after dashboard → movimientos.

### Batch 5 (PR5 — Cuentas Segment)

- [x] 5.1 Add cuentas steps 24–27 to `resources/js/components/tour/tourSteps.ts` from proposal §4 (real Spanish copy, verbatim; `cuentas: []` placeholder replaced; file header comment updated to reflect PR6–PR7 for the remaining empty segments).
- [x] 5.2 Add 4 anchors (`cuentas.header|create|table|reconciliation`) and `usePageTour('cuentas')` in `resources/js/pages/Cuentas/Index.vue`. No keyboard guard needed — Cuentas does not use `useKeyboardShortcuts` (design Trap 1: "Cuentas and Recurrentes do not use `useKeyboardShortcuts` — no change needed").
- [x] 5.3 Run `npm run build` (✓ 1.73s) and `npm run types:check` (15 pre-existing, 0 new); copy byte-faithfulness verified by executing `verify-copy.cjs` against proposal §4; manual browser walkthrough of steps 24–27 is a **pending manual-verification item for the user**.

**Cuentas → Categorías hand-off (PR5 scope, same precedent as PR3/PR4):** `TourLauncher.handleSegmentNext()` gained a `cuentas` branch that destroys the driver and `router.visit(categorias.index().url)`, mirroring the PR4 movimientos → cuentas branch. PR6–PR7 segments remain empty (`categorias`, `recurrentes`), so the tour ends at the hand-off until the next slice lands — identical to the intermediate state PR4 shipped after movimientos → cuentas.

### Batch 6 (PR6 — Categorías Segment)

- [x] 6.1 Add categorías steps 28–31 to `resources/js/components/tour/tourSteps.ts` from proposal §4 (real Spanish copy, verbatim; `categorias: []` placeholder replaced; file header comment updated to reflect PR7 for the remaining empty segment).
- [x] 6.2 Add 4 anchors (`categorias.header|monthNav|create|table`), `usePageTour('categorias')`, and extended the existing `isDialogOpen` guard with `isTourActive()` in `resources/js/pages/Categorias/Index.vue` (REQ-8 — this page DOES use `useKeyboardShortcuts`, same pattern as Dashboard/Movimientos).
- [x] 6.3 Run `npm run build` (✓ 1.68s) and `npm run types:check` (15 pre-existing, 0 new); copy byte-faithfulness verified by executing `verify-pr6.cjs` against proposal §4; manual browser walkthrough of steps 28–31 is a **pending manual-verification item for the user**.

**Categorías → Recurrentes hand-off (PR6 scope, same precedent as PR3/PR4/PR5):** `TourLauncher.handleSegmentNext()` gained a `categorias` branch that destroys the driver and `router.visit(recurrentes.index().url)`, mirroring the PR5 cuentas → categorias branch. The PR7 segment remains empty (`recurrentes`), so the tour ends at the hand-off until the final slice lands — identical to the intermediate state PR5 shipped after cuentas → categorias.

### Batch 7 (PR7 — Recurrentes Segment + Completion Wiring)

- [x] 7.1 Add recurrentes steps 32–36 to `resources/js/components/tour/tourSteps.ts` from proposal §4 (real Spanish copy, verbatim; `recurrentes: []` placeholder replaced). Step 36 is the target-less closing modal (no anchor, no side → driver.js centered popover, same pattern as PR2's old smoke step). No recurrentes step carries a precondition (proposal §4 preconditions are all "None" — the ResponsiveTable always renders with its `#empty` slot, so the empty-list case needs no skip); PR4's `precondition`/`anchorPresent()` mechanism is therefore NOT reused here.
- [x] 7.2 Add 4 anchors (`recurrentes.header|regenerate|create|table`) and `usePageTour('recurrentes')` in `resources/js/pages/Recurrentes/Index.vue`. No keyboard guard needed — Recurrentes does not use `useKeyboardShortcuts` (design Trap 1: "Cuentas and Recurrentes do not use `useKeyboardShortcuts` — no change needed").
- [x] 7.3 **Completion wiring (the user-confirmed defect is resolved).** `TourLauncher.handleSegmentNext()` gained the FINAL `recurrentes` branch: on the last step (the step-36 modal), the done/Listo click now calls `void tour.finish()` — the only code that persists `onboarding` AND disarms — instead of bare `tour.destroy()`. Driver.js `destroy()` (`h(false)`) skips `onDestroyStarted`, so the previous intermediate-hand-off pattern would never run `finish()`; PR7 routes the completed path through it. Dismissal (Esc/close/overlay) was already wired via the driver's `onDestroyStarted` → `finish()`. The finished path does NOT navigate (the tour simply ends on Recurrentes). `restart()` re-arms in memory and performs NO settings write, so the server flag is untouched until completion (REQ-6/S9).
- [x] 7.4 Run `npm run build` (✓ 1.98s), `npm run types:check` (15 pre-existing, 0 new), `php artisan test --compact --filter=Preferences` (37 passed, 124 assertions — PR1's persistence suite regression guard), prettier + eslint clean on all 3 changed files, and the executable completion-wiring check (`/tmp/opencode/pr7-verify/verify-pr7.cjs` → ALL PR7 ASSERTIONS PASSED). The full end-to-end tour across all five pages is the user's browser walkthrough — cumulative checklist in the return summary.

**Final-segment completion wiring (PR7 scope, resolves the confirmed defect):** `useTour.finish()` is the completion path: it resets state (armed=false, isActive=false, currentSegment=null, stepIndex=0), destroys the driver, then `await updateSettings({ onboarding: { completed_at: new Date().toISOString(), version: TOUR_VERSION } })` — the payload contains ONLY the `onboarding` key and the backend shallow-merges it into `users.settings`, preserving theme/density/other keys (Pest SS4). The launcher's `recurrentes` branch is the LAST branch in `handleSegmentNext` — there is NO further hand-off; the chaining ends in completion. Executable proof drives the REAL transpiled `useTour.ts` + the REAL `handleSegmentNext` from `TourLauncher.vue` against a stubbed driver: step-36 done click persists exactly one payload with version 1 + ISO completed_at, disarms, tears down, does not navigate; categorias hand-off does NOT persist (by design); non-last steps advance via `moveNext()`; `onDestroyStarted` dismissal persists the same payload; `restart()` performs zero settings writes.

## TDD Cycle Evidence

> **Stated deviation (PR1 precedent, applied consistently):** this project has NO JS test harness (confirmed decision — no Vitest/Vue Test Utils). Frontend-only slices use `npm run types:check` (vue-tsc) as the safety net and state the deviation explicitly. No PHP production files were touched in PR2, so no new Pest tests were required. If any PHP production file had been touched, strict RED→GREEN with Pest would apply.

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| 1.1 | `tests/Feature/Settings/PreferencesTest.php` | Feature | ✅ 32/32 | ✅ Written (5 tests) | ✅ Passed 37/37 | ✅ 5 spec scenarios SS1–SS5 | ➖ None needed |
| 1.2 | `tests/Feature/Settings/PreferencesTest.php` | Feature | ✅ 32/32 | ✅ (1.1 covers) | ✅ Passed 37/37 | ✅ min:1 + date + nullable/null covered | ➖ None needed |
| 1.3 | N/A — no JS test harness (stated deviation) | Type check | N/A | N/A | ✅ `npm run types:check` — 0 errors from this file | ➖ Single | ➖ None needed |
| 1.4 | `php artisan test --compact --filter=Preferences` | Feature | — | — | ✅ 37/37 | — | ✅ Pint clean |
| 2.1 | N/A — no JS test harness (stated deviation) | Type check / source verification | ✅ 15 pre-existing errors (baseline) | N/A — deviation | ✅ `npm run types:check` still 15, 0 new; target-less modal verified in installed 1.8.0 (`element?` optional + `#driver-dummy-element` fallback) | ➖ Single (one dependency) | ➖ None needed |
| 2.2 | N/A — no JS test harness (stated deviation) | Type check | ✅ 15 pre-existing | N/A — deviation | ✅ `npm run types:check` 15, 0 new; `npm run build` passed | ➖ Single (contract API) | ➖ None needed |
| 2.3 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ `npm run build` ✓ built in 2.89s; `types:check` 15, 0 new | ➖ Single (one mount) | ➖ None needed |
| 2.4 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ `npm run build` passed; `types:check` 15, 0 new | ➖ Single (one smoke step) | ➖ None needed |
| 2.5 | `npm run build` + `npm run types:check` | Build + type check | ✅ 15 pre-existing | N/A — deviation | ✅ build passed; types 15, 0 new | ➖ Single | ➖ None needed |
| 3.1 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ `npm run types:check` 15, 0 new; `npm run build` ✓ 2.75s; 17 steps with copy byte-matched to proposal §4 | ✅ 17 steps (8 shell + 9 dashboard), 2 segments | ➖ None needed |
| 3.2 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; anchors `nav.*` ×4 + `shell.sidebar` resolve to `<li>`/`<Sidebar>` roots | ✅ 5 anchors | ➖ None needed |
| 3.3 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; `shell.userMenu` on trigger, `shell.simulationToggle` + `shell.help` on header buttons; Help → `restart()` | ✅ 3 anchors + 1 wiring | ➖ None needed |
| 3.4 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing (7 Dashboard pre-existing errors NOT touched) | N/A — deviation | ✅ types 15, 0 new (Dashboard still exactly its 7 pre-existing `simulated_amount`/`is_sandbox` errors); build ✓; 9 anchors + `usePageTour('dashboard')` + REQ-8 guard | ✅ 9 anchors | ➖ None needed |
| 3.5 | `npm run build` + `npm run types:check` | Build + type check | ✅ 15 pre-existing | N/A — deviation | ✅ build ✓ 2.75s; types 15, 0 new | ➖ Single | ➖ None needed |
| 4.1 | N/A — no JS test harness (stated deviation) | Type check + build + executable filter check | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓ 2.56s; copy byte-faithful vs proposal §4 (6/6 steps) | ✅ 6 steps (3 with preconditions), anchor/side/title/description all asserted | ➖ None needed |
| 4.2 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; 6 anchors resolve to `<div>`/`<Card>`/`<Button>` single roots; REQ-8 guard extended | ✅ 6 anchors + 1 wiring | ➖ None needed |
| 4.3 | `npm run build` + `npm run types:check` + `/tmp/opencode/pr4-verify/verify-req4.cjs` | Build + type check + executable REQ-4 check | ✅ 15 pre-existing | N/A — deviation | ✅ build ✓ 2.56s; types 15, 0 new; skip assertions PASSED (6/5/5/5) | ✅ 4 states (current / future / past / empty) | ➖ None needed |
| 5.1 | N/A — no JS test harness (stated deviation) | Type check + build + executable copy check | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓ 1.73s; copy byte-faithful vs proposal §4 (4/4 steps) | ✅ 4 steps, anchor/side/title/description all asserted | ➖ None needed |
| 5.2 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; 4 anchors resolve to single-root elements (`<div>`/`<Button>`/`ResponsiveTable`/`<Card>`); no keyboard guard needed (Cuentas has no `useKeyboardShortcuts`) | ✅ 4 anchors + 1 wiring | ➖ None needed |
| 5.3 | `npm run build` + `npm run types:check` + `/tmp/opencode/pr5-verify/verify-copy.cjs` | Build + type check + executable copy check | ✅ 15 pre-existing | N/A — deviation | ✅ build ✓ 1.73s; types 15, 0 new; copy assertions PASSED (4/4 steps, no preconditions, anchors in page, `usePageTour('cuentas')` wired, hand-off present) | ✅ 4 steps + wiring + hand-off | ➖ None needed |
| 6.1 | N/A — no JS test harness (stated deviation) | Type check + build + executable copy check | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓ 1.68s; copy byte-faithful vs proposal §4 (4/4 steps) | ✅ 4 steps, anchor/side/title/description all asserted | ➖ None needed |
| 6.2 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; 4 anchors resolve to single-root elements (`<div>`/`<Button>`/`ResponsiveTable`); REQ-8 guard extended (Categorias uses `useKeyboardShortcuts` — arrows only) | ✅ 4 anchors + 1 wiring + 1 guard | ➖ None needed |
| 6.3 | `npm run build` + `npm run types:check` + `/tmp/opencode/pr6-verify/verify-pr6.cjs` | Build + type check + executable copy check | ✅ 15 pre-existing | N/A — deviation | ✅ build ✓ 1.68s; types 15, 0 new; copy assertions PASSED (4/4 steps, no preconditions, anchors in page, `usePageTour('categorias')` wired, guard extended, hand-off present) | ✅ 4 steps + wiring + guard + hand-off | ➖ None needed |
| 7.1 | N/A — no JS test harness (stated deviation) | Type check + build + executable copy check | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓ 1.98s; copy byte-faithful vs proposal §4 (5/5 steps); step 36 target-less (no anchor, no side) | ✅ 5 steps (4 anchored + 1 modal), no preconditions | ➖ None needed |
| 7.2 | N/A — no JS test harness (stated deviation) | Type check + build | ✅ 15 pre-existing | N/A — deviation | ✅ types 15, 0 new; build ✓; 4 anchors resolve to single-root elements (`<div>`/`<Button>`×2/`ResponsiveTable`); `usePageTour('recurrentes')` wired; no keyboard guard needed (no `useKeyboardShortcuts`) | ✅ 4 anchors + 1 wiring | ➖ None needed |
| 7.3 | `php artisan test --compact --filter=Preferences` + `/tmp/opencode/pr7-verify/verify-pr7.cjs` | Feature regression + executable completion-wiring check | ✅ 37/37 (PR1 baseline preserved) | N/A — deviation (completion wiring exercised via transpiled real source, see Work Unit Evidence) | ✅ Pest 37 passed / 124 assertions; **ALL PR7 ASSERTIONS PASSED** — real `handleSegmentNext` on the step-36 modal persists exactly one `{onboarding: {completed_at: ISO, version: 1}}` payload, disarms (armed=false, isActive=false, currentSegment=null), tears the driver down, does NOT navigate; dismissal (`onDestroyStarted`) persists the same payload; intermediate hand-offs do NOT persist; `restart()` performs zero settings writes | ✅ 5 completion paths (finish / dismiss / hand-off / advance / restart) | ➖ None needed |
| 7.4 | `npm run build` + `npm run types:check` + Pest + prettier + eslint | Build + type check + feature regression + linters | ✅ 15 pre-existing | N/A — deviation | ✅ build ✓ 1.98s; types 15, 0 new; Pest 37/37; prettier clean ×3; eslint 0 issues ×3; verify-pr7.cjs ALL PASSED | ➖ Single (full end-to-end is the user's browser walkthrough) | ➖ None needed |

### Test Summary (cumulative)
- **Total tests written**: 5 (PR1 Pest) — PR2–PR7 frontend-only, no harness (stated deviation)
- **Total tests passing**: 5 (37/37 focused run; baseline 32/32 preserved)
- **Layers used**: Feature (5) + Build/Type-check (PR2–PR7) + executable REQ-4 filter check (PR4) + executable copy checks (PR4, PR5, PR6, PR7) + executable completion-wiring check (PR7, real transpiled useTour + launcher handler)
- **Approval tests** (refactoring): None — no refactoring tasks
- **Pure functions created**: 1 (`toDriveSteps` in `tourSteps.ts` — pure step conversion; gained REQ-4 precondition filtering in PR4)

## Work Unit Evidence

### Unit 1 (PR1 — Settings onboarding flag round-trip)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `php artisan test --compact --filter=Preferences` → 37 passed, 124 assertions (was 32/32 baseline; 5 new tests added, all pass) |
| Runtime harness command/scenario and exact result | Backend: covered by the Pest feature tests above (real HTTP PUT against `settings.update`, real MySQL test DB). Frontend: `npm run types:check` (`vue-tsc --noEmit`) → 15 pre-existing errors, identical with and without this change (proven via stash); **0 errors attributable to PR1**. No browser runtime boundary exists for persistence plumbing in this slice (tour UI lands in PR2+). |
| Rollback boundary | Revert `app/Http/Requests/Settings/UpdateSettingsRequest.php` + `resources/js/composables/useSettings.ts` + the 5 tests in `tests/Feature/Settings/PreferencesTest.php` — single commit `feat: add onboarding settings persistence`; no migration, no schema change, stored `onboarding` key becomes inert |

### Unit 2 (PR2 — Driver.js + launcher + one smoke step)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR2** (identical count to the PR1 baseline; all 15 are the known pre-existing categories: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types). `npm run build` → **✓ built in 2.89s** (driver.js split into its own lazy chunk `driver.js-C7MFaeA3.js`, 25.35 kB / 7.20 kB gzip). |
| Runtime harness command/scenario and exact result | Browser smoke of the smoke step: **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with static wiring evidence instead: launcher mounts in the shared authenticated layout (`AppSidebarLayout.vue`), arm logic reads `settings.onboarding` (REQ-1), the target-less smoke step is registered and started, and the driver config persists completion on every destroy path (`onDestroyStarted`, verified in driver.js 1.8.0 source: `h(true)` invokes the hook and returns — the hook must call `destroy()` to complete cleanup, which `finish()` does). Manual smoke instructions for the user are listed in the return summary. |
| Rollback boundary | Revert commits `b0284c0` (`feat: add driver.js tour infrastructure`) + `4cc1dc3` (`style: fix eslint errors in tour infrastructure`, post-phase normalization) — 9 files: `package.json` + both lockfiles, `resources/js/composables/useTour.ts`, `usePageTour.ts`, `resources/js/components/tour/TourLauncher.vue`, `tourSteps.ts`, `resources/css/app.css`, `resources/js/layouts/app/AppSidebarLayout.vue`. Reverting PR2 alone disables the tour while leaving the PR1 persistence flag inert. `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. |

### Unit 3 (PR3 — Shell + Dashboard segment)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR3** (identical count and categories to the baseline: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types — Dashboard's 7 are pre-existing, only their line numbers shifted by the added anchors). `npm run build` → **✓ built in 2.75s**. |
| Runtime harness command/scenario and exact result | Browser walkthrough of shell + dashboard steps 1–17: **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with static wiring evidence instead: 17 anchors match the REQ-3 inventory (`shell.sidebar`, `nav.movimientos|cuentas|categorias|recurrentes`, `shell.userMenu`, `shell.simulationToggle`, `shell.help`, `dashboard.header|monthNav|metrics|budget|reconciliation|debts|goals|upcoming|chart`); Dashboard registers `usePageTour('dashboard')` which arms the shell segment first (REQ-2); the launcher's `setSegmentNextHandler` chains shell → dashboard → `router.visit(movimientos.index().url)` (S4), opens/closes the mobile Sheet around steps 2–6 (REQ-11, design decision a) with `waitForElement: 1000` for portal anchors, and closes the Sheet when the tour ends; REQ-8 suppression wired via `isDialogOpen: () => isTourActive()`. Manual checklist for the user is in the return summary. |
| Rollback boundary | Revert commits `80d10ed` (`feat: add shell and dashboard tour segments`) + `f26e064` (`fix: dedupe tour starts and tear down before segment chaining`) + `68f9c1c` (`fix: sweep stale driver dom on run and destroy`) — 10 files: `tourSteps.ts`, `types/navigation.ts`, `components/NavMain.vue`, `AppSidebar.vue`, `NavUser.vue`, `AppSidebarHeader.vue`, `pages/Dashboard.vue`, `composables/usePageTour.ts`, `composables/useTour.ts`, `components/TourLauncher.vue`. Reverting PR3 alone restores the PR2 smoke-tour behavior; `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. PR3 authored size: **338 changed lines (302 + / 36 −)** + 80 fix lines — within the 400-line budget, no `size:exception` needed. |

### Pre-review runtime fix (PR3, commit `f26e064`)

The user's manual walkthrough found two real defects, both fixed before any review freeze:

1. **Frozen step 1 (duplicate driver instances)**: `restart()` navigates to the Dashboard → the page remount fires `usePageTour`'s `advance()` → `run('shell')` #1, and the visit's `onSuccess` fires `start('shell')` #2. Both calls entered `ensureDriver()` while the first `await import('driver.js')` was still pending, so both saw `driverInstance === null` and **built two drivers** — the orphaned instance left a frozen step-1 popover on screen. Fix: memoize the construction promise (`driverPromise`) and make `run()` idempotent (`isActive && currentSegment === segment` → return).
2. **Frozen step 8 (orphaned render on chaining)**: the shell→dashboard chain called `start('dashboard')` on the live instance; driver.js `setSteps()` runs `resetState()`, which drops the popover/overlay removal handles, so the step-8 render stayed orphaned in the DOM. Fix: `tour.destroy()` before `tour.start('dashboard')` in the launcher's sequencing handler (the dashboard→movimientos hand-off already did this correctly — which is why the dashboard segment ended clean).

Re-verified after the fix: prettier + eslint clean on both files, `npm run build` ✓ 1.84s, `npm run types:check` 15 pre-existing / 0 new. A second walkthrough round surfaced one stale pre-fix orphan popover (Vite HMR does not clean `<body>` debris; the frozen "1 de 8" dialog was a zombie from the pre-fix round, not the live flow — step 8 and the dashboard segment were already clean). Hardening commit `68f9c1c fix: sweep stale driver dom on run and destroy` (+21 lines): `sweepStaleDriverDom()` runs in `run()` and `destroy()` and removes `#driver-popover-content`, `.driver-overlay`, and `#driver-dummy-element` leftovers. **User walkthrough PASSED after 68f9c1c**: (?) restart → steps 1–8 advance with a single popover, X/Esc clears everything, step 8 chains cleanly into dashboard 1–17, dashboard segment ends clean.

### Unit 4 (PR4 — Movimientos segment)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR4** (identical count and categories to the baseline: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types). `npm run build` → **✓ built in 2.56s**. `node /tmp/opencode/pr4-verify/verify-req4.cjs` → **ALL REQ-4 SKIP ASSERTIONS PASSED**: current month 6 steps; future month 5 (step 21 `actuales` skipped, S6); past month 5 (step 22 `proyectados` skipped); empty list 5 (step 23 `summary` skipped); every surviving step's selector conforms to `[data-tour="movimientos.*"]`. `node /tmp/opencode/pr4-verify/verify-copy.cjs` → **COPY BYTE-FAITHFUL: all 6 movimientos steps match proposal §4** (anchor/side/title/description), preconditions wired on 21/22/23, each queries its own anchor, true when present / false when absent. |
| Runtime harness command/scenario and exact result | Browser walkthrough of movimientos steps 18–23 chained after the dashboard segment (highlighting on the 6 anchors, conditional skips, `movimientos` → `cuentas` hand-off): **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with static wiring + executable-filter evidence instead (above). Manual checklist for the user is in the return summary. |
| Rollback boundary | Revert the PR4 commit (3 files: `resources/js/components/tour/tourSteps.ts`, `resources/js/pages/Movimientos/Index.vue`, `resources/js/components/tour/TourLauncher.vue`) — movimientos returns to an empty segment and the launcher falls back to the PR3 dashboard→movimientos hand-off behavior; PR1–PR3 remain intact. `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. PR4 authored size: **152 changed lines (122 + / 30 −)** — within the 400-line budget, no `size:exception` needed. |

### Unit 5 (PR5 — Cuentas segment)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR5** (identical count and categories to the baseline: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types). `npm run build` → **✓ built in 1.73s**. `node /tmp/opencode/pr5-verify/verify-copy.cjs` → **COPY BYTE-FAITHFUL: all 4 cuentas steps match proposal §4** (anchor/side/title/description), no preconditions (all "None"), all 4 anchors present in `Cuentas/Index.vue`, `usePageTour('cuentas')` wired, `toDriveSteps` produces 4 steps, hand-off to categorias present. |
| Runtime harness command/scenario and exact result | Browser walkthrough of cuentas steps 24–27 chained after the movimientos segment (highlighting on the 4 anchors, `cuentas` → `categorias` hand-off): **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with static wiring + executable copy-check evidence instead (above). Manual checklist for the user is in the return summary. |
| Rollback boundary | Revert the PR5 commit (3 files: `resources/js/components/tour/tourSteps.ts`, `resources/js/pages/Cuentas/Index.vue`, `resources/js/components/tour/TourLauncher.vue`) — cuentas returns to an empty segment and the launcher falls back to the PR4 movimientos→cuentas hand-off behavior; PR1–PR4 remain intact. `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. PR5 authored size: **57 changed lines (52 + / 5 −)** — within the 400-line budget, no `size:exception` needed. |

### Unit 6 (PR6 — Categorías segment)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR6** (identical count and categories to the baseline: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types). `npm run build` → **✓ built in 1.68s**. `node /tmp/opencode/pr6-verify/verify-pr6.cjs` → **ALL PR6 ASSERTIONS PASSED**: copy byte-faithful vs proposal §4 (4/4 steps, no preconditions), all 4 anchors present in `Categorias/Index.vue`, `usePageTour('categorias')` wired, REQ-8 guard extended, categorias→recurrentes hand-off present. |
| Runtime harness command/scenario and exact result | Browser walkthrough of categorías steps 28–31 chained after the cuentas segment (highlighting on the 4 anchors, arrow-key navigation NOT changing the month thanks to the REQ-8 guard, `categorias` → `recurrentes` hand-off): **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with static wiring + executable copy-check evidence instead (above). Manual checklist for the user is in the return summary. |
| Rollback boundary | Revert the PR6 commit (3 files: `resources/js/components/tour/tourSteps.ts`, `resources/js/pages/Categorias/Index.vue`, `resources/js/components/tour/TourLauncher.vue`) — categorias returns to an empty segment and the launcher falls back to the PR5 cuentas→categorias hand-off behavior; PR1–PR5 remain intact. `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. PR6 authored size: **66 changed lines (59 + / 7 −)** — within the 400-line budget, no `size:exception` needed. |

### Unit 7 (PR7 — Recurrentes segment + completion wiring)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | `npm run types:check` (`vue-tsc --noEmit`) → **15 errors, 0 attributable to PR7** (identical count and categories to the baseline: 7 Wayfinder-generated duplicate object keys, 1 BalanceLineChart `borderDash` readonly tuple, 7 Dashboard `simulated_amount`/`is_sandbox` prop types). `npm run build` → **✓ built in 1.98s**. `php artisan test --compact --filter=Preferences` → **37 passed, 124 assertions** (PR1's onboarding persistence suite — regression guard for task 7.3; baseline preserved). `prettier --check` → clean on all 3 files. `npx eslint` → **0 issues** on all 3 files. |
| Runtime harness command/scenario and exact result | Browser walkthrough of recurrentes steps 32–36 chained after the categorias segment, the step-36 modal's done click persisting completion, dismissal persisting, and Help restart re-arming without clearing the server flag: **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with executable evidence instead: `node /tmp/opencode/pr7-verify/verify-pr7.cjs` → **ALL PR7 ASSERTIONS PASSED** — the check drives the REAL transpiled `useTour.ts` and the REAL `handleSegmentNext` extracted from `TourLauncher.vue` against a stubbed driver: (1) step-36 done/Listo click → exactly one settings write `{onboarding:{completed_at:ISO,version:1}}`, armed=false, isActive=false, currentSegment=null, driver torn down, NO navigation; (2) categorias→recurrentes hand-off does NOT persist (by design) and navigates; (3) non-last recurrentes step advances via `moveNext()`; (4) dismissal via the real `onDestroyStarted` closure persists the same payload; (5) `restart()` performs zero settings writes and re-arms. Manual checklist for the user is in the return summary. |
| Rollback boundary | Revert the PR7 commit (3 files: `resources/js/components/tour/tourSteps.ts`, `resources/js/pages/Recurrentes/Index.vue`, `resources/js/components/tour/TourLauncher.vue`) — recurrentes returns to an empty segment and the launcher falls back to the PR6 categorias→recurrentes hand-off behavior (the tour ends at the hand-off without persisting completion — the pre-PR7 intermediate state); PR1–PR6 remain intact. `opencode.json` (unrelated local modification) and `openspec/` planning dir were intentionally NOT staged. PR7 authored size: **65 changed lines (60 + / 5 −)** — within the 400-line budget, no `size:exception` needed. |

## Deviations from Design (PR1)

None — implementation matches `design.md` traps 1–4:
- Trap 1 (whitelist): added `onboarding`, `onboarding.completed_at`, `onboarding.version` rules.
- Trap 2 (key-presence guard): `onboarding` added to `UserSettings` interface and `defaults`.
- Trap 3 (shallow merge): `hydrateSettings()` now nested-merges `onboarding`.
- Trap 4 (controller shallow `array_merge`): preserved unchanged; verified by "theme preserves onboarding" test.

## Deviations from Design (PR2)

All deviations are evidence-based adaptations to the **installed driver.js 1.8.0** (the design itself says "verify against the installed driver.js version"):

1. **`overlayClickNext: false` does not exist in 1.8.0.** Replaced with `overlayClickBehavior: 'close'` — overlay click dismisses the tour and persists completion, matching REQ-5's "dismissal (close button, Esc, or overlay)".
2. **CSS class names are from an older driver.js.** 1.8.0 uses `.driver-popover-footer-btn` (not `.driver-popover-btn`/`.driver-popover-btn-secondary`). The primary/secondary mapping is now `.driver-popover-next-btn` (primary tokens) and `.driver-popover-prev-btn` (secondary tokens); disabled stays `.driver-popover-btn-disabled`.
3. **z-index override value.** Design prescribed `z-index: 100 !important`; verification shows 1.8.0 ships overlay at inline `10000` and popover at `1000000000`, and sonner toasts render at `999999999` (all reka-ui portals at z-50). Setting 100 would drop the popover below toasts, so the override keeps the verified-good values (`10000` / `1000000000` with `!important` as an explicit, future-proof floor).
4. **`!important` on theme rules.** driver.css is injected at runtime from the lazy chunk, so equal-specificity app.css rules would lose the cascade tie; `!important` guarantees app tokens win.
5. **`.driver-overlay { opacity: 0.5 }` (design CSS) replaced by `overlayOpacity: 0.5` config.** 1.8.0 renders the overlay as an SVG whose path carries inline opacity; a CSS opacity on the SVG root would multiply to ~0.35.
6. **`router.visit(dashboard())` (design sketch) → `router.visit(dashboard().url)`.** The generated Wayfinder route returns a `RouteDefinition` object (`{ url, method }`); Inertia needs the URL string.
7. **`start()` accepts an optional segment param** (`start(segment?: TourSegment)`) and `setArmed()` is exposed — minimal extensions to the design contract so the launcher can arm (REQ-1) and start the smoke segment; the design's `start: () => void` remains callable with no args.
8. **TourStep.side type is `top|right|bottom|left`** (matches driver 1.8.0's `Side`); the design's `'over'` option is not part of the installed API — target-less steps simply omit `side`/`element` (centered modal).

## Deviations from Design (PR3)

1. **Design's claim that `shell.sidebar` exists on both viewports is FALSE (source-verified).** `Sidebar.vue` forwards `$attrs` to `<Sheet>` on mobile; reka-ui `DialogRoot` has `inheritAttrs: false` and never binds `$attrs`, so `data-tour="shell.sidebar"` is dropped on mobile (no DOM node). Per REQ-11 the step must remain available, so on mobile step 1 renders via driver.js's documented missing-element fallback (centered modal after `waitForElement`), while steps 2–6 use the real Sheet anchors (design decision (a), implemented) and steps 7–8 use the always-present header buttons. Fixing the root cause would require modifying the shared `ui/sidebar/Sidebar.vue` component — deliberately avoided to keep PR3 inside the proposal's affected-file list; noted for a follow-up if the full tour wants a real mobile highlight on step 1.
2. **Segment sequencing implemented via a launcher-registered `setSegmentNextHandler` hook (new `useTour` API surface) instead of the design's prose "launcher owns sequencing".** The driver's `onNextClick` callback lives in `useTour.ts` (single driver factory), so the launcher cannot intercept next/done clicks without a registration hook. This is the minimal extension that keeps sequencing in `TourLauncher.vue` as designed; `useTour.ts` stays a generic driver wrapper. `advance()` gained the shell-first special case for `dashboard` (REQ-2: shell steps 1–8 run before dashboard steps 9–17 on the same page).
3. **`waitForElement: 1000` added to the driver config** (design said "wait for next tick"). The driver-native MutationObserver wait is strictly more robust for the teleported Sheet portal; a missing element still falls back (modal) after the wait. `skipMissingElement` is deliberately NOT set in PR3: enabling it would SKIP step 1 on mobile (violating REQ-11), and PR3 has no conditional steps — REQ-4's skip behavior lands with PR4's movimientos segment.
4. **The design's driver-config sketch listed `overlayClickNext: false`; the installed 1.8.0 uses `overlayClickBehavior: 'close'`** — already resolved in PR2 (deviation 1, PR2), carried forward unchanged.
5. **`restart()` (bedaa3f) now coexists with page-registration auto-start**: after a restart visit, the Dashboard's `usePageTour` may ALSO start the shell segment; both paths converge to the same `run('shell')` state, so `onSuccess: () => start('shell')` is kept as the no-remount safety net (same-page visits do not re-run `onMounted`). No behavior conflict observed; both are idempotent.

## Deviations from Design (PR4)

1. **REQ-4 skip implemented per-step via a `precondition` filter in `toDriveSteps()`, not the driver's global `skipMissingElement`.** The design's `TourStep.precondition` field existed but was inert; PR4 wires it: `anchorPresent(anchor)` checks for the step's own `[data-tour="…"]` node at segment registration (page mount, before the driver is driven) and `toDriveSteps()` drops steps whose precondition returns false. The design prose ("skip the step and advance") is satisfied without enabling `skipMissingElement`, which would also skip the mobile shell step 1 (REQ-11) — see PR3 deviation 3. Progress text (`{{current}} de {{total}}`) counts only the available steps.
2. **Movimientos → Cuentas hand-off added in `TourLauncher.vue`.** Tasks 4.1–4.3 do not list TourLauncher, but design.md's data flow prescribes `router.visit(cuentas.index())` after step 23, and PR3 established the precedent of the completing PR adding its segment's hand-off (PR3's rollback boundary includes TourLauncher even though tasks 3.1–3.5 omit it). The hand-off mirrors the dashboard branch exactly (`tour.destroy()` then `router.visit(cuentas.index().url)`); it is inert until PR5 lands the cuentas segment (tour ends at the hand-off), identical to the PR3 intermediate state.
3. **`precondition` is evaluated once at segment registration, not per highlight.** DOM presence for the three conditional anchors is decided by page state (`v-if="!isFutureMonth"`, `v-if="!isPastMonth"`, `v-if="realMovements.length > 0"`), which cannot change while the segment runs because REQ-8 suppresses month navigation and the `N` shortcut, and the driver overlay blocks clicks. No dynamic re-check is needed.

## Deviations from Design (PR5)

None — implementation matches `design.md`:
- Anchor inventory matches the design's Cuentas segment exactly (`cuentas.header`, `cuentas.create`, `cuentas.table`, `cuentas.reconciliation`).
- `usePageTour('cuentas')` added; no keyboard guard needed per design Trap 1 (Cuentas does not use `useKeyboardShortcuts`).
- No conditional steps in this segment (all proposal §4 preconditions are "None"), so PR4's `precondition`/`anchorPresent()` mechanism is not reused here.
- The Cuentas → Categorías hand-off was added in `TourLauncher.vue` following the PR4 precedent (design.md's data flow prescribes `router.visit(categorias.index())` after step 27; tasks 5.1–5.3 omit TourLauncher, exactly as PR3/PR4 did).

## Deviations from Design (PR6)

None — implementation matches `design.md`:
- Anchor inventory matches the design's Categorías segment exactly (`categorias.header`, `categorias.monthNav`, `categorias.create`, `categorias.table`).
- `usePageTour('categorias')` added; REQ-8 keyboard guard extended exactly per design Trap 1 (`isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value || isTourActive()` — Categorias binds `ArrowLeft`/`ArrowRight`, no `N`).
- No conditional steps in this segment (all proposal §4 preconditions are "None"), so PR4's `precondition`/`anchorPresent()` mechanism is not reused here.
- The Categorías → Recurrentes hand-off was added in `TourLauncher.vue` following the PR5 precedent (design.md's data flow prescribes `router.visit(recurrentes.index())` after step 31; tasks 6.1–6.3 omit TourLauncher, exactly as PR3/PR4/PR5 did).

## Deviations from Design (PR7)

1. **The final-step completion path is implemented as an explicit `recurrentes` branch in `TourLauncher.handleSegmentNext()` calling `void tour.finish()`.** design.md's data flow prescribes "Step 36 (modal) → finish/dismiss → updateSettings({ onboarding: {...} })" and the launcher owns "the completion write", but the design does not spell out the click-path mechanics — it does not state that the done/Listo button routes through `onNextClick` → `handleSegmentNext` (whose intermediate branches use bare `tour.destroy()`, which skips `onDestroyStarted`, so `finish()` never runs). The minimal faithful reading — the last recurrentes step must reach `finish()`, the only code that persists AND disarms — is implemented, and the ambiguity is surfaced in the return summary: if a future design intends the final step to be dismissible-only (no done click), the `recurrentes` branch would be unnecessary; the implemented reading matches REQ-5/S7 ("WHEN the user finishes THEN updateSettings writes onboarding") and the user-confirmed defect description.
2. **No `anchorPresent()` reuse in this segment.** The task prompt asked to check whether any recurrentes step is conditional; proposal §4 lists precondition "None" for all 5 steps, and `recurrentes.table` targets a `ResponsiveTable` that always renders (its `#empty` slot handles the empty-list case), so the empty-list case needs no skip. REQ-4 explicitly says step 36 "having no target, MUST render regardless of page content".

## Issues Found

1. (PR1) `npm run types:check` reports 15 pre-existing TypeScript errors on branch `feature/Implement-onboarding-guide` (Wayfinder-generated `resources/js/actions/App/Http/Controllers/*.ts` duplicate object keys, `BalanceLineChart.vue` chartjs `borderDash` readonly tuple, `Dashboard.vue` `simulated_amount`/`is_sandbox` on prop types). Verified pre-existing: identical error count (15) with PR1 changes stashed. **Not introduced by any PR** — recommend a separate follow-up fix (out of scope) or explicit acceptance for the verify phase.
2. (PR2) The browser smoke of the smoke step (visual render, Esc/arrow keys) cannot be executed by this agent — flagged as a pending manual-verification item with exact instructions (see return summary). The automated gates (`npm run build`, `npm run types:check` with 0 new errors) pass.
3. (PR3) The browser walkthrough of shell + dashboard steps 1–17 (step rendering on anchors, mobile Sheet open/close, Esc/arrows during tour, restart via Help button) cannot be executed by this agent — flagged as a pending manual-verification item with exact instructions (see return summary). The automated gates pass.
4. (PR3) Pre-existing eslint violation in `Dashboard.vue` `displayUpcoming` (`@stylistic/padding-line-between-statements`, verified present at HEAD before this batch) — NOT introduced by PR3; left untouched as out of scope.
5. (PR3) Mid-tour manual navigation (user clicks a sidebar link during the tour) leaves the driver popover orphaned until the next segment action — same gap as PR2's smoke behavior; not a PR3 regression, noted for the final pass.
6. (PR4) `Movimientos/Index.vue` carries **12 pre-existing eslint errors** (verified identical at HEAD via `git stash`: 1 `@typescript-eslint/no-unused-vars` on `sandboxColumns`, 11 `@stylistic/padding-line-between-statements` in the sandbox/reorder computeds). PR4 introduced **0 new** eslint issues; the file was not in PR3's normalization set, so the pre-existing violations are out of scope (same treatment as Dashboard.vue's pre-existing padding violation).
7. (PR4) The browser walkthrough of movimientos steps 18–23 (rendering on the 6 anchors, future/past-month and empty-list skips in a real browser, `movimientos` → `cuentas` hand-off, `?` restart into the segment) cannot be executed by this agent — flagged as a pending manual-verification item with exact instructions (see return summary). The automated gates and the executable REQ-4 filter check pass.
8. (PR5) The browser walkthrough of cuentas steps 24–27 (rendering on the 4 anchors, `cuentas` → `categorias` hand-off after step 27, `?` restart into the segment) cannot be executed by this agent — flagged as a pending manual-verification item with exact instructions (see return summary). The automated gates and the executable copy check pass. Cuentas has no conditional steps, so no REQ-4 skip behavior applies to this segment.
9. (PR6) The browser walkthrough of categorías steps 28–31 (rendering on the 4 anchors, arrow keys NOT changing the selected month while the tour drives them, `categorias` → `recurrentes` hand-off after step 31, `?` restart into the segment) cannot be executed by this agent — flagged as a pending manual-verification item with exact instructions (see return summary). The automated gates and the executable copy check pass. Categorías has no conditional steps, so no REQ-4 skip behavior applies to this segment.
10. (PR7) The browser walkthrough of recurrentes steps 32–36 (rendering on the 4 anchors, the step-36 modal, its done/Listo click persisting completion, Esc/✕/overlay dismissal persisting the same payload, and Help restart re-arming without clearing the server flag) cannot be executed by this agent — flagged as the final pending manual-verification item with the cumulative end-to-end checklist in the return summary. The automated gates (build, types:check 15 pre-existing / 0 new, Pest 37/37) and the executable completion-wiring check (verify-pr7.cjs, ALL PASSED — real transpiled useTour + real launcher handler) pass. Recurrentes has no conditional steps, so no REQ-4 skip behavior applies to this segment.

## Workload / PR Boundary

- Mode: chained PR slice (PR7 of 7, stacked-to-main) — **FINAL batch; change is feature-complete (29/29 tasks)**
- Current work unit: Unit 7 — "Recurrentes segment + completion wiring"
- Boundary: starts at PR6 commit `2073424`, ends with the PR7 commit `feat: add recurrentes tour segment and completion`
- Estimated review budget impact: 3 files, **65 authored lines** (60 additions + 5 deletions). Within the 400-line budget — no `size:exception` needed.

## Verification Results (cumulative)

- (PR1) `php artisan test --compact --filter=Preferences`: **37 passed, 124 assertions** (baseline 32 preserved + 5 new)
- (PR1) `vendor/bin/pint --dirty --format agent`: **passed** (no style changes required)
- (PR2) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR2** (identical to baseline)
- (PR2) `npm run build`: **✓ built in 2.89s**; driver.js in its own lazy chunk (25.35 kB / 7.20 kB gzip)
- (PR2 normalization, commit `4cc1dc3`) Source-mutating normalizers converged pre-review (orchestrator): `prettier --check` clean and `eslint` 0 issues on all PR2 files — fixed 2 `@stylistic/padding-line-between-statements` in `useTour.ts` (eslint `--fix`) and 1 `vue/valid-template-root` in `TourLauncher.vue` (comment-only template → hidden single-root `<span hidden></span>`). Re-verified on the normalized bytes: `npm run build` ✓ 1.98s, `npm run types:check` 15 pre-existing / 0 new.
- (PR3) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR3** (identical count + categories to baseline; Dashboard's 7 pre-existing errors only shifted line numbers)
- (PR3) `npm run build`: **✓ built in 2.75s**
- (PR3) `prettier --check` on all 10 changed files: **clean**; `eslint` on all 10: **0 issues introduced** (1 pre-existing `Dashboard.vue` padding violation verified present at HEAD — untouched)
- (PR4) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR4** (identical count + categories to baseline)
- (PR4) `npm run build`: **✓ built in 2.56s**
- (PR4) `prettier --check` on all 3 changed files: **clean**; `eslint`: **0 issues introduced** (tourSteps.ts + TourLauncher.vue clean; Movimientos/Index.vue keeps its 12 pre-existing violations, verified identical at HEAD via stash)
- (PR4) `node /tmp/opencode/pr4-verify/verify-req4.cjs`: **ALL REQ-4 SKIP ASSERTIONS PASSED** (6/5/5/5 steps for current/future/past/empty states)
- (PR4) `node /tmp/opencode/pr4-verify/verify-copy.cjs`: **COPY BYTE-FAITHFUL: all 6 movimientos steps match proposal §4**; preconditions wired on 21/22/23
- (PR5) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR5** (identical count + categories to baseline)
- (PR5) `npm run build`: **✓ built in 1.73s**
- (PR5) `prettier --check` on all 3 changed files: **clean**; `eslint`: **0 issues on all 3 changed files** (tourSteps.ts, Cuentas/Index.vue, TourLauncher.vue)
- (PR5) `node /tmp/opencode/pr5-verify/verify-copy.cjs`: **COPY BYTE-FAITHFUL: all 4 cuentas steps match proposal §4**; no preconditions; anchors + `usePageTour('cuentas')` + hand-off asserted
- (PR6) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR6** (identical count + categories to baseline)
- (PR6) `npm run build`: **✓ built in 1.68s**
- (PR6) `prettier --check` on all 3 changed files: **clean**; `eslint`: **0 issues on all 3 changed files** (tourSteps.ts, Categorias/Index.vue, TourLauncher.vue)
- (PR6) `node /tmp/opencode/pr6-verify/verify-pr6.cjs`: **ALL PR6 ASSERTIONS PASSED** — copy byte-faithful (4/4 categorias steps vs proposal §4); no preconditions; anchors + `usePageTour('categorias')` + REQ-8 guard + hand-off asserted
- (PR7) `npm run types:check`: **15 pre-existing errors, 0 introduced by PR7** (identical count + categories to baseline)
- (PR7) `npm run build`: **✓ built in 1.98s**
- (PR7) `php artisan test --compact --filter=Preferences`: **37 passed, 124 assertions** (PR1 baseline preserved)
- (PR7) `prettier --check` on all 3 changed files: **clean**; `eslint`: **0 issues on all 3 changed files** (tourSteps.ts, Recurrentes/Index.vue, TourLauncher.vue)
- (PR7) `node /tmp/opencode/pr7-verify/verify-pr7.cjs`: **ALL PR7 ASSERTIONS PASSED** — copy byte-faithful (5/5 recurrentes steps vs proposal §4); step 36 target-less modal; 4 anchors + `usePageTour('recurrentes')`; real launcher `handleSegmentNext` on the step-36 done/Listo click persists exactly one `{onboarding:{completed_at,version:1}}` payload, disarms, tears down, no navigation; dismissal persists the same payload; intermediate hand-offs do NOT persist; `restart()` performs zero settings writes

## Native Review Outcome (PR2)

- Transaction: lineage `review-70c797d6c182973e` (risk medium, lens review-reliability, correction budget 200), scoped committed-only base-diff `d3f5cf2..4cc1dc3` (9 files, 477 lines), untracked planning dir excluded.
- Reviewer capture admitted; refuter corroborated finding **R3-1 (CRITICAL, inferential, introduced)** at `useTour.ts:130-139`: `restart()` cannot relaunch an active tour, and the destroy() no-persist contract is fragile for hand-offs.
- Correction plan captured (forecast 15 lines); correction committed as `bedaa3f fix: relaunch tour after restart and reset segment on destroy` (destroy() resets `currentSegment`; restart() relaunches via `router.visit` `onSuccess`). Normalized (eslint/prettier clean) and re-verified: `npm run build` ✓ 1.66s, `npm run types:check` 15 pre-existing / 0 new. Actual correction size: 12 insertions / 2 deletions.
- Verificación de fuente (orchestrator, driver.js 1.8.0 `dist/driver.js.mjs`): el mecanismo inferido por el finding (direct `destroy()` disparando `onDestroyStarted`) NO ocurre — `destroy:()=>h(!1)` salta el hook; solo Esc/✕/overlay lo disparan con `h(!0)`. El defecto observable era real por otra vía: el layout persistente no re-monta el launcher (nada relanza tras `restart()`) y `currentSegment` no se reseteaba en `destroy()` (bloqueaba el hand-off futuro en `advance()`). La corrección lo arregla por la vía real.
- **Terminal stop: `captured_artifacts_unverifiable`** — after the corrected candidate (new target `8032fe71…`) was snapshotted, the machinery stopped terminally during the corrected-candidate artifact build. Repair preflight: `unsupported` (0 eligible candidates). Review is NOT approved; delivery stays a human decision under ordinary repository policy. Options per contract: maintainer inspects the authority, or clone-local RDD disable (`D`).

---

## Batch 8 (Final Verification — tasks 8.1–8.2)

- Date: 2026-09-16. Mode: verification-only — **no source changes, no commit** (no files staged; `opencode.json` and `openspec/` untouched by git).
- Scope: the FINAL two tasks of the change; 27/29 → **29/29 tasks complete**. Verify unlocks after this batch.

### Task 8.1 — Automated gates (all three run FRESH)

- [x] 8.1 Run `php artisan test --compact --filter=Preferences`, `npm run build`, and `npm run types:check`.

Per-command verbatim results:

| Command | Observed result | Verdict |
|---|---|---|
| `php artisan test --compact --filter=Preferences` | `{"tool":"pest","result":"passed","tests":37,"passed":37,"assertions":124,"duration_ms":10019}` — `PEST_EXIT=0` | ✅ 37 passed / 124 assertions, exactly as expected |
| `npm run build` | `✓ built in 2.19s` — `BUILD_EXIT=0`; driver.js lazy chunk `driver.js-C7MFaeA3.js` 25.35 kB / 7.20 kB gzip; `useTour-BP1_17Rz.js` 3.35 kB, `usePageTour-C8Bq2otO.js` 7.03 kB in output | ✅ exit 0 |
| `npm run types:check` (`vue-tsc --noEmit`) | `TYPES_EXIT=2` (expected — vue-tsc exits non-zero on errors); **exactly 15 errors, 0 new**: 7× `TS1117` Wayfinder-generated duplicate object keys (`AccountController.ts(391,5)`, `CategoryController.ts(391,5)`, `MovementController.ts(391,5)`, `RecurringTransactionController.ts(325,5)`, `SandboxDebtController.ts(232,5)`, `SandboxMovementController.ts(232,5)`, `SandboxRecurringController.ts(232,5)`); 1× `TS2322` `BalanceLineChart.vue(187,16)` readonly `borderDash` tuple; 7× `TS2339` `Dashboard.vue` `simulated_amount` (751,48 / 751,73 / 763,44 / 763,69 / 766,31) + `is_sandbox` (825,77 / 839,31) | ✅ 15 pre-existing (7 Wayfinder + 1 BalanceLineChart + 7 Dashboard), 0 introduced |

Expected-failing baselines confirmed and NOT re-litigated: the 15 types:check errors above (pre-existing, same categories/count as every prior batch); pre-existing eslint violations in `Dashboard.vue` and `Movimientos/Index.vue` were not re-run — out of scope for this verification batch.

### Task 8.2 — Dark mode + 8 themes, reduced motion, mobile Sheet open/close

- [x] 8.2 Verify dark mode + all 8 themes, reduced motion, and mobile Sheet open/close for shell nav steps.

**CRITICAL EVIDENCE FORWARDED (user, 2026-09-16):** the USER COMPLETED THE FULL END-TO-END BROWSER WALKTHROUGH today and reported **"Todo funciona bien"** — all five pages chained (shell 1–8 → dashboard 9–17 → movimientos 18–23 with conditional skips verified per month/empty cases → cuentas 24–27 → categorías 28–31 → recurrentes 32–36 → final modal), keyboard suppression (←/→ drive the tour, not the month; `N` blocked), completion persists on reload (the previously-confirmed defect verified RESOLVED in runtime), dismissal persists, Help restart works, dark mode + themes render with app tokens, and the mobile Sheet open/close around shell steps 2–6 was verified in the earlier PR3 walkthrough.

**Code-level evidence for items the walkthrough did not explicitly cover individually (reduced motion, all 8 themes):**

1. **Reduced motion** — `resources/js/composables/useTour.ts:105` (driver config in `ensureDriver()`):
   - `animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches` — under `prefers-reduced-motion: reduce` the driver is created with `animate: false` (no popover transitions/scroll animations).
   - Second guard in `resources/css/app.css:286-291`: `@media (prefers-reduced-motion: reduce) { .driver-popover, .driver-overlay { animation: none !important; transition: none !important; } }`.
2. **Dark mode + all 8 themes** — the driver popover consumes app design tokens, which the `.dark` block and every `.theme-*` block redefine; `resources/css/app.css:199-264`:
   - `.driver-popover` (app.css:211-218): `background: var(--popover)`, `color: var(--popover-foreground)`, `border: 1px solid var(--border)`, `border-radius: var(--radius)`, `box-shadow: var(--shadow-lg)`, `font-family: var(--font-sans)` — all `!important` (required because driver.css is injected at runtime from the lazy chunk and would win the cascade tie).
   - `.driver-popover-title` (220-223): `color: var(--foreground)`; `.driver-popover-description` (225-227): `color: var(--muted-foreground)`; `.driver-popover-next-btn` (237-240): `background: var(--primary)` / `color: var(--primary-foreground)`; `.driver-popover-prev-btn` (242-245): `var(--secondary)` / `var(--secondary-foreground)`; `.driver-popover-close-btn` (257-264): `var(--muted-foreground)` → `var(--foreground)` on hover/focus.
   - Token sources: `.dark` block redefines the same tokens at `app.css:153-158` (`--background: hsl(0 0% 3.9%)`, `--popover: hsl(0 0% 3.9%)`, …); all **8 themes** in `resources/css/themes.css` each redefine `--popover`/`--primary`/etc. for light AND dark variants: `.theme-bold-tech` (13), `.theme-claude` (116), `.theme-default` (219), `.theme-pastel-dreams` (322), `.theme-quantum-rose` (425), `.theme-sunny-sprout` (528), `.theme-twitter` (631), `.theme-violet-bloom` (742) — 16 blocks total (8 light + 8 dark). Because the popover reads the CSS custom properties at render time, it inherits whichever theme/dark-mode token set is active.
3. **Mobile Sheet open/close for shell nav steps** — `resources/js/components/tour/TourLauncher.vue`:
   - Constants at lines 28-30: `SHEET_OPEN_INDEX = 0` (before step 2 `nav.movimientos`), `SHEET_CLOSE_INDEX = 5` (after step 6 `shell.userMenu`).
   - `handleSegmentNext()` lines 47-54: on mobile (`isMobile.value && segment === 'shell'`), `setOpenMobile(true)` when the active index is `SHEET_OPEN_INDEX` and `setOpenMobile(false)` at `SHEET_CLOSE_INDEX`.
   - Safety watch lines 139-147: closes the Sheet if the tour ends (Esc/dismiss/`isActive` → false) while it is open on mobile.
   - Runtime behavior for this item was explicitly verified by the user in the earlier PR3 walkthrough (Sheet opens around steps 2–6, closes after step 6).

### Verification Results (Batch 8 — cumulative additions)

- `php artisan test --compact --filter=Preferences`: **37 passed, 124 assertions** (`{"tool":"pest","result":"passed","tests":37,"passed":37,"assertions":124,"duration_ms":10019}`, exit 0)
- `npm run build`: **✓ built in 2.19s**, exit 0
- `npm run types:check`: **15 errors, exactly the pre-existing baseline** (7 Wayfinder `TS1117` duplicate keys + 1 `BalanceLineChart` `TS2322` borderDash + 7 `Dashboard` `TS2339` simulated_amount/is_sandbox), **0 new** (exit 2 as expected for vue-tsc with errors)
- 8.2 evidence: user full end-to-end walkthrough **PASSED** ("Todo funciona bien", 2026-09-16 — chained pages, conditional skips, keyboard suppression, completion persistence RESOLVED, dismissal persists, Help restart, dark mode + themes render) + code citations for reduced motion (`useTour.ts:105` + `app.css:286-291`) and theme tokens (`app.css:211-264` consuming `.dark` at 153-158 + 8 `.theme-*` blocks in `themes.css`), + Sheet open/close (`TourLauncher.vue:28-30,47-54,139-147`, user-verified in PR3)

### Workload / PR Boundary (Batch 8)

- Mode: **verification-only batch** — no delivery boundary, no authored lines, no commit. 29/29 tasks complete; **next: `sdd-verify`** (independent verification of the whole change).
- Git state after batch: working tree contains only the pre-existing untracked `opencode.json` modification and the `openspec/` planning dir — intentionally NOT staged (no source changes made).

---

## Remediation — CRITICAL C-1 (first-login auto-start does not fire)

- Date: 2026-09-16. Mode: Strict TDD (no-JS-harness deviation, PR4–PR7 precedent — the executable harness substitutes for a JS test runner, as documented below). Fix is a **targeted remediation of the single CRITICAL finding** from the verify report — no scope creep, no SUGGESTION findings touched.
- Commit: `2c00ff2` (`fix: auto-start tour on already-mounted pages after arming`) — 1 file, **23 insertions / 9 deletions**, well within the 400-line budget. Staged ONLY `resources/js/components/tour/TourLauncher.vue`; `opencode.json` and `openspec/` untouched.

### The defect (verified in source, not assumed)

Vue mounts the page (inside `AppContent`'s slot) **before** `TourLauncher` (a later sibling in `AppSidebarLayout.vue:32-35`). The page's `usePageTour().advance()` runs in `onMounted` while `armed` is still `false` (module-scoped default), so `advance()` returns at its arm gate (`useTour.ts:179-181`). `TourLauncher.onMounted` then runs `setArmed(true)`, but — because the current page *is* a tour page — performed no navigation and no start, and there was no watch/re-trigger on `armed`. Net effect: on any fresh authenticated load landing on a tour page (all five Fortify `LoginResponse` redirects map to tour pages), the tour armed but never auto-started. S5 (non-tour page) worked only because the launcher's `router.visit(dashboard)` remounts the Dashboard *after* arming. The harness reproduces this exactly: pre-fix, the mount-order scenario (page `advance()` first, launcher arm second) leaves `drive` at 0, `isActive` false, `currentSegment` null.

### The fix (post-arm trigger)

`TourLauncher.vue`:
1. `TOUR_PAGES` `Set` → `SEGMENT_BY_PAGE` `Map<string, TourSegment>` (same 5 components, now resolving the component name to its segment).
2. In `onMounted`, after `setArmed(true)`: `const segment = SEGMENT_BY_PAGE.get(page.component);` — non-tour pages keep the existing `router.visit(dashboard().url)` hand-off (S5 unchanged); tour pages now call `tour.advance(segment)`.

The trigger reuses the **existing** `advance()` API — no `useTour` change needed, so no new API surface and no justification burden. For the Dashboard, `advance('dashboard')` routes through its existing shell-first rule (`useTour.ts:188-192` → `run('shell')`, REQ-2: shell steps 1–8 before dashboard 9–17). For other tour pages, their own segment runs first (REQ-2: "if already on a tour page, that page's segment MUST run first"). No double-start risk: on a fresh load `currentSegment` is null and the page's early `advance()` already no-oped; the launcher's `advance()` is the only drive; `run()`'s idempotence guard (`isActive && currentSegment === segment`) covers any overlap (unchanged PR3 fix `f26e064`). The launcher's `onMounted` never re-runs on Inertia visits (persistent layout), so `restart()` and mid-tour hand-offs are unaffected (they already re-drive via page remount / `onSuccess`).

### TDD Cycle Evidence (remediation row)

| Task | Test File | Layer | Safety Net | RED | GREEN | TRIANGULATE | REFACTOR |
|------|-----------|-------|------------|-----|-------|-------------|----------|
| C-1 fix | N/A — no JS test harness (stated deviation, PR4–PR7 precedent) | Type check + build + executable mount-order harness (`/tmp/opencode/c1-remediation/verify-c1.cjs`, real transpiled `TourLauncher.vue` + `useTour.ts` + `usePageTour.ts` + `tourSteps.ts`) | ✅ 15 pre-existing types errors (baseline); Pest 37/37 (regression) | ✅ **RED confirmed**: `LAUNCHER_PATH=TourLauncher.prefix.vue node verify-c1.cjs` → exit 1, **9 auto-start assertions FAIL** (drive 0 / not active / no segment on Dashboard, Movimientos, version-bump); S2/S5 paths PASS pre-fix (proving the defect is isolated to the already-mounted-tour-page trigger) | ✅ **GREEN**: `node verify-c1.cjs` → **ALL C-1 ASSERTIONS PASSED** (22/22, exit 0) | ✅ 5 scenarios: S1 Dashboard fresh (shell-first), REQ-2 non-Dashboard tour page (movimientos), S3 version bump, S2 completed user, S5 non-tour redirect — plus the mount-order premise asserted before the launcher mounts | ➖ None needed (minimal fix, no refactor) |

### Work Unit Evidence (remediation)

| Evidence | Required value |
|---|---|
| Focused test command and exact result | RED: `LAUNCHER_PATH=/tmp/opencode/c1-remediation/TourLauncher.prefix.vue node /tmp/opencode/c1-remediation/verify-c1.cjs` → **9 assertion(s) FAILED, exit 1** — auto-start assertions (drive, isActive, currentSegment, step counts) fail on Dashboard / Movimientos / version-bump; S2 completed-user and S5 non-tour redirect PASS. GREEN: `node /tmp/opencode/c1-remediation/verify-c1.cjs` → **ALL C-1 ASSERTIONS PASSED, exit 0** (22 assertions). The harness drives the REAL transpiled `TourLauncher.vue` + `useTour.ts` + `usePageTour.ts` + `tourSteps.ts` against a stubbed driver/Inertia/DOM, replaying the real mount order (page `onMounted` first, launcher `onMounted` second). |
| Runtime harness command/scenario and exact result | Browser first-login auto-start on a fresh authenticated load (clear `onboarding.completed_at`, login → Dashboard → tour starts at `shell.sidebar`): **cannot be executed by this agent** (no browser driver). Reported `partial` on this single harness item, with executable mount-order evidence instead (above: page advance pre-arm no-ops, launcher post-arm trigger drives exactly once with the correct segment per page). Manual verification note for the user: fresh login / cleared flag → tour auto-starts on the Dashboard; completed user (`completed_at` set, version current) → no auto-start. |
| Rollback boundary | Revert commit `2c00ff2` alone (1 file, `resources/js/components/tour/TourLauncher.vue`) — the launcher returns to the pre-fix state (arm-only on tour pages, S5 redirect preserved); PR1–PR7 and the settings persistence flag remain intact. No migration, no schema change. |

### Deviations from Design (remediation)

None — the fix implements the verify report's own remediation item ("a post-arm trigger in `TourLauncher.onMounted` … that starts the current page's segment when a tour page is already mounted"). No `useTour.ts` change was needed (`advance()` already exposes the re-trigger API), so the minimal-footprint guidance (expect ~10–20 lines in TourLauncher.vue, "possibly a tiny useTour addition if a re-trigger API is genuinely needed — justify it") holds with zero justification needed.

### Issues Found (remediation)

None new. The 2 SUGGESTION findings from the verify report (mid-tour navigation orphan popover; mobile shell step-1 modal fallback) remain documented follow-ups, untouched. The 15 pre-existing `types:check` errors are unchanged (0 introduced).

### Verification Results (remediation — verbatim)

- `node /tmp/opencode/c1-remediation/verify-c1.cjs` (GREEN, fixed source): **ALL C-1 ASSERTIONS PASSED**, exit 0 — 22 assertions across S1 / REQ-2 non-Dashboard tour page / S3 / S2 / S5. RED pre-fix: 9 failures, exit 1 (see Work Unit Evidence).
- `npm run build`: **✓ built in 1.77s**, exit 0 (driver.js lazy chunk `driver.js-C7MFaeA3.js` 25.35 kB / 7.20 kB gzip unchanged).
- `npm run types:check` (`vue-tsc --noEmit`): **exit 2 (expected) — exactly 15 errors, 0 new**: 7× `TS1117` Wayfinder duplicate keys (Account/Category/Movement/RecurringTransaction/SandboxDebt/SandboxMovement/SandboxRecurring `*Controller.ts`), 1× `TS2322` `BalanceLineChart.vue(187,16)` readonly `borderDash`, 7× `TS2339` `Dashboard.vue` `simulated_amount` (751,48 / 751,73 / 763,44 / 763,69 / 766,31) + `is_sandbox` (825,77 / 839,31). Identical to the documented baseline.
- `npx prettier --check resources/js/components/tour/TourLauncher.vue`: **All files formatted correctly**, exit 0.
- `npx eslint resources/js/components/tour/TourLauncher.vue`: **No issues found**, exit 0.
- `php artisan test --compact --filter=Preferences`: **37 passed, 124 assertions** (`{"tool":"pest","result":"passed","tests":37,"passed":37,"assertions":124,"duration_ms":9178}`, exit 0) — PR1 persistence suite regression guard.

### Workload / PR Boundary (remediation)

- Mode: targeted remediation of CRITICAL C-1 — **not a new delivery slice**; single commit `2c00ff2` on `feature/Implement-onboarding-guide`.
- Current work unit: C-1 — "first-login auto-start does not fire".
- Boundary: starts at the verify-report finding (HEAD `4358ef6`), ends with commit `2c00ff2`.
- Estimated review budget impact: 1 file, **32 changed lines (23 + / 9 −)**. Within the 400-line budget — no `size:exception` needed.
- Next: **re-run `sdd-verify`** (verify-report revision `sha256:628be221…` superseded by this remediation; expect S1/S3 to flip to COMPLIANT).