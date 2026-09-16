# Settings Specification

## Requirements

### Requirement: Theme Token Scope (CSS)

Each of the 8 themes MUST define in scoped `.theme-{key}` (light) and `.theme-{key}.dark` (dark) blocks every token from the following table:

| Category | Tokens |
|----------|--------|
| Colors | `--background`, `--foreground`, `--card`, `--card-foreground`, `--popover`, `--popover-foreground`, `--primary`, `--primary-foreground`, `--secondary`, `--secondary-foreground`, `--muted`, `--muted-foreground`, `--accent`, `--accent-foreground`, `--destructive`, `--destructive-foreground`, `--border`, `--input`, `--ring` |
| Chart | `--chart-1`, `--chart-2`, `--chart-3`, `--chart-4`, `--chart-5` |
| Sidebar | `--sidebar`, `--sidebar-foreground`, `--sidebar-primary`, `--sidebar-primary-foreground`, `--sidebar-accent`, `--sidebar-accent-foreground`, `--sidebar-border`, `--sidebar-ring` |
| Radius | `--radius` |
| Shadows | `--shadow-2xs`, `--shadow-xs`, `--shadow-sm`, `--shadow`, `--shadow-md`, `--shadow-lg`, `--shadow-xl`, `--shadow-2xl` |
| Shadow comps | `--shadow-x`, `--shadow-y`, `--shadow-blur`, `--shadow-spread`, `--shadow-opacity`, `--shadow-color` |
| Spacing | `--spacing` |
| Tracking | `--tracking-normal` |

Each theme MUST NOT define `--font-sans`, `--font-serif`, `--font-mono` (stripped; app keeps Instrument Sans). Color values MAY use `oklch()`, `hsl()`, or any valid CSS color format. Themes MUST NOT use `:root`, `.dark`, `@import`, `@theme inline`, or `@layer base` selectors — only `.theme-{key}` and `.theme-{key}.dark`.

### Requirement: CSS File Separation

`resources/css/themes.css` (NEW) MUST host the 8 scoped theme blocks. `resources/css/app.css` MUST `@import './themes.css'` and MUST remove the 8 old `.theme-slate`, `.theme-rose`, `.theme-blue`, `.theme-green`, `.theme-amber`, `.theme-violet`, `.theme-teal`, `.theme-red` blocks (current lines ~165–333). Line 54 of `app.css` `@theme inline` MUST change `--color-sidebar: var(--sidebar-background)` to `--color-sidebar: var(--sidebar)`. The `@theme inline` block MUST also register bridge tokens for shadows (`--shadow-2xs` through `--shadow-2xl`, `--shadow-x/y/blur/spread/opacity/color`), `--spacing`, and `--tracking-normal` — each mapped to its `var(--*)` so Tailwind utilities consume scoped theme values.

### Requirement: Backend Theme Validation

`UpdateSettingsRequest.php` line 26 theme rule MUST accept ONLY:

```
default, bold-tech, claude, pastel-dreams, quantum-rose, sunny-sprout, twitter, violet-bloom
```

These 8 keys are nullable. Legacy keys (`slate, rose, blue, green, amber, violet, teal, red`) and any other value (e.g. `pink`) MUST be rejected with 422.

### Requirement: Theme Key Migration

A migration `database/migrations/*_remigrate_theme_keys_to_default.php` MUST update `users.settings` JSON as follows:

| Condition | Action |
|-----------|--------|
| `settings->theme` is one of `{slate, rose, blue, green, amber, violet, teal, red}` | Set to `'default'` |
| `settings->theme` is NULL | No change |
| `settings->theme` key is absent | No change |
| `settings->theme` is already one of the 8 new keys | No change |

The migration SHALL be idempotent (safe to re-run).

### Requirement: Frontend Default and Fallback

`useAppearance.ts` default `themeKey` (line 18) MUST change from `'slate'` to `'default'`. `initializeTheme()` MUST treat any theme key NOT in the 8 valid keys as `'default'` (frontend fallback). `useSettings.ts` `UserSettings.theme` type union (line 10) MUST list:

```
'default' | 'bold-tech' | 'claude' | 'pastel-dreams' | 'quantum-rose' | 'sunny-sprout' | 'twitter' | 'violet-bloom'
```

### Requirement: Preferences UI

`Preferences.vue` `palettes[]` array MUST contain exactly 8 entries with keys matching the Backend Theme Validation requirement. Each entry MUST have a Spanish label. The Card title MUST change from "Paleta de colores" to "Diseño" (or "Tema"). The Card description MUST change from "color principal" language to reflect full design-system selection. The existing click→`setTheme()` handler and visual ring pattern MUST be preserved.

### Requirement: Test Coverage

`PreferencesTest.php` MUST be updated:

| Change | Detail |
|--------|--------|
| Valid dataset (line 23) | `['theme', 'rose']` → `['theme', 'default']` |
| Invalid dataset (line 36) | Add `['theme', 'slate']` (legacy key rejected post-migration) |
| Invalid dataset | Keep `['theme', 'pink']` (still rejected) |
| References to `'rose'` (lines 49, 56, 64) | Replace with new valid key (e.g. `'default'` or `'claude'`) |
| New dataset | Assert all 8 new keys are accepted (200) |

### Requirement: Onboarding Validation

`UpdateSettingsRequest::rules()` MUST add:

| Key | Rules |
|-----|-------|
| `onboarding` | `nullable`, `array` |
| `onboarding.completed_at` | `nullable`, `date` |
| `onboarding.version` | `nullable`, `integer`, `min:1` |

Invalid values MUST return 422 with the error keyed `onboarding.completed_at` or `onboarding.version`. Unknown keys MUST continue to be dropped, unchanged from current behavior.

#### Scenario: Onboarding flag persists
- GIVEN an authenticated user
- WHEN they PUT `onboarding: { completed_at: '2026-09-15T12:00:00Z', version: 1 }`
- THEN the response is 204 and both nested values are stored in `users.settings`

#### Scenario: Invalid version rejected
- GIVEN an authenticated user
- WHEN they PUT `onboarding: { version: 0 }`
- THEN the response is 422 with an `onboarding.version` error

#### Scenario: Invalid completion date rejected
- GIVEN an authenticated user
- WHEN they PUT `onboarding: { completed_at: 'not-a-date' }`
- THEN the response is 422 with an `onboarding.completed_at` error

#### Scenario: Re-arm with null completion
- GIVEN a user whose settings contain `onboarding`
- WHEN they PUT `onboarding: { completed_at: null, version: 1 }`
- THEN the stored `completed_at` is `null` and the key remains present

### Requirement: Frontend Type, Default, and Hydration

`UserSettings` MUST add `onboarding: { completed_at: string | null; version: number }`. The `defaults` object MUST include `onboarding: { completed_at: null, version: 0 }`. `hydrateSettings()` MUST nested-merge the nested object (`{ ...defaults.onboarding, ...raw.onboarding }`) so a partial server object does not wipe the default `version`.

### Requirement: Round-Trip Without Silent No-ops

`updateSettings({ onboarding: { completed_at, version } })` MUST send the key, update the local reactive `settings` object, and sync the Inertia shared props. The `if (key in settings)` guard MUST NOT skip the write; `onboarding` MUST therefore be present in `UserSettings` and `defaults` (see Frontend Type, Default, and Hydration). On a non-OK response the existing error toast MUST fire.

#### Scenario: Local state syncs without a no-op
- GIVEN the frontend `settings` object hydrated with the `onboarding` default
- WHEN `updateSettings` is called with an `onboarding` payload and the server responds OK
- THEN the local `settings.onboarding` and the Inertia shared props reflect the new value

### Requirement: Existing Rules Unchanged

The theme (8 keys), density, start_section, projection_horizon, avatar_path and debt_category_id rules, and the controller's shallow `array_merge` into `users.settings`, MUST be preserved. A settings update for any other key MUST NOT delete `onboarding`.

#### Scenario: Other updates preserve onboarding
- GIVEN a user whose settings already contain `onboarding`
- WHEN they PUT `theme: 'claude'`
- THEN `onboarding` survives unchanged and `theme` is updated
## Scenarios

### Scenario S1: New user sees default theme
- GIVEN a freshly registered user with no `settings.theme`
- WHEN the user opens the app
- THEN `<html>` has class `theme-default`
- AND the `default` palette is marked active in Preferences

### Scenario S2: User selects bold-tech theme
- GIVEN a logged-in user on the Preferences page
- WHEN they click the bold-tech palette button
- THEN `setTheme('bold-tech')` is called
- AND `<html>` class becomes `theme-bold-tech`
- AND the PUT /settings request body contains `{ theme: 'bold-tech' }`

### Scenario S3: Full design-system tokens render
- GIVEN the `pastel-dreams` theme is applied
- WHEN the user views a card, button, and dialog
- THEN the card uses pastel-dreams `--radius` (1.5rem), `--background`, and pastel-dreams shadows
- AND buttons use pastel-dreams `--primary` color
- AND NO theme-specific font is applied (Instrument Sans remains)

### Scenario S4: Dark mode coexists with theme
- GIVEN a user has theme `claude` and appearance `dark`
- WHEN the app renders
- THEN `<html>` has both `theme-claude` and `dark` classes
- AND the `.theme-claude.dark` block's tokens take precedence for dark mode rendering

### Scenario S5: Legacy theme key migrated
- GIVEN an existing user has `settings.theme = 'slate'`
- WHEN the migration runs
- THEN their `settings.theme` is updated to `'default'`
- AND a subsequent PUT /settings with `theme: 'slate'` returns 422

### Scenario S6: Stale client theme key falls back
- GIVEN a user's browser has `themeKey = 'violet'` cached client-side (legacy)
- WHEN `initializeTheme()` reads theme `'violet'` from Inertia props
- THEN the frontend falls back to `'default'`
- AND `<html>` gets class `theme-default`

### Scenario S7: Invalid theme key rejected on backend
- GIVEN a PUT /settings request with body `{ theme: 'pink' }`
- WHEN the request is processed
- THEN the response is 422
- AND the `theme` field has a validation error

### Scenario S8: All 8 themes accepted on backend
- GIVEN a PUT /settings request for each of the 8 valid keys
- WHEN the request is processed
- THEN each returns 200 with no validation error

### Scenario S9: oklch colors resolve in chart
- GIVEN theme `twitter` is applied (uses oklch colors)
- WHEN BalanceLineChart reads `--primary` via `getComputedStyle`
- THEN the resolved value is in `rgb()` format
- AND the chart renders with the theme's primary color

### Scenario S10: Density unaffected by theme spacing
- GIVEN theme `pastel-dreams` (radius 1.5rem) and density `compact`
- WHEN a table row renders
- THEN cell padding follows the density `compact` utility classes (`p-2 text-sm`)
- AND the row border uses pastel-dreams `--border` color
- AND `--spacing` and `--radius` do NOT override density utility maps

### Scenario S11: Sidebar token alignment
- GIVEN any of the 8 themes is applied
- WHEN the sidebar renders
- THEN the sidebar background uses `--sidebar` (mapped via `@theme inline` `--color-sidebar: var(--sidebar)`)
- AND `--sidebar-background` is NOT the source-of-truth mapping

## Out of Scope
- Web font loading (future phase)
- Changes to shadcn/ui components (they inherit tokens naturally)
- Changes to BalanceLineChart.vue (already oklch-safe)
- Any model change (`users.settings` JSON column already exists)
- Any layout rewrite
