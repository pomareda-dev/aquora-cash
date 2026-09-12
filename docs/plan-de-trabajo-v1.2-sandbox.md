# Plan de trabajo v1.2 — Caja Diaria (Simulación de escenarios con sandbox)

> Extensión **posterior al MVP** (fases 0–9 de v1) e **independiente de v1.1 (PWA)**.
> Permite probar escenarios financieros hipotéticos — aceptar un préstamo y
> proyectar las cuotas, simular deudas, aportar a metas, recibir un pago grande
> y planificar compras — sin afectar el balance real. El usuario "entra en modo
> simulación", arma el escenario, lo observa sobre el dashboard/proyecciones, y
> luego lo **revierte** (descarta todo) o lo **guarda** (lo convierte en estado
> real).

**Modelo:** sandbox **único**, de a un escenario a la vez. No se modelan
escenarios paralelos con nombre ni comparación lado-a-lado. Se reutilizan los
flags `is_projected` y `is_sandbox` (nuevo, ortogonal) para aislar filas
hipotéticas del estado real, junto con un global scope `LiveScope`.

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue · Chart.js). Sin dependencias nuevas.

**Prerequisitos (ya cumplidos):** v1 completa (fases 0–9), v1.3 Deudas
(`DebtController`, `DebtStrategy`, `debts.payment_dates`, `debt_id` en
`movements`) y v1.4 Metas (`Goal`, `GoalContribution`, `apartadoAmount`,
`syncCompletion`). Suite actual: 387 tests en verde.

> **Revisión v1.2-r2 (2026-09):** este plan fue verificado contra el código real
> post-v1.3/v1.4 antes de iniciar la implementación. Cambios principales vs la
> versión anterior del documento:
>
> 1. **Deudas simuladas y aportes de metas simulados entran en el alcance**
>    (decisión del usuario). Se agrega `is_sandbox` a `debts` y
>    `goal_contributions`, no solo a `movements`/`recurring_transactions`.
> 2. **Corregido el marcador de cuotas auto-generadas**: el plan usaba
>    `recurring_id IS NOT NULL`; el código real identifica proyecciones con
>    `source='recurring' AND is_projected=true` (el delete de
>    `regenerateForUser` ya protege movimientos realizados — no hay que
>    reintroducir ese bug).
> 3. **Firmas reales de `ProjectionService`**: `regenerateForUser(int $userId,
>    ?int $horizonMonths = null): int` con horizonte por usuario
>    (`settings['projection_horizon']`, default 12) y generación vía
>    `Movement::forceCreate`. Los métodos sandbox espejan esta forma.
> 4. **LiveScope tiene puntos ciegos documentados**: relaciones `hasMany`
>    (`Debt::movements()`, `RecurringTransaction::movements()`), checks de
>    ownership en Goals (`destroy`/`can_delete`), y `Movement::nextSortOrder()`
>    filtran filas sandbox por defecto. Cada punto tiene tratamiento explícito.
> 5. **Convenciones del codebase aplicadas**: rutas con URI/nombres en español
>    (`simulacion.*`), `Inertia::flash('toast')` + `back()`, Wayfinder
>    (`@/routes/...`), factories con states, validación por composición (el
>    backend **fuerza** `is_sandbox`, nunca lo valida desde el cliente), y
>    binding de rutas: los endpoints reales devuelven 404 para filas sandbox
>    gratis (los global scopes aplican al route model binding).
> 6. **Eliminado el endpoint `status()`**: `hasSandbox` viaja como shared prop
>    de Inertia en cada visita; toda mutación hace `back()` y refresca props.
> 7. **Movimientos no "mezcla" filas sandbox en la lista real**: la página
>    Movimientos mantiene secciones separadas (Real / Proyectadas / Simulación).
>    El toggle "Incluir simulación" vive en Dashboard y Proyección, donde el
>    balance corrido/proyectado combinado tiene sentido.
>
> **Stack de referencia:** ver `plan-de-trabajo.md` para `is_projected`,
> `ProjectionService`, `RecurringTransaction`; `plan-de-trabajo-v1.3-deudas.md`
> para `Debt`/`DebtStrategy`; `plan-de-trabajo-v1.4-metas.md` para
> `Goal`/`GoalContribution`.

---

## 1. Objetivo

Que el usuario pueda:

1. **Entrar en modo simulación** desde el header, con un banner claro de
   "Estás en modo Simulación — los cambios no afectan tu balance real".
2. **Agregar movimientos simulados** (ingresos o egresos hipotéticos, p.ej.
   "Recibí un préstamo de 5000 hoy" o "Compra grande de 1500 en 2 semanas").
3. **Agregar pagos recurrentes simulados** (p.ej. "Cuotas de 500 por 12 meses")
   que generen proyecciones sandbox.
4. **Crear deudas simuladas** con desembolso, cuotas y factor de tasa — el
   mismo UX del módulo Deudas (v1.3), sin tocar el estado real.
5. **Agregar aportes simulados a metas reales** (incluidos aportes a fecha
   futura, para responder "¿llego a la meta ahorrando X por mes?").
6. **Ver el efecto** sobre dashboard, movimientos, proyección, deudas y metas,
   distinguiendo visualmente lo simulado de lo real.
7. **Revertir** el escenario (borrar todas las filas sandbox y volver al
   estado real intacto) o **Guardar como real** (convertir el escenario en
   estado real).

**No** es objetivo de v1.2: múltiples escenarios paralelos con nombre,
comparación lado-a-lado, archivar escenarios como "planes" nombrados, simular
mutando entidades reales existentes, ni simular sobre escenarios ya guardados.

---

## 2. Alcance

### Dentro del alcance (v1.2)

- **Columna `is_sandbox` booleana** (default `false`) en `movements`,
  `recurring_transactions`, `debts` y `goal_contributions`.
- **Scope global `LiveScope`** en `Movement`, `RecurringTransaction`, `Debt` y
  `GoalContribution` que excluye `is_sandbox=true` por defecto, desactivable con
  `withoutSandboxScope()`.
- **Modo simulación UI-only** (toggle en el header, persistido en
  `localStorage`) con banner visualmente distintivo. El modo NO crea estado en
  backend; la existencia de sandbox se deriva de las filas (`hasSandbox` como
  shared prop de Inertia).
- **Movimientos simulados CRUD**: alta/edición/borrado con `is_sandbox=true`,
  mismas validaciones que un movimiento real (`MovementRequest` reutilizado).
- **Recurrentes simulados CRUD**: templates `is_sandbox=true` que generan
  proyecciones sandbox (`source='recurring'`, `is_projected=true`,
  `is_sandbox=true`, `recurring_id` a la template).
- **Deudas simuladas CRUD + payoff**: `SandboxDebtController` espejando
  `DebtController` (desembolso + cuotas con `debt_id`, `is_projected` por
  fecha, factor de tasa, cierre anticipado) — todo con `is_sandbox=true`.
- **Aportes simulados a metas reales**: `goal_contributions.is_sandbox=true`,
  con fecha pasada o futura. No se simulan metas nuevas como entidad.
- **`ProjectionService` por scope**: `regenerateForUser` (real, protegido por
  LiveScope) + `regenerateSandboxForUser` (sandbox). La regeneración real no
  toca filas sandbox y viceversa.
- **Movimientos timeline**: sección "Simulación" separada cuando existe
  sandbox (sin "mezclar" filas en la lista real).
- **Proyección**: toggle "Incluir simulación" que integra filas sandbox al
  timeline paginado con badge; el balance corrido (proyección) las incluye.
- **Dashboard "con simulación"**: toggle que recalcula cards (balance,
  ingresos, gastos, fin de mes, presupuesto), chart con línea adicional,
  card Deudas con deudas simuladas y card Metas con aportes simulados.
- **Estrategias de deudas (avalanche/snowball) incluyendo deudas simuladas**
  en `deudas/{debt}` con `include_sandbox=1`.
- **Revertir**: borrar TODAS las filas `is_sandbox=true` del usuario (las 4
  tablas) en una transacción.
- **Guardar como real**: promoción transaccional (detalle en §3.8).
- **Tests Pest** por fase con factories que soporten estado sandbox.

### Fuera del alcance (deferido a v3 o posterior)

- **Escenarios paralelos con nombre** (entidad `Scenario`, comparación
  lado-a-lado) → v3. v1.2 = sandbox único reciclando `is_sandbox=true`.
- **Guardar como "plan" archivado** (guardar sin promover, re-abrible) → v3.
  En v1.2 "guardar" SIEMPRE promueve a real.
- **Simular cambios a una entidad real existente** ("qué tal si Falabella sube
  de 200 a 250", "qué tal si liquido mi deuda real X"): en v1.2 se simula
  creando filas sandbox nuevas (template/deuda sandbox paralela), nunca
  mutando filas reales. v3 podría modelar "overrides" puntuales.
- **Página de detalle (Show) para deudas simuladas**: las deudas sandbox viven
  como cards en la sección "Deudas simuladas" de Deudas/Index (con próxima
  cuota, restante y factor). El detalle completo y el payoff UX rico quedan
  para v3 si la necesidad emerge. Editar y liquidar sí funcionan desde el
  card/diálogo.
- **Metas simuladas como entidad** (crear una meta hipotética): solo se
  simulan aportes a metas reales.
- **Reorder de movimientos sandbox** (`movimientos/reorder`): fuera; la
  sección Simulación ordena por `date, sort_order, id`.
- **Auditoría de quién probó qué** (histórico de escenarios) → fuera.

---

## 3. Decisiones de arquitectura

### 3.1 `is_sandbox` booleano ortogonal a `is_projected`

`movements` gana `is_sandbox` bool default `false`. `is_sandbox` e
`is_projected` son **ortogonales**: una fila puede ser real proyectada o no, o
sandbox proyectada (cuota generada de una template/deuda sandbox) o no
(movimiento puntual sandbox). Las cuatro combinaciones son válidas. Los
queries existentes que filtran por `is_projected` siguen funcionando sin
cambios — LiveScope solo añade `is_sandbox=false`.

### 3.2 Scope global `LiveScope` en los cuatro modelos

Para **proteger** todos los queries existentes sin cazarlos uno por uno, se
añade un global scope `LiveScope` que añade `where is_sandbox = false` a cada
query. Beneficio inmediato y sin costo:

- `Movement::realBalance()`, `openingBalance()`, `nextSortOrder()` y todos los
  cálculos de `DashboardController`/`MovementController` excluyen filas
  sandbox **sin tocar una línea**.
- **Route model binding**: los endpoints reales (`/movimientos/{movement}`,
  `/deudas/{debt}`, etc.) devuelven **404 automáticamente** para filas
  sandbox — los global scopes aplican al binding implícito. Seguridad gratis:
  las filas sandbox solo se mutan por los endpoints sandbox.
- `movimientos/reorder`: `Movement::whereIn('id', $ids)` excluye ids sandbox →
  el check de conteo los rechaza con 403.

Las vistas/servicios sandbox optan explícitamente con
`Movimiento::withoutSandboxScope()`.

### 3.3 Puntos ciegos del LiveScope (donde el scope por defecto NO alcanza)

Lista verificada contra el código real — cada punto se implementa en su fase:

| Punto | Problema | Tratamiento |
| ----- | -------- | ----------- |
| `Debt::movements()` | Los accessors (`paid_installments`, `remaining`, `paid_amount`) y los checks de `DebtController` consultan `$debt->movements()`; con LiveScope una **deuda sandbox** no vería sus propios movimientos (todos `is_sandbox=true`). | Relación **sandbox-aware** (§3.4). |
| `RecurringTransaction::movements()` | Idem: `destroy` real borra `$template->movements()->where('is_projected', true)`; una template sandbox no vería sus cuotas. | Relación **sandbox-aware** (§3.4). |
| `Movement::nextSortOrder()` | Con LiveScope, el `max(sort_order)` no ve filas sandbox → dos filas sandbox del mismo día colisionan en `sort_order` (la generación de cuotas en lote lo provocaría seguro). | Nuevo `Movement::nextSandboxSortOrder()` (§5.2) usado por todos los writes sandbox. |
| `GoalController::destroy` / `can_delete` | `contributions()->exists()` y `contributions_count` excluyen aportes sandbox → una meta con solo aportes sandbox parecería borrable y el delete reventaría por FK (`goal_contributions.goal_id` restrict). | Checks con `withoutGlobalScope(LiveScope::class)` (Fase V1.2-6). |
| `Goal::progress_amount` / `apartadoAmount` | `withSum('contributions')` y `GoalContribution::query()` excluyen aportes sandbox → correcto por defecto (las metas no se completan por aportes simulados); las vistas de simulación calculan además el monto con sandbox explícitamente. | Deseado; la vista simulada suma aparte (Fase V1.2-6/V1.2-7). |
| `ProjectionService::generateForTemplate` | El check idempotente `Movement::where('recurring_id', ...)` y `nextSortOrder` no ven filas sandbox; la generación sandbox debe usar el scope sandbox. | Parámetro `$isSandbox` en el método privado (§3.6). |

### 3.4 Relaciones sandbox-aware (`Debt`, `RecurringTransaction`)

Los movimientos de una deuda/template comparten SIEMPRE el flag de su padre
(un movimiento de deuda sandbox nace con `is_sandbox=true` y ningún endpoint
real puede mutarlo). Por eso la relación puede volverse sandbox-aware sin
riesgo:

```php
public function movements(): HasMany
{
    $relation = $this->hasMany(Movement::class);

    // Los movimientos de una entidad sandbox también son sandbox;
    // LiveScope los ocultaría de su propio padre.
    if ($this->is_sandbox) {
        $relation->withoutGlobalScope(LiveScope::class);
    }

    return $relation;
}
```

- Deuda/template **real**: LiveScope aplica (`is_sandbox=false`) — idéntico al
  comportamiento actual.
- Deuda/template **sandbox**: el FK (`debt_id`/`recurring_id`) ya limita a sus
  propias filas — los accessors, el `show`, el `destroy` y el `payoff`
  funcionan con la misma lógica.

`Goal::contributions()` NO se hace sandbox-aware: la meta es real y sus
aportes pueden ser de ambos mundos; ahí se controla por query según la vista.

### 3.5 El estado del sandbox es la existencia de filas sandbox

No hay tabla `sandbox_sessions` ni columna `sandbox_active`. La pregunta
"¿hay un sandbox activo?" se resuelve con `SandboxService::hasSandboxRows($userId)`:

```php
public static function hasSandboxRows(int $userId): bool
{
    return Movement::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
        || RecurringTransaction::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
        || Debt::withoutSandboxScope()->where('user_id', $userId)->where('is_sandbox', true)->exists()
        || GoalContribution::withoutSandboxScope()
            ->where('is_sandbox', true)
            ->whereHas('goal', fn (Builder $q) => $q->where('user_id', $userId))
            ->exists(); // goal_contributions no tiene user_id
}
```

`hasSandbox` viaja como **shared prop de Inertia** (`HandleInertiaRequests::share`).
No hay endpoint `status()`: toda mutación termina en `back()` y refresca las
shared props. Rápido con los índices `(user_id, is_sandbox)` (y
`(goal_id, is_sandbox)`). Sin sync entre flag y datos — no puede haber
"sandbox activo sin filas" ni viceversa.

### 3.6 `ProjectionService` por scope (firmas reales)

El código actual (`app/Services/ProjectionService.php`):

- `generateForUser(int $userId, ?int $horizonMonths = null): int` — itera
  templates activas, genera vía `Movement::forceCreate` con `source='recurring'`,
  `is_projected=true`, check idempotente por `(recurring_id, date)`, solo
  fechas futuras, `nextSortOrder`.
- `regenerateForUser(int $userId, ?int $horizonMonths = null): int` — borra
  `source='recurring' AND is_projected=true` y regenera, en una transacción.
  **Protege los movimientos realizados** (el delete nunca toca
  `is_projected=false`) — bugfix post-v1 que este plan preserva.

**Con LiveScope, ambos métodos quedan correctos sin cambios**: el delete
 excluye filas sandbox y la query de templates excluye templates sandbox.

Se añade el espejo sandbox:

```php
public function generateSandboxForUser(int $userId, ?int $horizonMonths = null): int;
public function regenerateSandboxForUser(int $userId, ?int $horizonMonths = null): int;
```

- Delete sandbox: `Movement::withoutSandboxScope()->where('user_id', $userId)
  ->where('is_sandbox', true)->where('source', 'recurring')
  ->where('is_projected', true)->delete()`.
- Templates: `RecurringTransaction::withoutSandboxScope()
  ->where('user_id', $userId)->where('is_sandbox', true)->where('active', true)`.
- Generación: misma lógica con `is_sandbox=true` en el `forceCreate`,
  `nextSandboxSortOrder()` y check idempotente sin scope.
- Refactor: `generateForTemplate()` gana un parámetro `bool $isSandbox = false`
  que controla flag/sort/check; el resto de la lógica (día del mes con clamp,
  horizonte, importes) se comparte tal cual.
- Horizonte: ambos scopes leen `settings['projection_horizon']` del usuario
  (default `DEFAULT_HORIZON_MONTHS = 12`), igual que
  `GenerateProjectionsCommand`.

El comando CLI (`app:generate-projections`) queda intacto y NO toca sandbox
(LiveScope lo protege) — test explícito en V1.2-9.

### 3.7 Modo Simulación es UI-only (localStorage)

El toggle "Modo Simulación" del header solo muestra/oculta el banner y las
acciones. Se persiste en `localStorage` (clave `sandbox-mode`) para sobrevivir
al refresh — es preferencia de UI, no estado de backend. Encenderlo no crea
filas; apagarlo no borra nada. Si el usuario apaga el modo sin revertir ni
guardar, al volver a prenderlo el escenario sigue ahí (y `hasSandbox` sigue
llegando en cada visita). Solo **Revertir** y **Guardar** mutan datos.

Si ya existe un sandbox, activar el modo lo **reanuda** — no lo sobreescribe.
Aceptar uno nuevo requiere Revertir primero.

### 3.8 Guardar (commit) — promoción transaccional

"Guardar" SIEMPRE promueve el escenario a estado real, en UNA transacción
(`SandboxService::commit`):

1. **DELETE proyecciones sandbox**: `movements` con `is_sandbox=true AND
   source='recurring' AND is_projected=true` (las cuotas generadas — se
   regeneran como reales en el paso final).
2. **UPDATE movimientos manuales sandbox** (`is_sandbox=true AND
   source='manual'`) → `is_sandbox=false`. Esto cubre: movimientos puntuales
   del diálogo **y** desembolsos/cuotas/liquidaciones de deudas sandbox (todos
   tienen `source='manual'` y `debt_id`).
3. **UPDATE templates recurrentes sandbox** → `is_sandbox=false`.
4. **UPDATE deudas sandbox** → `is_sandbox=false` (sus movimientos ya quedaron
   reales en el paso 2).
5. **UPDATE aportes de metas**: `goal_contributions` con `is_sandbox=true` y
   `date <= today` → `is_sandbox=false`. Los aportes **con fecha futura se
   DELETE** (eran plan de ahorro, no dinero ya guardado; ver §3.10).
6. **`syncCompletion()`** por cada meta con aportes promovidos (una meta puede
   completarse recién al guardar — durante la simulación nunca se completa por
   aportes sandbox).
7. **`regenerateForUser($userId, $horizonte)`** (scope real) — regenera las
   cuotas reales desde todas las templates activas, incluidas las recién
   promovidas.

Resultado: el balance real incluye el préstamo, las deudas aparecen en el
módulo Deudas, las cuotas como proyección real, y `hasSandbox` vuelve a `false`.

**Revertir** (`SandboxService::revert`), también transaccional, orden
children→parents (FKs restrict):

1. `DELETE movements WHERE is_sandbox=true` (cubre manuales, de deudas y
   proyecciones).
2. `DELETE recurring_transactions WHERE is_sandbox=true`.
3. `DELETE debts WHERE is_sandbox=true` (después de sus movimientos).
4. `DELETE goal_contributions WHERE is_sandbox=true` (las metas reales quedan).

Ambas acciones deben ser idempotentes-safe ante estado vacío (revert sin
filas = no-op OK; commit sin filas = 422 con mensaje claro).

### 3.9 Datos reales pueden cambiar durante el sandbox

El "snapshot" es **conceptual**: el sandbox es una capa aditiva sobre el
estado real. Revertir borra solo filas sandbox y deja el estado real como esté
en ese momento (el usuario pudo haber agregado movimientos reales durante la
simulación — se quedan). Si se quisiera "congelar" el estado real → v3.

### 3.10 Semántica de aportes simulados con fecha futura

Los aportes reales (`StoreGoalContributionRequest`) exigen `date <= today`
(dinero ya ahorrado). Los aportes **sandbox** permiten fecha futura (es el
caso de uso "¿llego ahorrando 500/mes?"). Al guardar:

- Fecha pasada → se promueven a reales (dinero ya ahorrado, `syncCompletion`
  corre).
- Fecha futura → se **descartan** (aún no ocurrieron). El copy del confirm lo
  explicita: "Los aportes simulados con fecha futura no se guardan".

Durante la simulación, Metas muestra el progreso "real + simulado" separado;
el apartado real nunca se infla con aportes simulados.

### 3.11 UI: banner + estilos diferenciados + secciones aparte

- Header (`AppHeader`): toggle "Modo Simulación" (icono `FlaskConical` de
  lucide) + pastilla "1 escenario activo" cuando `hasSandbox`.
- Banner amber al activar el modo, con acciones: "Agregar movimiento
  simulado", "Agregar recurrente simulado", "Agregar deuda simulada",
  "Revertir escenario" (destructivo + confirm), "Guardar como real"
  (primary + confirm). Los botones terminales se conectan en V1.2-8; antes de
  esa fase el banner muestra solo las acciones ya implementadas.
- Movimientos: sección "Simulación" debajo de reales/proyectadas (fondo
  dashed/amber, badge "Simulado"). Sin "mezcla" en la lista real — el
  running balance de la vista real queda limpio.
- Proyección: toggle "Incluir simulación" (default off). ON = filas sandbox
  integradas al timeline paginado con badge; el balance corrido las incluye
  (es una proyección combinada — ese es el valor de la vista).
- Recurrentes: sección "Plantillas simuladas".
- Deudas: sección "Deudas simuladas" con cards (factor, próxima cuota,
  restante) + editar/liquidar/eliminar sandbox. Estrategias avalanche/snowball
  en `Deudas/Show` incluyen deudas sandbox (badge) con `include_sandbox=1`.
- Metas: aportes simulados en el historial de aportes de cada meta (badge) +
  botón "Aporte simulado"; resumen con "apartado simulado" adicional.
- Dashboard: toggle "Incluir simulación" (solo visible con `hasSandbox`).

### 3.12 Rutas y controladores (convención del codebase)

URIs y nombres de rutas en español; controladores en inglés — como
`movimientos.*`, `deudas`, `metas.aportes.*`:

```php
// Sandbox — movements
Route::post('simulacion/movimientos', [SandboxMovementController::class, 'store'])->name('simulacion.movimientos.store');
Route::put('simulacion/movimientos/{movement}', [SandboxMovementController::class, 'update'])->name('simulacion.movimientos.update');
Route::patch('simulacion/movimientos/{movement}', [SandboxMovementController::class, 'update'])->name('simulacion.movimientos.patch');
Route::delete('simulacion/movimientos/{movement}', [SandboxMovementController::class, 'destroy'])->name('simulacion.movimientos.destroy');

// Sandbox — recurring
Route::post('simulacion/recurrentes', [SandboxRecurringController::class, 'store'])->name('simulacion.recurrentes.store');
Route::put('simulacion/recurrentes/{recurringTransaction}', [SandboxRecurringController::class, 'update'])->name('simulacion.recurrentes.update');
Route::patch('simulacion/recurrentes/{recurringTransaction}', [SandboxRecurringController::class, 'update'])->name('simulacion.recurrentes.patch');
Route::delete('simulacion/recurrentes/{recurringTransaction}', [SandboxRecurringController::class, 'destroy'])->name('simulacion.recurrentes.destroy');
Route::post('simulacion/recurrentes/regenerate', [SandboxRecurringController::class, 'regenerate'])->name('simulacion.recurrentes.regenerate');

// Sandbox — debts
Route::post('simulacion/deudas', [SandboxDebtController::class, 'store'])->name('simulacion.deudas.store');
Route::put('simulacion/deudas/{debt}', [SandboxDebtController::class, 'update'])->name('simulacion.deudas.update');
Route::patch('simulacion/deudas/{debt}', [SandboxDebtController::class, 'update'])->name('simulacion.deudas.patch');
Route::delete('simulacion/deudas/{debt}', [SandboxDebtController::class, 'destroy'])->name('simulacion.deudas.destroy');
Route::post('simulacion/deudas/{debt}/payoff', [SandboxDebtController::class, 'payoff'])->name('simulacion.deudas.payoff');

// Sandbox — goal contributions (la meta es real; el aporte es sandbox)
Route::post('simulacion/metas/{goal}/aportes', [SandboxGoalContributionController::class, 'store'])->name('simulacion.metas.aportes.store');
Route::delete('simulacion/metas/{goal}/aportes/{contribution}', [SandboxGoalContributionController::class, 'destroy'])->name('simulacion.metas.aportes.destroy');

// Terminales
Route::post('simulacion/revertir', [SandboxController::class, 'revert'])->name('simulacion.revertir');
Route::post('simulacion/guardar', [SandboxController::class, 'commit'])->name('simulacion.guardar');
```

- Todos dentro del grupo `['auth', 'verified']`.
- **Binding**: los endpoints sandbox NO usan route model binding implícito
  para entidades sandbox (LiveScope los 404). Los controllers resuelven
  manualmente, estilo del codebase:

```php
$movement = Movement::withoutSandboxScope()
    ->where('is_sandbox', true)
    ->findOrFail($id);

if ($movement->user_id !== $request->user()->id) {
    abort(403);
}
```

  (Un id real en un endpoint sandbox → 404; un id sandbox ajeno → 403.)
  `Goal` en `simulacion/metas/{goal}/aportes` sí usa binding normal (la meta
  es real).
- El backend **fuerza** `is_sandbox=true` en cada write sandbox; el cliente
  jamás lo envía ni se valida (`'is_sandbox' => 'required|true'` del plan
  anterior era inválido e innecesario). Los FormRequests existentes se
  reutilizan por composición (`MovementRequest`, `RecurringRequest`,
  `StoreDebtRequest`/`UpdateDebtRequest`/`PayoffDebtRequest`). Solo los
  aportes sandbox necesitan request nuevo (fecha futura permitida).
- Feedback con `Inertia::flash('toast', [...])` + `back()`, como todo el app.
- Tras agregar rutas: regenerar Wayfinder (`php artisan wayfinder:generate`,
  o el watcher de `composer run dev`).

---

## 4. Modelo de datos

### 4.1 Migración

```php
// movements: is_sandbox
Schema::table('movements', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after('is_projected');
    $table->index(['user_id', 'is_sandbox']);
});

// recurring_transactions: is_sandbox
Schema::table('recurring_transactions', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after('active');
    $table->index(['user_id', 'is_sandbox']);
});

// debts: is_sandbox
Schema::table('debts', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after('closed_at');
    $table->index(['user_id', 'is_sandbox']);
});

// goal_contributions: is_sandbox (sin user_id — índice por goal)
Schema::table('goal_contributions', function (Blueprint $table) {
    $table->boolean('is_sandbox')->default(false)->after('notes');
    $table->index(['goal_id', 'is_sandbox']);
});
```

MySQL soporta `->after()` (motor de la DB actual). Una sola migración.
Todos los registros existentes defaultean `false` — cero impacto.

### 4.2 Modelos

En `Movement`, `RecurringTransaction`, `Debt` y `GoalContribution`:

- `'is_sandbox'` en `$fillable` y `$casts` (`'boolean'`).
- Registrar `LiveScope` + helpers:

```php
use App\Models\Scopes\LiveScope;

protected static function booted(): void
{
    static::addGlobalScope(LiveScope::class);
}

public static function withoutSandboxScope(): Builder
{
    return static::query()->withoutGlobalScope(LiveScope::class);
}

public function scopeSandbox(Builder $query): void
{
    $query->withoutGlobalScope(LiveScope::class)
        ->where($query->getModel()->getTable().'.is_sandbox', true);
}
```

- `Movement::nextSandboxSortOrder()` (nuevo, §5.2).
- `Debt::movements()` y `RecurringTransaction::movements()` sandbox-aware
  (§3.4). Actualizar el PHPDoc `@property bool $is_sandbox`.

### 4.3 Scope global `LiveScope`

`app/Models/Scopes/LiveScope.php`:

```php
class LiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.is_sandbox', false);
    }
}
```

### 4.4 Factories

`MovementFactory`, `RecurringTransactionFactory`, `DebtFactory`,
`GoalContributionFactory`: añadir `'is_sandbox' => false` a `definition()` y
state helper `sandboxed()`:

```php
public function sandboxed(): static
{
    return $this->state(fn (array $attributes) => [
        'is_sandbox' => true,
    ]);
}
```

(Como los states `projected()`/`sortOrder()` existentes en `MovementFactory`.)

---

## 5. Servicios

### 5.1 `SandboxService` (nuevo)

`app/Services/SandboxService.php`:

```php
public static function hasSandboxRows(int $userId): bool;
public function revert(int $userId): void;   // DB::transaction, §3.8
public function commit(int $userId): void;   // DB::transaction, §3.8 (422 si no hay filas)
```

`commit` lanza `abort(422, ...)` si no hay filas sandbox (defensivo — el
botón solo aparece con `hasSandbox`). El commit llama
`ProjectionService::regenerateForUser` con el horizonte del usuario
(`settings['projection_horizon'] ?? ProjectionService::DEFAULT_HORIZON_MONTHS`).

### 5.2 `ProjectionService` (refactor)

Ver §3.6. Resumen de la firma final:

```php
public function generateForUser(int $userId, ?int $horizonMonths = null): int;          // real (sin cambios)
public function regenerateForUser(int $userId, ?int $horizonMonths = null): int;       // real (sin cambios)
public function generateSandboxForUser(int $userId, ?int $horizonMonths = null): int;   // nuevo
public function regenerateSandboxForUser(int $userId, ?int $horizonMonths = null): int; // nuevo

private function generateForTemplate(
    RecurringTransaction $template,
    int $userId,
    int $horizonMonths,
    Carbon $today,
    bool $isSandbox = false,
): int;
```

Y en `Movement`:

```php
public static function nextSandboxSortOrder(int $userId, string $date, bool $isProjected): int
{
    return (int) static::withoutSandboxScope()
        ->where('user_id', $userId)
        ->where('date', $date)
        ->where('is_sandbox', true)
        ->where('is_projected', $isProjected)
        ->max('sort_order') + 1;
}
```

Todos los writes sandbox (movimiento manual sandbox, cuotas generadas,
movimientos de deuda sandbox) usan `nextSandboxSortOrder`.

---

## 6. Fases del plan

### Fase V1.2-0 — Migración, LiveScope, modelos y factories

**Objetivo:** añadir `is_sandbox` sin romper nada existente. Las 387 pruebas
de la suite actual siguen pasando idénticas (las filas nuevas defaultean en
`false` y LiveScope las deja fuera de todos los queries existentes).

**Tareas**

1. Migración única: `is_sandbox` + índices en las 4 tablas (§4.1).
2. `LiveScope` en `app/Models/Scopes/` (§4.3).
3. Los 4 modelos: `$fillable`, `$casts`, `booted()` con LiveScope,
   `withoutSandboxScope()`, `scopeSandbox()` (§4.2).
4. `Debt::movements()` y `RecurringTransaction::movements()` sandbox-aware
   (§3.4).
5. `Movement::nextSandboxSortOrder()` (§5.2).
6. Factories: `is_sandbox` en `definition()` + state `sandboxed()` (§4.4).
7. Tests Pest:
   - Query normal excluye filas sandbox; `withoutSandboxScope()` las trae
     todas; `sandbox()` trae solo sandbox — para los 4 modelos.
   - Route model binding de endpoints reales 404 para ids sandbox (mover
     un movimiento/deuda sandbox por su endpoint real).
   - Accessors de una `Debt` sandbox (`sandboxed()` + movimientos sandbox)
     calculan `paid_installments`/`remaining`/`paid_amount` correctamente
     (relación sandbox-aware).
   - Factory `sandboxed()` en los 4 modelos.

**Criterios de aceptación**

- [ ] Migración corre sin perder datos (todos defaultean false).
- [ ] Suite existente completa en verde (sin modificar tests previos).
- [ ] `Movement::where('user_id', $u)->get()` excluye filas sandbox sin tocar
      el código existente; `withoutSandboxScope()` las incluye.
- [ ] `/movimientos/{id-sandbox}` y `/deudas/{id-sandbox}` → 404.

**Commits:** `feat: is_sandbox column on movements, recurring, debts and goal contributions`,
`feat: LiveScope global scope and sandbox helpers`,
`test: sandbox scope filtering and bindings`.

---

### Fase V1.2-1 — `SandboxService` + shared prop `hasSandbox`

**Objetivo:** exponer al frontend si existe sandbox activo.

**Tareas**

1. `app/Services/SandboxService.php` con `hasSandboxRows()` (§3.5). Los
   métodos `revert`/`commit` se completan en V1.2-8 (crear la clase con el
   static primero).
2. `HandleInertiaRequests::share`: añadir `'hasSandbox'` cuando el usuario
   está autenticado:

```php
'hasSandbox' => $request->user()
    ? SandboxService::hasSandboxRows($request->user()->id)
    : false,
```

3. Tests: prop `hasSandbox=false` sin filas; `true` al crear una fila sandbox
   en cada una de las 4 entidades (dataset); vuelve a `false` tras borrarla.

**Criterios de aceptación**

- [ ] Cada página Inertia recibe `hasSandbox` booleano.
- [ ] El cálculo usa `withoutSandboxScope()` (las queries tradicionales no).
- [ ] Tests cubren las 4 entidades.

**Commits:** `feat: SandboxService hasSandboxRows`,
`feat: hasSandbox Inertia shared prop`,
`test: has_sandbox shared prop`.

---

### Fase V1.2-2 — Movimientos simulados CRUD + UI del modo simulación

**Objetivo:** backend de movimientos sandbox + el "Modo Simulación" (toggle,
banner, composable, diálogo).

**Tareas**

1. `SandboxMovementController` (`store`/`update`/`destroy`): usa
   `MovementRequest`, fuerza `is_sandbox=true` y `source='manual'`,
   `nextSandboxSortOrder()` para el sort; resuelve el modelo sin scope
   (§3.12); toasts en español.
2. Rutas `simulacion/movimientos` (§3.12) + regenerar Wayfinder.
3. `useSandbox` composable (`resources/js/composables/useSandbox.ts`):
   `hasSandbox` (desde `usePage().props`), `modeActive` (localStorage
   `sandbox-mode`), `enter()`, `exit()`. Los métodos `revert()`/`save()` se
   agregan en V1.2-8.
4. Toggle "Modo Simulación" en `AppHeader` (icono `FlaskConical`; pastilla
   "1 escenario activo" cuando `hasSandbox`).
5. Banner amber (nuevo `resources/js/components/sandbox/SimulationBanner.vue`
   + `types.ts` en la carpeta): "Estás en modo Simulación — los cambios no
   afectan tu balance real" + botón "Agregar movimiento simulado".
6. `MovementDialog` con prop `mode: 'real' | 'sandbox'` (default `'real'`):
   pastilla "Simulado" en la cabecera, submit a rutas `simulacion.movimientos.*`
   vía Wayfinder. Sin cambios de comportamiento cuando `mode='real'`.
7. Tests:
   - Crear movimiento sandbox: no aparece en la lista real
     (`MovementController::index` no lo trae — LiveScope), aparece con
     `withoutSandboxScope()`.
   - Editar/borrar sandbox no toca filas reales; borrar un real no toca
     sandbox.
   - Endpoint real con id sandbox → 404; endpoint sandbox con id real → 404;
     id ajeno → 403.

**Criterios de aceptación**

- [ ] Con el modo activo, agrego "préstamo recibido 5000 hoy" como movimiento
      simulado: toast de éxito y `hasSandbox` pasa a `true` en la respuesta.
- [ ] El balance real (`realBalance`, dashboard) sigue sin contar los 5000.
- [ ] Edición/borrado funciona sin afectar filas reales (verificado por tests
      en esta fase; la visual llega en V1.2-3).

**Commits:** `feat: SandboxMovementController`,
`feat: simulacion movement routes`,
`feat: useSandbox composable`,
`feat: simulation mode toggle and banner`,
`feat: sandbox movement dialog`,
`test: sandbox movement CRUD`.

---

### Fase V1.2-3 — Timeline: sección Simulación en Movimientos + Proyección con simulación

**Objetivo:** que el usuario VEA los movimientos sandbox.

**Tareas**

1. `MovementController::index`: tercera prop `sandboxMovements` cuando
   `hasSandbox` — query `Movement::sandbox()->forMonth(...)` con los mismos
   campos que los otros grupos + `is_projected`; orden `date, sort_order, id`;
   **sin** `running_balance` (la sección es informativa — el balance corrido
   real no se mezcla con simulación en esta página). Los grupos real/proyectado
   quedan intactos (LiveScope ya los protege).
2. `Movimientos/Index.vue`: sección "Simulación" debajo de las existentes
   (dashed/amber, badge "Simulado"), con editar/borrar (endpoints sandbox,
   `MovementDialog` mode sandbox). Solo cuando `sandboxMovements.length > 0`.
3. `ProjectionController::index`: query param `include_sandbox` (default off).
   ON = el query paginado usa `Movement::withoutSandboxScope()` (real +
   sandbox), cada fila trae `is_sandbox` para el badge, y el balance corrido
   (el "carry") incluye las filas sandbox — es una proyección combinada. El
   toggle viaja en la paginación (`withQueryString`).
4. `Proyeccion/Index.vue`: toggle "Incluir simulación" (visible con
   `hasSandbox`); badge "Simulado" en filas sandbox (estilo amber como el
   badge de recurring existente).
5. Tests:
   - `sandboxMovements` solo llega cuando hay filas sandbox; partición real/
     proyectada no cambia.
   - Proyección con `include_sandbox=1`: incluye filas sandbox, el
     running balance las acumula; sin el param, idéntico a hoy.

**Criterios de aceptación**

- [ ] Tras crear un movimiento sandbox, Movimientos muestra la sección
      "Simulación" con la fila y estilo distintivo.
- [ ] Proyección con "Incluir simulación" ON: la fila aparece integrada con
      badge y la proyección combinada refleja el flujo con el escenario.
- [ ] Ambas páginas vuelven a su estado real apagando el toggle/sin filas.

**Commits:** `feat: movements index sandbox group`,
`feat: simulation section in Movimientos`,
`feat: proyeccion include_sandbox`,
`test: movements and projection with sandbox`.

---

### Fase V1.2-4 — Recurrentes simulados + ProjectionService por scope

**Objetivo:** simular cuotas periódicas (p.ej. un préstamo informal a 12
cuotas) con proyecciones sandbox.

**Tareas**

1. `ProjectionService`: refactor de §5.2 — parámetro `bool $isSandbox` en
   `generateForTemplate`, métodos `generateSandboxForUser`/
   `regenerateSandboxForUser`. La lógica real queda byte-compatible en
   comportamiento (los tests de `ProjectionTest`/
   `GenerateProjectionsTest`/`RecurringTransactionTest` siguen en verde).
2. `SandboxRecurringController`: `store`/`update`/`destroy`/
   `regenerate` espejando `RecurringTransactionController` pero forzando
   `is_sandbox=true`; tras create/update/delete →
   `regenerateSandboxForUser($userId)`. `destroy` borra las cuotas de la
   template vía la relación sandbox-aware + la template.
3. Rutas `simulacion/recurrentes` (§3.12) + Wayfinder.
4. `RecurringDialog` con prop `mode` (análogo a `MovementDialog`).
5. `Recurrentes/Index.vue`: sección "Plantillas simuladas" (solo con
   `hasSandbox`), con editar/borrar sandbox.
6. Tests:
   - Crear template sandbox → genera N movimientos `is_sandbox=true`,
     `source='recurring'`, `is_projected=true` con `recurring_id` — y NINGUNA
     fila real.
   - `regenerateForUser` (real) no borra/regenera filas sandbox;
     `regenerateSandboxForUser` no toca reales.
   - Editar/borrar template sandbox regenera solo el scope sandbox.
   - El comando CLI `app:generate-projections` no genera ni borra sandbox.

**Criterios de aceptación**

- [ ] Creo "Préstamo x12 cuotas 500" como template sandbox: aparecen 12
      cuotas sandbox (visibles en Proyección con el toggle de V1.2-3). La
      proyección real sigue intacta.
- [ ] Borro la template sandbox → las 12 filas desaparecen (regeneración).
- [ ] La timeline real no cambia en ningún paso.

**Commits:** `refactor: ProjectionService sandbox scope`,
`feat: SandboxRecurringController`,
`feat: sandbox recurring dialog and section`,
`test: sandbox recurring and projection`.

---

### Fase V1.2-5 — Deudas simuladas

**Objetivo:** simular préstamos con el UX del módulo Deudas: desembolso,
cuotas, factor de tasa, liquidación — todo sandbox.

**Tareas**

1. `SandboxDebtController`: `store`/`update`/`destroy`/`payoff` espejando
   `DebtController` (misma validación: `StoreDebtRequest`/
   `UpdateDebtRequest`/`PayoffDebtRequest`; mismo requisito de
   `debt_category_id` en Preferencias vía `resolveLoanCategory`; mismos
   `DB::transaction`) pero:
   - La deuda nace con `is_sandbox=true`.
   - Los movimientos (desembolso `+principal`, cuotas `-installment` con
     `debt_id`, `is_projected` por fecha futura) nacen con `is_sandbox=true`
     y `nextSandboxSortOrder()`.
   - `destroy`: el check "pagos reales registrados" usa la relación
     sandbox-aware (los movimientos de una deuda sandbox son sandbox) —
     semántica espejo: no borrar una deuda sandbox con cuotas ya "pagadas"
     (is_projected=false) dentro del escenario.
   - `payoff`: crea el movimiento de liquidación sandbox, borra las cuotas
     proyectadas de esa deuda y cierra la deuda sandbox.
   - Resolución manual de `{debt}` sin scope (§3.12).
2. Rutas `simulacion/deudas` (§3.12) + Wayfinder.
3. `DebtDialog` con prop `mode: 'sandbox'` (submit a rutas sandbox; mismo
   comportamiento de `payment_dates` dinámicas).
4. `Deudas/Index.vue`: sección "Deudas simuladas" (solo con `hasSandbox`) con
   cards: factor derivado (badge ×1,xx), restante, próxima cuota — reutilizando
   el card existente con un flag de estilo/badge. Acciones: editar, liquidar
   (payoff dialog), eliminar — todas a endpoints sandbox. **Sin página Show
   para deudas sandbox** (fuera de alcance).
5. `DebtController::show`: query param `include_sandbox` — cuando ON, el input
   de `DebtStrategy` incluye las deudas sandbox activas (cada ítem con flag
   `is_sandbox`); la UI de avalanche/snowball las marca con badge. Default
   OFF (estrategia real).
6. Tests:
   - Crear deuda sandbox "Préstamo 5000 x12": deuda + desembolso + 12 cuotas,
     todos `is_sandbox=true`; nada en listas/cards reales
     (`DebtController::index` no la trae).
   - `remaining`/`paid_installments` correctos en la deuda sandbox
     (relación sandbox-aware).
   - Payoff de deuda sandbox: movimiento sandbox + cierre; no toca reales.
   - Destroy con las reglas espejo.
   - Estrategia con `include_sandbox=1` incluye deudas sandbox marcadas.

**Criterios de aceptación**

- [ ] Simulo "Préstamo 5000 x12 de 500" desde el banner: aparece en la
      sección "Deudas simuladas" con factor y próxima cuota; el desembolso y
      las cuotas aparecen en la sección "Simulación" de Movimientos (y en
      Proyección con toggle ON).
- [ ] El módulo Deudas real (cards, detail, estrategias sin toggle) sigue
      idéntico.
- [ ] "Revertir" (una vez implementado) o borrar la deuda sandbox limpia
      deuda + movimientos.

**Commits:** `feat: SandboxDebtController`,
`feat: sandbox debt routes`,
`feat: sandbox debt dialog and section`,
`feat: debt strategies with sandbox`,
`test: sandbox debts`.

---

### Fase V1.2-6 — Aportes de metas simulados

**Objetivo:** simular ahorro en metas reales, incluyendo aportes futuros
("¿llego a la meta ahorrando X por mes?").

**Tareas**

1. `StoreSandboxGoalContributionRequest` (nuevo): mismas reglas que
   `StoreGoalContributionRequest` pero `date` permite fechas futuras
   (`'date' => ['required', 'date']` — sin `before_or_equal:today`).
2. `SandboxGoalContributionController`: `store`/`destroy` sobre una **meta
   real** (binding normal de `Goal` + ownership 403). El aporte nace con
   `is_sandbox=true`. `destroy` resuelve el aporte manualmente:
   `GoalContribution::withoutSandboxScope()->where('is_sandbox', true)->findOrFail(...)`
   + ownership de la meta.
3. **Fix de puntos ciegos en el flujo real** (§3.3):
   - `GoalController::destroy`: el check `contributions()->exists()` pasa a
     `contributions()->withoutGlobalScope(LiveScope::class)->exists()` —
     una meta con aportes (reales o sandbox) no se puede borrar.
   - `GoalController::index`: `can_delete` cuenta también aportes sandbox
     (`withCount` con closure sin scope + `where('is_sandbox', true)`); y
     cuando `hasSandbox`, añade por meta `simulated_amount` (suma con scope
     sandbox) y en el resumen `apartado_simulado`.
4. `Metas/Index.vue`: en el historial de aportes de cada meta, los aportes
   sandbox aparecen con badge "Simulado" (solo con `hasSandbox`); botón
   "Aporte simulado" por meta (usa `ContributionDialog` con prop `mode`).
   El progreso de la meta muestra "real + simulado" cuando hay aportes
   simulados (texto secundario, sin tocar la barra de progreso real).
5. `syncCompletion` NUNCA corre por aportes sandbox (LiveScope protege
   `progress_amount`) — la meta no se completa "en simulación"; solo al
   guardar (V1.2-8).
6. Tests:
   - Aporte sandbox (pasado y futuro): no cambia `progress_amount` real, no
     completa la meta, no infla `apartadoAmount` real; sí aparece en
     `simulated_amount`/`apartado_simulado`.
   - `can_delete`/`destroy` de meta considera aportes sandbox (409 con solo
     aportes sandbox; sin el fix sería FK 500).
   - Destroy de aporte sandbox no toca reales.

**Criterios de aceptación**

- [ ] Simulo "500/mes hasta diciembre" en una meta: la card muestra el aporte
      con badge y el progreso "con simulación"; el apartado real no cambia.
- [ ] Una meta solo con aportes sandbox no se puede borrar (mensaje claro).
- [ ] La lista de metas real sigue idéntica sin simulación.

**Commits:** `feat: sandbox goal contributions`,
`fix: goal deletion checks see sandbox contributions`,
`feat: simulated contributions in Metas`,
`test: sandbox goal contributions`.

---

### Fase V1.2-7 — Dashboard "con simulación" completo

**Objetivo:** que el dashboard refleje el escenario completo cuando el
usuario lo pida.

**Tareas**

1. `DashboardController::index`: query param `include_sandbox` (default off).
   Cuando ON, todas las métricas se calculan **además** con sandbox (la
   vista muestra el valor con simulación y el real como referencia):
   - `cards.realBalance`: incluye movimientos sandbox con `is_projected=false`
     y `date <= today` (sin scope).
   - `cards.monthIncome` / `monthExpense`: ídem con los filtros del mes.
   - `cards.projectedEndOfMonth`: el `futureSum` incluye filas sandbox.
   - `budgetOverview.spent`: incluye gastos sandbox de categorías con límite.
   - `reconciliation`: `reconciled` y `difference` siguen calculados SOLO con
     real (la reconciliación es contra cuentas reales); cuando ON se añade
     `difference_with_sandbox` informativo.
   - `upcomingProjections`: incluye filas sandbox con flag para badge.
   - `debtsOverview`: incluye deudas sandbox (flag `is_sandbox`) — abajo, tras
     las reales.
   - `goalsOverview`: `progress_amount`/`percent` con aportes sandbox + flag;
     `goalsSummary.apartado_with_sandbox` informativo.
   - `chartData`: se mantiene como línea base real y se añade
     `chartDataSimulated` (balance diario acumulando también filas sandbox).
2. `Dashboard.vue`: toggle "Incluir simulación" (visible solo con
   `hasSandbox`) → visita `/dashboard?include_sandbox=1` conservando el mes
   seleccionado. Cards con badge "sim" cuando el valor difiere del real
   (subtexto con el valor real). Debts/Goals cards marcan filas simuladas.
3. `BalanceLineChart`: prop opcional `simulatedData?: { date: string;
   balance: number }[]` → segundo dataset dashed amber "Con simulación"
   (mismo eje; Chart.js soporta multi-dataset — el componente ya usa
   vue-chartjs).
4. Tests: dashboard con préstamo sandbox + 12 cuotas + deuda sandbox + aporte
   sandbox, con y sin `include_sandbox`; asserts de cada métrica; el sin-param
   es byte-compatible con hoy.

**Criterios de aceptación**

- [ ] Con "Préstamo sandbox 5000 + 12 cuotas" y toggle ON: la card de balance
      sube por el préstamo, la de fin de mes muestra el flujo con las cuotas,
      el chart muestra la línea simulada sobre la real.
- [ ] Card Deudas incluye la deuda simulada; card Metas muestra el progreso
      con aportes simulados.
- [ ] Toggle OFF → todo vuelve a valores reales (sin parámetro, respuesta
      idéntica a la actual).

**Commits:** `feat: dashboard include_sandbox param`,
`feat: dashboard include simulation toggle`,
`feat: balance chart simulation overlay`,
`test: dashboard with sandbox`.

---

### Fase V1.2-8 — Revertir y Guardar como real

**Objetivo:** las dos acciones terminales del sandbox, transaccionales sobre
las 4 entidades.

**Tareas**

1. `SandboxService::revert(int $userId)` (§3.8): transacción con los 4
   DELETE en orden children→parents.
2. `SandboxService::commit(int $userId)` (§3.8): transacción con los 7 pasos
   (delete proyecciones sandbox → promote movimientos `source='manual'` →
   promote templates → promote deudas → promote aportes pasados + delete
   futuros → `syncCompletion` de metas afectadas → `regenerateForUser`).
   `abort(422, 'No hay un escenario de simulación activo.')` si no hay filas.
3. `SandboxController` con `revert()`/`commit()` → rutas
   `simulacion/revertir` / `simulacion/guardar` + Wayfinder. Ambos terminan
   con toast y `back()` (`hasSandbox` llega `false` en la respuesta).
4. Frontend:
   - `useSandbox` gana `revert()`/`save()` (router.post a las rutas).
   - Botones en el `SimulationBanner`: "Revertir escenario" (destructivo,
     confirm dialog: "Revertir descartará TODO el escenario simulado. Esta
     acción no se puede deshacer. ¿Confirmar?") y "Guardar como real"
     (primary, confirm: "Guardar convertirá el escenario en permanente y no
     se puede deshacer. Los aportes simulados con fecha futura no se
     guardarán. ¿Confirmar?").
   - Tras cualquiera: el modo simulación se apaga (localStorage) y las props
     refrescan solas con `back()`.
5. Tests:
   - Revert con escenario completo (movimiento + template con cuotas + deuda
     con movimientos + aportes): borra TODO, el estado real queda intacto
     (fixture de movimientos reales antes/después idéntica), `hasSandbox=false`.
   - Commit: movimientos manuales (incl. de la deuda) pasan a reales con
     `source='manual'`, `is_sandbox=false`; templates promovidas regeneran
     cuotas REALES (`source='recurring'`, `is_projected=true`,
     `is_sandbox=false`); la deuda aparece en `DebtController::index`;
     aportes pasados se vuelven reales, futuros desaparecen; meta con aportes
     suficientes se completa (`syncCompletion`); `hasSandbox=false`.
   - Transaccionalidad: mock que lanza a mitad del commit/revert → rollback,
     estado sandbox intacto.
   - Commit sin filas sandbox → 422.

**Criterios de aceptación**

- [ ] Tras configurar préstamo sandbox + 12 cuotas + deuda + aportes,
      "Revertir" deja el sistema exactamente como antes.
- [ ] "Guardar como real": el ingreso aparece como real en Movimientos, las
      cuotas como proyección real, la deuda en Deudas, los aportes pasados en
      Metas — y `hasSandbox=false`.
- [ ] Ambas acciones son transaccionales (fallar a mitad no deja basura).

**Commits:** `feat: sandbox revert`,
`feat: sandbox commit to real`,
`feat: sandbox terminal actions UI`,
`test: sandbox revert and commit transactions`.

---

### Fase V1.2-9 — Pulido, edge cases y tests finales

**Objetivo:** cerrar v1.2 robusto.

**Tareas**

1. Edge case CLI: `app:generate-projections --user=X` no genera ni borra filas
   sandbox (test explícito).
2. Edge case: modificar/borrar una template, deuda o meta **real** mientras
   existe sandbox activo — el sandbox permanece intacto y viceversa (tests).
3. Edge case documentado: cuota sandbox (`source='recurring'`) cuya fecha
   pasó durante la simulación — al guardar se elimina (paso 1 del commit) y
   **no se regenera** (la generación solo crea fechas futuras, misma semántica
   que el flujo real). El usuario la registra manualmente si quiere. Test +
   nota en el confirm del Guardar.
4. Edge case: aportes sandbox sobre una meta que se completa → al guardar,
   `syncCompletion` marca `completed_at`; si luego se revierten aportes
   reales... (fuera: los aportes ya reales se manejan con el flujo de metas
   existente). Test del camino feliz.
5. UX copy: revisar todos los textos del banner, toggles, confirms y badges
   (español neutro, consistente con los toasts existentes).
6. `vendor/bin/pint --dirty --format agent`; `npm run build` sin warnings de
   Vue; `php artisan wayfinder:generate` actualizado; suite completa
   `php artisan test --compact` en verde.
7. Docs: marcar este plan como completado; nota breve en el README de docs.

**Criterios de aceptación**

- [ ] Edge cases cubiertos con tests.
- [ ] Copy final en español neutro.
- [ ] Pint pasa; build sin warnings; suite completa verde.
- [ ] Plan marcado como completado.

**Commits:** `test: sandbox edge cases`,
`docs: v1.2 simulation feature complete`.

---

## 7. Orden recomendado y dependencias

```
V1.2-0 (migración + LiveScope + factories)      ← fundación: TODO depende de esto
   ↓
V1.2-1 (SandboxService + hasSandbox shared prop)
   ↓
V1.2-2 (movimientos sandbox CRUD + modo simulación UI)
   ↓
V1.2-3 (sección Simulación en Movimientos + Proyección)   ← ver el efecto parcial
   ↓
V1.2-4 (recurrentes sandbox + ProjectionService por scope) ← fase más densa del núcleo
   ↓
V1.2-5 (deudas sandbox)
   ↓
V1.2-6 (aportes de metas sandbox)
   ↓
V1.2-7 (dashboard con simulación)                ← requiere 4, 5 y 6 para reflejar todo
   ↓
V1.2-8 (revertir / guardar — transaccional sobre las 4 entidades)
   ↓
V1.2-9 (pulido y tests)
```

- V1.2-3 antes de V1.2-4: ver el efecto de un movimiento puntual sandbox
  valida el modelo antes de meter el motor de proyección.
- V1.2-7 después de 4/5/6: el toggle del dashboard solo vale la pena cuando
  hay cuotas, deudas y aportes que reflejar.
- V1.2-8 después de todo: revert/commit deben conocer todas las entidades.

---

## 8. Estimación orientativa

| Fase     | Esfuerzo | Comentario                                                              |
| -------- | -------- | ---------------------------------------------------------------------- |
| V1.2-0   | M        | Migración 4 tablas + LiveScope + relaciones sandbox-aware + factories. |
| V1.2-1   | S        | Servicio estático + shared prop.                                       |
| V1.2-2   | M        | Controller + composable + banner + diálogo mode.                      |
| V1.2-3   | M        | Grupos en index + sección + proyección con toggle.                     |
| V1.2-4   | L        | Refactor ProjectionService por scope + controller + vistas.           |
| V1.2-5   | L        | Deudas sandbox (CRUD + payoff + sección + estrategias).               |
| V1.2-6   | M        | Aportes sandbox + fixes de checks en Goals.                           |
| V1.2-7   | L        | Dashboard completo (cards + deudas + metas + chart overlay).           |
| V1.2-8   | M        | Revert/commit transaccional sobre 4 entidades.                       |
| V1.2-9   | S–M      | Edge cases y pulido.                                                   |

**Total:** ~8–9 días de trabajo concentrado (el plan original, sin deudas ni
metas, estimaba 4–5). V1.2-4, V1.2-5 y V1.2-7 son los días más caros.

---

## 9. Riesgos y mitigaciones

| Riesgo                                                                 | Mitigación                                                                                                      |
| ---------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| Fuga de filas sandbox en vistas reales (scope olvidado)                 | `LiveScope` global + helpers `withoutSandboxScope()`/`sandbox()` + tests por modelo; binding real 404 sandbox.  |
| Relaciones `hasMany` ocultan filas sandbox a su propio padre           | Relaciones sandbox-aware en `Debt`/`RecurringTransaction` (§3.4) + tests de accessors; tabla de puntos ciegos §3.3. |
| `regenerateForUser` real borra/regenera filas sandbox por error         | LiveScope protege el delete y la query de templates; tests de aislamiento en V1.2-4 y V1.2-9 (incl. CLI).       |
| `sort_order` duplicado en generación sandbox                            | `nextSandboxSortOrder()` específico (§5.2).                                                                     |
| `Guardar` deja huérfanos si falla a mitad                              | Todo en `DB::transaction`; test con mock que lanza a mitad.                                                      |
| FK 500 al borrar padres con hijos sandbox ocultos por el scope          | Checks con `withoutGlobalScope` (goals §3.3); orden children→parents en revert.                                 |
| Usuario confunde "Modo Simulación" (toggle) con "Revertir"             | Copy claro; el toggle no borra nada (localStorage); solo Revertir/Guardar mutan, con confirm.                   |
| Snapshot físico esperado (congelar lo real durante la simulación)       | Documentado en UI: "los cambios reales que hagas durante la simulación se quedan". v3 si surge.                 |
| `hasSandbox` = 4 exists por request                                     | Índices compuestos; early-exit por orden (movement primero es el más común).                                    |
| Performance con escenarios grandes (100+ cuotas)                        | Sandbox único + transacciones cortas + índices. Escala personal: no se espera problema.                          |
| Scope creep (más entidades simuladas, comparación, planes)              | Fuera de alcance explícito (§2); cada adición futura repite este patrón.                                        |

---

## 10. Próximos pasos inmediatos

1. ~~Verificar que `is_sandbox` no colisiona con columnas existentes~~ —
   verificado en esta revisión: no existe en ninguna de las 4 tablas.
2. ~~Confirmar que `ProjectionService` puede refactorizarse sin romper tests~~ —
   verificado: el refactor es aditivo (parámetro default + 2 métodos nuevos);
   los tests de `ProjectionTest`/`GenerateProjectionsTest` no cambian.
3. Empezar por Fase V1.2-0.

---

## 11. Decisiones confirmadas

- **Sandbox único**, de a un escenario a la vez. No hay entidad `Scenario`.
  Todas las filas `is_sandbox=true` del usuario = el sandbox activo.
- **`is_sandbox` en las 4 tablas** (`movements`, `recurring_transactions`,
  `debts`, `goal_contributions`) — decisión del usuario: deudas simuladas y
  aportes simulados a metas ENTRAN en v1.2.
- **`is_sandbox` ortogonal a `is_projected`**; las cuatro combinaciones son
  válidas. Las cuotas auto-generadas se identifican por `source='recurring'
  AND is_projected=true` (como el código real), NO por `recurring_id`.
- **`LiveScope` global** sobre los 4 modelos; opt-in `withoutSandboxScope()`.
  Relaciones `Debt::movements()`/`RecurringTransaction::movements()`
  sandbox-aware; checks de Goals ven aportes sandbox.
- **El backend fuerza `is_sandbox=true`** en cada write sandbox; el cliente
  no lo envía. FormRequests existentes reutilizados por composición; solo
  nuevo request para aportes sandbox (fecha futura).
- **Sin snapshot físico del estado real**. El sandbox es una capa aditiva;
  revertir borra filas sandbox, no restaura el pasado.
- **"Guardar" SIEMPRE promueve a real** (transaccional, 7 pasos §3.8). No hay
  "plan archivado" (v3).
- **Aportes sandbox con fecha futura se descartan al guardar** (§3.10):
  eran plan, no dinero. Las metas solo se completan al guardar
  (`syncCompletion`), nunca en simulación.
- **No se simula mutando entidades reales**: para "qué tal si Falabella
  sube a 250" se crea una template/deuda sandbox paralela.
- **Modo Simulación es UI-only** (localStorage); solo Revertir/Guardar
  mutan datos. `hasSandbox` es shared prop de Inertia — sin endpoint status.
- **Movimientos no mezcla** filas sandbox en la lista real (secciones
  separadas); el toggle "Incluir simulación" vive en Dashboard y Proyección.
- **Deudas sandbox sin página Show**; estrategias incluyen deudas sandbox
  con `include_sandbox=1`.
- **Rutas en español** (`simulacion.*`), binding manual en endpoints
  sandbox, toasts `Inertia::flash` + `back()`, Wayfinder regenerado.
- **Plan independiente de v1.1 (PWA)**: ambos se pueden implementar en
  paralelo o en el orden que el usuario prefiera.
