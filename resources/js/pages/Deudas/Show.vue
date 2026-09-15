<script setup lang="ts">
import DebtMovementsList from '@/components/debts/DebtMovementsList.vue';
import DebtStrategyComparison from '@/components/debts/DebtStrategyComparison.vue';
import DebtTotals from '@/components/debts/DebtTotals.vue';
import type { DebtDetailData, DebtMovementData, DebtStrategyData } from '@/components/debts/types';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useCurrency } from '@/composables/useCurrency';
import deudas from '@/routes/deudas';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
  debt: DebtDetailData;
  payment_history: DebtMovementData[];
  schedule: DebtMovementData[];
  strategy: DebtStrategyData;
  includeSandbox?: boolean;
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

const { format } = useCurrency();

const factorLabel = computed(() => {
  if (props.debt.rate_factor <= 0) {
    return '—';
  }

  return `×${new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(props.debt.rate_factor)}`;
});

function formatDate(dateStr: string): string {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

const scheduleEmptyMessage = computed(() => {
  if (!props.debt.is_active) {
    return 'La deuda está cerrada. No hay cuotas proyectadas.';
  }

  return 'No hay cuotas proyectadas.';
});

function toggleIncludeSandbox(checked: boolean): void {
  router.get(
    deudas.show.url(props.debt.id, { query: { include_sandbox: checked ? 1 : 0 } }),
    {},
    { preserveState: true, preserveScroll: true },
  );
}
</script>

<template>
  <Head :title="debt.name" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div class="mb-2">
      <div class="flex items-center gap-2">
        <h1 class="text-2xl font-bold tracking-tight">
          {{ debt.name }}
        </h1>
        <Badge
          variant="outline"
          class="tabular-nums"
          title="Factor de tasa derivado"
        >
          {{ factorLabel }}
        </Badge>
        <Badge
          v-if="!debt.is_active"
          variant="secondary"
          >Cerrada</Badge
        >
      </div>
      <p class="text-sm text-muted-foreground">
        Desembolso de
        <span class="font-medium tabular-nums">
          {{ format(debt.principal_amount) }}
        </span>
        el {{ formatDate(debt.disbursement_date) }} · {{ debt.installments_count }} cuotas de
        <span class="font-medium tabular-nums">
          {{ format(debt.installment_amount) }}
        </span>
      </p>
      <div class="mt-3 flex items-center gap-2">
        <Switch
          :checked="includeSandbox ?? false"
          @update:checked="toggleIncludeSandbox"
        />
        <Label class="text-sm text-muted-foreground">Incluir deudas simuladas en la estrategia</Label>
      </div>
    </div>

    <!-- Totals + progress -->
    <DebtTotals :debt="debt" />

    <!-- Payment history (real movements, desc) -->
    <DebtMovementsList
      title="Historial de pagos"
      :movements="payment_history"
      empty-message="No hay pagos registrados todavía."
    />

    <!-- Schedule (projected movements, asc) -->
    <DebtMovementsList
      title="Cronograma"
      :movements="schedule"
      :empty-message="scheduleEmptyMessage"
    />

    <!-- Payment strategy comparison across active debts -->
    <DebtStrategyComparison
      v-if="strategy.avalanche.length > 0"
      :strategy="strategy"
      :current-debt-id="debt.id"
    />
  </div>
</template>
