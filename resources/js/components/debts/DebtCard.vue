<script setup lang="ts">
import type { DebtData } from '@/components/debts/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCurrency } from '@/composables/useCurrency';
import deudas from '@/routes/deudas';
import { Link } from '@inertiajs/vue3';
import { HandCoins, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
  debt: DebtData;
}>();

const emit = defineEmits<{
  (e: 'edit'): void;
  (e: 'payoff'): void;
  (e: 'remove'): void;
}>();

const { format } = useCurrency();

const progressPercentage = computed(() => {
  if (!props.debt.is_active) {
    return 100;
  }

  if (props.debt.installments_count === 0) {
    return 0;
  }

  return Math.min(100, Math.round((props.debt.paid_installments / props.debt.installments_count) * 100));
});

const factorLabel = computed(() => {
  if (props.debt.rate_factor <= 0) {
    return '—';
  }

  return `×${new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(props.debt.rate_factor)}`;
});

const nextInstallment = computed(() => {
  if (!props.debt.is_active) {
    return null;
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const next = props.debt.payment_dates
    .slice()
    .sort()
    .find(date => new Date(date + 'T00:00:00') > today);

  return next ?? null;
});

function formatDate(dateStr: string): string {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function formatClosedAt(closedAt: string): string {
  return new Date(closedAt.replace(' ', 'T')).toLocaleDateString('es-PE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}
</script>

<template>
  <Card class="h-full gap-4 py-4">
    <CardHeader>
      <div class="flex items-start justify-between gap-2">
        <CardTitle class="text-base leading-snug">
          <component
            :is="debt.is_sandbox ? 'span' : Link"
            v-bind="debt.is_sandbox ? {} : { href: deudas.show.url(debt.id) }"
            :class="debt.is_sandbox ? '' : 'transition-colors hover:underline'"
          >
            {{ debt.name }}
          </component>
        </CardTitle>
        <div class="flex shrink-0 items-center gap-1.5">
          <Badge
            v-if="debt.is_sandbox"
            variant="outline"
            class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
          >
            Simulado
          </Badge>
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
          >
            Cerrada
          </Badge>
        </div>
      </div>
    </CardHeader>

    <CardContent class="flex flex-1 flex-col justify-center gap-4">
      <div class="space-y-1.5">
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">Cuotas pagadas</span>
          <span class="font-medium tabular-nums">
            {{ debt.paid_installments }}/{{ debt.installments_count }} · {{ progressPercentage }}%
          </span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            role="progressbar"
            :aria-valuenow="progressPercentage"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Progreso de ${debt.name}: ${progressPercentage}%`"
            class="h-full rounded-full bg-primary transition-all duration-300"
            :style="{ width: progressPercentage + '%' }"
          />
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
        <div>
          <dt class="text-muted-foreground">Cuota mensual</dt>
          <dd class="font-medium tabular-nums">
            {{ format(debt.installment_amount) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Total a pagar</dt>
          <dd class="font-medium tabular-nums">
            {{ format(debt.total_to_pay) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Restante</dt>
          <dd class="font-semibold tabular-nums">
            {{ format(debt.remaining) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Próxima cuota</dt>
          <dd
            v-if="nextInstallment"
            class="font-medium"
          >
            <span class="tabular-nums">
              {{ formatDate(nextInstallment) }}
            </span>
            <span class="text-muted-foreground tabular-nums"> · {{ format(debt.installment_amount) }} </span>
          </dd>
          <dd
            v-else
            class="text-muted-foreground"
          >
            —
          </dd>
        </div>
      </dl>

      <p
        v-if="!debt.is_active && debt.closed_at"
        class="text-xs text-muted-foreground"
      >
        Cerrada el {{ formatClosedAt(debt.closed_at) }}
      </p>
    </CardContent>

    <CardFooter class="justify-end gap-1">
      <template v-if="debt.is_active">
        <Button
          variant="ghost"
          size="icon"
          class="size-8"
          aria-label="Liquidar deuda"
          @click="emit('payoff')"
        >
          <HandCoins class="size-3.5" />
        </Button>
        <Button
          variant="ghost"
          size="icon"
          class="size-8"
          aria-label="Editar deuda"
          @click="emit('edit')"
        >
          <Pencil class="size-3.5" />
        </Button>
      </template>
      <TooltipProvider :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <span
              class="inline-flex"
              :class="debt.can_delete ? '' : 'cursor-not-allowed'"
            >
              <Button
                variant="ghost"
                size="icon"
                class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                :disabled="!debt.can_delete"
                aria-label="Eliminar deuda"
                @click="emit('remove')"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent v-if="!debt.can_delete">
            <p>Tiene pagos registrados. Usa «Liquidar deuda».</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </CardFooter>
  </Card>
</template>
