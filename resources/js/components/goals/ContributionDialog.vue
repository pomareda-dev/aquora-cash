<script setup lang="ts">
import type { GoalData } from '@/components/goals/types';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrency } from '@/composables/useCurrency';
import metas from '@/routes/metas';
import simulacionMetasAportes from '@/routes/simulacion/metas/aportes';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = withDefaults(
  defineProps<{
    open: boolean;
    goal: GoalData | null;
    mode?: 'real' | 'sandbox';
  }>(),
  {
    mode: 'real',
  }
);

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved'): void;
}>();

const { format } = useCurrency();

const isSandbox = computed(() => props.mode === 'sandbox');

const form = useForm({
  amount: '',
  date: '',
  notes: '',
});

watch(
  () => props.open,
  isOpen => {
    if (!isOpen) {
      return;
    }

    form.reset();
    form.clearErrors();

    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    form.date = `${year}-${month}-${day}`;
  }
);

function closeDialog(): void {
  emit('update:open', false);
}

function submit(): void {
  if (!props.goal) {
    return;
  }

  form.transform(data => ({
    ...data,
    amount: Number(data.amount),
    notes: data.notes || null,
  }));

  const url = isSandbox.value
    ? simulacionMetasAportes.store.url(props.goal.id)
    : metas.aportes.store.url(props.goal.id);

  form.post(url, {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved');
      closeDialog();
    },
  });
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="closeDialog"
  >
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <div class="flex items-center gap-2">
          <DialogTitle>Registrar aporte</DialogTitle>
          <Badge
            v-if="isSandbox"
            variant="outline"
            class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
          >
            Simulado
          </Badge>
        </div>
        <DialogDescription>
          Sumá dinero a la meta
          <strong>{{ goal?.name }}</strong
          >.
          <span v-if="isSandbox">Este aporte es simulado y no afecta tu progreso real.</span>
          <span v-else>El aporte no es un ingreso ni un gasto: queda apartado del disponible real.</span>
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <div class="rounded-md bg-muted px-3 py-2 text-sm">
          Progreso actual:
          <span class="font-semibold tabular-nums">
            {{ goal ? format(goal.progress_amount) : '—' }}
          </span>
          <span class="text-muted-foreground"> de {{ goal ? format(goal.target_amount) : '—' }} </span>
        </div>

        <div class="grid gap-2">
          <Label for="contribution_amount">Monto</Label>
          <Input
            id="contribution_amount"
            v-model="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
          />
          <InputError :message="form.errors.amount" />
        </div>

        <div class="grid gap-2">
          <Label for="contribution_date">Fecha</Label>
          <Input
            id="contribution_date"
            v-model="form.date"
            type="date"
            :max="isSandbox ? undefined : form.date"
          />
          <InputError :message="form.errors.date" />
        </div>

        <div class="grid gap-2">
          <Label for="contribution_notes">Nota (opcional)</Label>
          <Input
            id="contribution_notes"
            v-model="form.notes"
            type="text"
            placeholder="Ej: primer sueldo del mes"
          />
          <InputError :message="form.errors.notes" />
        </div>

        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            @click="closeDialog"
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Guardando...' : isSandbox ? 'Simular aporte' : 'Registrar aporte' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
