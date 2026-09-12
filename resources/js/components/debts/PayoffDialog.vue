<script setup lang="ts">
import type { DebtData } from '@/components/debts/types';
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
import deudas from '@/routes/deudas';
import simulacionDeudas from '@/routes/simulacion/deudas';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = withDefaults(defineProps<{
  open: boolean;
  debt: DebtData | null;
  mode?: 'real' | 'sandbox';
}>(), {
  mode: 'real',
});

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'saved'): void;
}>();

const { format } = useCurrency();

const form = useForm({
  amount: '',
});

watch(
  () => props.open,
  isOpen => {
    if (isOpen && props.debt) {
      form.amount = String(props.debt.remaining);
      form.clearErrors();
    }
  }
);

function closeDialog(): void {
  emit('update:open', false);
}

function submit(): void {
  if (!props.debt) {
    return;
  }

  form.transform(data => ({
    ...data,
    amount: Number(data.amount),
  }));

  const url = props.mode === 'sandbox'
    ? simulacionDeudas.payoff.url(props.debt.id)
    : deudas.payoff.url(props.debt.id);

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
        <DialogTitle>Liquidar deuda</DialogTitle>
        <DialogDescription>
          Ingresa el monto acordado de liquidación. Se creará un único movimiento «Liquidación anticipada
          {{ debt?.name }}» fechado hoy y se eliminarán las cuotas proyectadas restantes. La deuda quedará cerrada como
          historial.
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <div class="rounded-md bg-muted px-3 py-2 text-sm">
          Restante actual:
          <span class="font-semibold tabular-nums">
            {{ debt ? format(debt.remaining) : '—' }}
          </span>
        </div>

        <div class="grid gap-2">
          <Label for="payoff_amount">Monto acordado</Label>
          <Input
            id="payoff_amount"
            v-model="form.amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
          />
          <InputError :message="form.errors.amount" />
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
            {{ form.processing ? 'Liquidando...' : 'Liquidar deuda' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
