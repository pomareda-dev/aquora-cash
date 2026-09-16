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
 * Lazily create the driver instance. The dynamic import keeps driver.js out
 * of the SSR bundle and off the initial client chunk (SSR-safety risk).
 */
async function ensureDriver(): Promise<Driver | null> {
  if (typeof window === 'undefined') {
    return null;
  }

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
      overlayClickBehavior: 'close',
      overlayOpacity: 0.5,
      stagePadding: 4,
      stageRadius: 8,
      animate: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      onHighlightStarted: (_element, _step, opts) => {
        stepIndex.value = opts.index ?? 0;
      },
      onDestroyStarted: () => {
        // Finish AND dismissal (close, Esc, overlay) persist completion (REQ-5).
        void finish();
      },
    });
  }

  return driverInstance;
}

async function run(segment: TourSegment | null): Promise<void> {
  if (!segment) {
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

  void run(segment);
}

/** Re-arm in memory and navigate to the Dashboard; the launcher restarts on mount. */
function restart(): void {
  setArmed(true);

  if (isActive.value) {
    destroy();
  }

  router.visit(dashboard().url);
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
  isActive.value = false;
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
    destroy,
  };
}
