<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { useSettings } from '@/composables/useSettings';
import { TOUR_VERSION, useTour, type TourSegment } from '@/composables/useTour';
import { dashboard } from '@/routes';
import categorias from '@/routes/categorias';
import cuentas from '@/routes/cuentas';
import movimientos from '@/routes/movimientos';
import recurrentes from '@/routes/recurrentes';
import { router, usePage } from '@inertiajs/vue3';
import type { Driver } from 'driver.js';
import { onMounted, watch } from 'vue';

const { settings } = useSettings();
const tour = useTour();
const { isMobile, openMobile, setOpenMobile } = useSidebar();
const page = usePage();

/** Inertia component names that own a tour segment (REQ-2). */
const TOUR_PAGES = new Set([
  'Dashboard',
  'Movimientos/Index',
  'Cuentas/Index',
  'Categorias/Index',
  'Recurrentes/Index',
]);

/** Shell nav steps 2-6 render inside the mobile Sheet (REQ-11). */
const SHEET_OPEN_INDEX = 0; // before step 2 (nav.movimientos)
const SHEET_CLOSE_INDEX = 5; // after step 6 (shell.userMenu)

/**
 * Segment sequencing: called on every next/done click. Advances the driver
 * normally, chains the shell segment into the dashboard segment on the same
 * page, hands off to the next tour page, and opens/closes the mobile Sheet
 * around the shell nav steps.
 */
function handleSegmentNext(segment: TourSegment | null, driver: Driver | null): void {
  if (!segment || !driver) {
    driver?.moveNext();

    return;
  }

  const index = driver.getActiveIndex() ?? 0;

  // Mobile: the Sheet must be open for the nav anchors (steps 2-6) to exist.
  if (isMobile.value && segment === 'shell') {
    if (index === SHEET_OPEN_INDEX) {
      setOpenMobile(true);
    } else if (index === SHEET_CLOSE_INDEX) {
      setOpenMobile(false);
    }
  }

  if (!driver.isLastStep()) {
    driver.moveNext();

    return;
  }

  // Last shell step chains into the dashboard segment on the same page.
  if (segment === 'shell') {
    // Tear down first: chaining setSteps+drive on the live instance
    // resetState()s away the popover/overlay removal handles and orphans
    // the previous step render in the DOM.
    tour.destroy();
    tour.start('dashboard');

    return;
  }

  // Last dashboard step hands off to Movimientos; later slices continue.
  if (segment === 'dashboard') {
    tour.destroy();
    router.visit(movimientos.index().url);

    return;
  }

  // Last movimientos step hands off to Cuentas; later slices continue.
  if (segment === 'movimientos') {
    tour.destroy();
    router.visit(cuentas.index().url);

    return;
  }

  // Last cuentas step hands off to Categorías; later slices continue.
  if (segment === 'cuentas') {
    tour.destroy();
    router.visit(categorias.index().url);

    return;
  }

  // Last categorías step hands off to Recurrentes, the final segment.
  if (segment === 'categorias') {
    tour.destroy();
    router.visit(recurrentes.index().url);

    return;
  }

  // Last recurrentes step (the step-36 modal) completes the tour: its
  // done/Listo click must persist completion and disarm (REQ-5). A bare
  // tour.destroy() would skip onDestroyStarted (driver.js destroys without
  // the hook), so finish() — the only code that persists onboarding AND
  // tears the driver down — is the completion path.
  if (segment === 'recurrentes') {
    void tour.finish();

    return;
  }

  driver.moveNext();
}

onMounted(() => {
  tour.setSegmentNextHandler(handleSegmentNext);

  const onboarding = settings.onboarding;
  const completedAt = onboarding?.completed_at ?? null;
  const version = onboarding?.version ?? 0;

  // REQ-1: auto-run when never completed or when the stored version is outdated.
  if (completedAt !== null && version >= TOUR_VERSION) {
    return;
  }

  tour.setArmed(true);

  // REQ-2 / S5: start on the Dashboard; non-tour pages hand off first.
  if (!TOUR_PAGES.has(page.component)) {
    router.visit(dashboard().url);
  }
});

// Close the mobile Sheet if the tour ends while it is open (Esc / dismiss).
watch(
  () => tour.isActive.value,
  active => {
    if (!active && isMobile.value && openMobile.value) {
      setOpenMobile(false);
    }
  }
);
</script>

<template>
  <!-- Renderless: it only drives the driver.js tour lifecycle. -->
  <span hidden></span>
</template>
