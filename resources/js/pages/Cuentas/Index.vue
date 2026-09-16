<script setup lang="ts">
import type { AccountData } from '@/components/accounts/AccountDialog.vue';
import AccountDialog from '@/components/accounts/AccountDialog.vue';
import type { ResponsiveColumn } from '@/components/ResponsiveTable.vue';
import ResponsiveTable from '@/components/ResponsiveTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { TableCell, TableFooter, TableRow } from '@/components/ui/table';
import { useCurrency } from '@/composables/useCurrency';
import { usePageTour } from '@/composables/usePageTour';
import { useSettings } from '@/composables/useSettings';
import cuentas from '@/routes/cuentas';
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  accounts: AccountData[];
  reconciliation: {
    totalAccounts: number;
    realBalance: number;
    difference: number;
    reconciled: boolean;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Cuentas',
        href: cuentas.index(),
      },
    ],
  },
});

const { format } = useCurrency();
const { densityClass } = useSettings();

// Register this page's tour segment (steps 24-27).
usePageTour('cuentas');

// --- Kind helpers ---
const kindLabels: Record<string, string> = {
  bank: 'Banco',
  wallet: 'Billetera',
  cash: 'Efectivo',
  credit: 'Tarjeta de crédito',
  other: 'Otro',
};

const kindBadgeVariant: Record<string, string> = {
  bank: 'default',
  wallet: 'secondary',
  cash: 'outline',
  credit: 'destructive',
  other: 'secondary',
};

function asAccount(row: Record<string, unknown>): AccountData {
  return row as unknown as AccountData;
}

// --- Table columns ---
const tableColumns: ResponsiveColumn[] = [
  { key: '__drag', header: '', dragHandle: true },
  { key: 'kind', header: 'Tipo' },
  {
    key: 'name',
    header: 'Cuenta',
    primary: true,
    className: 'font-medium',
  },
  {
    key: 'balance',
    header: 'Saldo',
    align: 'right',
    className: 'font-medium tabular-nums',
  },
  {
    key: 'status',
    header: 'Estado',
    align: 'center',
    hideOnMobile: true,
  },
];

// --- Dialog state ---
const showCreateDialog = ref(false);
const editingAccount = ref<AccountData | null>(null);
const deleteTarget = ref<AccountData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
  editingAccount.value = null;
  showCreateDialog.value = true;
}

function onReorder(ids: number[]) {
  if (ids.length <= 1) {
    return;
  }

  router.patch(
    cuentas.reorder.url(),
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

function openEdit(account: AccountData) {
  editingAccount.value = account;
  showCreateDialog.value = true;
}

function confirmDelete(account: AccountData) {
  deleteTarget.value = account;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  router.delete(cuentas.destroy.url(deleteTarget.value.id), {
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

// --- Totals ---
const totalBalance = computed(() => {
  return props.accounts.reduce((sum, a) => sum + a.balance, 0);
});
</script>

<template>
  <Head title="Cuentas" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div
      class="mb-2"
      data-tour="cuentas.header"
    >
      <h1 class="text-2xl font-bold tracking-tight">Cuentas</h1>
      <p class="text-sm text-muted-foreground">Administra tus cuentas y saldos, y concilia contra el balance real</p>
    </div>

    <!-- Actions -->
    <div class="flex items-center justify-between gap-4">
      <div />
      <Button
        data-tour="cuentas.create"
        @click="openCreate"
      >
        <Plus class="mr-1 size-4" />
        Nueva cuenta
      </Button>
    </div>

    <!-- Accounts Table -->
    <ResponsiveTable
      data-tour="cuentas.table"
      :columns="tableColumns"
      :rows="accounts as unknown as Record<string, unknown>[]"
      row-key="id"
      draggable
      @reorder="onReorder"
    >
      <template #cell-kind="{ row }">
        <Badge :variant="kindBadgeVariant[asAccount(row).kind] as any">
          {{ kindLabels[asAccount(row).kind] }}
        </Badge>
      </template>

      <template #cell-name="{ row }">
        <div class="flex items-center gap-2">
          {{ asAccount(row).name }}
          <Badge
            v-if="asAccount(row).exclude_from_reconciliation"
            variant="outline"
            class="text-muted-foreground"
          >
            Excluida
          </Badge>
        </div>
      </template>

      <template #cell-balance="{ row }">
        {{ format(asAccount(row).balance) }}
      </template>

      <template #cell-status="{ row }">
        <span
          v-if="asAccount(row).exclude_from_reconciliation"
          class="text-xs text-muted-foreground"
        >
          —
        </span>
        <span
          v-else
          class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
        >
          <CheckCircle2 class="size-3.5" />
          Incluida
        </span>
      </template>

      <template #actions="{ row }">
        <div class="flex items-center justify-end gap-1">
          <Button
            variant="ghost"
            size="icon"
            class="size-8"
            aria-label="Ajustar saldo"
            @click="openEdit(asAccount(row))"
          >
            <Pencil class="size-3.5" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
            aria-label="Eliminar cuenta"
            @click="confirmDelete(asAccount(row))"
          >
            <Trash2 class="size-3.5" />
          </Button>
        </div>
      </template>

      <template #empty>
        <div class="flex flex-col items-center gap-1">
          No hay cuentas registradas.
          <Button
            variant="link"
            class="mt-1"
            @click="openCreate"
          >
            Crear la primera cuenta
          </Button>
        </div>
      </template>

      <!-- Footer with totals -->
      <template #footer>
        <TableFooter v-if="accounts.length > 0">
          <TableRow>
            <TableCell
              :class="[densityClass.cell, 'font-semibold']"
              colspan="3"
            >
              Total
            </TableCell>
            <TableCell :class="[densityClass.cell, 'text-right font-bold tabular-nums']">
              {{ format(totalBalance) }}
            </TableCell>
            <TableCell
              :class="densityClass.cell"
              colspan="2"
            />
          </TableRow>
        </TableFooter>
      </template>
    </ResponsiveTable>

    <!-- Reconciliation Panel -->
    <Card data-tour="cuentas.reconciliation">
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <span>Conciliación</span>
          <Badge :variant="reconciliation.reconciled ? 'secondary' : 'destructive'">
            {{ reconciliation.reconciled ? 'Conciliado' : 'Descuadre' }}
          </Badge>
        </CardTitle>
        <CardDescription>
          Verificación cruzada entre el saldo de tus cuentas y el balance real de movimientos
        </CardDescription>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
          <div class="flex flex-col gap-1">
            <span class="text-sm text-muted-foreground"> Total cuentas </span>
            <span
              class="text-2xl font-bold tabular-nums"
              :class="reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : ''"
            >
              {{ format(reconciliation.totalAccounts) }}
            </span>
            <span class="text-xs text-muted-foreground"> Saldos de cuentas (excluidas no) </span>
          </div>

          <div class="flex flex-col gap-1">
            <span class="text-sm text-muted-foreground"> Balance real </span>
            <span class="text-2xl font-bold tabular-nums">
              {{ format(reconciliation.realBalance) }}
            </span>
            <span class="text-xs text-muted-foreground"> Apertura + movimientos hasta hoy </span>
          </div>

          <div class="flex flex-col gap-1">
            <span class="text-sm text-muted-foreground"> Diferencia </span>
            <div class="flex items-center gap-2">
              <span
                class="text-2xl font-bold tabular-nums"
                :class="
                  reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                "
              >
                {{ format(reconciliation.difference) }}
              </span>
            </div>
            <span
              class="text-sm"
              :class="
                reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
              "
            >
              <span
                v-if="reconciliation.reconciled"
                class="inline-flex items-center gap-1"
              >
                <CheckCircle2 class="size-4" />
                Conciliado
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1"
              >
                <AlertTriangle class="size-4" />
                Descuadre de {{ format(Math.abs(reconciliation.difference)) }}
              </span>
            </span>
          </div>
        </div>
      </CardContent>
    </Card>
  </div>

  <!-- Create / Edit Dialog -->
  <AccountDialog
    v-model:open="showCreateDialog"
    :account="editingAccount"
    @saved="showCreateDialog = false"
  />

  <!-- Delete Confirmation Dialog -->
  <Dialog
    :open="showDeleteDialog"
    @update:open="showDeleteDialog = false"
  >
    <DialogContent class="sm:max-w-[380px]">
      <DialogHeader>
        <DialogTitle>Eliminar cuenta</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar esta cuenta?
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
