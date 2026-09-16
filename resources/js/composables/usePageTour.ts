import { toDriveSteps, tourStepsBySegment } from '@/components/tour/tourSteps';
import { useTour, type TourSegment } from '@/composables/useTour';
import { onMounted } from 'vue';

/**
 * Per-page tour registration hook.
 *
 * Call once in onMounted() on every tour page. Registers the page's steps
 * with the useTour singleton and, when the tour is armed, starts the
 * segment if it is the next one in the canonical order.
 */
export function usePageTour(segment: TourSegment): void {
  onMounted(() => {
    const tour = useTour();

    tour.setSteps(segment, toDriveSteps(tourStepsBySegment[segment] ?? []));

    // Shell steps run on the Dashboard (REQ-2): register them so the
    // launcher can chain shell (1-8) then dashboard (9-17) in one page.
    if (segment === 'dashboard') {
      tour.setSteps('shell', toDriveSteps(tourStepsBySegment.shell ?? []));
    }

    tour.advance(segment);
  });
}
