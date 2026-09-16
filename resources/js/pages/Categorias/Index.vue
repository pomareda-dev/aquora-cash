<script setup lang="ts">
import type { CategoryData } from '@/components/categories/CategoryDialog.vue';
import CategoryDialog from '@/components/categories/CategoryDialog.vue';
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
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { usePageTour } from '@/composables/usePageTour';
import { useTour } from '@/composables/useTour';
import categorias from '@/routes/categorias';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  categories: CategoryData[];
  selectedMonth: string;
  currentMonth: string;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Categorías',
        href: categorias.index(),
      },
    ],
  },
});

const { format, formatSigned } = useCurrency();
const { isTourActive } = useTour();

// Register this page's tour segment (steps 28-31).
usePageTour('categorias');

function formatSign(value: number): string {
  if (value === 0) {
    return format(value);
  }

  return formatSigned(value);
}

function asCategory(row: Record<string, unknown>): CategoryData {
  return row as unknown as CategoryData;
}

// --- Table columns ---
const tableColumns: ResponsiveColumn[] = [
  { key: '__drag', header: '', dragHandle: true },
  { key: 'kind', header: 'Tipo' },
  {
    key: 'name',
    header: 'Nombre',
    primary: true,
    className: 'font-medium',
  },
  {
    key: 'balance',
    header: 'Balance',
    align: 'right',
    className: 'font-medium tabular-nums',
  },
  {
    key: 'monthly_limit',
    header: 'Límite',
    align: 'right',
    hideOnMobile: true,
    className: 'tabular-nums text-muted-foreground',
  },
  { key: 'progress', header: 'Progreso', hideOnMobile: true },
];

// --- Month navigation ---
const selectedDate = computed(() => {
  const [year, month] = props.selectedMonth.split('-').map(Number);

  return new Date(year, month - 1, 1);
});

const monthLabel = computed(() => {
  return selectedDate.value.toLocaleDateString('es-PE', {
    month: 'long',
    year: 'numeric',
  });
});

const isCurrentMonth = computed(() => props.selectedMonth === props.currentMonth);

function navigateMonth(delta: number) {
  const date = new Date(selectedDate.value);
  date.setMonth(date.getMonth() + delta);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  router.visit(`${categorias.index.url()}?month=${year}-${month}`, {
    preserveScroll: true,
  });
}

function goToToday() {
  router.visit(categorias.index.url(), {
    preserveScroll: true,
  });
}

// --- Kind helpers ---
const kindLabels: Record<string, string> = {
  expense: 'Gasto',
  income: 'Ingreso',
  transfer: 'Transferencia',
};

const kindBadgeVariant: Record<string, string> = {
  expense: 'destructive',
  income: 'secondary',
  transfer: 'outline',
};

function progressPercentage(cat: CategoryData): number {
  if (!cat.monthly_limit || cat.monthly_limit <= 0) {
    return 0;
  }

  return (cat.spent / cat.monthly_limit) * 100;
}

function progressColor(pct: number): string {
  if (pct > 100) {
    return 'bg-red-500';
  }

  if (pct >= 75) {
    return 'bg-amber-500';
  }

  return 'bg-green-500';
}

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingCategory = ref<CategoryData | null>(null);
const deleteTarget = ref<CategoryData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
  editingCategory.value = null;
  showCreateDialog.value = true;
}

function onReorder(ids: number[]) {
  if (ids.length <= 1) {
    return;
  }

  router.patch(
    categorias.reorder.url(),
    {
      ids,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        /* flash success handled by server */
      },
    }
  );
}

// --- Keyboard shortcuts ---
// Suppress month navigation while the tour drives the arrow keys (REQ-8).
useKeyboardShortcuts(
  [
    { key: 'ArrowLeft', handler: () => navigateMonth(-1) },
    { key: 'ArrowRight', handler: () => navigateMonth(1) },
  ],
  {
    isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value || isTourActive(),
  }
);

function openEdit(category: CategoryData) {
  editingCategory.value = category;
  showCreateDialog.value = true;
}

function confirmDelete(category: CategoryData) {
  deleteTarget.value = category;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  router.delete(categorias.destroy.url(deleteTarget.value.id), {
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
</script>

<template>
  <Head :title="`Presupuestos — ${monthLabel}`" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div
      class="mb-2"
      data-tour="categorias.header"
    >
      <h1 class="text-2xl font-bold tracking-tight">Categorías y presupuestos</h1>
      <p class="text-sm text-muted-foreground">Gestiona tus categorías y controla tu presupuesto mensual</p>
    </div>

    <!-- Month Navigation + Create Button -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div
        class="flex items-center gap-2"
        data-tour="categorias.monthNav"
      >
        <Button
          variant="outline"
          size="icon"
          aria-label="Mes anterior"
          title="Mes anterior (←)"
          @click="navigateMonth(-1)"
        >
          <ChevronLeft class="size-4" />
        </Button>

        <span class="min-w-[160px] text-center text-lg font-semibold capitalize">
          {{ monthLabel }}
        </span>

        <Button
          variant="outline"
          size="icon"
          aria-label="Mes siguiente"
          title="Mes siguiente (→)"
          @click="navigateMonth(1)"
        >
          <ChevronRight class="size-4" />
        </Button>

        <Button
          variant="ghost"
          size="sm"
          :disabled="isCurrentMonth"
          @click="goToToday"
        >
          Hoy
        </Button>
      </div>

      <Button
        data-tour="categorias.create"
        @click="openCreate"
      >
        <Plus class="mr-1 size-4" />
        Nueva categoría
      </Button>
    </div>

    <!-- Table -->
    <ResponsiveTable
      data-tour="categorias.table"
      :columns="tableColumns"
      :rows="categories as unknown as Record<string, unknown>[]"
      row-key="id"
      draggable
      @reorder="onReorder"
    >
      <template #cell-kind="{ row }">
        <Badge :variant="kindBadgeVariant[asCategory(row).kind] as any">
          {{ kindLabels[asCategory(row).kind] }}
        </Badge>
      </template>

      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          <span
            v-if="asCategory(row).color"
            class="inline-block size-3 shrink-0 rounded-full"
            :style="{
              backgroundColor: asCategory(row).color ?? undefined,
            }"
          />
          {{ asCategory(row).name }}
        </div>
      </template>

      <template #cell-balance="{ row }">
        <span
          class="font-medium tabular-nums"
          :class="
            asCategory(row).balance > 0
              ? 'text-green-600 dark:text-green-400'
              : asCategory(row).balance < 0
                ? 'text-red-600 dark:text-red-400'
                : ''
          "
        >
          {{ formatSign(asCategory(row).balance) }}
        </span>
      </template>

      <template #cell-monthly_limit="{ row }">
        {{ asCategory(row).monthly_limit !== null ? format(asCategory(row).monthly_limit ?? 0) : '—' }}
      </template>

      <template #cell-progress="{ row }">
        <div
          v-if="(asCategory(row).monthly_limit ?? 0) > 0"
          class="flex items-center gap-3"
        >
          <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
            <div
              class="h-full rounded-full transition-all duration-300"
              :class="progressColor(progressPercentage(asCategory(row)))"
              :style="{
                width: Math.min(progressPercentage(asCategory(row)), 100) + '%',
              }"
            />
          </div>
          <span
            class="shrink-0 text-xs font-medium tabular-nums"
            :class="{
              'text-red-600 dark:text-red-400': progressPercentage(asCategory(row)) > 100,
              'text-amber-600 dark:text-amber-400':
                progressPercentage(asCategory(row)) >= 75 && progressPercentage(asCategory(row)) <= 100,
              'text-green-600 dark:text-green-400': progressPercentage(asCategory(row)) < 75,
            }"
          >
            {{ Math.round(progressPercentage(asCategory(row))) }}%
          </span>
        </div>
        <span
          v-else
          class="text-xs text-muted-foreground"
        >
          Sin límite
        </span>
      </template>

      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            class="size-8"
            aria-label="Editar categoría"
            @click="openEdit(asCategory(row))"
          >
            <Pencil class="size-3.5" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
            aria-label="Eliminar categoría"
            @click="confirmDelete(asCategory(row))"
          >
            <Trash2 class="size-3.5" />
          </Button>
        </div>
      </template>

      <template #empty>
        <div class="flex flex-col items-center gap-1">
          No hay categorías.
          <Button
            variant="link"
            class="mt-1"
            @click="openCreate"
          >
            Crear la primera categoría
          </Button>
        </div>
      </template>
    </ResponsiveTable>
  </div>

  <!-- Create / Edit Dialog -->
  <CategoryDialog
    v-model:open="showCreateDialog"
    :category="editingCategory"
    @saved="showCreateDialog = false"
  />

  <!-- Delete Confirmation Dialog -->
  <Dialog
    :open="showDeleteDialog"
    @update:open="showDeleteDialog = false"
  >
    <DialogContent class="sm:max-w-[380px]">
      <DialogHeader>
        <DialogTitle>Eliminar categoría</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar esta categoría?
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
