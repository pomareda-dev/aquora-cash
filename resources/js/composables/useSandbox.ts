import { router, usePage } from '@inertiajs/vue3';
import { guardar, revertir } from '@/routes/simulacion';
import { computed, ref } from 'vue';

const STORAGE_KEY = 'sandbox-mode';

// Shared reactive state across all component instances
const modeActive = ref(false);

// Initialize from localStorage on module load
if (typeof window !== 'undefined') {
  modeActive.value = localStorage.getItem(STORAGE_KEY) === 'true';
}

export function useSandbox() {
  const page = usePage();

  const hasSandbox = computed(() => {
    return (page.props.hasSandbox as boolean) ?? false;
  });

  function enter() {
    modeActive.value = true;
    if (typeof window !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, 'true');
    }
  }

  function exit() {
    modeActive.value = false;
    if (typeof window !== 'undefined') {
      localStorage.removeItem(STORAGE_KEY);
    }
  }

  function toggle() {
    if (modeActive.value) {
      exit();
    } else {
      enter();
    }
  }

  function revert() {
    router.post(revertir.url(), {}, {
      preserveScroll: true,
      onSuccess: () => {
        exit();
      },
    });
  }

  function save() {
    router.post(guardar.url(), {}, {
      preserveScroll: true,
      onSuccess: () => {
        exit();
      },
    });
  }

  return {
    hasSandbox,
    modeActive,
    enter,
    exit,
    toggle,
    revert,
    save,
  };
}
