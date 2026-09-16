import { useSettings } from '@/composables/useSettings';
import { dashboard } from '@/routes';
import { router } from '@inertiajs/vue3';
import type { Driver, DriveStep } from 'driver.js';
import { readonly, ref } from 'vue';

/**
 * Canonical tour segment order. Each segment maps to one page component;
 * the shell segment runs on the Dashboard.
 */
export type TourSegment = 'shell' | 'dashboard' | 'movimientos' | 'cuentas' | 'categorias' | 'recurrentes';

/** Bump this to re-trigger the tour for every user. */
export const TOUR_VERSION = 1;

export interface TourState {
  isActive: boolean;
  armed: boolean;
  currentSegment: TourSegment | null;
  stepIndex: number;
}

// Module-scoped singleton state — shared by every component that calls useTour().
const isActive = ref(false);
const armed = ref(false);
const currentSegment = ref<TourSegment | null>(null);
const stepIndex = ref(0);

const stepsBySegment = new Map<TourSegment, DriveStep[]>();
let driverInstance: Driver | null = null;
let driverPromise: Promise<Driver | null> | null = null;

/**
 * Segment sequencing hook. The launcher registers a handler that decides
 * what happens when the user clicks next/done (REQ-2 hand-offs, mobile
 * Sheet control). When no handler is set, the driver advances normally.
 */
export type SegmentNextHandler = (segment: TourSegment | null, driver: Driver | null) => void;

let segmentNextHandler: SegmentNextHandler | null = null;

function setSegmentNextHandler(handler: SegmentNextHandler | null): void {
  segmentNextHandler = handler;
}

function isTourActive(): boolean {
  return isActive.value;
}

/** Arm/disarm the tour. The launcher evaluates REQ-1 once per page load. */
function setArmed(value: boolean): void {
  armed.value = value;
}

/** Register a segment's steps (converted from tourSteps.ts). */
function setSteps(segment: TourSegment, steps: DriveStep[]): void {
  stepsBySegment.set(segment, steps);
}

/**
 * Defensive sweep of driver.js DOM leftovers. driver.js keeps its
 * popover/overlay removal handles in internal state that setSteps()
 * resetState() can drop, so an interrupted or superseded run can leave
 * orphaned elements on <body> forever; nothing else owns cleaning them.
 */
function sweepStaleDriverDom(): void {
  if (typeof document === 'undefined') {
    return;
  }

  document
    .querySelectorAll('#driver-popover-content, .driver-overlay, #driver-dummy-element')
    .forEach(element => element.remove());
}

/**
 * Lazily create the driver instance. The dynamic import keeps driver.js out
 * of the SSR bundle and off the initial client chunk (SSR-safety risk).
 */
async function ensureDriver(): Promise<Driver | null> {
  if (typeof window === 'undefined') {
    return null;
  }

  // Memoize the construction promise: two overlapping run() calls would
  // both see driverInstance === null while the dynamic import is pending
  // and build two drivers, leaving a frozen duplicate popover on screen.
  if (!driverPromise) {
    driverPromise = (async () => {
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
        overlayClickBehavior: 'close',
        overlayOpacity: 0.5,
        stagePadding: 4,
        stageRadius: 8,
        animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        // Let portal-rendered anchors (mobile sidebar Sheet) appear before
        // highlighting the step; a missing element falls back after the wait.
        waitForElement: 1000,
        onHighlightStarted: (_element, _step, opts) => {
          stepIndex.value = opts.index ?? 0;
        },
        onNextClick: () => {
          if (segmentNextHandler) {
            segmentNextHandler(currentSegment.value, driverInstance);

            return;
          }

          driverInstance?.moveNext();
        },
        onDestroyStarted: () => {
          // Finish AND dismissal (close, Esc, overlay) persist completion (REQ-5).
          void finish();
        },
      });

      return driverInstance;
    })();
  }

  return driverPromise;
}

async function run(segment: TourSegment | null): Promise<void> {
  if (!segment) {
    return;
  }

  // Idempotence: a page remount and restart()'s onSuccess can both request
  // the same segment in the same tick; re-driving an active segment would
  // duplicate the tour UI.
  if (isActive.value && currentSegment.value === segment) {
    return;
  }

  const steps = stepsBySegment.get(segment);

  if (!steps?.length) {
    return;
  }

  const instance = await ensureDriver();

  if (!instance) {
    return;
  }

  // Clear any orphaned driver DOM before rendering: a previous or
  // superseded run may have lost its removal handles.
  sweepStaleDriverDom();

  currentSegment.value = segment;
  stepIndex.value = 0;
  isActive.value = true;
  instance.setSteps(steps);
  instance.drive(0);
}

/** Begin the tour from the given segment (or the current one). */
function start(segment?: TourSegment): void {
  void run(segment ?? currentSegment.value);
}

/**
 * Register + run a segment. Called by usePageTour() when a tour page mounts.
 * No-op while the tour is not armed or a different segment is active.
 */
function advance(segment: TourSegment): void {
  if (!armed.value) {
    return;
  }

  if (currentSegment.value !== null && currentSegment.value !== segment) {
    return;
  }

  // The shell segment always runs first on the Dashboard (REQ-2).
  if (segment === 'dashboard' && currentSegment.value === null && (stepsBySegment.get('shell')?.length ?? 0) > 0) {
    void run('shell');

    return;
  }

  void run(segment);
}

/**
 * Re-arm in memory, tear down any active tour without persisting, and
 * navigate to the Dashboard. The persistent layout never remounts
 * TourLauncher on an Inertia visit, so the relaunch must come from here,
 * once the Dashboard page has swapped in.
 */
function restart(): void {
  setArmed(true);

  if (isActive.value) {
    destroy();
  }

  router.visit(dashboard().url, {
    onSuccess: () => {
      start('shell');
    },
  });
}

/** Persist completion and tear the driver down (REQ-5). */
async function finish(): Promise<void> {
  armed.value = false;
  isActive.value = false;
  currentSegment.value = null;
  stepIndex.value = 0;
  destroy();

  const { updateSettings } = useSettings();
  await updateSettings({
    onboarding: {
      completed_at: new Date().toISOString(),
      version: TOUR_VERSION,
    },
  });
}

/** Destroy the active driver without persisting (segment hand-off / restart). */
function destroy(): void {
  driverInstance?.destroy();
  sweepStaleDriverDom();
  isActive.value = false;
  currentSegment.value = null;
}

export function useTour() {
  return {
    isActive: readonly(isActive),
    armed: readonly(armed),
    currentSegment: readonly(currentSegment),
    stepIndex: readonly(stepIndex),
    isTourActive,
    setArmed,
    start,
    restart,
    finish,
    advance,
    setSteps,
    setSegmentNextHandler,
    destroy,
  };
}
