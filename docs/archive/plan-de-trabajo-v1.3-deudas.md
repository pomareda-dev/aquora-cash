# Plan de trabajo v1.3 — Deudas

> Módulo nuevo basado en `docs/planes-futuros.md`.
> Cada fase termina en un **hito revisable** (test o checklist manual) para
> avanzar y validar de a una fase a la vez.
> Documento hermano: [`plan-de-trabajo-v1.4-metas.md`](./plan-de-trabajo-v1.4-metas.md).

**Stack:** el mismo de v1 (Laravel 13 · Inertia 3 · Vue 3 + TypeScript ·
Tailwind · shadcn-vue). Sin dependencias nuevas.

---

## 1. Objetivo

Que el usuario pueda:

1. Crear una deuda ingresando: monto prestado, fecha de desembolso, cuota
   (fija), número de cuotas y fechas de pago (tantas como cuotas).
2. Que el sistema genere los movimientos vinculados: desembolso (`+principal`)
   y una cuota (`−cuota`) por cada fecha, con categoría "Préstamo", marcados
   como proyectados si su fecha es futura.
3. Ver las deudas activas en cards con barra de progreso y badge del **factor de
   tasa derivado**.
4. Entrar al detalle de cada deuda: historial de pagos, cronograma, totales
   (prestado / a pagar / pagado / restante), factor ponderado entre sus deudas y
   comparativa avalancha vs. bola de nieve.
5. **Liquidar la deuda anticipadamente** (pago total, en ocasiones menor al
   restante total): cierra la deuda y elimina las cuotas proyectadas restantes.
6. Eliminar una deuda SOLO si no tiene ningún pago realizado (caso "creada por
   error"). Si tiene pagos, el botón eliminar se bloquea.
7. Ver un card resumen de deudas activas en el dashboard.

---

## 2. Alcance

**Dentro de v1.3:**

- Tabla `debts` y FK `debt_id nullable` en `movements`.
- CRUD de deudas con generación/regeneración transaccional de movimientos.
- Acción **payoff/liquidación anticipada**: crea un movimiento único vinculado,
  marca `closed_at`, elimina las cuotas proyectadas restantes.
- Validación estricta de `destroy`: solo si no hay pagos reales vinculados.
- Página índice con cards (progreso + badge factor) y página detalle.
- Servicio `DebtStrategy` (PHP puro) con orden avalancha/bola de nieve y factor
  ponderado, usando el **factor derivado** (ver §3).
- Card de deudas en dashboard.
- Tests Pest por fase (feature + unit del servicio).

**Fuera de v1.3 (diferido):**

- Motor de amortización (interés calculado cuando solo das la tasa).
- Deudas por cobrar (dinero prestado a terceros).
- Pagos parciales de una cuota (se marca toda la cuota; se puede con notas).

---

## 3. Decisiones de arquitectura

1. **Vínculo con movimientos:** FK `debt_id nullable` en `movements`
   (`->nullOnDelete()`, como `category_id`). El progreso = movimientos reales
   (`is_projected = false`) vinculados.
2. **Cronograma como JSON:** `payment_dates` se guarda como columna JSON
   (`list<string>` de fechas) en `debts`. La validación exige
   `count(payment_dates) === installments_count`.
3. **Tasa derivada, no input (P1 resuelta):** la tasa NO se ingresa. Se calcula
   como **factor derivado**:
   `factor = (installment_amount × installments_count) / principal_amount`
   (ej. `1.50` → badge "×1,50"). El badge y el orden avalancha usan este factor.
   No hay columna `rate`; es un accessor computado. Si falta desembolso o
   cuotas, `factor = 0` (se muestra "—").
4. **Generación de movimientos en transacción:** en `store`, dentro de
   `DB::transaction`: crear deuda + 1 movimiento desembolso + N movimientos de
   cuota. `is_projected = fecha > hoy` por cada uno. Descripción "Cuota {name}
   (k/n)". `sort_order` vía `Movement::nextSortOrder`.
5. **Update = regenerar solo proyectados:** al editar fechas/cuota/nº de
   cuotas, se borran los movimientos **proyectados** vinculados y se recrean;
   los reales (pagados) no se tocan. Mismo patrón que
   `RecurringTransactionController@regenerate`.
6. **Pagar anticipado (P3 resuelta):** acción `POST deudas/{debt}/payoff`:
   - Form Request con `amount > 0` (el monto acordado de liquidación).
   - Transacción: crear 1 movimiento vinculado fechado hoy, `is_projected =
     false`, monto negativo, descripción "Liquidación anticipada {name}".
   - Eliminar los movimientos proyectados restantes vinculados.
   - Setear `closed_at = now()`.
   Después, `destroy` sigue bloqueado: la deuda queda como historial.
7. **Destroy seguro (P3 resuelta):** Rechazar con `409` si existe al menos un
   movimiento real vinculado (`is_projected = false`). Solo deudas "sin pagos"
   pueden eliminarse; transacción opcional para borrar proyectados.
8. **Categoría "Préstamo":** se busca por nombre en las categorías del usuario;
   si no existe, `category_id = null` (el movimiento igual se crea).
9. **Progreso = cuotas pagadas / cuotas totales** (no montos). Deuda cerrada
   (`closed_at`) repone a 100 %.
10. **`DebtStrategy` como servicio puro:** entrada = colección de
    `{name, remaining, factor, installment}`; salida = orden avalancha (factor
    desc), orden bola de nieve (restante asc), y **factor ponderado**
    `Σ(restante × factor) / Σ restante`. Testeable con datasets, sin HTTP.

---

## 4. Modelo de datos

```php
// migration: create_debts_table
Schema::create('debts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->decimal('principal_amount', 12, 2);
    $table->date('disbursement_date');
    $table->decimal('installment_amount', 12, 2);
    $table->unsignedSmallInteger('installments_count');
    $table->json('payment_dates'); // list<string> Y-m-d
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});

// migration: add_debt_id_to_movements_table
Schema::table('movements', function (Blueprint $table) {
    $table->foreignId('debt_id')->nullable()->constrained()->nullOnDelete();
});
```

Relaciones y accessors en `Debt`:

- `user()`, `movements()` (hasMany).
- `rateFactor`: si `!$principal_amount` → `0`; si
  `(installment_amount × installments_count) / principal_amount` → factor.
- `totalToPay = installment_amount × installments_count`.
- `paidInstallments = movements()->actual()->count()`.
- `remaining = totalToPay − Σ(cuotas reales vinculadas)` (no negativo).
- `isActive = closed_at === null`.

En `Movement`: `debt()` (belongsTo). Agregar `debt_id` a `$fillable`.

---

## 5. Fases

### Fase V1.3-1 — Modelo de datos: deudas y vínculo con movimientos

- `php artisan make:model Debt --migration --factory --no-interaction`
- Migraciones de §4 + relaciones + accessors (incluido `rateFactor`).
- `DebtFactory` con estado válido (payment_dates coherentes con
  installments_count).
- Tests de modelo al estilo de `tests/Feature/ModelTest.php`: relaciones,
  casts, accessors (factor incluido), cierre (`closed_at`).

**Hito de revisión:** `php artisan test --compact --filter=Debt` verde y
`php artisan migrate:fresh --seed` OK.

### Fase V1.3-2 — CRUD backend de deudas (transaccional) + payoff

- `php artisan make:controller DebtController --resource --no-interaction`
- Rutas `deudas.*` siguiendo convención (`Route::resource('deudas', ...)`) +
  `POST deudas/{debt}/payoff`.
- Form Request `StoreDebtRequest` / `UpdateDebtRequest` / `PayoffDebtRequest`:
  `payment_dates` array, `count == installments_count`, fechas válidas;
  cuota/principal > 0. Payoff: `amount > 0`.
- `store`: transacción con deuda + movimientos (desembolso + cuotas).
- `update`: regenerar solo proyectados.
- `payoff`: transacción — movimiento único vinculado + borrar proyectados +
  `closed_at`.
- `destroy`: **rechazar con 409** si hay pagos reales vinculados; si no hay,
  borrar proyectados con transacción.
- Tests feature: validación 422 ante count-distinto; store crea 1+N movimientos;
  payoff cierra y elimina proyectados; destroy 409 con pagos / 204 sin pagos;
  update regenera proyectados y conserva reales.

**Hito de revisión:** feature tests verdes; crear/pagar/editar/borrar/payoff
verificable desde tinker/Postman y en la página Movimientos.

### Fase V1.3-3 — Página Deudas (índice con cards + payoff modal)

- `resources/js/pages/Deudas/Index.vue`:
  - Cards por deuda activa (o cerrada en historial): nombre, barra de progreso
    (cuotas pagadas/n o 100 % si cerrada), badge del factor (×1,xx), restante,
    próxima cuota (fecha + monto).
  - Modal crear/editar: inputs de `payment_dates` dinámicos, sincronizados con
    `installments_count` (agregar/quitar fechas).
  - **Botón eliminar deshabilitado** cuando hay pagos, con tooltip explicativo
    ("Tiene pagos registrados. Usa 'Liquidar deuda'.").
  - **Modal Liquidar deuda**: pide monto acordado (por defecto restante),
    explica que crea un único movimiento y cierra la deuda.
- Entrada en el sidebar/navegación (`deudas`).
- Wayfinder: usar imports desde `@/actions/.../DebtController` y `@/routes/deudas`
  (corre `npm run dev` para regenerar tipos).

**Hito de revisión:** recorrido visual — crear una deuda, ver card con barra,
marcar una cuota en Movimientos, la barra avanza; probar liquidar cerrada y
eliminar bloqueado/apto.

### Fase V1.3-4 — Detalle de deuda

- `DebtController@show` con totales y colecciones:
  - Historial de pagos (movimientos reales vinculados, desc), incluido el
    movimiento de liquidación si lo hay.
  - Cronograma (movimientos proyectados vinculados, asc) — vacio si cerrada.
  - Totales: prestado / a pagar / pagado / restante. Badge del factor.
- `resources/js/pages/Deudas/Show.vue` con esas secciones.
- Tests feature de `show`: sumas correctas con pagos parciales y proyectados.

**Hito de revisión:** con una deuda de prueba, verificar a mano que
pagado + restante = total a pagar, y el factor derivado coincide con tu
calculadora.

### Fase V1.3-5 — Avalancha vs. bola de nieve + factor ponderado

- `php artisan make:class Services/DebtStrategy --no-interaction`
- PHP puro: orden avalancha (factor desc), bola de nieve (restante asc), factor
  ponderado. Sin HTTP, sin base de datos.
- Tests unit con datasets (`tests/Unit`), incluyendo empates y una sola deuda.
- Sección comparativa en `Deudas/Show.vue` (y/o índice): orden recomendado por
  cada método sobre las deudas activas del usuario + el factor ponderado
  agregado.

**Hito de revisión:** con 2–3 deudas reales, verificar a mano el orden de cada
método y el factor ponderado en calculadora.

### Fase V1.3-6 — Card de deudas en dashboard + pulido

- `DashboardController`: agregar resumen de deudas activas (`closed_at null`):
  nombre, progreso % y próxima cuota próxima.
- Card correspondiente en `resources/js/pages/Dashboard.vue`.
- Actualizar `DashboardTest`.
- `vendor/bin/pint --dirty --format agent` y suite completa
  `php artisan test --compact`.

**Hito de revisión:** dashboard muestra el card; suite completa verde.

---

## 6. Orden recomendado y dependencias

`V1.3-1 → V1.3-2 → V1.3-3 → V1.3-4 → V1.3-5 → V1.3-6`.
La Fase 5 (estrategia) es independiente del frontend y puede paralelizarse con
3–4 si se quiere.
Referencia: v1.4 Metas (ver [`plan-de-trabajo-v1.4-metas.md`](./plan-de-trabajo-v1.4-metas.md))
comparte solo el dashboard; no hay otra dependencia cruzada.

---

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
| --- | --- |
| `payment_dates` JSON editable → cronograma inconsistente | Validación de count en Form Request + update regenera proyectados. |
| Factor derivado da 0 si faltan datos | Accessor devuelve 0 y se muestra "—" en badge; orden avalancha lo respeta. |
| Payoff sin borradón de proyectados | La transacción de payoff borra proyectados restantes; test lo comprueba. |
| El usuario edita movimientos vinculados a mano | Se muestran en Movimientos con nota de origen; si se eliminan, el progreso baja (comportamiento honesto, no corrupción). |
| Categoría "Préstamo" renombrada/eliminada | FK `nullOnDelete` y fallback `category_id = null` en generación. |

---

## 8. Estimación orientativa

| Fases | Estimación |
| --- | --- |
| 6 fases (V1.3-1 a V1.3-6) | ~2–3 sesiones de trabajo |

## 9. Próximos pasos inmediatos

1. Empezar **Fase V1.3-1** (modelo de deudas) y revisar su hito.
2. Al cerrar v1.3, arrancar v1.4
   ([`plan-de-trabajo-v1.4-metas.md`](./plan-de-trabajo-v1.4-metas.md)).
