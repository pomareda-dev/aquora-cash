# Tasks: Driver.js Usage Guide

## Review Workload Forecast

| Field | Value |
|---|---|
| Estimated changed lines | ~820 authored (lockfile churn excluded) |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR1 → PR2 → PR3 → PR4 → PR5 → PR6 → PR7 |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Focused test command | Runtime harness | Rollback boundary |
|---|---|---|---|---|---|
| 1 | Settings onboarding flag round-trip | PR 1 | `php artisan test --compact --filter=Preferences` | Pest only | Revert `UpdateSettingsRequest.php` + `useSettings.ts` |
| 2 | Driver.js + launcher + one smoke step | PR 2 | `npm run build && npm run types:check` | Browser smoke of one step | Remove launcher mount + tour code/CSS |
| 3 | Shell + Dashboard segment | PR 3 | `npm run build && npm run types:check` | Browser walkthrough steps 1–17 | Revert shell + dashboard anchors/copy |
| 4 | Movimientos segment | PR 4 | `npm run build && npm run types:check` | Browser walkthrough steps 18–23 | Revert `Movimientos/Index.vue` + copy |
| 5 | Cuentas segment | PR 5 | `npm run build && npm run types:check` | Browser walkthrough steps 24–27 | Revert `Cuentas/Index.vue` + copy |
| 6 | Categorías segment | PR 6 | `npm run build && npm run types:check` | Browser walkthrough steps 28–31 | Revert `Categorias/Index.vue` + copy |
| 7 | Recurrentes segment + step 36 modal | PR 7 | `npm run build && npm run types:check` | End-to-end finish/dismiss/restart | Revert `Recurrentes/Index.vue` + copy |

> All Spanish tour copy is read from `openspec/changes/driverjs-usage-guide/proposal.md` §4 (read-only).

## Phase 1: PR1 — Settings Persistence

- [x] 1.1 RED: Append 5 onboarding Pest tests to `tests/Feature/Settings/PreferencesTest.php` (persist, invalid version/date, theme preserves onboarding, re-arm null); run and expect failures.
- [x] 1.2 Implement `onboarding` validation rules in `app/Http/Requests/Settings/UpdateSettingsRequest.php`.
- [x] 1.3 Add `onboarding` type, default, and nested hydration to `resources/js/composables/useSettings.ts`.
- [x] 1.4 GREEN: Run `php artisan test --compact --filter=Preferences`; run Pint on changed PHP.

## Phase 2: PR2 — Tour Infrastructure

- [x] 2.1 Install `driver.js`: run `pnpm add driver.js && npm install --package-lock-only`, verify the installed version supports a target-less modal step, and commit `package.json`, `pnpm-lock.yaml`, and `package-lock.json` together.
- [x] 2.2 Create `resources/js/composables/useTour.ts` singleton and `resources/js/composables/usePageTour.ts` hook.
- [x] 2.3 Create `resources/js/components/tour/TourLauncher.vue` and mount it in `resources/js/layouts/app/AppSidebarLayout.vue`.
- [x] 2.4 Create `resources/js/components/tour/tourSteps.ts` with one smoke step and add driver.js theme/z-index/reduced-motion overrides to `resources/css/app.css`.
- [x] 2.5 Run `npm run build` and `npm run types:check`; manually smoke the smoke step and confirm Esc/arrow keys behave.

## Phase 3: PR3 — Shell + Dashboard Segment

- [x] 3.1 Add shell steps 1–8 and dashboard steps 9–17 to `resources/js/components/tour/tourSteps.ts` from proposal §4.
- [x] 3.2 Add `tourId?: string` to `resources/js/types/navigation.ts`; bind nav item anchors in `resources/js/components/NavMain.vue`; add `data-tour="shell.sidebar"` in `resources/js/components/AppSidebar.vue`.
- [x] 3.3 Add `data-tour="shell.userMenu"` in `resources/js/components/NavUser.vue`; add `data-tour="shell.simulationToggle"` and a Help button (`data-tour="shell.help"`) wired to `restart()` in `resources/js/components/AppSidebarHeader.vue`.
- [x] 3.4 Add 9 dashboard anchors, `usePageTour('dashboard')`, and `isDialogOpen: () => isTourActive()` in `resources/js/pages/Dashboard.vue`.
- [x] 3.5 Run `npm run build` and `npm run types:check`; manually walk through shell and dashboard segments.

## Phase 4: PR4 — Movimientos Segment

- [x] 4.1 Add movimientos steps 18–23 to `resources/js/components/tour/tourSteps.ts` from proposal §4.
- [x] 4.2 Add 6 anchors, `usePageTour('movimientos')`, and extend the existing `isDialogOpen` guard with `isTourActive()` in `resources/js/pages/Movimientos/Index.vue`.
- [x] 4.3 Run `npm run build` and `npm run types:check`; verify conditional skips for future/past months and empty list.

## Phase 5: PR5 — Cuentas Segment

- [x] 5.1 Add cuentas steps 24–27 to `resources/js/components/tour/tourSteps.ts` from proposal §4.
- [x] 5.2 Add 4 anchors and `usePageTour('cuentas')` in `resources/js/pages/Cuentas/Index.vue`.
- [x] 5.3 Run `npm run build` and `npm run types:check`; manually walk through the segment.

## Phase 6: PR6 — Categorías Segment

- [x] 6.1 Add categorías steps 28–31 to `resources/js/components/tour/tourSteps.ts` from proposal §4.
- [x] 6.2 Add 4 anchors, `usePageTour('categorias')`, and extend the existing `isDialogOpen` guard with `isTourActive()` in `resources/js/pages/Categorias/Index.vue`.
- [x] 6.3 Run `npm run build` and `npm run types:check`; manually walk through the segment.

## Phase 7: PR7 — Recurrentes + Completion

- [x] 7.1 Add recurrentes steps 32–36 to `resources/js/components/tour/tourSteps.ts`; step 36 as a target-less driver modal (or fallback anchor), from proposal §4.
- [x] 7.2 Add 4 anchors and `usePageTour('recurrentes')` in `resources/js/pages/Recurrentes/Index.vue`.
- [x] 7.3 Verify finish and dismissal persist onboarding while preserving other settings; verify Help restart re-arms without clearing the server flag.
- [x] 7.4 Run `npm run build` and `npm run types:check`; run the full tour end-to-end across all five pages.

## Phase 8: Final Verification

- [x] 8.1 Run `php artisan test --compact --filter=Preferences`, `npm run build`, and `npm run types:check`.
- [x] 8.2 Verify dark mode + all 8 themes, reduced motion, and mobile Sheet open/close for shell nav steps.
