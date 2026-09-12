<script setup lang="ts">
import type { DebtStrategyData, DebtStrategyItem } from '@/components/debts/types';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useCurrency } from '@/composables/useCurrency';
import deudas from '@/routes/deudas';
import { Link } from '@inertiajs/vue3';

const props = defineProps<{
  strategy: DebtStrategyData;
  currentDebtId: number;
}>();

const { format } = useCurrency();

function factorLabel(factor: number): string {
  if (factor <= 0) {
    return '—';
  }

  return `×${new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(factor)}`;
}

function weightedFactorLabel(): string {
  if (props.strategy.weighted_factor <= 0) {
    return '—';
  }

  return `×${new Intl.NumberFormat('es-PE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(props.strategy.weighted_factor)}`;
}

function isCurrent(item: DebtStrategyItem): boolean {
  return item.id === props.currentDebtId;
}
</script>

<template>
  <Card class="gap-4 py-4">
    <CardHeader>
      <div class="flex flex-wrap items-center justify-between gap-2">
        <CardTitle class="text-base">Estrategia de pago</CardTitle>
        <Badge
          variant="outline"
          class="tabular-nums"
          title="Factor ponderado entre tus deudas activas"
        >
          Factor ponderado {{ weightedFactorLabel() }}
        </Badge>
      </div>
    </CardHeader>
    <CardContent class="grid gap-6 lg:grid-cols-2">
      <div class="space-y-2">
        <div>
          <h3 class="text-sm font-semibold">Avalancha</h3>
          <p class="text-xs text-muted-foreground">Mayor factor primero (ahorra en intereses).</p>
        </div>
        <ol
          v-if="strategy.avalanche.length > 0"
          class="space-y-2"
        >
          <li
            v-for="(item, index) in strategy.avalanche"
            :key="item.id"
            class="flex items-center justify-between gap-3 rounded-lg border p-3"
            :class="isCurrent(item) ? 'border-primary' : ''"
          >
            <div class="flex min-w-0 items-center gap-2">
              <span class="text-sm font-semibold text-muted-foreground tabular-nums"> {{ index + 1 }}. </span>
              <component
                :is="item.is_sandbox ? 'span' : Link"
                v-bind="item.is_sandbox ? {} : { href: deudas.show.url(item.id) }"
                class="truncate text-sm font-medium"
                :class="[
                  isCurrent(item) ? 'text-primary' : '',
                  item.is_sandbox ? '' : 'transition-colors hover:underline',
                ]"
              >
                {{ item.name }}
              </component>
              <Badge
                v-if="item.is_sandbox"
                variant="outline"
                class="shrink-0 border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
              >
                Simulado
              </Badge>
              <Badge
                v-if="isCurrent(item)"
                variant="secondary"
                class="shrink-0"
              >
                Actual
              </Badge>
            </div>
            <div class="flex shrink-0 items-center gap-2 text-sm">
              <Badge
                variant="outline"
                class="tabular-nums"
              >
                {{ factorLabel(item.factor) }}
              </Badge>
              <span class="text-muted-foreground tabular-nums">
                {{ format(item.remaining) }}
              </span>
            </div>
          </li>
        </ol>
        <p
          v-else
          class="text-sm text-muted-foreground"
        >
          No hay deudas activas.
        </p>
      </div>

      <div class="space-y-2">
        <div>
          <h3 class="text-sm font-semibold">Bola de nieve</h3>
          <p class="text-xs text-muted-foreground">Menor restante primero (gana motivación).</p>
        </div>
        <ol
          v-if="strategy.snowball.length > 0"
          class="space-y-2"
        >
          <li
            v-for="(item, index) in strategy.snowball"
            :key="item.id"
            class="flex items-center justify-between gap-3 rounded-lg border p-3"
            :class="isCurrent(item) ? 'border-primary' : ''"
          >
            <div class="flex min-w-0 items-center gap-2">
              <span class="text-sm font-semibold text-muted-foreground tabular-nums"> {{ index + 1 }}. </span>
              <component
                :is="item.is_sandbox ? 'span' : Link"
                v-bind="item.is_sandbox ? {} : { href: deudas.show.url(item.id) }"
                class="truncate text-sm font-medium"
                :class="[
                  isCurrent(item) ? 'text-primary' : '',
                  item.is_sandbox ? '' : 'transition-colors hover:underline',
                ]"
              >
                {{ item.name }}
              </component>
              <Badge
                v-if="item.is_sandbox"
                variant="outline"
                class="shrink-0 border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
              >
                Simulado
              </Badge>
              <Badge
                v-if="isCurrent(item)"
                variant="secondary"
                class="shrink-0"
              >
                Actual
              </Badge>
            </div>
            <div class="flex shrink-0 items-center gap-2 text-sm">
              <Badge
                variant="outline"
                class="tabular-nums"
              >
                {{ factorLabel(item.factor) }}
              </Badge>
              <span class="text-muted-foreground tabular-nums">
                {{ format(item.remaining) }}
              </span>
            </div>
          </li>
        </ol>
        <p
          v-else
          class="text-sm text-muted-foreground"
        >
          No hay deudas activas.
        </p>
      </div>
    </CardContent>
  </Card>
</template>
