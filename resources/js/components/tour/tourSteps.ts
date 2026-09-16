import type { TourSegment } from '@/composables/useTour';
import type { DriveStep } from 'driver.js';

/**
 * Rich tour step definition. Kept separate from driver.js's DriveStep so
 * that all copy and per-step behavior lives in one reviewable file; pages
 * stay thin and only register their segment.
 */
export interface TourStep {
  /** data-tour anchor selector, e.g. 'shell.sidebar'. Omit for a target-less modal step. */
  anchor?: string;
  /** Title copy. Spanish UI copy per proposal §4. */
  title: string;
  /** Description copy. Spanish UI copy per proposal §4. */
  description: string;
  /** Popover side relative to the anchored element. */
  side?: 'top' | 'right' | 'bottom' | 'left';
  /** When provided and false, the step is skipped at run time. */
  precondition?: () => boolean;
  /** When true, the step is skipped on mobile viewports. */
  mobileSkip?: boolean;
}

/**
 * REQ-4 precondition factory for conditionally rendered anchors. The check
 * runs when the segment registers its steps (page mount), before the driver
 * is driven: an absent target is filtered out instead of rendering an empty
 * or mispositioned popover. Deliberately per-step rather than the driver's
 * global `skipMissingElement`, which would also skip the mobile shell step 1
 * (REQ-11 requires that step to stay available).
 */
function anchorPresent(anchor: string): () => boolean {
  return () => typeof document !== 'undefined' && document.querySelector(`[data-tour="${anchor}"]`) !== null;
}

/**
 * All step definitions keyed by segment. Shell steps run on the Dashboard.
 * Copy is real Spanish UI copy from proposal §4 (verbatim). Segments for
 * Categorías and Recurrentes fill in across PR6–PR7.
 */
export const tourStepsBySegment: Record<TourSegment, TourStep[]> = {
  shell: [
    {
      anchor: 'shell.sidebar',
      side: 'right',
      title: 'Tu menú principal',
      description:
        'Desde aquí llegas a todas las secciones: Tablero, Movimientos, Categorías, Cuentas, Recurrentes, Deudas, Metas y Proyección.',
    },
    {
      anchor: 'nav.movimientos',
      side: 'right',
      title: 'Movimientos',
      description: 'Aquí registras tus ingresos y gastos, y revisas tu saldo real y proyectado del mes.',
    },
    {
      anchor: 'nav.cuentas',
      side: 'right',
      title: 'Cuentas',
      description: 'Administra tus cuentas y concilia sus saldos contra el balance real de movimientos.',
    },
    {
      anchor: 'nav.categorias',
      side: 'right',
      title: 'Categorías y presupuestos',
      description: 'Organiza tus movimientos por categoría y define un presupuesto mensual para cada una.',
    },
    {
      anchor: 'nav.recurrentes',
      side: 'right',
      title: 'Recurrentes',
      description:
        'Crea plantillas de ingresos y gastos que se repiten cada mes; el sistema proyecta sus cuotas automáticamente.',
    },
    {
      anchor: 'shell.userMenu',
      side: 'right',
      title: 'Tu cuenta',
      description: 'Desde aquí accedes a tu configuración y cierras sesión.',
    },
    {
      anchor: 'shell.simulationToggle',
      side: 'bottom',
      title: 'Modo Simulación',
      description:
        'Actívalo para probar escenarios sin afectar tu balance real. Cuando está activo verás una franja ámbar arriba con las opciones para revertir el escenario o guardarlo como real.',
    },
    {
      anchor: 'shell.help',
      side: 'bottom',
      title: 'Guía de uso',
      description: '¿Te perdiste? Pulsa aquí para volver a ver esta guía cuando quieras.',
    },
  ],
  dashboard: [
    {
      anchor: 'dashboard.header',
      side: 'bottom',
      title: 'Tablero',
      description: 'Este es tu resumen financiero del mes: todo lo importante de un vistazo.',
    },
    {
      anchor: 'dashboard.monthNav',
      side: 'bottom',
      title: 'Navegación por mes',
      description: 'Muévete entre meses con las flechas — o con las teclas ← y → — y vuelve al mes actual con «Hoy».',
    },
    {
      anchor: 'dashboard.metrics',
      side: 'bottom',
      title: 'Indicadores del mes',
      description: 'Balance actual, ingresos, gastos y la proyección a fin de mes, calculados con tus movimientos.',
    },
    {
      anchor: 'dashboard.budget',
      side: 'top',
      title: 'Resumen de presupuesto',
      description:
        'Cuánto llevas gastado de cada categoría con presupuesto. La barra se pone ámbar al 75 % y roja si superas el límite.',
    },
    {
      anchor: 'dashboard.reconciliation',
      side: 'top',
      title: 'Mini conciliación',
      description:
        'Compara el saldo de tus cuentas con el balance real de movimientos. Si todo cuadra, verás «Conciliado».',
    },
    {
      anchor: 'dashboard.debts',
      side: 'top',
      title: 'Deudas activas',
      description: 'El progreso de cada deuda, cuánto te falta por pagar y cuándo vence la próxima cuota.',
    },
    {
      anchor: 'dashboard.goals',
      side: 'top',
      title: 'Metas',
      description: 'Cuánto tienes apartado para tus metas y cuánto te queda disponible de verdad.',
    },
    {
      anchor: 'dashboard.upcoming',
      side: 'top',
      title: 'Próximos movimientos',
      description: 'Lo que se viene en los próximos 7 días, incluyendo lo que proyectan tus recurrentes.',
    },
    {
      anchor: 'dashboard.chart',
      side: 'top',
      title: 'Balance del mes',
      description: 'La evolución de tu balance día a día durante el mes seleccionado.',
    },
  ],
  movimientos: [
    {
      anchor: 'movimientos.header',
      side: 'bottom',
      title: 'Movimientos',
      description: 'Registra y consulta tus ingresos y egresos.',
    },
    {
      anchor: 'movimientos.monthNav',
      side: 'bottom',
      title: 'Mes',
      description: 'Cambia de mes con las flechas — o con ← y →. «Hoy» te devuelve al mes actual.',
    },
    {
      anchor: 'movimientos.create',
      side: 'bottom',
      title: 'Nuevo movimiento',
      description:
        'Crea un ingreso o un gasto. Atajo: pulsa N. En modo Simulación el botón se vuelve ámbar y el movimiento no afecta tu balance real.',
    },
    {
      anchor: 'movimientos.actuales',
      side: 'top',
      title: 'Movimientos actuales',
      description:
        'Lo que ya ocurrió este mes, con su saldo acumulado. Puedes reordenarlos arrastrando el ícono de la izquierda.',
      precondition: anchorPresent('movimientos.actuales'),
    },
    {
      anchor: 'movimientos.proyectados',
      side: 'top',
      title: 'Movimientos proyectados',
      description: 'Lo que todavía no ocurre: cuotas de recurrentes y movimientos futuros, con el saldo estimado.',
      precondition: anchorPresent('movimientos.proyectados'),
    },
    {
      anchor: 'movimientos.summary',
      side: 'top',
      title: 'Resumen del mes',
      description: 'Ingresos, gastos, neto y balance final del mes, calculados solo con movimientos reales.',
      precondition: anchorPresent('movimientos.summary'),
    },
  ],
  cuentas: [
    {
      anchor: 'cuentas.header',
      side: 'bottom',
      title: 'Cuentas',
      description: 'Administra tus cuentas y saldos, y concilia contra el balance real.',
    },
    {
      anchor: 'cuentas.create',
      side: 'bottom',
      title: 'Nueva cuenta',
      description: 'Registra una cuenta nueva indicando su tipo y su saldo inicial.',
    },
    {
      anchor: 'cuentas.table',
      side: 'top',
      title: 'Tus cuentas',
      description:
        'Tipo, saldo y si la cuenta entra o no en la conciliación. El total aparece al pie. Puedes reordenarlas arrastrando.',
    },
    {
      anchor: 'cuentas.reconciliation',
      side: 'top',
      title: 'Conciliación',
      description:
        'Compara el total de tus cuentas con el balance real. Si la diferencia es cero la insignia dice «Conciliado»; si no, «Descuadre».',
    },
  ],
  categorias: [],
  recurrentes: [],
};

/**
 * Convert our rich TourStep definitions into driver.js DriveSteps.
 * Steps whose precondition returns false are dropped (REQ-4 skip), so the
 * driver never highlights an absent conditional target and progress counts
 * only the available steps. Steps without an anchor render as a centered
 * modal popover (driver.js falls back to an offscreen dummy element).
 */
export function toDriveSteps(steps: TourStep[]): DriveStep[] {
  return steps
    .filter(step => step.precondition?.() ?? true)
    .map(step => {
      const driveStep: DriveStep = {
        popover: {
          title: step.title,
          description: step.description,
          ...(step.side ? { side: step.side } : {}),
        },
      };

      if (step.anchor) {
        driveStep.element = `[data-tour="${step.anchor}"]`;
      }

      return driveStep;
    });
}
