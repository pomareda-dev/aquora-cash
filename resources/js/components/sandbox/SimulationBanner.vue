<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useSandbox } from '@/composables/useSandbox';
import { FlaskConical } from '@lucide/vue';
import { ref } from 'vue';

const { hasSandbox, revert, save } = useSandbox();

const showRevertConfirm = ref(false);
const showSaveConfirm = ref(false);
</script>

<template>
  <div class="border-b border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/50">
    <div class="mx-auto flex items-center gap-3 px-4 py-2">
      <FlaskConical class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
      <span class="text-sm font-medium text-amber-800 dark:text-amber-300">
        Estás en modo Simulación — los cambios no afectan tu balance real
      </span>
      <div v-if="hasSandbox" class="ml-auto flex items-center gap-2">
        <Button
          variant="destructive"
          size="sm"
          @click="showRevertConfirm = true"
        >
          Revertir escenario
        </Button>
        <Button
          size="sm"
          @click="showSaveConfirm = true"
        >
          Guardar como real
        </Button>
      </div>
    </div>
  </div>

  <!-- Revert confirmation dialog -->
  <Dialog :open="showRevertConfirm" @update:open="showRevertConfirm = $event">
    <DialogContent class="sm:max-w-[420px]">
      <DialogHeader>
        <DialogTitle>Revertir escenario de simulación</DialogTitle>
        <DialogDescription>
          Revertir descartará TODO el escenario simulado. Esta acción no se puede deshacer. ¿Confirmar?
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="showRevertConfirm = false">Cancelar</Button>
        <Button
          variant="destructive"
          @click="
            showRevertConfirm = false;
            revert();
          "
        >
          Revertir
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>

  <!-- Save confirmation dialog -->
  <Dialog :open="showSaveConfirm" @update:open="showSaveConfirm = $event">
    <DialogContent class="sm:max-w-[420px]">
      <DialogHeader>
        <DialogTitle>Guardar escenario como real</DialogTitle>
        <DialogDescription>
          Guardar convertirá el escenario en permanente y no se puede deshacer. Los aportes simulados con fecha futura no se guardarán. ¿Confirmar?
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button variant="outline" @click="showSaveConfirm = false">Cancelar</Button>
        <Button
          @click="
            showSaveConfirm = false;
            save();
          "
        >
          Guardar
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
