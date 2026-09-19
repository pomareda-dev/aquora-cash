<script setup lang="ts">
import type { CategoryData, MovementData } from '@/components/movements/MovementDialog.vue';
import MovementDialog from '@/components/movements/MovementDialog.vue';
import type { ResponsiveColumn } from '@/components/ResponsiveTable.vue';
import ResponsiveTable from '@/components/ResponsiveTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { useSandbox } from '@/composables/useSandbox';
import { useSettings } from '@/composables/useSettings';
import { useTour } from '@/composables/useTour';
import movimientos from '@/routes/movimientos';
import simulacionMovimientos from '@/routes/simulacion/movimientos';
import { Head, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  realMovements: MovementData[];
  projectedMovements: MovementData[];
  sandboxMovements?: MovementData[];
  categories: CategoryData[];
  selectedMonth: string;
  openingBalance: number;
  projectedOpeningBalance: number;
  currentMonth: string;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Movimientos',
        href: movimientos.index(),
      },
    ],
  },
});

const { format, formatSigned } = useCurrency();
const { densityClass } = useSettings();
const { modeActive } = useSandbox();
const { isTourActive } = useTour();

// Register this page's tour segment (steps 18-23).
usePageTour('movimientos');

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

// Past month (before current): only "Actuales" shown — projected are future.
const isPastMonth = computed(() => props.selectedMonth < props.currentMonth);

// Future month (after current): only "Proyectados" shown — real haven't happened.
const isFutureMonth = computed(() => props.selectedMonth > props.currentMonth);

function navigateMonth(delta: number) {
  const date = new Date(selectedDate.value);
  date.setMonth(date.getMonth() + delta);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  router.visit(`${movimientos.index.url()}?month=${year}-${month}`, {
    preserveScroll: true,
  });
}

function goToToday() {
  router.visit(movimientos.index.url(), {
    preserveScroll: true,
  });
}

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingMovement = ref<MovementData | null>(null);
const deleteTarget = ref<MovementData | null>(null);
const showDeleteDialog = ref(false);

const dialogMode = computed<'real' | 'sandbox'>(() => {
  if (editingMovement.value) {
    return editingMovement.value.is_sandbox ? 'sandbox' : 'real';
  }

  return modeActive.value ? 'sandbox' : 'real';
});

function openCreate() {
  editingMovement.value = null;
  showCreateDialog.value = true;
}

// --- Keyboard shortcuts ---
// Suppress month navigation and the N shortcut while the tour drives the keys (REQ-8).
useKeyboardShortcuts(
  [
    { key: 'ArrowLeft', handler: () => navigateMonth(-1) },
    { key: 'ArrowRight', handler: () => navigateMonth(1) },
    { key: 'n', handler: () => openCreate(), ignoreShift: true },
  ],
  {
    isDialogOpen: () => showCreateDialog.value || showDeleteDialog.value || isTourActive(),
  }
);

function openEdit(movement: MovementData) {
  editingMovement.value = movement;
  showCreateDialog.value = true;
}

function confirmDelete(movement: MovementData) {
  deleteTarget.value = movement;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  const url = deleteTarget.value.is_sandbox
    ? simulacionMovimientos.destroy.url(deleteTarget.value.id)
    : movimientos.destroy.url(deleteTarget.value.id);

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

// --- Summary calculations (from realMovements only) ---
const summary = computed(() => {
  const reales = props.realMovements;
  const income = reales.filter(m => m.amount > 0).reduce((sum, m) => sum + m.amount, 0);
  const expense = reales.filter(m => m.amount < 0).reduce((sum, m) => sum + m.amount, 0);
  const closingBalance = reales.length > 0 ? reales[reales.length - 1].running_balance : props.openingBalance;

  return { income, expense, closingBalance };
});

function formatSign(value: number): string {
  if (value === 0) {
    return format(value);
  }

  return formatSigned(value);
}

function asMovement(row: Record<string, unknown>): MovementData {
  return row as unknown as MovementData;
}

// --- Table columns ---
const tableColumns: ResponsiveColumn[] = [
  { key: '__drag', header: '', dragHandle: true },
  {
    key: 'date',
    header: 'Fecha',
    className: 'font-medium whitespace-nowrap',
  },
  { key: 'description', header: 'Movimiento', primary: true },
  { key: 'category', header: 'Tipo', className: 'text-muted-foreground' },
  {
    key: 'amount',
    header: 'Cantidad',
    align: 'right',
    className: 'font-medium tabular-nums',
  },
  {
    key: 'balance',
    header: 'Balance',
    align: 'right',
    hideOnMobile: true,
    className: 'font-medium tabular-nums',
  },
];

// "Actuales" renders inside a scrollable container, so its headers stay sticky.
const actualColumns = tableColumns.map(c =>
  c.key === '__drag' ? c : { ...c, headerClassName: 'sticky top-0 z-10 bg-background' }
);

// "Proyectados" needs its own balance-like slot, so it uses a distinct key
// (and a different header text) instead of sharing `cell-balance`. It is not
// draggable, so the drag-handle column is dropped entirely.
const projectedColumns = tableColumns
  .filter(c => c.key !== '__drag')
  .map(c => (c.key === 'balance' ? { ...c, key: 'projected_balance', header: 'Proyección' } : c));

// Sandbox section: no drag handle, no balance column
const sandboxColumns = tableColumns
  .filter(c => c.key !== '__drag' && c.key !== 'balance')
  .map(c => (c.key === 'amount' ? { ...c, header: 'Cantidad' } : c));

// --- Reorder handling ---
// Display list for "Actuales": newest-first. Props keep the chronological
// (oldest-first) order so running_balance and summary stay untouched; this
// ref is a reversed copy the table component may mutate during a drag.
const realList = ref<MovementData[]>([]);

watch(
  () => props.realMovements,
  movements => {
    realList.value = [...movements].reverse();
  },
  { immediate: true }
);

// --- Sandbox integration ---
// Determine today's date for comparison
const today = computed(() => {
  const now = new Date();
  now.setHours(0, 0, 0, 0);
  return now;
});

// Combine real + sandbox movements for "Actuales" section
const combinedActualList = computed(() => {
  const sandboxActual = (props.sandboxMovements ?? []).filter(m => {
    const moveDate = new Date(m.date + 'T00:00:00');
    moveDate.setHours(0, 0, 0, 0);
    // In past months, all movements are "actual"
    // In current month, only movements up to today are "actual"
    // In future months, no movements are "actual"
    if (isPastMonth.value) return true;
    if (isFutureMonth.value) return false;
    return moveDate <= today.value;
  });

  // Combine and sort by date (newest first for display)
  const combined = [...realList.value, ...sandboxActual].sort((a, b) => {
    const dateA = new Date(a.date + 'T00:00:00');
    const dateB = new Date(b.date + 'T00:00:00');
    return dateB.getTime() - dateA.getTime();
  });

  // Recalculate running balances (display is newest-first, but balance accumulates oldest-first)
  // Start from the oldest and accumulate
  const chronological = [...combined].reverse();
  let balance = props.openingBalance;

  return chronological
    .map(m => {
      balance += m.amount;
      return { ...m, running_balance: balance };
    })
    .reverse(); // Back to newest-first for display
});

// Combine projected + sandbox movements for "Proyectados" section
const combinedProjectedMovements = computed(() => {
  const sandboxProjected = (props.sandboxMovements ?? []).filter(m => {
    const moveDate = new Date(m.date + 'T00:00:00');
    moveDate.setHours(0, 0, 0, 0);
    // In past months, no movements are "projected"
    // In current month, only movements after today are "projected"
    // In future months, all movements are "projected"
    if (isPastMonth.value) return false;
    if (isFutureMonth.value) return true;
    return moveDate > today.value;
  });

  // Combine and sort by date (oldest first for balance calculation)
  const combined = [...props.projectedMovements, ...sandboxProjected].sort((a, b) => {
    const dateA = new Date(a.date + 'T00:00:00');
    const dateB = new Date(b.date + 'T00:00:00');
    return dateA.getTime() - dateB.getTime();
  });

  // Calculate running balances starting from projected opening balance
  let balance = props.projectedOpeningBalance;

  return combined.map(m => {
    balance += m.amount;
    return { ...m, running_balance: balance };
  });
});

// Closing balance shown in each section header (mobile, where the balance
// column is hidden). Both are computed from the totals instead of the display
// order so a drag reorder cannot change them.
const actualClosingBalance = computed(() =>
  combinedActualList.value.reduce((balance, movement) => balance + movement.amount, props.openingBalance)
);

const projectedClosingBalance = computed(() =>
  combinedProjectedMovements.value.reduce(
    (balance, movement) => balance + movement.amount,
    props.projectedOpeningBalance
  )
);

// The table emits ids in display (newest-first) order, but the server expects
// chronological (oldest-first) order, so reverse before sending — the same
// payload the previous implementation sent.
function handleReorder(ids: number[]) {
  if (ids.length <= 1) {
    return;
  }

  router.patch(
    movimientos.reorder.url(),
    {
      ids: [...ids].reverse(),
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        /* flash success handled by server */
      },
    }
  );
}
</script>

<template>
  <Head :title="monthLabel" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div
      class="mb-2"
      data-tour="movimientos.header"
    >
      <h1 class="text-2xl font-bold tracking-tight">Movimientos</h1>
      <p class="text-sm text-muted-foreground">Registra y consulta tus ingresos y egresos</p>
    </div>

    <!-- Month Navigation + Create Button -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div
        class="flex items-center gap-2"
        data-tour="movimientos.monthNav"
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
        data-tour="movimientos.create"
        :title="modeActive ? 'Nuevo movimiento simulado (N)' : 'Nuevo movimiento (N)'"
        :class="
          modeActive
            ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300 dark:hover:bg-amber-900'
            : ''
        "
        @click="openCreate"
      >
        <Plus class="mr-1 size-4" />
        {{ modeActive ? 'Nuevo movimiento simulado' : 'Nuevo movimiento' }}
      </Button>
    </div>

    <!-- Reales Section (hidden in future months — nothing has happened yet) -->
    <Card
      v-if="!isFutureMonth"
      data-tour="movimientos.actuales"
    >
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Actuales</CardTitle>
        <CardAction
          v-if="combinedActualList.length > 0"
          class="self-center text-right md:hidden"
        >
          <span class="text-xs text-muted-foreground">Balance </span>
          <span class="text-sm font-semibold tabular-nums">{{ format(actualClosingBalance) }}</span>
        </CardAction>
      </CardHeader>
      <CardContent class="p-0">
        <ResponsiveTable
          :columns="actualColumns"
          :rows="combinedActualList as unknown as Record<string, unknown>[]"
          row-key="id"
          :draggable="!combinedActualList.some(m => m.is_sandbox)"
          container-class="max-h-[560px] overflow-y-auto"
          @reorder="handleReorder"
        >
          <template #cell-date="{ row }">
            {{
              new Date(asMovement(row).date + 'T00:00:00').toLocaleDateString('es-PE', {
                day: 'numeric',
                month: 'short',
              })
            }}
          </template>

          <template #cell-description="{ row }">
            <div class="flex items-center gap-2">
              <span>{{ asMovement(row).description }}</span>
              <Badge
                v-if="asMovement(row).is_sandbox"
                variant="outline"
                class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
              >
                Simulado
              </Badge>
            </div>
          </template>

          <template #cell-category="{ row }">
            <div class="flex items-center gap-2">
              <span
                v-if="asMovement(row).category_color"
                class="inline-block size-3 shrink-0 rounded-full"
                :style="{
                  backgroundColor: asMovement(row).category_color ?? undefined,
                }"
              />
              {{ asMovement(row).category_name ?? 'Sin categoría' }}
            </div>
          </template>

          <template #cell-amount="{ row }">
            <span
              class="font-medium tabular-nums"
              :class="
                asMovement(row).amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
              "
            >
              {{ formatSigned(asMovement(row).amount) }}
            </span>
          </template>

          <template #cell-balance="{ row }">
            {{ format(asMovement(row).running_balance) }}
          </template>

          <template #actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <Button
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Editar movimiento"
                @click="openEdit(asMovement(row))"
              >
                <Pencil class="size-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                aria-label="Eliminar movimiento"
                @click="confirmDelete(asMovement(row))"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </div>
          </template>

          <template #empty> No hay movimientos reales. </template>

          <template #footer>
            <tbody class="bg-muted/30">
              <tr>
                <td :class="densityClass.cell" />
                <td
                  :class="[densityClass.cell, 'font-medium text-muted-foreground']"
                  colspan="2"
                >
                  Saldo inicial
                </td>
                <td :class="densityClass.cell" />
                <td :class="[densityClass.cell, 'text-right font-medium text-muted-foreground']">
                  {{ format(openingBalance) }}
                </td>
                <td :class="[densityClass.cell, 'text-right font-medium']">
                  {{ format(openingBalance) }}
                </td>
                <td :class="densityClass.cell" />
              </tr>
            </tbody>
          </template>
        </ResponsiveTable>
      </CardContent>
    </Card>

    <!-- Proyectados Section (hidden in past months — projections are future-only) -->
    <Card
      v-if="!isPastMonth"
      data-tour="movimientos.proyectados"
    >
      <CardHeader class="pb-3">
        <CardTitle class="text-base">Proyectados</CardTitle>
        <CardAction
          v-if="combinedProjectedMovements.length > 0"
          class="self-center text-right md:hidden"
        >
          <span class="text-xs text-muted-foreground">Balance final </span>
          <span class="text-sm font-semibold tabular-nums">{{ format(projectedClosingBalance) }}</span>
        </CardAction>
      </CardHeader>
      <CardContent class="p-0">
        <ResponsiveTable
          :columns="projectedColumns"
          :rows="combinedProjectedMovements as unknown as Record<string, unknown>[]"
          row-key="id"
          container-class="overflow-auto"
        >
          <template #cell-date="{ row }">
            {{
              new Date(asMovement(row).date + 'T00:00:00').toLocaleDateString('es-PE', {
                day: 'numeric',
                month: 'short',
              })
            }}
          </template>

          <template #cell-description="{ row }">
            <div class="flex items-center gap-2">
              <span>{{ asMovement(row).description }}</span>
              <Badge
                v-if="asMovement(row).is_sandbox"
                variant="outline"
                class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
              >
                Simulado
              </Badge>
              <Badge
                v-else
                variant="outline"
                class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
              >
                Proyectado
              </Badge>
            </div>
          </template>

          <template #cell-category="{ row }">
            <div class="flex items-center gap-2">
              <span
                v-if="asMovement(row).category_color"
                class="inline-block size-3 shrink-0 rounded-full"
                :style="{
                  backgroundColor: asMovement(row).category_color ?? undefined,
                }"
              />
              {{ asMovement(row).category_name ?? 'Sin categoría' }}
            </div>
          </template>

          <template #cell-amount="{ row }">
            <span
              class="font-medium tabular-nums"
              :class="
                asMovement(row).amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
              "
            >
              {{ formatSigned(asMovement(row).amount) }}
            </span>
          </template>

          <template #cell-projected_balance="{ row }">
            <span
              :class="
                asMovement(row).running_balance >= 0 ? 'text-muted-foreground' : 'text-red-600/60 dark:text-red-400/60'
              "
            >
              {{ format(asMovement(row).running_balance) }}
            </span>
          </template>

          <template #actions="{ row }">
            <div class="flex items-center justify-end gap-1">
              <Button
                variant="ghost"
                size="icon"
                class="size-8"
                aria-label="Editar movimiento"
                @click="openEdit(asMovement(row))"
              >
                <Pencil class="size-3.5" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                aria-label="Eliminar movimiento"
                @click="confirmDelete(asMovement(row))"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </div>
          </template>

          <template #empty> No hay movimientos proyectados. </template>
        </ResponsiveTable>
      </CardContent>
    </Card>

    <!-- Monthly Summary (from realMovements only) -->
    <div
      v-if="realMovements.length > 0"
      data-tour="movimientos.summary"
      class="flex flex-wrap gap-6 rounded-md border p-4"
    >
      <div class="flex flex-col gap-1">
        <span class="text-xs tracking-wide text-muted-foreground uppercase"> Ingresos </span>
        <span class="text-lg font-semibold text-green-600 tabular-nums dark:text-green-400">
          {{ format(summary.income) }}
        </span>
      </div>
      <div class="flex flex-col gap-1">
        <span class="text-xs tracking-wide text-muted-foreground uppercase"> Gastos </span>
        <span class="text-lg font-semibold text-red-600 tabular-nums dark:text-red-400">
          {{ format(Math.abs(summary.expense)) }}
        </span>
      </div>
      <div class="flex flex-col gap-1">
        <span class="text-xs tracking-wide text-muted-foreground uppercase"> Neto del mes </span>
        <span
          class="text-lg font-semibold tabular-nums"
          :class="
            summary.income + summary.expense >= 0
              ? 'text-green-600 dark:text-green-400'
              : 'text-red-600 dark:text-red-400'
          "
        >
          {{ formatSign(summary.income + summary.expense) }}
        </span>
      </div>
      <div class="ml-auto flex flex-col gap-1">
        <span class="text-xs tracking-wide text-muted-foreground uppercase"> Balance final </span>
        <span class="text-lg font-semibold tabular-nums">
          {{ format(summary.closingBalance) }}
        </span>
      </div>
    </div>
  </div>

  <!-- Create / Edit Dialog -->
  <MovementDialog
    v-model:open="showCreateDialog"
    :movement="editingMovement"
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
        <DialogTitle>Eliminar movimiento</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar este movimiento?
          <br />
          <strong>{{ deleteTarget?.description }}</strong>
          &mdash;
          <span v-if="deleteTarget">
            {{ formatSigned(deleteTarget.amount) }}
          </span>
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
