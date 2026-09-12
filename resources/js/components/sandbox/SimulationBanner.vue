<script setup lang="ts">
import { FlaskConical } from '@lucide/vue';
</script>

<template>
  <div class="border-b border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50">
    <div class="mx-auto flex items-center gap-3 px-4 py-2">
      <FlaskConical class="h-4 w-4 text-amber-600 dark:text-amber-400" />
      <span class="text-sm font-medium text-amber-800 dark:text-amber-300">
        Estás en modo Simulación — los cambios no afectan tu balance real
      </span>
    </div>
  </div>
</template>
