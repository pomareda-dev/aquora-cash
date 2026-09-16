# Archive Report — driverjs-usage-guide

**Change**: driverjs-usage-guide  
**Archived**: 2026-09-16  
**Final Verdict**: PASS (implementation complete, verified)  
**Delivery Status**: PENDING (human-owned decision)

---

## Executive Summary

The driverjs-usage-guide change implemented a complete interactive onboarding tour system for the caja-diaria application using Driver.js. The tour guides users through five pages (Dashboard, Movimientos, Cuentas, Categorías, Recurrentes) with 36 steps total, covering shell navigation, page-specific features, and completion persistence.

**Implementation**: Complete (29/29 tasks)  
**Verification**: PASS (16/16 requirements, 20/20 scenarios, runtime-verified by user)  
**Delivery**: NOT DONE — 7-PR stacked chain exists as local commits on `feature/Implement-onboarding-guide` but no remote tracking branch or PRs created. Creating/pushing the PRs is the maintainer's delivery decision under ordinary repository policy.

---

## Final State (Authoritative)

### Implementation Complete
- **Tour infrastructure**: Driver.js integration, TourLauncher.vue with SEGMENT_BY_PAGE map, post-arm advance trigger (commit `2c00ff2` — C-1 remediation)
- **Five page segments**: shell (9 steps), dashboard (9 steps), movimientos (6 steps), cuentas (4 steps), categorías (4 steps), recurrentes (5 steps + completion)
- **Completion persistence**: Final step routes to `tour.finish()` (persist + disarm, no navigation) — commit `4358ef6` (PR7)
- **Settings persistence**: Tour-armed flag stored in user preferences — commit `d3f5cf2`
- **Restart semantics**: Help menu "Restart tour" option re-arms the flag
- **Keyboard suppression**: Tour blocks keyboard shortcuts during active steps
- **Dark mode/themes**: Tour popovers respect theme tokens
- **Mobile behavior**: Sheet-based navigation on mobile viewports

### Verification Passed
- **Final verdict**: PASS (sha256:ca88f33871caa5af3aedf59115e93ae056881ab6330858e65dfff1289f99b27d)
- **Requirements**: 16/16 passed
- **Scenarios**: 20/20 passed
- **Runtime verification** (2026-09-16, user): Two rounds of end-to-end browser walkthrough across all five pages — "Todo funciona bien". Post-remediation first-login check PASSED: fresh flag-null login auto-starts the tour on the Dashboard, completed users do not auto-start.
- **Test gates**: Pest Preferences 37 passed/124 assertions; build exit 0; types:check 15 pre-existing errors / 0 new.

### Critical Defects Resolved
- **C-1 (auto-start on already-mounted pages)**: Remediated in commit `2c00ff2` — TourLauncher.vue now advances the tour after arming when the page is already mounted. RED/GREEN executable harness: 9 assertions failed pre-fix, 22/22 pass post-fix.
- **Completion persistence**: Remediated in commit `4358ef6` (PR7) — final recurrentes branch routes step-36 done/Listo to `tour.finish()` (persist + disarm). Runtime-verified by user.

### Commit Chain (on top of pre-change base)
1. `d3f5cf2` — settings persistence
2. `b0284c0` — tour infrastructure
3. `4cc1dc3` — eslint normalization
4. `bedaa3f` — PR2 review correction
5. `80d10ed` — shell+dashboard segments
6. `f26e064` + `68f9c1c` — PR3 walkthrough fixes
7. `e38664c` — movimientos
8. `3bbfd5b` — cuentas
9. `2073424` — categorías
10. `4358ef6` — recurrentes+completion (PR7)
11. `2c00ff2` — C-1 remediation

---

## Specs Synced

| Domain | Action | Details |
|--------|--------|---------|
| settings | Updated | Composed 4 ADDED requirements into canonical (11 total: 7 preserved + 4 new) |
| user-onboarding-tour | Created | New domain spec with 12 requirements (full-spec format) |

### Canonical Spec Paths
- `openspec/specs/settings/spec.md` — 11 requirements (Theme Token Scope, CSS File Separation, Backend Theme Validation, Theme Key Migration, Frontend Default and Fallback, Preferences UI, Test Coverage, Onboarding Validation, Frontend Type/Default/Hydration, Round-Trip Without Silent No-ops, Existing Rules Unchanged)
- `openspec/specs/user-onboarding-tour/spec.md` — 12 requirements (Trigger Conditions, Segment Order and Cross-Page Hand-Off, Step Content Contract, Conditional Steps and Skip Behavior, Completion Persistence, Restart Semantics, Anchor Contract, Keyboard Suppression, Theme and Dark-Mode Rendering, Accessibility, Mobile Behavior, Code-Evidenced Traps)

### Spec Convention Migration (2026-09-16, user decision)
Live canonical + this change's deltas migrated to OpenSpec standard (`### Requirement:` / `#### Scenario:`) so `sdd-archive-compose` works. Historical archived changes retain the old `### REQ-N:` format (closed records, untouched). The canonical settings spec still carries its legacy detached `## Scenarios` section (intentionally preserved — compose treats it as unrelated content).

---

## Archive Contents

All artifacts preserved in `openspec/changes/archive/2026-09-16-driverjs-usage-guide/`:

- **proposal.md**: Present (23.5K)
- **exploration.md**: Present (12.0K)
- **specs/settings/spec.md**: Present (2.9K) — delta format, 4 ADDED requirements
- **specs/user-onboarding-tour/spec.md**: Present (9.4K) — full-spec format, 12 requirements
- **design.md**: Present (22.4K)
- **tasks.md**: Present (5.9K) — 29/29 tasks complete
- **apply-progress.md**: Present (73.8K) — batches 1–8 + remediation, cumulative
- **verify-report.md**: Present (12.3K) — final verdict PASS

---

## Open Items (Post-Change, Human-Owned)

The following items are documented but do NOT block archiving. They are the maintainer's decisions under ordinary repository policy or out-of-scope follow-ups.

### 1. Delivery Pending
The 7-PR stacked-to-main chain exists only as local commits on `feature/Implement-onboarding-guide`. No remote tracking branch; `gh` CLI unavailable in this environment. Creating/pushing the PRs is the maintainer's delivery decision.

### 2. PR2 Native Review
Terminally stopped at `captured_artifacts_unverifiable` — maintainer decision pending (inspect authority or clone-local RDD disable).

### 3. PR3 Native Review
Frozen at reviewer capture (lineage `review-2130cc8dabe9c0e6`), resumable; the user set it aside. Its candidate predates the completion fix (resolved later by PR7 within the designed scope).

### 4. PR1 Advisory Follow-Ups
Minor advisory findings from PR1 review — documented, not blocking.

### 5. Two SUGGESTION Findings from Verify
Documented follow-ups, deliberately out of scope:
- **Mid-tour manual navigation**: Can orphan a popover until the next segment action.
- **Mobile shell step 1**: Falls back to a centered modal (reka-ui DialogRoot inheritAttrs drops the anchor — root-cause fix would touch the shared ui/sidebar component).

### 6. Accepted Baselines
- 15 pre-existing vue-tsc errors (7 Wayfinder duplicate keys, 1 BalanceLineChart borderDash, 7 Dashboard simulated_amount/is_sandbox)
- Pre-existing eslint violations in Dashboard.vue and Movimientos/Index.vue

---

## Mechanical Archive Verification

### Step 2: Spec Sync
- **Settings compose**: `gentle-ai sdd-archive-compose --canonical openspec/specs/settings/spec.md --delta openspec/changes/driverjs-usage-guide/specs/settings/spec.md --output openspec/specs/settings/spec.md.compose-tmp` → exit 0, 11 requirements composed (7 preserved + 4 ADDED). Atomic `.compose-tmp` + `mv` pattern applied.
- **user-onboarding-tour copy**: Mechanical shell copy via `mktemp` + `cp` + `diff -r` + `mv` pattern. `diff -r` readback: empty (no differences) — byte-for-byte match.

### Step 3: Archive Move
- **Snapshot**: Created at `/tmp/sdd-archive.12IEcM/source` before move
- **git mv**: Failed (exit 128) — git considers source directory empty (files are untracked SDD artifacts)
- **Plain mv fallback**: Succeeded after verifying source matches snapshot
- **diff -r readback**: Empty (no differences) — archive matches snapshot byte-for-byte

### Step 4: Archive Verification
- [x] Main specs updated correctly (settings: 11 requirements; user-onboarding-tour: 12 requirements)
- [x] Change folder moved to archive
- [x] Archive preserves all artifacts that existed (8 files + 2 spec directories)
- [x] Archived tasks retain their original bytes; 29/29 completed
- [x] Active changes directory no longer has this change
- [x] Verbatim `diff -r` readback output included and empty (no differences)

---

## SDD Cycle Complete

The change is archived. Implementation: **complete and verified**. Verification: **PASS** (16/16 requirements, 20/20 scenarios, runtime-verified). Delivery: **PENDING** (human-owned decision).

Unfinished tasks: **none observed** (29/29 complete).  
Unresolved findings: **none blocking** (open items above are post-change, human-owned).

---

## Engram Mirror

Archive report saved to Engram with observation IDs for traceability:
- **Project**: caja-diaria
- **Topic**: `sdd/driverjs-usage-guide/archive-report`
- **Type**: architecture
- **Observation ID**: #459 (sync_id: obs-b537c7ceb8550100)

### Artifacts Read (Engram Observation IDs)
- proposal.md: #432
- specs (user-onboarding-tour + settings): #433
- design.md: #434
- tasks.md: #435
- apply-progress.md: #437
- verify-report.md: #454

---

**Archive Date**: 2026-09-16  
**Archived By**: sdd-archive sub-agent  
**Archive Mode**: hybrid (filesystem + Engram)
