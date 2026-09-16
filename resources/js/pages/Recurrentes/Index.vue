<script setup lang="ts">
import type { CategoryData, RecurringData } from '@/components/recurring/RecurringDialog.vue';
import RecurringDialog from '@/components/recurring/RecurringDialog.vue';
import type { ResponsiveColumn } from '@/components/ResponsiveTable.vue';
import ResponsiveTable from '@/components/ResponsiveTable.vue';
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
import { useCurrency } from '@/composables/useCurrency';
import { usePageTour } from '@/composables/usePageTour';
import { useSandbox } from '@/composables/useSandbox';
import recurrentes from '@/routes/recurrentes';
import simulacionRecurrentes from '@/routes/simulacion/recurrentes';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, RefreshCw, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  templates: RecurringData[];
  sandboxTemplates?: RecurringData[];
  categories: CategoryData[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Recurrentes',
        href: recurrentes.index(),
      },
    ],
  },
});

const { format, formatSigned } = useCurrency();
const { modeActive } = useSandbox();

// Register this page's tour segment (steps 32-36).
usePageTour('recurrentes');

// Combine real + sandbox templates
const combinedTemplates = computed(() => {
  return [...props.templates, ...(props.sandboxTemplates ?? [])];
});

function asTemplate(row: Record<string, unknown>): RecurringData {
  return row as unknown as RecurringData;
}

// --- Table columns ---
const tableColumns: ResponsiveColumn[] = [
  {
    key: 'name',
    header: 'Nombre',
    primary: true,
    className: 'font-medium',
  },
  {
    key: 'amount',
    header: 'Importe',
    align: 'right',
    className: 'font-medium tabular-nums',
  },
  {
    key: 'category_name',
    header: 'Categoría',
    className: 'text-muted-foreground',
  },
  {
    key: 'day_of_month',
    header: 'Día',
    align: 'center',
    hideOnMobile: true,
    className: 'tabular-nums',
  },
  {
    key: 'start_month',
    header: 'Inicio',
    hideOnMobile: true,
    className: 'tabular-nums',
  },
  {
    key: 'end_month',
    header: 'Fin',
    hideOnMobile: true,
    className: 'tabular-nums',
  },
  { key: 'active', header: 'Estado', align: 'center' },
];

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingTemplate = ref<RecurringData | null>(null);
const deleteTarget = ref<RecurringData | null>(null);
const showDeleteDialog = ref(false);

const dialogMode = computed<'real' | 'sandbox'>(() => {
  if (editingTemplate.value) {
    return editingTemplate.value.is_sandbox ? 'sandbox' : 'real';
  }

  return modeActive.value ? 'sandbox' : 'real';
});

function openCreate() {
  editingTemplate.value = null;
  showCreateDialog.value = true;
}

function openEdit(template: RecurringData) {
  editingTemplate.value = template;
  showCreateDialog.value = true;
}

function confirmDelete(template: RecurringData) {
  deleteTarget.value = template;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  const url = deleteTarget.value.is_sandbox
    ? simulacionRecurrentes.destroy.url(deleteTarget.value.id)
    : recurrentes.destroy.url(deleteTarget.value.id);

  router.delete(url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteDialog.value = false;
      deleteTarget.value = null;
    },
    onError: () => {
      showDeleteDialog.value = false;
      deleteTarget.value = null;
    },
  });
}

function regenerateProjections() {
  const url = modeActive.value ? simulacionRecurrentes.regenerate.url() : recurrentes.regenerate.url();

  router.post(url, {}, { preserveScroll: true });
}

function parseDate(dateStr: string | null): string {
  if (!dateStr) {
    return '—';
  }

  const d = new Date(dateStr + 'T00:00:00');

  return d.toLocaleDateString('es-PE', { month: 'short', year: 'numeric' });
}

function formatSign(value: number): string {
  if (value === 0) {
    return format(value);
  }

  return formatSigned(value);
}
</script>

<template>
  <Head title="Recurrentes" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div
      class="mb-2"
      data-tour="recurrentes.header"
    >
      <h1 class="text-2xl font-bold tracking-tight">Transacciones Recurrentes</h1>
      <p class="text-sm text-muted-foreground">Gestiona tus plantillas de ingresos y gastos periódicos</p>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          data-tour="recurrentes.regenerate"
          @click="regenerateProjections"
        >
          <RefreshCw class="mr-1 size-4" />
          Regenerar proyecciones
        </Button>
      </div>
      <Button
        :class="
          modeActive
            ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900'
            : ''
        "
        data-tour="recurrentes.create"
        @click="openCreate"
      >
        <Plus class="mr-1 size-4" />
        {{ modeActive ? 'Nueva plantilla simulada' : 'Nueva plantilla' }}
      </Button>
    </div>

    <!-- Table -->
    <ResponsiveTable
      :columns="tableColumns"
      :rows="combinedTemplates as unknown as Record<string, unknown>[]"
      row-key="id"
      data-tour="recurrentes.table"
    >
      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span class="font-medium">{{ asTemplate(row).name }}</span>
          <Badge
            v-if="asTemplate(row).is_sandbox"
            variant="outline"
            class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
          >
            Simulado
          </Badge>
        </div>
      </template>

      <template #cell-amount="{ row }">
        <span
          class="font-medium tabular-nums"
          :class="asTemplate(row).amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
        >
          {{ formatSign(asTemplate(row).amount) }}
        </span>
      </template>

      <template #cell-category_name="{ row }">
        {{ asTemplate(row).category_name ?? 'Sin categoría' }}
      </template>

      <template #cell-day_of_month="{ row }">
        {{ asTemplate(row).day_of_month }}
      </template>

      <template #cell-start_month="{ row }">
        {{ parseDate(asTemplate(row).start_month) }}
      </template>

      <template #cell-end_month="{ row }">
        {{ parseDate(asTemplate(row).end_month) }}
      </template>

      <template #cell-active="{ row }">
        <Badge :variant="asTemplate(row).active ? 'secondary' : 'outline'">
          {{ asTemplate(row).active ? 'Activo' : 'Inactivo' }}
        </Badge>
      </template>

      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            class="size-8"
            aria-label="Editar plantilla"
            @click="openEdit(asTemplate(row))"
          >
            <Pencil class="size-3.5" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
            aria-label="Eliminar plantilla"
            @click="confirmDelete(asTemplate(row))"
          >
            <Trash2 class="size-3.5" />
          </Button>
        </div>
      </template>

      <template #empty>
        <div class="flex flex-col items-center gap-1">
          No hay plantillas recurrentes.
          <Button
            variant="link"
            class="mt-1"
            @click="openCreate"
          >
            Crear la primera plantilla
          </Button>
        </div>
      </template>
    </ResponsiveTable>
  </div>

  <!-- Create / Edit Dialog -->
  <RecurringDialog
    v-model:open="showCreateDialog"
    :template="editingTemplate"
    :categories="categories"
    :mode="dialogMode"
    @saved="showCreateDialog = false"
  />

  <!-- Delete Confirmation Dialog -->
  <Dialog
    :open="showDeleteDialog"
    @update:open="showDeleteDialog = false"
  >
    <DialogContent class="sm:max-w-[380px]">
      <DialogHeader>
        <DialogTitle>Eliminar plantilla</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar esta plantilla recurrente?
          <br />
          <strong>{{ deleteTarget?.name }}</strong>
          <br />
          Esta acción no se puede deshacer.
        </DialogDescription>
      </DialogHeader>
      <DialogFooter>
        <Button
          variant="outline"
          @click="showDeleteDialog = false"
        >
          Cancelar
        </Button>
        <Button
          variant="destructive"
          @click="executeDelete"
        >
          Eliminar
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
