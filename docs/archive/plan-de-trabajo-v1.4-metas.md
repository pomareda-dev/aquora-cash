# Plan de trabajo v1.4 — Metas

> Módulo nuevo basado en `docs/planes-futuros.md`.
> Cada fase termina en un **hito revisable** (test o checklist manual) para
> avanzar y validar de a una fase a la vez.
> Documento hermano: [`plan-de-trabajo-v1.3-deudas.md`](./plan-de-trabajo-v1.3-deudas.md).
>
> **Revisión 2 (post-v1.3):** el plan original se escribió antes de implementar
> Deudas. Esta revisión lo alinea con las convenciones que quedó usando el
> código real tras v1.3 (PR #4–#7): componentes de frontend extraídos por
> módulo (`components/debts/*`), autorización manual con `abort(403)`, toasts
> con `Inertia::flash`, destroy protegido con 409, `Collapsible` en lugar de
> un accordion que no existe instalado, rutas vía `@/routes/*`, y sin
> columnas (`color`, `sort_order`) que el frontend nunca iba a usar (deudas no
> las necesitó).

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue). Sin dependencias nuevas.

---

## 0. Dudas aclaradas (de `planes-futuros.md`)

### 0.1 ¿Cómo se relaciona una meta con los movimientos?

Respuesta corta: **los aportes a una meta NO deben ser movimientos**. Se modelan
en una tabla propia (`goal_contributions`) que referencia directamente a la meta
(`goal_id`), y el progreso es la suma de esos aportes. El formulario de aporte
obliga a elegir la meta, así que la referencia al "ítem en particular" queda
garantizada por diseño, no por convención.

Descarté las alternativas con evidencia del código actual:

- **Agregar `goal_id` a `movements`** (estilo `recurring_id`/`debt_id`): obliga
  a decidir un signo para el movimiento (ver 0.2), y cualquier edición/eliminación
  del movimiento distorsiona el progreso de la meta. Si algún día se quiere
  *trazabilidad* con un movimiento real, se puede agregar
  `movement_id nullable` a `goal_contributions` — pero es un adorno, no la fuente
  de verdad.
- **Solo categoría "Meta" en movimientos**: la categoría agrupa, pero no
  identifica la meta; con dos metas activas no sabes a cuál fue cada aporte.

### 0.2 ¿El dinero apartado a una meta es ingreso o gasto?

**Ni ingreso ni gasto: es "apartado" (earmark).** Un movimiento con signo dice
que el dinero *entró* o *salió* de tus cuentas; apartar no mueve dinero — sigue
en BCP1 u otra cuenta, solo cambia su etiqueta ("está reservado").

Evidencia verificada contra `DashboardController@index` actual:

- Si lo registras como **ingreso (`amount > 0`)**: infla el card "Ingresos del
  mes" aunque no entró dinero nuevo.
- Si lo registras como **gasto (`amount < 0`)**: infla "Gastos del mes",
  distorsiona los presupuestos por categoría (kind `expense` con límite) y baja
  el **saldo real**, rompiendo la conciliación con la suma de cuentas
  (`$difference = totalAccounts - realBalance`).

Por eso el modelo correcto es:

1. **Fuente de verdad:** `goal_contributions` (fecha, monto positivo, nota).
2. **Métrica derivada anti-sobregiro:**
   `Disponible real = realBalance − Σ apartado en metas activas`.
   Se muestra en el dashboard y en la página de metas, y es lo que resuelve el
   miedo de "tocar el dinero ahorrado": ves cuánto queda realmente libre sin
   falsear ningún balance. La **conciliación no cambia** (los aportes no son
   movimientos; el apartado vive "encima" del saldo real).
3. **Gasto final:** cuando compras lo de la meta, es un gasto normal en la
   categoría que corresponda. La meta se cierra; no hay doble conteo.

### 0.3 ¿Una meta necesita categoría o configuración? (nueva)

No. Las deudas generan movimientos y por eso terminaron exigiendo una categoría
de préstamo configurada en Preferencias (`debt_category_id`, corrección de
v1.3 PR #5–#6). Los aportes NO son movimientos (§0.1): no hay nada que
categorizar ni configurar. La página de metas no lleva banner de configuración
ni entrada en Preferencias.

---

## 1. Objetivo

Que el usuario pueda:

1. Crear una meta con nombre y monto total (fecha objetivo opcional).
2. Registrar aportes (fecha ≤ hoy, monto, nota opcional) contra una meta
   específica.
3. Ver las metas activas en cards con barra de progreso; historial de aportes
   desplegable dentro del card (sin página individual).
4. **Eliminar una meta SOLO si no tiene ningún aporte** (caso "creada por
   error"). Si tiene aportes, el botón eliminar se bloquea con tooltip
   (espejo del patrón de deudas).
5. Ver "Disponible real = saldo real − apartado en metas activas" en la página
   de metas y en el dashboard, junto al card resumen de metas.

---

## 2. Alcance

**Dentro de v1.4:**

- Tablas `goals` y `goal_contributions`.
- CRUD de metas **sin página detalle** (`resource` except `create`/`edit`/`show`)
  + aportes anidados (`store`/`destroy`).
- Cierre/reapertura automática por progreso (`Goal::syncCompletion`).
- Destroy de meta protegido: 409 si tiene aportes.
- Página índice con cards, progreso, días restantes (o "Vencida"), historial
  de aportes desplegable y strip "Apartado / Disponible real".
- Métrica "Disponible real" y card resumen en dashboard.
- Toasts de feedback en cada acción (`Inertia::flash` + Sonner).
- Tests Pest por fase: `GoalTest` (modelo), `GoalControllerTest` (feature) y
  actualización de `DashboardTest`.

**Fuera de v1.4 (diferido):**

- Retiros parciales de una meta (aportes negativos).
- **Editar un aporte** (`update`): borrar y volver a aportar cubre el caso.
- **Aportes con fecha futura** (planificados): ligados a la proyección de ritmo
  de ahorro (ver §3.8).
- **Color y orden manual** (`color`, `sort_order` + `metas.reorder`): deudas no
  lo necesitó y el drag existente es solo para tablas (`ResponsiveTable`), no
  cards. Se agregan después sin migración destructiva si piden.
- Vínculo `movement_id` en aportes (trazabilidad con el libro mayor).
- Proyección "¿llego a la fecha?" con ritmo de ahorro sugerido.
- Sub-metas o metas compartidas entre usuarios.

---

## 3. Decisiones de arquitectura

1. **Aportes ≠ movimientos** (ver §0): no tocan `movements` ni los balances.
   La relación meta↔ítem es la FK `goal_id` en `goal_contributions`.
2. **Cierre automático explícito, sin observer:** método de modelo
   `Goal::syncCompletion(): void` — recalcula `completed_at` desde la base
   (`Σ aportes ≥ target → now()`, `Σ < target → null`). Se llama dentro de
   `DB::transaction` desde: aporte `store`, aporte `destroy`, y `GoalController@update`
   cuando cambia `target_amount`. El codebase no usa observers; el estilo es
   explícito en transacción (patrón `DebtController`).
   *Corrección sobre el draft original:* aportar a una meta completada **no la
   reabre** (la suma solo crece, sigue ≥ target). Reabren: borrar un aporte que
   baja la suma del objetivo, o subir el `target_amount` por encima del progreso.
3. **Progreso con una query:** índice con `withSum('contributions', 'amount')`
   + `withCount('contributions')` (para `can_delete`) + `with` de aportes
   ordenados desc para el historial del card — sin N+1 (patrón
   `DebtController@index` con `real_movements_count`).
4. **Disponible real derivado, no guardado:** `Goal::apartadoAmount(int $userId): float`
   = Σ `goal_contributions.amount` vía `whereHas('goal')` sobre metas activas
   (`completed_at null`) — una sola query. Lo reutilizan `GoalController@index`
   y `DashboardController@index`: `availableReal = round(realBalance − apartado, 2)`.
5. **Historial desplegable con `ui/collapsible`** (ya instalado; no existe
   componente accordion en el proyecto). En lugar de página individual, el card
   despliega sus aportes porque la data por meta es chica. Si más adelante una
   meta crece, se promueve a página propia sin romper el modelo.
6. **Autorización manual por ownership** (`abort(403)` si `user_id` distinto),
   en `update`/`destroy` de meta y en los endpoints de aportes — convención del
   codebase (`DebtController`, `CategoryController`), sin Policies.
7. **Destroy seguro (espejo de deudas):** rechazar con `409` si la meta tiene
   al menos un aporte. Para borrar una meta con aportes: borrar los aportes
   primero desde el historial del card. El `cascadeOnDelete` de la FK queda
   solo como red de seguridad, igual que en deudas.
8. **Aportes honestos: `amount > 0` y `date ≤ hoy`.** Un aporte con fecha
   futura inflaría el progreso y deflactaría el "Disponible real" antes de que
   el dinero esté apartado. Los aportes planificados van de la mano de la
   proyección de ritmo (diferido).
9. **Frontend por módulo:** `resources/js/components/goals/` con
   `GoalCard.vue`, `GoalDialog.vue`, `ContributionDialog.vue` y `types.ts`
   (patrón de `components/debts/`). Barra de progreso con `div` + clases de
   Tailwind como en el Dashboard (no hay componente `Progress` instalado).
10. **Feedback:** `Inertia::flash('toast', ['type' => 'success', 'message' => ...])`
    en cada acción exitosa; el `Toaster` (Sonner) ya está en el layout.
11. **Orden del índice:** `created_at desc`, activas arriba y completadas en
    sección "Completadas" abajo (espejo de `Deudas/Index.vue` con
    activas/historial). Sin `sort_order` ni `metas.reorder` en v1.4.
12. **Fechas en aportes:** hoy por defecto en el diálogo (mismo criterio que el
    fix del diálogo de movimientos).

---

## 4. Modelo de datos

```php
// migration: create_goals_table
Schema::create('goals', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->decimal('target_amount', 12, 2);
    $table->date('target_date')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});

// migration: create_goal_contributions_table
Schema::create('goal_contributions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
    $table->date('date');
    $table->decimal('amount', 12, 2); // siempre positiva (validar en Request)
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index(['goal_id', 'date']);
});
```

Sin `color` ni `sort_order` (ver §2 "Fuera de v1.4").

Relaciones: `User::goals()` (hasMany), `Goal::user()` (belongsTo),
`Goal::contributions()` (hasMany), `GoalContribution::goal()` (belongsTo).
Aportes se crean vía `$goal->contributions()->create([...])`.

Modelo `Goal`:

- `$fillable`: `name`, `target_amount`, `target_date`, `completed_at`.
- `casts()`: `target_amount` → `decimal:2`, `target_date` → `date`,
  `completed_at` → `datetime`.
- Accessors (snake_case, convención de `Debt`):

  - `progress_amount`: Σ `contributions.amount` (consulta lazy; en el índice
    usar `contributions_sum_amount` que carga el `withSum`).
  - `percent`: `(int) min(100, round(progress / target × 100))` — `target` > 0
    está garantizado por validación; defensivo igualmente.
  - `remaining_amount`: `max(0, target − progress)`.
  - `is_complete`: `completed_at !== null`.
- `syncCompletion(): void` (ver §3.2). Compara con redondeo a 2 decimales.
- Static `apartadoAmount(int $userId): float` (ver §3.4).

Modelo `GoalContribution`:

- `$fillable`: `date`, `amount`, `notes`.
- `casts()`: `date` → `date`, `amount` → `decimal:2`.

Factories:

- `GoalFactory`: nombre realista ("Laptop nueva", "Viaje a Bariloche",
  "Fondo de emergencia"), `target_amount` 500–10000, `target_date` null o
  futura, `completed_at` null. Estado `completed()` que setea `completed_at`
  (espejo del estado `closed()` de `DebtFactory`).
- `GoalContributionFactory`: `date` pasada (≤ hoy), `amount` positivo
  50–500, `notes` a veces null.

---

## 5. Fases

### Fase V1.4-1 — Modelo de datos: metas y aportes

- `php artisan make:model Goal --migration --factory --no-interaction`
- `php artisan make:model GoalContribution --migration --factory --no-interaction`
- Migraciones de §4 + relaciones (`User::goals`) + casts + accessors +
  `syncCompletion` + `apartadoAmount`.
- Factories de §4 (con estado `completed()`).
- Tests de modelo estilo `tests/Feature/DebtTest.php` en
  `tests/Feature/GoalTest.php`: relaciones, casts, accessors (progreso, % con
  meta cumplida y excedida, restante no negativo), `syncCompletion` (completa
  al alcanzar, se mantiene completada al aportar de más, reabre al caer bajo
  el objetivo), `apartadoAmount` (ignora metas completadas).

**Hito de revisión:** `php artisan test --compact --filter=Goal` verde y
`php artisan migrate:fresh --seed` OK.

### Fase V1.4-2 — CRUD backend de metas y aportes

- `php artisan make:controller GoalController --resource --no-interaction`
  (quedarán `index`, `store`, `update`, `destroy`) y
  `php artisan make:controller GoalContributionController --no-interaction`
  (`store`, `destroy`).
- Rutas siguiendo la convención real de `deudas`:

  ```php
  Route::resource('metas', GoalController::class)
      ->except(['create', 'edit', 'show'])
      ->parameters(['metas' => 'goal']);
  Route::post('metas/{goal}/aportes', [GoalContributionController::class, 'store'])
      ->name('metas.aportes.store');
  Route::delete('metas/{goal}/aportes/{contribution}', [GoalContributionController::class, 'destroy'])
      ->name('metas.aportes.destroy');
  ```

- Form Requests: `GoalRequest` (compartido store/update, patrón
  `CategoryRequest`): `name` requerido, `target_amount` numérico > 0,
  `target_date` fecha opcional. `StoreGoalContributionRequest`: `amount`
  numérico > 0, `date` requerida + `before_or_equal:hoy`, `notes` opcional
  `max:1000`.
- `index`: `withSum` + `withCount` + `with(['contributions' => desc por date,
  id])` + map por meta (id, nombre, montos, %, restante, `can_delete`,
  `days_to_target` con signo para detectar "Vencida", aportes mapeados) +
  summary (`apartado`, `available_real`) con `Goal::apartadoAmount` y
  `Movement::realBalance`.
- `store` / `update` de meta: `update` recalcula con `syncCompletion()` si
  cambió `target_amount` (transacción).
- Aporte `store`: transacción — crear aporte + `$goal->syncCompletion()`.
  Aporte sobre meta completada: permitido (la meta sigue completada).
- Aporte `destroy`: transacción — borrar + `syncCompletion()` (puede reabrir).
  El aporte se resuelve **scoped a la meta del path** (404 si el aporte no
  pertenece a esa meta) tras validar ownership de la meta (403).
- `destroy` de meta: **rechazar con 409** si `contributions()->exists()`;
  si no hay aportes, borrado directo (sin transacción necesaria: una sola
  tabla).
- Toasts de éxito en cada acción (mensajes tipo "Meta creada correctamente.",
  "Aporte registrado correctamente.").
- Tests feature en `tests/Feature/GoalControllerTest.php`: guest redirect;
  props del índice; store válido; 422 (target ≤ 0, name vacío, fecha inválida,
  aporte ≤ 0, aporte con fecha futura); update; destroy 204 sin aportes /
  409 con aportes; 403 sobre meta ajena; ciclo completo vía endpoints:
  aportar hasta completar → meta completada → borrar un aporte → reabierta;
  subir target con progreso existente → reabierta.

**Hito de revisión:** feature tests verdes; ciclo completo crear meta →
aportar → completar → reabrir verificable en tinker.

### Fase V1.4-3 — Página Metas (índice con cards + aportes)

- Componentes en `resources/js/components/goals/`:
  - `GoalCard.vue`: nombre, barra de progreso (`div` + clases, tope 100 %,
    color según avance como el Dashboard), monto acumulado/total, % real
    visible aunque supere 100, días restantes o badge "Vencida" (fecha
    objetivo pasada y meta activa), badge "Completada" en la sección de
    completadas; `Collapsible` "Aportes (n)" con la lista (fecha, monto, nota)
    y borrar aporte con diálogo de confirmación; botón eliminar deshabilitado
    + `Tooltip` cuando hay aportes ("Tiene aportes registrados. Borra los
    aportes primero.").
  - `GoalDialog.vue`: crear/editar meta (nombre, monto, fecha objetivo
    opcional).
  - `ContributionDialog.vue`: monto, fecha (hoy por defecto), nota opcional.
  - `types.ts`: tipo `GoalData` (patrón de `components/debts/types.ts`).
- `resources/js/pages/Metas/Index.vue`: orquestación igual a
  `Deudas/Index.vue` — computadas activas/completadas, empty state ("No hay
  metas registradas"), estado de diálogos, confirmación de borrado de meta.
  Strip superior con "Apartado en metas: S/ X · Disponible real: S/ Y" (datos
  del índice); disponible negativo en rojo.
- Sidebar: entrada "Metas" en `resources/js/components/AppSidebar.vue` después
  de Deudas (icono lucide `Target`, `href: metas.index()`).
- Wayfinder: imports desde `@/routes/metas` (como hace `Deudas/Index.vue` con
  `@/routes/deudas`); correr `npm run dev` para regenerar los tipos.

**Hito de revisión:** recorrido visual — crear meta, aportar, ver la barra
moverse y completarse; borrar un aporte y ver la meta reabrirse; eliminar
bloqueado con aportes y habilitado sin ellos; el strip "Disponible real"
cuadra con el cálculo a mano.

### Fase V1.4-4 — "Disponible real" y cards en dashboard + pulido

- `DashboardController@index`:
  - `$apartado = Goal::apartadoAmount($userId)`;
  - `goalsSummary`: `apartado`, `available_real` =
    `round(realBalance − apartado, 2)`, `active_count`;
  - `goalsOverview`: metas activas mapeadas (id, nombre, target, progreso,
    %, restante, `target_date`, días) — convive con `debtsOverview` sin
    conflicto (v1.3 ya aplicada).
- `resources/js/pages/Dashboard.vue`: card "Metas" (resumen apartado +
  disponible real + mini barras de las metas activas) junto al card de
  deudas; "Disponible real" en rojo si es negativo; sin metas activas →
  estado vacío discreto.
- Actualizar `DashboardTest`: props `goalsSummary`/`goalsOverview` en el
  assert de estructura + caso numérico (p. ej. realBalance 1300, apartado
  activo 300 → `available_real` 1000; meta completada **no** resta).
- Opcional: 2 metas demo en `CajaDiariaDemoSeeder` (una al 60 %, una
  completada) para ver el card con datos.
- Pulido: `vendor/bin/pint --dirty --format agent`, `npm run lint`,
  `npm run format`, `npm run types:check` y suite completa
  `php artisan test --compact`.

**Hito de revisión:** con datos reales, `Disponible real = Saldo real −
Apartado` cuadra a mano; suite completa verde.

---

## 6. Orden recomendado y dependencias

`V1.4-1 → V1.4-2 → V1.4-3 → V1.4-4`. Independiente de v1.3 Deudas
([`plan-de-trabajo-v1.3-deudas.md`](./plan-de-trabajo-v1.3-deudas.md)) salvo por
compartir el dashboard en la Fase V1.4-4 (las tarjetas conviven sin conflicto).

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| "Apartado" alto hace ilusorio el saldo libre | El card muestra ambos números juntos (saldo real **y** disponible), nunca uno solo. |
| Aporte con fecha futura infla progreso y deflacta el disponible | Validación `before_or_equal:hoy` + test 422. |
| Borrar meta con aportes pierde el historial | 409 + botón deshabilitado con tooltip; borrar aportes primero (espejo de deudas). |
| Meta completada "olvida" el apartado | Decisión de diseño documentada (§0.2): al completarse, sus aportes dejan de restar el disponible — ya es dinero para gastar. |
| Progreso supera el target (sobre-aporte) | Barra con tope 100 % y % real visible; `remaining_amount` nunca negativo. |
| Disponible real negativo (aportes > saldo real) | Mostrarlo honesto y en rojo; es señal de datos desactualizados, no un bug. |
| Edición de aporte inexistente | Borrar + volver a aportar (documentado); `update` diferido. |

---

## 8. Estimación orientativa

| Fases | Estimación |
| --- | --- |
| 4 fases (V1.4-1 a V1.4-4) | ~2 sesiones de trabajo |

## 9. Próximos pasos inmediatos

1. Empezar **Fase V1.4-1** (modelo de metas y aportes) y revisar su hito.
2. Nota: las tarjetas de dashboard conviven con las de v1.3 Deudas si esa
   versión está aplicada (ya lo está).
