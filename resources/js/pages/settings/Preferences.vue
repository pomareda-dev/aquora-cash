<script setup lang="ts">
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useSettings } from '@/composables/useSettings';
import { edit } from '@/routes/preferences';
import { Head } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed } from 'vue';

defineOptions({
  layout: {
    breadcrumbs: [
      {
        title: 'Preferencias',
        href: edit(),
      },
    ],
  },
});

const { settings, updateSettings } = useSettings();

const props = defineProps<{
  categories: {
    id: number;
    name: string;
    kind: 'expense' | 'income' | 'transfer';
    color: string | null;
  }[];
}>();

// --- Density ---
const densityOptions = [
  { value: 'compact' as const, label: 'Compacto' },
  { value: 'comfortable' as const, label: 'Cómodo' },
] as const;

function handleDensityChange(value: string) {
  updateSettings({ density: value as 'compact' | 'comfortable' });
}

// --- Start section ---
const startSectionOptions = [
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'movements', label: 'Movimientos' },
  { value: 'categories', label: 'Categorías' },
  { value: 'accounts', label: 'Cuentas' },
  { value: 'recurring', label: 'Recurrentes' },
] as const;

function handleStartSectionChange(value: AcceptableValue) {
  updateSettings({
    start_section: value as 'dashboard' | 'movements' | 'categories' | 'accounts' | 'recurring',
  });
}

// --- Projection horizon ---
function handleProjectionHorizonBlur(event: Event) {
  const target = event.target as HTMLInputElement;
  let value = parseInt(target.value, 10);

  if (isNaN(value) || value < 1) {
    value = 1;
  } else if (value > 24) {
    value = 24;
  }

  target.value = String(value);

  if (value !== settings.projection_horizon) {
    updateSettings({ projection_horizon: value });
  }
}

// --- Debt category ---
const debtCategoryValue = computed(() => (settings.debt_category_id ? String(settings.debt_category_id) : ''));

function handleDebtCategoryChange(value: AcceptableValue) {
  const selected = props.categories.find(category => String(category.id) === value);

  updateSettings({ debt_category_id: selected ? selected.id : null });
}
</script>

<template>
  <Head title="Preferencias" />

  <h1 class="sr-only">Preferencias</h1>

  <div class="space-y-6">
    <Heading
      variant="small"
      title="Preferencias"
      description="Personaliza la apariencia y el comportamiento de la aplicación"
    />

    <!-- Appearance -->
    <Card>
      <CardHeader>
        <CardTitle>Apariencia</CardTitle>
        <CardDescription>Elige el modo claro, oscuro o el que use tu sistema</CardDescription>
      </CardHeader>
      <CardContent>
        <AppearanceTabs />
      </CardContent>
    </Card>

    <!-- Density -->
    <Card>
      <CardHeader>
        <CardTitle>Densidad</CardTitle>
        <CardDescription> Controla el espaciado de los elementos en las tablas y listas </CardDescription>
      </CardHeader>
      <CardContent>
        <div class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800">
          <button
            v-for="opt in densityOptions"
            :key="opt.value"
            type="button"
            class="rounded-md px-3.5 py-1.5 text-sm transition-colors"
            :class="
              settings.density === opt.value
                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60'
            "
            @click="handleDensityChange(opt.value)"
          >
            {{ opt.label }}
          </button>
        </div>
      </CardContent>
    </Card>

    <!-- Start section -->
    <Card>
      <CardHeader>
        <CardTitle>Sección de inicio</CardTitle>
        <CardDescription> ¿Qué pantalla quieres ver al iniciar sesión? </CardDescription>
      </CardHeader>
      <CardContent>
        <Select
          :model-value="settings.start_section"
          @update:model-value="handleStartSectionChange"
        >
          <SelectTrigger class="w-full md:w-64">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="opt in startSectionOptions"
              :key="opt.value"
              :value="opt.value"
            >
              {{ opt.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </CardContent>
    </Card>

    <!-- Projection horizon -->
    <Card>
      <CardHeader>
        <CardTitle>Horizonte de proyección</CardTitle>
        <CardDescription> Número de meses que abarcará la proyección financiera </CardDescription>
      </CardHeader>
      <CardContent>
        <div class="flex flex-col gap-2 md:max-w-48">
          <Label for="projection-horizon">Meses (1 a 24)</Label>
          <Input
            id="projection-horizon"
            type="number"
            min="1"
            max="24"
            :default-value="settings.projection_horizon"
            @blur="handleProjectionHorizonBlur"
          />
        </div>
      </CardContent>
    </Card>

    <!-- Debt category -->
    <Card>
      <CardHeader>
        <CardTitle>Categoría para préstamos</CardTitle>
        <CardDescription>
          Se usará en los movimientos de desembolso, cuotas y liquidaciones de tus préstamos.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Select
          :model-value="debtCategoryValue"
          @update:model-value="handleDebtCategoryChange"
        >
          <SelectTrigger class="w-full md:w-64">
            <SelectValue placeholder="Sin categoría" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">
              <span class="text-muted-foreground"> Sin categoría </span>
            </SelectItem>
            <SelectItem
              v-for="category in categories"
              :key="category.id"
              :value="String(category.id)"
            >
              <span class="flex items-center gap-2">
                <span
                  v-if="category.color"
                  class="size-2.5 rounded-full"
                  :style="{ backgroundColor: category.color }"
                />
                {{ category.name }}
              </span>
            </SelectItem>
          </SelectContent>
        </Select>
      </CardContent>
    </Card>
  </div>
</template>
