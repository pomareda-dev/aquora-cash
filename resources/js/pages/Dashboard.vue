<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import BalanceLineChart from '@/components/ui/chart/BalanceLineChart.vue';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useCurrency } from '@/composables/useCurrency';
import { useKeyboardShortcuts } from '@/composables/useKeyboardShortcuts';
import { usePageTour } from '@/composables/usePageTour';
import { useSandbox } from '@/composables/useSandbox';
import { useTour } from '@/composables/useTour';
import { dashboard } from '@/routes';
import deudas from '@/routes/deudas';
import metas from '@/routes/metas';
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, onMounted, watch } from 'vue';

interface BudgetCategory {
  id: number;
  name: string;
  color: string | null;
  monthly_limit: number;
  spent: number;
}

interface ActiveDebtSummary {
  id: number;
  name: string;
  remaining: number;
  paid_installments: number;
  installments_count: number;
  rate_factor: number;
  next_date: string | null;
  next_amount: number;
}

interface ActiveGoalSummary {
  id: number;
  name: string;
  target_amount: number;
  progress_amount: number;
  percent: number;
  remaining_amount: number;
  target_date: string | null;
  days_to_target: number | null;
  simulated_amount?: number;
}

interface GoalsSummary {
  apartado: number;
  available_real: number;
  active_count: number;
}

interface UpcomingMovement {
  id: number;
  date: string;
  description: string;
  category_name: string | null;
  amount: number;
  is_projected: boolean;
  is_sandbox?: boolean;
}

interface ChartPoint {
  date: string;
  balance: number;
}

const props = defineProps<{
  cards: {
    realBalance: number;
    monthIncome: number;
    monthExpense: number;
    projectedEndOfMonth: number;
  };
  budgetOverview: BudgetCategory[];
  reconciliation: {
    totalAccounts: number;
    realBalance: number;
    difference: number;
    reconciled: boolean;
  };
  debtsOverview: ActiveDebtSummary[];
  goalsOverview: ActiveGoalSummary[];
  goalsSummary: GoalsSummary;
  upcomingProjections: UpcomingMovement[];
  chartData: ChartPoint[];
  selectedMonth: string;
  currentMonth: string;
  includeSandbox?: boolean;
  simulatedCards?: {
    realBalance: number;
    monthIncome: number;
    monthExpense: number;
    projectedEndOfMonth: number;
  } | null;
  chartDataSimulated?: ChartPoint[] | null;
  simulatedBudgetOverview?: BudgetCategory[] | null;
  simulatedDebtsOverview?: (ActiveDebtSummary & { is_sandbox?: boolean })[] | null;
  simulatedGoalsOverview?: (ActiveGoalSummary & { simulated_amount?: number })[] | null;
  simulatedGoalsSummary?: (GoalsSummary & { apartado_with_sandbox?: number }) | null;
  simulatedUpcoming?: (UpcomingMovement & { is_sandbox?: boolean })[] | null;
  differenceWithSandbox?: number | null;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Tablero',
        href: dashboard(),
      },
    ],
  },
});

const { format, formatSigned } = useCurrency();
const { hasSandbox, modeActive } = useSandbox();
const { isTourActive } = useTour();

// Register this page's tour segment (shell + dashboard steps run here).
usePageTour('dashboard');

// --- Sandbox simulation toggle ---
function toggleSandboxSimulation(checked: boolean) {
  const params = new URLSearchParams(window.location.search);

  if (checked) {
    params.set('include_sandbox', '1');
  } else {
    params.delete('include_sandbox');
  }

  router.visit(`${dashboard.url()}?${params.toString()}`, {
    preserveScroll: true,
  });
}

// Auto-ON sandbox simulation when mode is active and sandbox data exists
onMounted(() => {
  if (modeActive.value && hasSandbox.value && !props.includeSandbox) {
    toggleSandboxSimulation(true);
  }
});

// React to simulation mode transitions
watch(modeActive, active => {
  if (active && hasSandbox.value && !props.includeSandbox) {
    toggleSandboxSimulation(true);
  } else if (!active && props.includeSandbox) {
    toggleSandboxSimulation(false);
  }
});

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
  router.visit(`${dashboard.url()}?month=${year}-${month}`, {
    preserveScroll: true,
  });
}

function goToToday() {
  router.visit(dashboard.url(), {
    preserveScroll: true,
  });
}

// --- Keyboard shortcuts ---
// Suppress month navigation while the tour drives the arrow keys (REQ-8).
useKeyboardShortcuts(
  [
    { key: 'ArrowLeft', handler: () => navigateMonth(-1) },
    { key: 'ArrowRight', handler: () => navigateMonth(1) },
  ],
  {
    isDialogOpen: () => isTourActive(),
  }
);

// --- Progress bar helpers ---
function progressPercentage(cat: BudgetCategory): number {
  if (!cat.monthly_limit || cat.monthly_limit <= 0) {
    return 0;
  }

  return (cat.spent / cat.monthly_limit) * 100;
}

function debtProgress(debt: ActiveDebtSummary): number {
  if (debt.installments_count === 0) {
    return 0;
  }

  return Math.min(100, Math.round((debt.paid_installments / debt.installments_count) * 100));
}

function goalProgressColor(percent: number): string {
  if (percent >= 75) {
    return 'bg-amber-500';
  }

  return 'bg-green-500';
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

// --- Simulated display values ---
const displayBalance = computed(() =>
  props.includeSandbox && props.simulatedCards ? props.simulatedCards.realBalance : props.cards.realBalance
);

const displayIncome = computed(() =>
  props.includeSandbox && props.simulatedCards ? props.simulatedCards.monthIncome : props.cards.monthIncome
);

const displayExpense = computed(() =>
  props.includeSandbox && props.simulatedCards ? props.simulatedCards.monthExpense : props.cards.monthExpense
);

const displayProjected = computed(() =>
  props.includeSandbox && props.simulatedCards
    ? props.simulatedCards.projectedEndOfMonth
    : props.cards.projectedEndOfMonth
);

const displayBudgetOverview = computed(() =>
  props.includeSandbox && props.simulatedBudgetOverview ? props.simulatedBudgetOverview : props.budgetOverview
);

const displayGoalsOverview = computed(() =>
  props.includeSandbox && props.simulatedGoalsOverview ? props.simulatedGoalsOverview : props.goalsOverview
);

const displayGoalsSummary = computed(() =>
  props.includeSandbox && props.simulatedGoalsSummary ? props.simulatedGoalsSummary : props.goalsSummary
);

const displayUpcoming = computed(() => {
  if (props.includeSandbox && props.simulatedUpcoming) {
    return props.simulatedUpcoming;
  }
  return props.upcomingProjections;
});

// --- Date formatting ---
function formatDate(dateStr: string): string {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  });
}
</script>

<template>
  <Head :title="`Tablero — ${monthLabel}`" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 sm:gap-6">
    <!-- Header -->
    <div
      class="mb-2"
      data-tour="dashboard.header"
    >
      <h1 class="text-2xl font-bold tracking-tight">Tablero</h1>
      <p class="text-sm text-muted-foreground">Resumen financiero del mes</p>
    </div>

    <!-- Month Navigation -->
    <div
      class="flex items-center gap-2"
      data-tour="dashboard.monthNav"
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

      <div
        v-if="hasSandbox"
        class="ml-auto flex items-center gap-2"
      >
        <Switch
          id="include-sandbox"
          :checked="includeSandbox ?? false"
          @update:checked="toggleSandboxSimulation"
        />
        <Label
          for="include-sandbox"
          class="cursor-pointer text-sm"
        >
          Incluir simulación
        </Label>
      </div>
    </div>

    <!-- Metric Cards (4) -->
    <div
      class="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4"
      data-tour="dashboard.metrics"
    >
      <Card class="py-3 sm:py-5">
        <CardContent class="px-4 sm:px-6">
          <div class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1">
            <CardTitle class="text-sm font-medium text-muted-foreground"> Balance actual </CardTitle>
            <p
              class="text-xl font-bold tabular-nums sm:text-2xl"
              :class="displayBalance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ format(displayBalance) }}
            </p>
            <p
              v-if="includeSandbox && simulatedCards && simulatedCards.realBalance !== cards.realBalance"
              class="text-xs text-muted-foreground tabular-nums"
            >
              Real: {{ format(cards.realBalance) }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card class="py-3 sm:py-5">
        <CardContent class="px-4 sm:px-6">
          <div class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1">
            <CardTitle class="text-sm font-medium text-muted-foreground"> Ingresos del mes </CardTitle>
            <p class="text-xl font-bold text-green-600 tabular-nums sm:text-2xl dark:text-green-400">
              {{ formatSigned(displayIncome) }}
            </p>
            <p
              v-if="includeSandbox && simulatedCards && simulatedCards.monthIncome !== cards.monthIncome"
              class="text-xs text-muted-foreground tabular-nums"
            >
              Real: {{ formatSigned(cards.monthIncome) }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card class="py-3 sm:py-5">
        <CardContent class="px-4 sm:px-6">
          <div class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1">
            <CardTitle class="text-sm font-medium text-muted-foreground"> Gastos del mes </CardTitle>
            <p class="text-xl font-bold text-red-600 tabular-nums sm:text-2xl dark:text-red-400">
              -{{ format(displayExpense) }}
            </p>
            <p
              v-if="includeSandbox && simulatedCards && simulatedCards.monthExpense !== cards.monthExpense"
              class="text-xs text-muted-foreground tabular-nums"
            >
              Real: {{ format(cards.monthExpense) }}
            </p>
          </div>
        </CardContent>
      </Card>

      <Card class="py-3 sm:py-5">
        <CardContent class="px-4 sm:px-6">
          <div class="flex items-baseline justify-between gap-3 sm:flex-col sm:items-start sm:gap-1">
            <CardTitle class="text-sm font-medium text-muted-foreground"> Proyección a fin de mes </CardTitle>
            <p
              class="text-xl font-bold tabular-nums sm:text-2xl"
              :class="displayProjected >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
            >
              {{ format(displayProjected) }}
            </p>
            <p
              v-if="
                includeSandbox && simulatedCards && simulatedCards.projectedEndOfMonth !== cards.projectedEndOfMonth
              "
              class="text-xs text-muted-foreground tabular-nums"
            >
              Real: {{ format(cards.projectedEndOfMonth) }}
            </p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Row: Budget Overview + Mini Reconciliation -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Budget Overview -->
      <Card data-tour="dashboard.budget">
        <CardHeader>
          <CardTitle class="text-base">Resumen de presupuesto</CardTitle>
        </CardHeader>
        <CardContent>
          <div
            v-if="displayBudgetOverview.length > 0"
            class="space-y-4"
          >
            <div
              v-for="cat in displayBudgetOverview"
              :key="cat.id"
              class="space-y-1.5"
            >
              <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                  <span
                    v-if="cat.color"
                    class="inline-block size-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: cat.color }"
                  />
                  <span class="font-medium">{{ cat.name }}</span>
                </div>
                <span class="text-muted-foreground tabular-nums">
                  {{ format(cat.spent) }}
                  /
                  {{ format(cat.monthly_limit) }}
                </span>
              </div>
              <div class="flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                  <div
                    role="progressbar"
                    :aria-valuenow="Math.round(progressPercentage(cat))"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`Presupuesto ${cat.name}: ${Math.round(progressPercentage(cat))}% usado`"
                    class="h-full rounded-full transition-all duration-300"
                    :class="progressColor(progressPercentage(cat))"
                    :style="{
                      width: Math.min(progressPercentage(cat), 100) + '%',
                    }"
                  />
                </div>
                <span
                  class="shrink-0 text-xs font-medium tabular-nums"
                  :class="{
                    'text-red-600 dark:text-red-400': progressPercentage(cat) > 100,
                    'text-amber-600 dark:text-amber-400':
                      progressPercentage(cat) >= 75 && progressPercentage(cat) <= 100,
                    'text-green-600 dark:text-green-400': progressPercentage(cat) < 75,
                  }"
                >
                  {{ Math.round(progressPercentage(cat)) }}%
                </span>
              </div>
            </div>
          </div>
          <p
            v-else
            class="py-4 text-center text-sm text-muted-foreground"
          >
            No hay categorías con presupuesto definido.
          </p>
        </CardContent>
      </Card>

      <!-- Mini Reconciliation -->
      <Card data-tour="dashboard.reconciliation">
        <CardHeader>
          <CardTitle class="text-base">Mini conciliación</CardTitle>
        </CardHeader>
        <CardContent>
          <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
              <span class="text-sm text-muted-foreground">Total cuentas</span>
              <span class="text-sm font-medium tabular-nums">{{ format(reconciliation.totalAccounts) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-muted-foreground">Balance real</span>
              <span class="text-sm font-medium tabular-nums">{{ format(reconciliation.realBalance) }}</span>
            </div>
            <hr class="border-t border-border" />
            <div class="flex items-center justify-between">
              <span class="text-sm font-medium">Diferencia</span>
              <span
                class="text-sm font-bold tabular-nums"
                :class="
                  reconciliation.reconciled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
                "
              >
                {{ reconciliation.reconciled ? '✅ Conciliado' : `⚠️ ${format(Math.abs(reconciliation.difference))}` }}
              </span>
            </div>
            <template v-if="includeSandbox && differenceWithSandbox !== null && differenceWithSandbox !== undefined">
              <hr class="border-t border-border" />
              <div class="flex items-center justify-between">
                <span class="text-sm text-muted-foreground">Diferencia con simulación</span>
                <span
                  class="text-sm font-bold tabular-nums"
                  :class="
                    Math.abs(differenceWithSandbox) <= 0.01
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-amber-600 dark:text-amber-400'
                  "
                >
                  {{
                    Math.abs(differenceWithSandbox) <= 0.01
                      ? '✅ Conciliado'
                      : `⚠️ ${format(Math.abs(differenceWithSandbox))}`
                  }}
                </span>
              </div>
            </template>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Active Debts -->
    <Card data-tour="dashboard.debts">
      <CardHeader class="flex-row items-center justify-between space-y-0">
        <CardTitle class="text-base">Deudas activas</CardTitle>
        <Button
          variant="ghost"
          size="sm"
          as-child
        >
          <Link :href="deudas.index()">Ver todas</Link>
        </Button>
      </CardHeader>
      <CardContent>
        <div
          v-if="
            debtsOverview.length > 0 || (includeSandbox && simulatedDebtsOverview && simulatedDebtsOverview.length > 0)
          "
          class="space-y-4"
        >
          <div
            v-for="debt in debtsOverview"
            :key="debt.id"
            class="space-y-1.5"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <Link
                :href="deudas.show.url(debt.id)"
                class="truncate font-medium transition-colors hover:underline"
              >
                {{ debt.name }}
              </Link>
              <span class="shrink-0 text-muted-foreground tabular-nums">
                {{ debt.paid_installments }}/{{ debt.installments_count }} · {{ debtProgress(debt) }}%
              </span>
            </div>
            <div class="flex items-center gap-3">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                  role="progressbar"
                  :aria-valuenow="debtProgress(debt)"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  :aria-label="`Progreso de ${debt.name}: ${debtProgress(debt)}%`"
                  class="h-full rounded-full bg-primary transition-all duration-300"
                  :style="{
                    width: debtProgress(debt) + '%',
                  }"
                />
              </div>
            </div>
            <div class="flex items-center justify-between text-xs text-muted-foreground">
              <span>
                Restante
                <span class="font-medium tabular-nums">
                  {{ format(debt.remaining) }}
                </span>
              </span>
              <span v-if="debt.next_date">
                Próxima cuota
                <span class="font-medium tabular-nums">
                  {{ formatDate(debt.next_date) }}
                  · {{ format(debt.next_amount) }}
                </span>
              </span>
              <span v-else>Sin cuotas pendientes</span>
            </div>
          </div>

          <template v-if="includeSandbox && simulatedDebtsOverview && simulatedDebtsOverview.length > 0">
            <hr class="border-t border-dashed border-border" />
            <div
              v-for="debt in simulatedDebtsOverview"
              :key="`sim-${debt.id}`"
              class="space-y-1.5"
            >
              <div class="flex items-center justify-between gap-3 text-sm">
                <span class="flex items-center gap-2 truncate font-medium">
                  {{ debt.name }}
                  <Badge
                    variant="outline"
                    class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                  >
                    Simulado
                  </Badge>
                </span>
                <span class="shrink-0 text-muted-foreground tabular-nums">
                  {{ debt.paid_installments }}/{{ debt.installments_count }} · {{ debtProgress(debt) }}%
                </span>
              </div>
              <div class="flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                  <div
                    role="progressbar"
                    :aria-valuenow="debtProgress(debt)"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-label="`Progreso simulado de ${debt.name}: ${debtProgress(debt)}%`"
                    class="h-full rounded-full bg-amber-500 transition-all duration-300"
                    :style="{
                      width: debtProgress(debt) + '%',
                    }"
                  />
                </div>
              </div>
              <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span>
                  Restante
                  <span class="font-medium tabular-nums">
                    {{ format(debt.remaining) }}
                  </span>
                </span>
                <span v-if="debt.next_date">
                  Próxima cuota
                  <span class="font-medium tabular-nums">
                    {{ formatDate(debt.next_date) }}
                    · {{ format(debt.next_amount) }}
                  </span>
                </span>
                <span v-else>Sin cuotas pendientes</span>
              </div>
            </div>
          </template>
        </div>
        <p
          v-else
          class="py-4 text-center text-sm text-muted-foreground"
        >
          No hay deudas activas.
        </p>
      </CardContent>
    </Card>

    <!-- Active Goals -->
    <Card data-tour="dashboard.goals">
      <CardHeader class="flex-row items-center justify-between space-y-0">
        <CardTitle class="text-base">Metas</CardTitle>
        <Button
          variant="ghost"
          size="sm"
          as-child
        >
          <Link :href="metas.index()">Ver todas</Link>
        </Button>
      </CardHeader>
      <CardContent>
        <div class="mb-4 grid gap-3 sm:grid-cols-2">
          <div class="flex items-center justify-between rounded-lg bg-muted px-3 py-2 text-sm">
            <span class="text-muted-foreground">Apartado en metas</span>
            <span class="font-semibold tabular-nums">
              {{ format(displayGoalsSummary.apartado) }}
            </span>
          </div>
          <div class="flex items-center justify-between rounded-lg bg-muted px-3 py-2 text-sm">
            <span class="text-muted-foreground">Disponible real</span>
            <span
              class="tabular-nums"
              :class="
                displayGoalsSummary.available_real < 0
                  ? 'font-semibold text-red-600 dark:text-red-400'
                  : 'font-semibold'
              "
            >
              {{ format(displayGoalsSummary.available_real) }}
            </span>
          </div>
          <div
            v-if="includeSandbox && simulatedGoalsSummary"
            class="flex items-center justify-between rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-sm dark:border-amber-800 dark:bg-amber-950"
          >
            <span class="text-amber-700 dark:text-amber-300">Apartado con simulación</span>
            <span class="font-semibold text-amber-700 tabular-nums dark:text-amber-300">
              {{ format(simulatedGoalsSummary.apartado_with_sandbox ?? 0) }}
            </span>
          </div>
        </div>
        <div
          v-if="displayGoalsOverview.length > 0"
          class="space-y-4"
        >
          <div
            v-for="goal in displayGoalsOverview"
            :key="goal.id"
            class="space-y-1.5"
          >
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="flex items-center gap-2 truncate font-medium">
                <Link
                  :href="metas.index()"
                  class="truncate transition-colors hover:underline"
                >
                  {{ goal.name }}
                </Link>
                <Badge
                  v-if="includeSandbox && goal.simulated_amount && goal.simulated_amount > 0"
                  variant="outline"
                  class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                >
                  Simulado
                </Badge>
              </span>
              <span class="shrink-0 text-muted-foreground tabular-nums">
                {{ format(goal.progress_amount) }} · {{ goal.percent }}%
              </span>
            </div>
            <div
              v-if="includeSandbox && goal.simulated_amount && goal.simulated_amount > 0"
              class="text-xs text-amber-600 dark:text-amber-400"
            >
              +{{ format(goal.simulated_amount) }} simulado
            </div>
            <div class="flex items-center gap-3">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                  role="progressbar"
                  :aria-valuenow="goal.percent"
                  aria-valuemin="0"
                  aria-valuemax="100"
                  :aria-label="`Progreso de ${goal.name}: ${goal.percent}%`"
                  class="h-full rounded-full transition-all duration-300"
                  :class="goalProgressColor(goal.percent)"
                  :style="{
                    width: Math.min(goal.percent, 100) + '%',
                  }"
                />
              </div>
            </div>
            <div class="flex items-center justify-between text-xs text-muted-foreground">
              <span>
                Falta
                <span class="font-medium tabular-nums">
                  {{ format(goal.remaining_amount) }}
                </span>
              </span>
              <span v-if="goal.target_date && goal.days_to_target !== null && goal.days_to_target < 0"> Vencida </span>
              <span v-else-if="goal.target_date && goal.days_to_target !== null">
                {{ goal.days_to_target === 0 ? 'Hoy es el día' : `${goal.days_to_target} días` }}
              </span>
              <span v-else>Sin fecha</span>
            </div>
          </div>
        </div>
        <p
          v-else
          class="py-4 text-center text-sm text-muted-foreground"
        >
          No hay metas activas.
        </p>
      </CardContent>
    </Card>

    <!-- Row: Upcoming Projections + Chart -->
    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Upcoming Projected Movements -->
      <Card data-tour="dashboard.upcoming">
        <CardHeader>
          <CardTitle class="text-base">Próximos movimientos (7 días)</CardTitle>
        </CardHeader>
        <CardContent>
          <div
            v-if="displayUpcoming.length > 0"
            class="space-y-3"
          >
            <div
              v-for="mov in displayUpcoming"
              :key="mov.id"
              class="flex items-center justify-between gap-4 rounded-md border p-3"
              :class="{
                'border-dashed border-amber-300 dark:border-amber-800': mov.is_sandbox,
              }"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span class="truncate text-sm font-medium">{{ mov.description }}</span>
                  <Badge
                    v-if="mov.is_projected"
                    variant="outline"
                    class="border-blue-300 bg-blue-50 px-1.5 py-0 text-[10px] text-blue-600 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-400"
                  >
                    Proyectado
                  </Badge>
                  <Badge
                    v-if="mov.is_sandbox"
                    variant="outline"
                    class="border-amber-300 bg-amber-50 px-1.5 py-0 text-[10px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                  >
                    Simulado
                  </Badge>
                </div>
                <p class="mt-0.5 text-xs text-muted-foreground">
                  {{ formatDate(mov.date) }}
                  <span v-if="mov.category_name"> · {{ mov.category_name }}</span>
                </p>
              </div>
              <span
                class="shrink-0 text-sm font-bold tabular-nums"
                :class="mov.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
              >
                {{ formatSigned(mov.amount) }}
              </span>
            </div>
          </div>
          <p
            v-else
            class="py-4 text-center text-sm text-muted-foreground"
          >
            No hay movimientos proyectados para los próximos 7 días.
          </p>
        </CardContent>
      </Card>

      <!-- Chart -->
      <Card data-tour="dashboard.chart">
        <CardHeader>
          <CardTitle class="text-base">Balance del mes</CardTitle>
        </CardHeader>
        <CardContent>
          <BalanceLineChart
            :data="chartData"
            :simulated-data="includeSandbox ? chartDataSimulated : undefined"
          />
        </CardContent>
      </Card>
    </div>
  </div>
</template>
