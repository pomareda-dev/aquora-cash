import { update as updateSettingsRoute } from '@/routes/settings';
import { usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { toast } from 'vue-sonner';

/**
 * Shape of the `settings` JSON column on the User model.
 * All keys are optional — defaults are applied in the composable.
 */
export interface UserSettings {
  density: 'compact' | 'comfortable';
  start_section: 'dashboard' | 'movements' | 'categories' | 'accounts' | 'recurring';
  projection_horizon: number;
  debt_category_id: number | null;
  onboarding: {
    completed_at: string | null;
    version: number;
  };
}

const defaults: UserSettings = {
  density: 'comfortable',
  start_section: 'dashboard',
  projection_horizon: 12,
  debt_category_id: null,
  onboarding: { completed_at: null, version: 0 },
};

/**
 * Read the XSRF-TOKEN cookie for fetch requests to Laravel.
 */
function getCsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

  if (!match) {
    return null;
  }

  return decodeURIComponent(match[1]);
}

/**
 * Merge the server raw settings object into our typed defaults.
 *
 * Nested objects (e.g. `onboarding`) are merged key-by-key so a partial
 * server value does not wipe the default sub-keys.
 */
function hydrateSettings(raw: Record<string, unknown> | null | undefined): UserSettings {
  const base = { ...defaults, ...raw } as UserSettings;

  if (raw?.onboarding) {
    base.onboarding = {
      ...defaults.onboarding,
      ...(raw.onboarding as Partial<UserSettings['onboarding']>),
    };
  }

  return base;
}

/**
 * Density class map.
 *
 * Batch 2b can consume these to apply density-appropriate padding/sizing
 * to tables, cards, and other layout elements.
 *
 * Usage in a table cell:
 *   <td :class="densityClass.cell">...</td>
 *
 * @example
 *   import { useSettings } from '@/composables/useSettings'
 *   const { densityClass } = useSettings()
 */
export type DensityClassMap = {
  /** Padding and font size for table/data cells */
  cell: string;
  /** Padding and font size for table header cells */
  header: string;
  /** Spacing between card elements */
  cardGap: string;
  /** Overall padding for list items */
  listItem: string;
};

const densityClassMap: Record<UserSettings['density'], DensityClassMap> = {
  compact: {
    cell: 'p-2 text-sm',
    header: 'px-2 py-1.5 text-xs',
    cardGap: 'gap-3',
    listItem: 'py-1.5 px-2',
  },
  comfortable: {
    cell: 'p-4',
    header: 'px-4 py-3 text-sm',
    cardGap: 'gap-6',
    listItem: 'py-3 px-4',
  },
};

export function useSettings() {
  const rawSettings = computed(
    () => (usePage().props.auth.user as Record<string, unknown>)?.settings as Record<string, unknown> | null | undefined
  );

  const settings = reactive<UserSettings>(hydrateSettings(rawSettings.value));

  // Keep the reactive object in sync when shared props change
  watch(rawSettings, newRaw => {
    const hydrated = hydrateSettings(newRaw);
    (Object.keys(hydrated) as (keyof UserSettings)[]).forEach(key => {
      (settings as Record<string, unknown>)[key] = hydrated[key];
    });
  });

  /**
   * Persist partial settings to the server and update local state on success.
   */
  async function updateSettings(partial: Partial<UserSettings>): Promise<void> {
    const csrfToken = getCsrfToken();

    if (!csrfToken) {
      return;
    }

    try {
      const response = await fetch(updateSettingsRoute.url(), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-XSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(partial),
      });

      if (!response.ok) {
        throw new Error(`Server responded with ${response.status}`);
      }

      // Update local reactive state immediately
      (Object.keys(partial) as (keyof UserSettings)[]).forEach(key => {
        if (key in settings) {
          (settings as Record<string, unknown>)[key] = partial[key] as unknown;
        }
      });

      // Sync back to Inertia shared props so other components see the change
      const page = usePage();
      const user = page.props.auth.user as Record<string, unknown>;
      const currentSettings = (user.settings as Record<string, unknown>) ?? {};
      user.settings = { ...currentSettings, ...partial };
    } catch {
      toast.error('No se pudieron guardar las preferencias');
    }
  }

  /**
   * Computed density class map that updates when density changes.
   */
  const densityClass = computed<DensityClassMap>(
    () => densityClassMap[settings.density] ?? densityClassMap.comfortable
  );

  return {
    settings,
    updateSettings,
    densityClass,
  };
}
