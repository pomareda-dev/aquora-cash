<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppHeader.vue';
import AppShell from '@/components/AppShell.vue';
import SimulationBanner from '@/components/sandbox/SimulationBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import { useSandbox } from '@/composables/useSandbox';
import type { BreadcrumbItem } from '@/types';

type Props = {
  breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
});

const { modeActive } = useSandbox();
</script>

<template>
  <AppShell variant="header">
    <AppHeader :breadcrumbs="breadcrumbs" />
    <SimulationBanner v-if="modeActive" />
    <AppContent variant="header">
      <slot />
    </AppContent>
    <Toaster />
  </AppShell>
</template>
