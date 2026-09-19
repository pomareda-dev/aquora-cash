# ODD: Settings section restructure

**Feature**: `settings-cleanup`
**Branch**: `bugfix/Fix-mobile-issues` (not default branch)
**Objective**: Restructure the "Configuración" section: remove the Appearance tab and relocate its content into Preferences, remove the profile photo feature entirely (sidebar avatar shows initials only), and remove the color "Temas" palettes entirely.

## Problem

The settings area carries two features that will not survive the upcoming full redesign:

1. An 8-slot color palette system ("Temas") spread across a composable, a dedicated CSS file, backend validation, and persisted user settings.
2. A profile photo upload feature (controller, route, validation, UI, sidebar image conditionals) that does not scale for many users.

The "Apariencia" tab duplicates a concern that belongs with the other preferences, so its content moves into "Preferencias".

## Interpretation (explicit)

- **"Apariencia" content** = the light/dark/system selector (`AppearanceTabs`). It MOVES into "Preferencias" as its own card.
- **"Temas"** = the 8 color palettes (`theme-*` CSS + `setTheme`). It is DELETED end-to-end.
- Dark/light/system mode is NOT a "Tema". It stays, relocated.

## Scope

### In scope
- Delete palette/theme system: composable, CSS file, backend validation key, persisted setting, tests.
- Delete profile photo feature: controller, route, validation key, UI, tests, types, sidebar image conditionals.
- Delete the "Apariencia" tab/page; relocate `AppearanceTabs` into Preferences.
- Simplify `UserInfo` avatar to initials-only.

### Out of scope
- Any visual redesign.
- Dark/light/system behavior beyond relocation.
- The historical migration `database/migrations/2026_07_08_005306_remigrate_theme_keys_to_default.php` (append-only DB history; deleting an applied migration breaks `migrate:rollback`). Flagged, not deleted.
- Unrelated `opencode.json` working-tree change.

## Constraints
- Follow existing conventions (Pest, Inertia v3 + Vue 3, Wayfinder generated routes are gitignored).
- No unused imports/vars left behind (eslint + vue-tsc must pass).
- `resources/js/routes` is generated: regenerate with `php artisan wayfinder:generate`, never hand-edit.

## Tasks

### T1 — Remove palette/theme system
**Files**: `resources/js/composables/useAppearance.ts`, `resources/js/app.ts`, `resources/css/app.css`, `resources/css/themes.css` (delete), `app/Http/Requests/Settings/UpdateSettingsRequest.php`, `app/Http/Controllers/Settings/PreferencesController.php` (docblock), `resources/js/composables/useSettings.ts`, `tests/Feature/Settings/PreferencesTest.php`
- [x] Remove `VALID_THEMES`, `ValidTheme`, `themeKey`, `applyPalette`, `setTheme`, `syncThemeFromPage`, and the `@/routes/settings` import from `useAppearance.ts`; keep `Appearance`/`ResolvedAppearance`, `updateTheme`, `useAppearance`, cookie + localStorage logic, and a dark-mode-only `initializeTheme`.
- [x] Remove `syncThemeFromPage` import + `router.on('success', ...)` from `app.ts` (keep `initializeTheme()`), drop now-unused `router` import.
- [x] Remove `@import './themes.css';` and update the two comments referencing themes.css / "8 themes" in `app.css`.
- [x] Delete `resources/css/themes.css`.
- [x] Remove the `theme` rule from `UpdateSettingsRequest`.
- [x] Remove `theme` from `UserSettings` interface + `defaults` in `useSettings.ts`.
- [x] Fix `PreferencesController::update` docblock.
- [x] Remove theme-related datasets/tests from `PreferencesTest.php`; rewrite tests that used `theme` as a vehicle ("ignores unknown keys", "merges with existing settings", "preserves onboarding") to use a still-valid key.

### T2 — Remove profile photo feature
**Files**: `app/Http/Controllers/Settings/UserProfilePhotoController.php` (delete), `routes/settings.php`, `app/Http/Requests/Settings/UpdateSettingsRequest.php`, `resources/js/pages/settings/Preferences.vue`, `resources/js/composables/useSettings.ts`, `resources/js/types/auth.ts`, `tests/Feature/Settings/PreferencesTest.php`
- [x] Delete `UserProfilePhotoController.php` and its import + POST route in `routes/settings.php`.
- [x] Remove the `avatar_path` rule from `UpdateSettingsRequest`.
- [x] Remove `avatar_path` from `UserSettings` + `defaults`.
- [x] Remove `avatar?: string` from `User` type in `types/auth.ts`.
- [x] Delete all profile-photo UI + script from `Preferences.vue` (card, `uploading`, `uploadError`, `previewUrl`, `AVATAR_*`, `handleFileChange`, `uploadPhoto`, `getCsrfToken`, `profilePhoto` import, avatar imports).
- [x] Delete all photo-upload tests and now-unused `UploadedFile`/`Storage` imports from `PreferencesTest.php`.

### T3 — Merge Appearance tab into Preferences
**Files**: `resources/js/pages/settings/Appearance.vue` (delete), `resources/js/layouts/settings/Layout.vue`, `resources/js/pages/settings/Preferences.vue`
- [x] Delete `Appearance.vue`.
- [x] Remove the `appearance.edit` route from `routes/settings.php`.
- [x] Remove the "Apariencia" nav item and `editAppearance` import from the settings `Layout.vue`.
- [x] Render `AppearanceTabs` inside a new "Apariencia" card in `Preferences.vue` (first card, before Densidad).

### T4 — Sidebar avatar initials-only
**Files**: `resources/js/components/UserInfo.vue`
- [x] Remove `avatarUrl`/`showAvatar`/`AvatarImage` and the image conditional; render `AvatarFallback` with `getInitials` only.

### T5 — Verification
- [x] `vendor/bin/pint --dirty --format agent` — passed.
- [x] `php artisan wayfinder:generate --with-form` — passed (`--with-form` matches `formVariants: true` in `vite.config.ts`).
- [x] `php artisan test --compact tests/Feature/Settings/PreferencesTest.php` — 23 passed, 82 assertions.
- [x] `npm run types:check` — no errors in touched files; 7 pre-existing TS1117 errors in generated Wayfinder action files (duplicate PUT/PATCH dictionary keys).
- [x] `npm run lint:check` — clean for all touched files; 4 pre-existing errors in untouched files (`useSandbox.ts`, `Dashboard.vue`, `Movimientos/Index.vue`).
- [x] `npm run build` — passed.

## Acceptance criteria
- Settings nav has exactly: Perfil, Seguridad, Preferencias. No "Apariencia".
- Preferences renders the light/dark/system selector plus density, start section, projection horizon, debt category.
- No `theme`/palette code remains in FE, BE, or CSS.
- No `avatar_path` / profile-photo code remains; no route `config.profile-photo.store`.
- `UserInfo` avatar has no `AvatarImage` conditional.
- All verification commands pass.

## Checks / TDD
- Resolved mode: standard functional verification (no strict TDD flag configured). Runner: `php artisan test` (Pest).
- Frontend gates: `vue-tsc --noEmit`, `vite build`.
- PHP style: Pint.

## Delivery strategy
`ask-on-risk` (default). Forecast: ~250-320 authored changed lines (mostly deletions), under the 400-line budget. Single commit per task on the current feature branch.

## Progress log
- [x] Mapped all settings/appearance/avatar/theme code paths.
- [x] T1–T4 implemented: palette/theme system removed end-to-end, profile photo feature removed, Apariencia tab merged into Preferencias, sidebar avatar simplified to initials.
- [x] T5 verification run (2026-09-19):
  - `vendor/bin/pint --dirty --format agent`: passed.
  - `php artisan wayfinder:generate --with-form`: passed; `routes/appearance` and `routes/config/profile-photo` removed from generated output.
  - `php artisan test --compact tests/Feature/Settings/PreferencesTest.php`: 23 passed, 82 assertions.
  - `php artisan test --compact tests/Feature/Settings`: 30 passed, 2 skipped (pre-existing passkey skips in `SecurityTest.php`), 111 assertions.
  - `npm run types:check`: 7 pre-existing TS1117 errors, all in generated `resources/js/actions/**` (duplicate `PUT`/`PATCH` keys for `cuentas/{account}`, etc.); zero errors in touched source.
  - `npm run lint:check`: 4 pre-existing errors in untouched files (`useSandbox.ts`, `Dashboard.vue`, `Movimientos/Index.vue`); touched files clean.
  - `npm run build`: passed.
- [x] Residual references reported (not code): `resources/themes/*.css` raw palette sources, generic `ui/avatar/AvatarImage.vue` primitive, historical `docs/archive/plan-de-trabajo.md`, and `openspec/specs/settings/spec.md` line ~120 (still lists `theme`/`avatar_path` as preserved). Out of the enumerated task file list.
