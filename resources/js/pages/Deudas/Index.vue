<script setup lang="ts">
import DebtCard from '@/components/debts/DebtCard.vue';
import DebtDialog from '@/components/debts/DebtDialog.vue';
import PayoffDialog from '@/components/debts/PayoffDialog.vue';
import type { DebtData } from '@/components/debts/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import deudas from '@/routes/deudas';
import preferences from '@/routes/preferences';
import simulacionDeudas from '@/routes/simulacion/deudas';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Plus, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  debts: DebtData[];
  sandboxDebts?: DebtData[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Deudas',
        href: deudas.index(),
      },
    ],
  },
});

const activeDebts = computed(() => props.debts.filter(d => d.is_active));
const closedDebts = computed(() => props.debts.filter(d => !d.is_active));

const debtCategoryConfigured = computed(() => {
  const settings = (usePage().props.auth.user as Record<string, unknown>)?.settings as
    Record<string, unknown> | null | undefined;

  return Boolean(settings?.debt_category_id);
});

// --- Dialog state ---
const showDebtDialog = ref(false);
const editingDebt = ref<DebtData | null>(null);
const payoffTarget = ref<DebtData | null>(null);
const showPayoffDialog = ref(false);
const deleteTarget = ref<DebtData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
  editingDebt.value = null;
  showDebtDialog.value = true;
}

function openEdit(debt: DebtData) {
  editingDebt.value = debt;
  showDebtDialog.value = true;
}

function openPayoff(debt: DebtData) {
  payoffTarget.value = debt;
  showPayoffDialog.value = true;
}

function confirmDelete(debt: DebtData) {
  deleteTarget.value = debt;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  const url = deleteTarget.value.is_sandbox
    ? simulacionDeudas.destroy.url(deleteTarget.value.id)
    : deudas.destroy.url(deleteTarget.value.id);

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
</script>

<template>
  <Head title="Deudas" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="mb-2">
        <h1 class="text-2xl font-bold tracking-tight">Deudas</h1>
        <p class="text-sm text-muted-foreground">Gestiona tus préstamos: progreso, pagos y liquidación anticipada</p>
      </div>
      <Button @click="openCreate">
        <Plus class="mr-1 size-4" />
        Nueva deuda
      </Button>
    </div>

    <!-- Category alert -->
    <Alert
      v-if="!debtCategoryConfigured"
      variant="default"
    >
      <TriangleAlert class="size-4" />
      <AlertTitle>Sin categoría configurada</AlertTitle>
      <AlertDescription class="flex flex-wrap items-center justify-between gap-3">
        <span>
          Los movimientos de tus préstamos (desembolso, cuotas y liquidación) se guardan sin categoría. Elegí una antes
          de crear deudas para identificarlos en tus reportes.
        </span>
        <Button
          as-child
          variant="outline"
          size="sm"
        >
          <Link :href="preferences.edit()"> Configurar categoría </Link>
        </Button>
      </AlertDescription>
    </Alert>

    <!-- Empty state -->
    <Card v-if="debts.length === 0">
      <CardHeader>
        <CardTitle class="text-base">No hay deudas registradas</CardTitle>
      </CardHeader>
      <CardContent class="flex flex-col items-center gap-3">
        <p class="text-sm text-muted-foreground">Registra tu primer préstamo para hacer seguimiento de tus cuotas.</p>
        <Button
          variant="link"
          @click="openCreate"
        >
          Crear la primera deuda
        </Button>
      </CardContent>
    </Card>

    <!-- Active debts -->
    <div
      v-else-if="activeDebts.length > 0"
      class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
    >
      <DebtCard
        v-for="debt in activeDebts"
        :key="debt.id"
        :debt="debt"
        @edit="openEdit(debt)"
        @payoff="openPayoff(debt)"
        @remove="confirmDelete(debt)"
      />
    </div>

    <!-- History -->
    <div
      v-if="closedDebts.length > 0"
      class="space-y-3"
    >
      <h2 class="text-lg font-semibold tracking-tight">Historial</h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <DebtCard
          v-for="debt in closedDebts"
          :key="debt.id"
          :debt="debt"
          @remove="confirmDelete(debt)"
        />
      </div>
    </div>

    <!-- Sandbox debts section -->
    <div
      v-if="sandboxDebts && sandboxDebts.length > 0"
      class="space-y-3"
    >
      <h2 class="text-lg font-semibold tracking-tight">Deudas simuladas</h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <DebtCard
          v-for="debt in sandboxDebts"
          :key="debt.id"
          :debt="debt"
          @edit="openEdit(debt)"
          @payoff="openPayoff(debt)"
          @remove="confirmDelete(debt)"
        />
      </div>
    </div>
  </div>

  <!-- Create / Edit Dialog -->
  <DebtDialog
    v-model:open="showDebtDialog"
    :debt="editingDebt"
    :mode="editingDebt?.is_sandbox ? 'sandbox' : 'real'"
    @saved="showDebtDialog = false"
  />

  <!-- Payoff Dialog -->
  <PayoffDialog
    v-model:open="showPayoffDialog"
    :debt="payoffTarget"
    :mode="payoffTarget?.is_sandbox ? 'sandbox' : 'real'"
    @saved="showPayoffDialog = false"
  />

  <!-- Delete Confirmation Dialog -->
  <Dialog
    :open="showDeleteDialog"
    @update:open="showDeleteDialog = false"
  >
    <DialogContent class="sm:max-w-[420px]">
      <DialogHeader>
        <DialogTitle>Eliminar deuda</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar esta deuda?
          <br />
          <strong>{{ deleteTarget?.name }}</strong>
          <br />
          Se eliminarán también sus cuotas proyectadas. Esta acción no se puede deshacer.
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
