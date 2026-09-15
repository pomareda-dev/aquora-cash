<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
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
  <AppShell variant="sidebar">
    <AppSidebar />
    <SimulationBanner v-if="modeActive" />
    <AppContent
      variant="sidebar"
      class="overflow-x-hidden"
    >
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <slot />
    </AppContent>
    <Toaster />
  </AppShell>
</template>
