<script setup lang="ts">
import type { DebtData } from '@/components/debts/types';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
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
import { useSettings } from '@/composables/useSettings';
import deudas from '@/routes/deudas';
import preferences from '@/routes/preferences';
import simulacionDeudas from '@/routes/simulacion/deudas';
import { Link, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

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

const { settings } = useSettings();

const debtCategoryConfigured = computed(() => settings.debt_category_id !== null);

const categoryError = ref('');

const form = useForm({
  name: '',
  principal_amount: '',
  disbursement_date: '',
  installment_amount: '',
  installments_count: '',
  payment_dates: [] as string[],
});

function toYMD(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

function addMonths(dateStr: string, months: number): string {
  const date = new Date(dateStr + 'T00:00:00');
  const day = date.getDate();
  date.setDate(1);
  date.setMonth(date.getMonth() + months);

  const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  date.setDate(Math.min(day, daysInMonth));

  return toYMD(date);
}

function syncPaymentDates(count: number): void {
  const dates = [...form.payment_dates];

  while (dates.length > count) {
    dates.pop();
  }

  while (dates.length < count) {
    const last = dates[dates.length - 1] ?? props.debt?.disbursement_date ?? form.disbursement_date;
    dates.push(last ? addMonths(last, 1) : '');
  }

  form.payment_dates = dates;
}

watch(
  () => form.installments_count,
  raw => {
    const count = parseInt(String(raw), 10);

    if (!Number.isFinite(count) || count < 1) {
      return;
    }

    syncPaymentDates(count);
  }
);

watch(
  () => props.open,
  isOpen => {
    if (!isOpen) {
      return;
    }

    form.reset();
    form.clearErrors();
    categoryError.value = '';

    if (props.debt) {
      form.name = props.debt.name;
      form.principal_amount = String(props.debt.principal_amount);
      form.disbursement_date = props.debt.disbursement_date;
      form.installment_amount = String(props.debt.installment_amount);
      form.installments_count = String(props.debt.installments_count);
      form.payment_dates = [...props.debt.payment_dates];
    } else {
      form.disbursement_date = toYMD(new Date());
    }
  }
);

const isEditing = () => props.debt !== null;

function closeDialog(): void {
  emit('update:open', false);
}

function submit(): void {
  if (!isEditing() && !debtCategoryConfigured.value) {
    categoryError.value = 'Configura una categoría para tus préstamos antes de crear la deuda.';

    return;
  }

  form.transform(data => ({
    ...data,
    principal_amount: Number(data.principal_amount),
    installment_amount: Number(data.installment_amount),
    installments_count: parseInt(String(data.installments_count), 10),
  }));

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      emit('saved');
      closeDialog();
    },
  };

  if (props.mode === 'sandbox') {
    if (isEditing()) {
      form.put(simulacionDeudas.update.url(props.debt!.id), options);
    } else {
      form.post(simulacionDeudas.store.url(), options);
    }
  } else {
    if (isEditing()) {
      form.put(deudas.update.url(props.debt!.id), options);
    } else {
      form.post(deudas.store.url(), options);
    }
  }
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="closeDialog"
  >
    <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-[500px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          {{ isEditing() ? 'Editar deuda' : 'Nueva deuda' }}
          <Badge
            v-if="mode === 'sandbox'"
            variant="secondary"
            class="bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300"
          >
            Simulado
          </Badge>
        </DialogTitle>
        <DialogDescription>
          {{
            isEditing()
              ? 'Actualiza los datos de la deuda. Solo se regeneran las cuotas proyectadas; los pagos reales no se tocan.'
              : 'Registra un préstamo: se crearán el desembolso y una cuota por cada fecha de pago.'
          }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <!-- Category warning (create only) -->
        <Alert
          v-if="!isEditing() && !debtCategoryConfigured"
          variant="default"
        >
          <TriangleAlert class="size-4" />
          <AlertTitle>Sin categoría configurada</AlertTitle>
          <AlertDescription>
            Elegí la categoría para los movimientos de tus préstamos en Preferencias antes de crear la deuda.
            <Link
              :href="preferences.edit()"
              class="font-medium underline underline-offset-4"
            >
              Ir a Preferencias
            </Link>
          </AlertDescription>
        </Alert>
        <InputError :message="categoryError" />

        <!-- Nombre -->
        <div class="grid gap-2">
          <Label for="name">Nombre</Label>
          <Input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Ej: Préstamo personal"
          />
          <InputError :message="form.errors.name" />
        </div>

        <!-- Monto prestado -->
        <div class="grid gap-2">
          <Label for="principal_amount">Monto prestado</Label>
          <Input
            id="principal_amount"
            v-model="form.principal_amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
          />
          <InputError :message="form.errors.principal_amount" />
        </div>

        <!-- Fecha de desembolso -->
        <div class="grid gap-2">
          <Label for="disbursement_date">Fecha de desembolso</Label>
          <Input
            id="disbursement_date"
            v-model="form.disbursement_date"
            type="date"
          />
          <InputError :message="form.errors.disbursement_date" />
        </div>

        <!-- Cuota fija -->
        <div class="grid gap-2">
          <Label for="installment_amount">Cuota fija</Label>
          <Input
            id="installment_amount"
            v-model="form.installment_amount"
            type="number"
            step="0.01"
            min="0.01"
            placeholder="0.00"
          />
          <InputError :message="form.errors.installment_amount" />
        </div>

        <!-- Número de cuotas -->
        <div class="grid gap-2">
          <Label for="installments_count">Número de cuotas</Label>
          <Input
            id="installments_count"
            v-model="form.installments_count"
            type="number"
            step="1"
            min="1"
            max="360"
            placeholder="12"
          />
          <InputError :message="form.errors.installments_count" />
        </div>

        <!-- Fechas de pago -->
        <div class="grid gap-2">
          <Label>Fechas de pago</Label>
          <p class="text-xs text-muted-foreground">
            Una fecha por cuota. Se agregan o quitan al cambiar el número de cuotas.
          </p>
          <div
            v-if="form.payment_dates.length > 0"
            class="grid max-h-52 gap-2 overflow-y-auto pr-1"
          >
            <div
              v-for="(_, index) in form.payment_dates"
              :key="index"
              class="grid grid-cols-[4.5rem_1fr] items-center gap-2"
            >
              <Label
                :for="`payment_date_${index}`"
                class="text-xs text-muted-foreground"
              >
                Cuota {{ index + 1 }}
              </Label>
              <Input
                :id="`payment_date_${index}`"
                v-model="form.payment_dates[index]"
                type="date"
              />
            </div>
          </div>
          <p
            v-else
            class="text-xs text-muted-foreground"
          >
            Define el número de cuotas para generar las fechas de pago.
          </p>
          <InputError :message="form.errors.payment_dates" />
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
            {{ form.processing ? 'Guardando...' : isEditing() ? 'Actualizar' : 'Crear' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
