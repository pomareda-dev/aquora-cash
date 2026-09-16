<script setup lang="ts">
import { toDriveSteps, tourStepsBySegment } from '@/components/tour/tourSteps';
import { useSettings } from '@/composables/useSettings';
import { TOUR_VERSION, useTour } from '@/composables/useTour';
import { onMounted } from 'vue';

const { settings } = useSettings();
const tour = useTour();

onMounted(() => {
  const onboarding = settings.onboarding;
  const completedAt = onboarding?.completed_at ?? null;
  const version = onboarding?.version ?? 0;

  // REQ-1: auto-run when never completed or when the stored version is outdated.
  if (completedAt !== null && version >= TOUR_VERSION) {
    return;
  }

  tour.setArmed(true);

  // PR2: no per-page hooks exist yet, so register the smoke placeholder
  // segment directly. PR3 replaces this with usePageTour() registration
  // and the Dashboard hand-off for non-tour pages.
  tour.setSteps('shell', toDriveSteps(tourStepsBySegment.shell ?? []));
  tour.start('shell');
});
</script>

<template>
  <!-- Renders nothing; it only drives the driver.js tour lifecycle. -->
</template>
