<script setup lang="ts">
import type { ContributionData, GoalData } from '@/components/goals/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCurrency } from '@/composables/useCurrency';
import { useSandbox } from '@/composables/useSandbox';
import metas from '@/routes/metas';
import simulacionMetasAportes from '@/routes/simulacion/metas/aportes';
import { router } from '@inertiajs/vue3';
import { ChevronDown, HandCoins, Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  goal: GoalData;
}>();

const emit = defineEmits<{
  (e: 'edit'): void;
  (e: 'contribute'): void;
  (e: 'remove'): void;
}>();

const { format } = useCurrency();
const { modeActive } = useSandbox();

const deleteContributionTarget = ref<ContributionData | null>(null);
const showDeleteContributionDialog = ref(false);

const realPercent = computed(() => {
  if (props.goal.target_amount <= 0) {
    return 0;
  }

  return Math.round((props.goal.progress_amount / props.goal.target_amount) * 100);
});

const progressColor = computed(() => {
  if (realPercent.value > 100) {
    return 'bg-red-500';
  }

  if (realPercent.value >= 75) {
    return 'bg-amber-500';
  }

  return 'bg-green-500';
});

const daysLabel = computed(() => {
  if (!props.goal.target_date || props.goal.days_to_target === null || props.goal.days_to_target < 0) {
    return null;
  }

  if (props.goal.days_to_target === 0) {
    return 'Hoy es el día';
  }

  return props.goal.days_to_target === 1 ? 'Queda 1 día' : `Quedan ${props.goal.days_to_target} días`;
});

const isOverdue = computed(
  () =>
    !props.goal.is_complete &&
    props.goal.target_date !== null &&
    props.goal.days_to_target !== null &&
    props.goal.days_to_target < 0
);

function formatDate(dateStr: string): string {
  return new Date(dateStr + 'T00:00:00').toLocaleDateString('es-PE', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  });
}

function confirmDeleteContribution(contribution: ContributionData): void {
  deleteContributionTarget.value = contribution;
  showDeleteContributionDialog.value = true;
}

function executeDeleteContribution(): void {
  if (!deleteContributionTarget.value) {
    return;
  }

  const target = deleteContributionTarget.value;
  const url = target.is_sandbox
    ? simulacionMetasAportes.destroy.url({ goal: props.goal.id, contribution: target.id })
    : metas.aportes.destroy.url({ goal: props.goal.id, contribution: target.id });

  router.delete(url, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteContributionDialog.value = false;
      deleteContributionTarget.value = null;
    },
    onError: () => {
      showDeleteContributionDialog.value = false;
      deleteContributionTarget.value = null;
    },
  });
}
</script>

<template>
  <Card class="h-full gap-4 py-4">
    <CardHeader>
      <div class="flex items-start justify-between gap-2">
        <CardTitle class="text-base leading-snug">
          {{ goal.name }}
        </CardTitle>
        <div class="flex shrink-0 items-center gap-1.5">
          <Badge
            v-if="isOverdue"
            variant="destructive"
          >
            Vencida
          </Badge>
          <Badge
            v-if="goal.is_complete"
            variant="secondary"
          >
            Completada
          </Badge>
        </div>
      </div>
    </CardHeader>

    <CardContent class="flex flex-1 flex-col justify-center gap-4">
      <div class="space-y-1.5">
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">Ahorrado</span>
          <span class="font-medium tabular-nums">
            {{ format(goal.progress_amount) }} / {{ format(goal.target_amount) }} · {{ realPercent }}%
          </span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
          <div
            role="progressbar"
            :aria-valuenow="Math.min(realPercent, 100)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Progreso de ${goal.name}: ${realPercent}%`"
            class="h-full rounded-full transition-all duration-300"
            :class="progressColor"
            :style="{ width: Math.min(realPercent, 100) + '%' }"
          />
        </div>
        <div
          v-if="goal.simulated_amount && goal.simulated_amount > 0"
          class="flex items-center justify-between text-xs text-amber-600 dark:text-amber-400"
        >
          <span>Con simulación</span>
          <span class="font-medium tabular-nums">
            {{ format(goal.progress_amount + goal.simulated_amount) }} / {{ format(goal.target_amount) }}
          </span>
        </div>
      </div>

      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
        <div>
          <dt class="text-muted-foreground">Falta ahorrar</dt>
          <dd class="font-semibold tabular-nums">
            {{ format(goal.remaining_amount) }}
          </dd>
        </div>
        <div>
          <dt class="text-muted-foreground">Objetivo</dt>
          <dd
            v-if="goal.target_date"
            class="font-medium"
          >
            <span class="tabular-nums">{{ formatDate(goal.target_date) }}</span>
            <span
              v-if="daysLabel"
              class="text-muted-foreground"
            >
              · {{ daysLabel }}
            </span>
          </dd>
          <dd
            v-else
            class="text-muted-foreground"
          >
            Sin fecha
          </dd>
        </div>
      </dl>

      <Collapsible class="w-full">
        <CollapsibleTrigger as-child>
          <Button
            variant="ghost"
            size="sm"
            class="w-full justify-between"
          >
            <span>Aportes ({{ goal.contributions.length }})</span>
            <ChevronDown class="size-4 transition-transform data-[state=open]:rotate-180" />
          </Button>
        </CollapsibleTrigger>
        <CollapsibleContent class="mt-1">
          <div
            v-if="goal.contributions.length > 0"
            class="divide-y rounded-md border"
          >
            <div
              v-for="contribution in goal.contributions"
              :key="contribution.id"
              class="flex items-start justify-between gap-2 px-3 py-2"
            >
              <div class="min-w-0 flex-1">
                <div class="flex items-baseline justify-between gap-2 text-sm">
                  <span class="font-medium tabular-nums">
                    {{ format(contribution.amount) }}
                  </span>
                  <div class="flex shrink-0 items-center gap-1">
                    <span class="text-xs text-muted-foreground tabular-nums">
                      {{ formatDate(contribution.date) }}
                    </span>
                    <Badge
                      v-if="contribution.is_sandbox"
                      variant="outline"
                      class="border-amber-300 bg-amber-50 px-1 py-0 text-[9px] text-amber-600 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-400"
                    >
                      Simulado
                    </Badge>
                  </div>
                </div>
                <p
                  v-if="contribution.notes"
                  class="mt-0.5 truncate text-xs text-muted-foreground"
                >
                  {{ contribution.notes }}
                </p>
              </div>
              <Button
                variant="ghost"
                size="icon"
                class="size-7 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                aria-label="Eliminar aporte"
                @click="confirmDeleteContribution(contribution)"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </div>
          </div>
          <p
            v-else
            class="rounded-md border border-dashed px-3 py-2 text-center text-xs text-muted-foreground"
          >
            Aún no hay aportes.
          </p>
        </CollapsibleContent>
      </Collapsible>
    </CardContent>

    <CardFooter class="justify-end gap-1">
      <TooltipProvider :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <Button
              variant="ghost"
              size="icon"
              class="size-8"
              :class="
                modeActive
                  ? 'text-amber-600 hover:bg-amber-50 hover:text-amber-700 dark:text-amber-400 dark:hover:bg-amber-950 dark:hover:text-amber-300'
                  : ''
              "
              :aria-label="modeActive ? 'Aporte simulado' : 'Registrar aporte'"
              @click="emit('contribute')"
            >
              <HandCoins class="size-3.5" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            {{ modeActive ? 'Aporte simulado' : 'Registrar aporte' }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
      <Button
        variant="ghost"
        size="icon"
        class="size-8"
        aria-label="Editar meta"
        @click="emit('edit')"
      >
        <Pencil class="size-3.5" />
      </Button>
      <TooltipProvider :delay-duration="0">
        <Tooltip>
          <TooltipTrigger as-child>
            <span
              class="inline-flex"
              :class="goal.can_delete ? '' : 'cursor-not-allowed'"
            >
              <Button
                variant="ghost"
                size="icon"
                class="size-8 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950"
                :disabled="!goal.can_delete"
                aria-label="Eliminar meta"
                @click="emit('remove')"
              >
                <Trash2 class="size-3.5" />
              </Button>
            </span>
          </TooltipTrigger>
          <TooltipContent v-if="!goal.can_delete">
            <p>Tiene aportes registrados. Borra los aportes primero.</p>
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </CardFooter>

    <Dialog
      :open="showDeleteContributionDialog"
      @update:open="showDeleteContributionDialog = false"
    >
      <DialogContent class="sm:max-w-[380px]">
        <DialogHeader>
          <DialogTitle>Eliminar aporte</DialogTitle>
          <DialogDescription>
            ¿Estás seguro de eliminar este aporte?
            <br />
            <strong>{{ deleteContributionTarget ? format(deleteContributionTarget.amount) : '' }}</strong>
            <br />
            <span v-if="deleteContributionTarget?.is_sandbox">
              Al ser un aporte simulado, la meta no se verá afectada.
            </span>
            <span v-else> Si el aporte era necesario para completar la meta, la meta se reabrirá. </span>
          </DialogDescription>
        </DialogHeader>
        <DialogFooter>
          <Button
            variant="outline"
            @click="showDeleteContributionDialog = false"
          >
            Cancelar
          </Button>
          <Button
            variant="destructive"
            @click="executeDeleteContribution"
          >
            Eliminar
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </Card>
</template>
