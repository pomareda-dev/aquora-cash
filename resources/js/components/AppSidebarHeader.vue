<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useSandbox } from '@/composables/useSandbox';
import type { BreadcrumbItem } from '@/types';
import { FlaskConical } from '@lucide/vue';

withDefaults(
  defineProps<{
    breadcrumbs?: BreadcrumbItem[];
  }>(),
  {
    breadcrumbs: () => [],
  }
);

const { modeActive, toggle: toggleSandbox } = useSandbox();
</script>

<template>
  <header
    class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
  >
    <div class="flex items-center gap-2">
      <SidebarTrigger class="-ml-1" />
      <template v-if="breadcrumbs && breadcrumbs.length > 0">
        <Breadcrumbs :breadcrumbs="breadcrumbs" />
      </template>
    </div>
    <div class="ml-auto flex items-center gap-2">
      <TooltipProvider :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <Button
              variant="outline"
              size="icon"
              class="group relative h-9 w-9 cursor-pointer"
              :class="
                modeActive
                  ? 'border-amber-300 bg-amber-50 text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400'
                  : 'text-muted-foreground'
              "
              @click="toggleSandbox"
            >
              <FlaskConical class="size-5" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            {{ modeActive ? 'Desactivar modo Simulación' : 'Activar modo Simulación' }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </div>
  </header>
</template>
