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
    tour.advance(segment);
  });
}
