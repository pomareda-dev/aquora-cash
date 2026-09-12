<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import recurrentes from '@/routes/recurrentes';
import simulacionRecurrentes from '@/routes/simulacion/recurrentes';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

export interface CategoryData {
  id: number;
  name: string;
  kind: string;
  color: string | null;
}

export interface RecurringData {
  id: number;
  name: string;
  amount: number;
  category_id: number | null;
  category_name: string | null;
  day_of_month: number;
  start_month: string;
  end_month: string | null;
  active: boolean;
  is_sandbox?: boolean;
}

const props = withDefaults(
  defineProps<{
    open: boolean;
    template: RecurringData | null;
    categories: CategoryData[];
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

const form = useForm({
  name: '',
  amount: '',
  category_id: 'none',
  day_of_month: '',
  start_month: '',
  end_month: '',
  active: true,
});

function resetForm() {
  form.reset();
  form.clearErrors();
}

function populateFormForEdit(template: RecurringData) {
  form.name = template.name;
  form.amount = String(template.amount);
  form.category_id = template.category_id !== null ? String(template.category_id) : 'none';
  form.day_of_month = String(template.day_of_month);
  form.start_month = template.start_month;
  form.end_month = template.end_month || '';
  form.active = template.active;
}

watch(
  () => props.open,
  isOpen => {
    if (isOpen && props.template) {
      populateFormForEdit(props.template);
    } else if (isOpen && !props.template) {
      // Default start_month to current month
      const now = new Date();
      const year = now.getFullYear();
      const month = String(now.getMonth() + 1).padStart(2, '0');
      form.start_month = `${year}-${month}-01`;
      form.active = true;
    }
  }
);

const isEditing = () => props.template !== null;

function closeDialog() {
  emit('update:open', false);
  resetForm();
}

function submit() {
  form.transform(data => ({
    ...data,
    category_id: data.category_id === 'none' ? null : Number(data.category_id),
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
      form.put(simulacionRecurrentes.update.url(props.template!.id), options);
    } else {
      form.post(simulacionRecurrentes.store.url(), options);
    }
  } else {
    if (isEditing()) {
      form.put(recurrentes.update.url(props.template!.id), options);
    } else {
      form.post(recurrentes.store.url(), options);
    }
  }
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="closeDialog"
  >
    <DialogContent class="sm:max-w-[425px]">
      <DialogHeader>
        <DialogTitle class="flex items-center gap-2">
          {{ isEditing() ? 'Editar plantilla' : 'Nueva plantilla recurrente' }}
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
              ? 'Actualiza los datos de la transacción recurrente.'
              : 'Define una transacción que se repite cada mes.'
          }}
        </DialogDescription>
      </DialogHeader>

      <form
        class="flex flex-col gap-4"
        @submit.prevent="submit"
      >
        <!-- Nombre -->
        <div class="grid gap-2">
          <Label for="name">Nombre</Label>
          <Input
            id="name"
            v-model="form.name"
            type="text"
            placeholder="Ej: Falabella, Alquiler"
          />
          <InputError :message="form.errors.name" />
        </div>

        <!-- Importe -->
        <div class="grid gap-2">
          <Label for="amount">Importe</Label>
          <Input
            id="amount"
            v-model="form.amount"
            type="number"
            step="0.01"
            placeholder="0.00"
          />
          <p class="text-xs text-muted-foreground">Usa signo negativo para gastos (ej: -300.96)</p>
          <InputError :message="form.errors.amount" />
        </div>

        <!-- Categoría -->
        <div class="grid gap-2">
          <Label for="category_id">Categoría (opcional)</Label>
          <Select v-model="form.category_id">
            <SelectTrigger>
              <SelectValue placeholder="Seleccionar categoría" />
            </SelectTrigger>
            <SelectContent>
              <SelectGroup>
                <SelectItem value="none"> Sin categoría </SelectItem>
                <SelectItem
                  v-for="cat in categories"
                  :key="cat.id"
                  :value="String(cat.id)"
                >
                  {{ cat.name }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
          <InputError :message="form.errors.category_id" />
        </div>

        <!-- Día del mes -->
        <div class="grid gap-2">
          <Label for="day_of_month">Día del mes</Label>
          <Input
            id="day_of_month"
            v-model="form.day_of_month"
            type="number"
            step="1"
            min="1"
            max="31"
            placeholder="1"
          />
          <InputError :message="form.errors.day_of_month" />
        </div>

        <!-- Mes de inicio -->
        <div class="grid gap-2">
          <Label for="start_month">Mes de inicio</Label>
          <Input
            id="start_month"
            v-model="form.start_month"
            type="date"
          />
          <InputError :message="form.errors.start_month" />
        </div>

        <!-- Mes de fin -->
        <div class="grid gap-2">
          <Label for="end_month">Mes de fin (opcional)</Label>
          <Input
            id="end_month"
            v-model="form.end_month"
            type="date"
          />
          <p class="text-xs text-muted-foreground">Déjalo vacío para que se genere indefinidamente</p>
          <InputError :message="form.errors.end_month" />
        </div>

        <!-- Activo toggle -->
        <div class="flex items-center gap-2">
          <Checkbox
            id="active"
            v-model="form.active"
          />
          <Label
            for="active"
            class="cursor-pointer text-sm leading-none font-normal"
          >
            Activo
          </Label>
        </div>
        <InputError :message="form.errors.active" />

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
