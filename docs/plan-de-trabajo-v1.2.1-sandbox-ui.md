# Plan de trabajo v1.2.1 — Caja Diaria (Sandbox UI: wiring de creación y Dashboard)

> Complemento de **v1.2** (Simulación de escenarios con sandbox). **Sin cambios
> de backend**: las fases V1.2-0 a V1.2-9 están completas (482 tests en verde),
> pero la UI nunca pudo crear filas sandbox de movimientos, recurrentes y
> deudas — solo los aportes de metas funcionan. Este plan cierra ese gap.

## Contexto del gap (verificado contra el código, 2026-09-14)

- El banner de simulación no tiene acciones de creación; por decisión de
  diseño queda **SOLO con Guardar/Revertir** (como está hoy).
- Los botones "Nuevo X" de Movimientos/Recurrentes/Deudas abren su diálogo
  **siempre en modo real**: el `mode` se deriva de `editingX?.is_sandbox`, que
  es `null` al crear → el POST va al endpoint real.
- **Chicken-and-egg**: las secciones sandbox y el toggle del Dashboard se
  gatean por `hasSandbox` (existencia de filas), que nunca llega a `true`
  desde la UI (salvo aportes de metas).
- **UX trap**: con el banner activo ("los cambios no afectan tu balance
  real"), "Nuevo movimiento" crea un movimiento REAL.

## Decisiones de diseño (usuario, 2026-09-14)

1. Toggle "Modo Simulación" en el header (ya existe) — **sin cambios**.
2. Banner SOLO Guardar/Revertir con el texto actual (ya existe) — **sin
   cambios**.
3. Creación en las páginas respectivas (patrón `contributionMode` de Metas,
   `Metas/Index.vue:49`): **crear respeta el modo; editar respeta la fila**.
   Los botones "Nuevo X" cambian de label y estilo con el modo activo e
   incluyen la palabra "simulado".
4. Dashboard: "Incluir simulación" **auto-ON** cuando el modo está activo y
   `hasSandbox`; **OFF simétrico** al desactivar el modo. Toggle manual
   intacto. Modelo visual: simulado protagonista + `Real: S/ X` de subtexto
   (como está implementado).
5. Diferenciadores visuales de filas: ya existen (badges ámbar "Simulado",
   secciones aparte) — **sin cambios**.

**Semántica común:** editar una fila real con el modo activo sigue siendo
real (endpoint real); editar una fila sandbox siempre sandbox. Para crear
algo real con el modo activo: apagar el modo primero.

**Stack:** solo `resources/js` (Vue 3 + TS). Cero migraciones, cero rutas
nuevas, cero dependencias. Archivos a tocar: 5 (`Movimientos/Index.vue`,
`Recurrentes/Index.vue`, `Deudas/Index.vue`, `GoalCard.vue`, `Dashboard.vue`).

---

## Fase V1.2.1-1 — Wiring de creación: "crear respeta el modo"

### Movimientos/Index.vue

1. Importar `useSandbox` → `modeActive`.
2. Computed `dialogMode`: editando → `editingMovement.is_sandbox ? 'sandbox'
   : 'real'`; creando → `modeActive ? 'sandbox' : 'real'`.
3. `:mode="dialogMode"` (hoy línea 643, derivado solo de `editingMovement`).
4. Botón "Nuevo movimiento" (líneas 306–313): con modo activo → label
   **"Nuevo movimiento simulado"** + variante ámbar (outline con tokens
   ámbar existentes: `border-amber-300 bg-amber-50 text-amber-800` y
   equivalente dark). El atajo `n` (mismo `openCreate`) hereda el
   comportamiento.

### Recurrentes/Index.vue

1–3. Ídem con `editingTemplate` (hoy línea 344).
4. Botón "Nueva plantilla" (línea 185) → **"Nueva plantilla simulada"** +
   ámbar con modo activo.
5. `regenerateProjections()` (líneas 132–140): con modo activo postea a
   `simulacion.recurrentes.regenerate` (regenera solo scope sandbox); sin
   modo, a `recurrentes.regenerate` como hoy.

### Deudas/Index.vue

1–3. Ídem con `editingDebt` para `DebtDialog` (hoy línea 209).
4. Botón "Nueva deuda" (líneas 111/148) → **"Nueva deuda simulada"** + ámbar
   con modo activo.
5. `PayoffDialog` (línea 217) **SIN cambios**: liquidar es edit-semantics
   (respeta `is_sandbox` de la deuda objetivo).
6. El requisito de categoría de préstamos en Preferencias aplica igual para
   deudas sandbox (alert existente en `DebtDialog`).

### Metas (GoalCard.vue)

1. El flujo de creación ya funciona (`contributionMode`,
   `Metas/Index.vue:49`). Solo el botón "Registrar aporte" →
   **"Aporte simulado"** + ámbar con modo activo (consistencia con las otras
   páginas; wording del plan v1.2 original §3.11). `GoalCard` consume
   `useSandbox` directamente (el estado `modeActive` es compartido a nivel
   módulo).

### Criterios de aceptación

- [ ] Con el modo activo, crear "Préstamo recibido 5000 hoy" desde
      Movimientos → toast, fila en sección "Simulación" con badge,
      `hasSandbox=true`, balance real intacto.
- [ ] Con el modo activo, "Nueva plantilla simulada" (500 × 12) → 12 cuotas
      sandbox visibles en Proyección con el toggle ON; proyección real
      intacta.
- [ ] Con el modo activo, "Nueva deuda simulada" → card en "Deudas
      simuladas" (factor, próxima cuota) + desembolso/cuotas en sección
      "Simulación".
- [ ] "Regenerar proyecciones" con modo activo no toca proyecciones reales.
- [ ] Editar/borrar filas sandbox existentes sigue funcionando (endpoints
      sandbox por `is_sandbox`, ya implementado).
- [ ] Con el modo inactivo, cada página es byte-compatible con hoy
      (crear = real).

**Commits:** `feat: sandbox mode-aware movement creation`,
`feat: sandbox mode-aware recurring creation and regenerate`,
`feat: sandbox mode-aware debt creation`,
`feat: simulated label on goal contribution button`.

---

## Fase V1.2.1-2 — Dashboard auto-ON

### Dashboard.vue (ya importa `useSandbox`, línea 117)

1. Agregar `modeActive` al destructure.
2. `onMounted`: si `modeActive && hasSandbox && !includeSandbox` →
   `toggleSandboxSimulation(true)` (reusa el helper existente, conserva el
   mes seleccionado).
3. `watch(modeActive)`:
   - transición a ON con `hasSandbox` y sin param →
     `toggleSandboxSimulation(true)`;
   - transición a OFF con param presente → `toggleSandboxSimulation(false)`
     (cleanup simétrico: el Dashboard refleja el modo).
4. Toggle manual intacto: el usuario puede apagarlo con el modo activo y
   nada lo re-fuerza (el watch solo reacciona a transiciones del modo).
5. Modelo visual de cards y chart: **SIN cambios** (ya implementado:
   protagonista simulado + `Real:` subtexto cuando difiere, línea ámbar
   dashed "Con simulación").

### Criterios de aceptación

- [ ] Activar el modo desde el header estando en el Dashboard → visita con
      `include_sandbox=1` sin tocar el switch; el escenario queda visible.
- [ ] Entrar al Dashboard con el modo ya activo y `hasSandbox` → ídem
      (`onMounted`).
- [ ] Apagar el modo → el Dashboard vuelve a valores reales (param
      removido).
- [ ] Toggle manual OFF con modo activo respeta la elección.
- [ ] Sin `hasSandbox` no hay auto-ON (el switch ni siquiera se renderiza).

### Edge cases documentados

- Guardar/Revertir desde el Dashboard: `back()` puede dejar
  `include_sandbox=1` en la URL con `hasSandbox=false` → los valores
  simulados son idénticos a los reales (display igual, sin subtextos porque
  no difieren). El modo se apaga (`exit()` en `useSandbox`); la próxima
  visita ya no lleva param.

**Commit:** `feat: dashboard include_sandbox auto-on with simulation mode`.

---

## Fase V1.2.1-3 — Hints de discoverability + verificación final

### Secciones con empty-state cuando hay modo pero no filas

El `v-if` de cada sección pasa de `sandboxX.length > 0` a
`sandboxX.length > 0 || modeActive`, mostrando el header + hint **"Lo que
crees con el modo activo aparecerá acá"**:

- Movimientos "Simulación" (línea 515)
- Recurrentes "Plantillas simuladas" (línea 265)
- Deudas "Deudas simuladas" (línea 188)

Esto ataca el problema original de discoverability ("no sé cómo se hace una
simulación"): la página indica dónde va a aterrizar lo simulado antes de que
exista.

### Verificación final

1. `php artisan test --compact` — suite completa en verde (sin tocar tests
   existentes; no hay cambios de backend).
2. `npm run types:check` (vue-tsc).
3. `npm run lint:check` + `npm run format:check`.
4. `npm run build`.
5. Walkthrough manual end-to-end del flujo del plan v1.2 §1: entrar en modo
   → armar préstamo 5000 × 12 + deuda simulada + aportes a meta → observar
   Dashboard (auto-ON), Proyección, Deudas, Metas → Revertir; repetir →
   Guardar como real.

### Criterios de aceptación

- [ ] Con modo activo y sin escenario, las tres páginas muestran su sección
      con hint.
- [ ] Suite completa en verde; types/lint/format/build limpios.
- [ ] Walkthrough manual completo sin errores.

**Commits:** `feat: sandbox section empty-state hints`,
`chore: sandbox ui final verification`.

---

## Fuera de alcance

- Botones de creación en el banner (opción A, descartada).
- Cambios de backend de cualquier tipo (todo existe y está testeado).
- Tests browser/E2E nuevos (no hay infraestructura de tests frontend; no se
  agregan dependencias sin aprobación).
- Página Show para deudas sandbox, escenarios paralelos, overrides de
  entidades reales (todo ya definido fuera de alcance en v1.2/v3).
