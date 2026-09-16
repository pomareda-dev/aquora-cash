import type { TourSegment } from '@/composables/useTour';
import type { DriveStep } from 'driver.js';

/**
 * Rich tour step definition. Kept separate from driver.js's DriveStep so
 * that all copy and per-step behavior lives in one reviewable file; pages
 * stay thin and only register their segment.
 */
export interface TourStep {
  /** data-tour anchor selector, e.g. 'shell.sidebar'. Omit for a target-less modal step. */
  anchor?: string;
  /** Title copy. Spanish UI copy per proposal §4. */
  title: string;
  /** Description copy. Spanish UI copy per proposal §4. */
  description: string;
  /** Popover side relative to the anchored element. */
  side?: 'top' | 'right' | 'bottom' | 'left';
  /** When provided and false, the step is skipped at run time. */
  precondition?: () => boolean;
  /** When true, the step is skipped on mobile viewports. */
  mobileSkip?: boolean;
}

/**
 * PR2 placeholder smoke step: a target-less modal that proves the whole
 * driver.js pipeline renders. Replaced by the real shell + dashboard steps
 * in PR3.
 */
const smokeStep: TourStep = {
  title: 'Guía de uso',
  description: 'Paso de prueba: la guía se cargó correctamente.',
};

/**
 * All step definitions keyed by segment. Shell steps run on the Dashboard.
 * Empty segments fill in across PR3–PR7.
 */
export const tourStepsBySegment: Record<TourSegment, TourStep[]> = {
  shell: [smokeStep],
  dashboard: [],
  movimientos: [],
  cuentas: [],
  categorias: [],
  recurrentes: [],
};

/**
 * Convert our rich TourStep definitions into driver.js DriveSteps.
 * Steps without an anchor render as a centered modal popover (driver.js
 * falls back to an offscreen dummy element).
 */
export function toDriveSteps(steps: TourStep[]): DriveStep[] {
  return steps.map(step => {
    const driveStep: DriveStep = {
      popover: {
        title: step.title,
        description: step.description,
        ...(step.side ? { side: step.side } : {}),
      },
    };

    if (step.anchor) {
      driveStep.element = `[data-tour="${step.anchor}"]`;
    }

    return driveStep;
  });
}
