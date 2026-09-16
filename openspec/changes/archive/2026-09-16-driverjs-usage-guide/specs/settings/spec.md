# Delta for Settings — Onboarding Flag

## ADDED Requirements

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
