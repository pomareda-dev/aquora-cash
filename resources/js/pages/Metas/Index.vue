<script setup lang="ts">
import ContributionDialog from '@/components/goals/ContributionDialog.vue';
import GoalCard from '@/components/goals/GoalCard.vue';
import GoalDialog from '@/components/goals/GoalDialog.vue';
import type { GoalData, GoalsSummary } from '@/components/goals/types';
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
import { useCurrency } from '@/composables/useCurrency';
import { useSandbox } from '@/composables/useSandbox';
import metas from '@/routes/metas';
import { Head, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  goals: GoalData[];
  summary: GoalsSummary;
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Metas',
        href: metas.index(),
      },
    ],
  },
});

const { format } = useCurrency();
const { modeActive: sandboxModeActive } = useSandbox();

const activeGoals = computed(() => props.goals.filter(g => !g.is_complete));
const completedGoals = computed(() => props.goals.filter(g => g.is_complete));

const availableRealClass = computed(() =>
  props.summary.available_real < 0 ? 'text-red-600 dark:text-red-400' : 'font-semibold text-foreground tabular-nums'
);

const contributionMode = computed(() => (sandboxModeActive.value ? 'sandbox' : 'real'));

// --- Dialog state ---
const showGoalDialog = ref(false);
const editingGoal = ref<GoalData | null>(null);
const contributionTarget = ref<GoalData | null>(null);
const showContributionDialog = ref(false);
const deleteTarget = ref<GoalData | null>(null);
const showDeleteDialog = ref(false);

function openCreate() {
  editingGoal.value = null;
  showGoalDialog.value = true;
}

function openEdit(goal: GoalData) {
  editingGoal.value = goal;
  showGoalDialog.value = true;
}

function openContribute(goal: GoalData) {
  contributionTarget.value = goal;
  showContributionDialog.value = true;
}

function confirmDelete(goal: GoalData) {
  deleteTarget.value = goal;
  showDeleteDialog.value = true;
}

function executeDelete() {
  if (!deleteTarget.value) {
    return;
  }

  router.delete(metas.destroy.url(deleteTarget.value.id), {
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
  <Head title="Metas" />

  <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="mb-2">
        <h1 class="text-2xl font-bold tracking-tight">Metas</h1>
        <p class="text-sm text-muted-foreground">Apartá dinero para tus objetivos y seguí tu progreso</p>
      </div>
      <Button @click="openCreate">
        <Plus class="mr-1 size-4" />
        Nueva meta
      </Button>
    </div>

    <!-- Summary strip -->
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="flex items-center justify-between rounded-xl border px-4 py-3">
        <span class="text-sm text-muted-foreground">Apartado en metas</span>
        <span class="font-semibold tabular-nums">
          {{ format(summary.apartado) }}
        </span>
      </div>
      <div class="flex items-center justify-between rounded-xl border px-4 py-3">
        <span class="text-sm text-muted-foreground">Disponible real</span>
        <span :class="availableRealClass">
          {{ format(summary.available_real) }}
        </span>
      </div>
    </div>

    <!-- Simulated summary -->
    <div
      v-if="summary.apartado_simulado && summary.apartado_simulado > 0"
      class="flex items-center justify-between rounded-xl border border-dashed border-amber-300 px-4 py-3 dark:border-amber-800"
    >
      <span class="text-sm text-amber-600 dark:text-amber-400">Simulado en metas</span>
      <span class="font-semibold tabular-nums text-amber-600 dark:text-amber-400">
        {{ format(summary.apartado_simulado) }}
      </span>
    </div>

    <!-- Empty state -->
    <Card v-if="goals.length === 0">
      <CardHeader>
        <CardTitle class="text-base">No hay metas registradas</CardTitle>
      </CardHeader>
      <CardContent class="flex flex-col items-center gap-3">
        <p class="text-sm text-muted-foreground">
          Creá tu primera meta para empezar a apartar dinero hacia un objetivo.
        </p>
        <Button
          variant="link"
          @click="openCreate"
        >
          Crear la primera meta
        </Button>
      </CardContent>
    </Card>

    <!-- Active goals -->
    <div
      v-else-if="activeGoals.length > 0"
      class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
    >
      <GoalCard
        v-for="goal in activeGoals"
        :key="goal.id"
        :goal="goal"
        @edit="openEdit(goal)"
        @contribute="openContribute(goal)"
        @remove="confirmDelete(goal)"
      />
    </div>

    <!-- Completed goals -->
    <div
      v-if="completedGoals.length > 0"
      class="space-y-3"
    >
      <h2 class="text-lg font-semibold tracking-tight">Completadas</h2>
      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <GoalCard
          v-for="goal in completedGoals"
          :key="goal.id"
          :goal="goal"
          @remove="confirmDelete(goal)"
        />
      </div>
    </div>
  </div>

  <!-- Create / Edit Dialog -->
  <GoalDialog
    v-model:open="showGoalDialog"
    :goal="editingGoal"
    @saved="showGoalDialog = false"
  />

  <!-- Contribution Dialog -->
  <ContributionDialog
    v-model:open="showContributionDialog"
    :goal="contributionTarget"
    :mode="contributionMode"
    @saved="showContributionDialog = false"
  />

  <!-- Delete Confirmation Dialog -->
  <Dialog
    :open="showDeleteDialog"
    @update:open="showDeleteDialog = false"
  >
    <DialogContent class="sm:max-w-[380px]">
      <DialogHeader>
        <DialogTitle>Eliminar meta</DialogTitle>
        <DialogDescription>
          ¿Estás seguro de eliminar esta meta?
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
