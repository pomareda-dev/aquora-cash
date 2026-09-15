# Plan de trabajo — Caja Diaria

> Aplicación personal de registro de gastos y proyección financiera.
> Reemplaza el flujo actual basado en Google Sheets (`proyeccion-2026.xlsx`).

**Stack:** Laravel 13 · Inertia 3 · Vue 3 (Composition API + TypeScript) ·
Tailwind CSS · shadcn-vue

**Referencias previas:** ver `analisis-sistema-actual.md` para el detalle de cómo
funciona la planilla actual.

---

## 1. Objetivo

Construir un sistema sencillo que replique —y mejore— las capacidades actuales:

1. Registro diario de movimientos (ingresos/gastos) con fecha, descripción,
   categoría e importe con signo.
2. Balance acumulado por movimiento (equivalente a la columna E de la planilla).
3. Proyección del saldo en fechas futuras (movimientos proyectados + recurrentes).
4. Presupuesto por categoría con límite mensual y gasto acumulado.
5. Saldo de cuentas (bancos/billeteras) con conciliación contra el balance real.
6. Vistas por mes (equivalente a las hojas `Julio-26`, etc.) sin necesidad de
   copiar fórmulas ni arrastrar “Capital” a mano.

---

## 2. Alcance

### Dentro del alcance

- Aplicación **mono-usuario** (uso personal). Autenticación simple para proteger
  los datos.
- Una sola línea temporal de movimientos (real + proyectado + recurrente).
- CRUD de movimientos, categorías, cuentas y pagos recurrentes.
- Dashboard con métricas clave del mes y proyección.
- **Personalización de la app:** paleta de colores (8 opciones incluyendo
  rosado), foto de perfil, densidad de tablas, sección de inicio, día de inicio
  de semana y horizonte de proyección.
- **Moneda única: PEN (soles).** Sin multi-moneda ni conversiones.
- **Cuentas como snapshot manual de saldos**, sin vínculo movimiento↔cuenta (igual
  que en la planilla actual).
- **Despliegue local únicamente** en esta iteración.

### Fuera del alcance (fases futuras)

- Multi-usuario / cuentas compartidas.
- Sincronización automática con bancos (open banking).
- Presupuestos anuales o por quincena.
- Exportación a PDF/Excel (se puede añadir después fácilmente).
- **Gestión específica de la cuenta “Liquidación”** — el usuario tiene otros
  planes para ese fondo; por ahora solo se crea manualmente y se **excluye de la
  conciliación**.
- **Importación desde Excel** (descartado por el usuario: no se necesita migrar
  datos históricos; la Fase 8 original se reemplaza por personalización).
- Despliegue a producción (futuro: shared hosting bajo un subdominio nuevo de
  `pomareda.dev`, gestionado por el usuario).

---

## 3. Decisiones de arquitectura

### 3.1 Línea temporal unificada vs. hojas por mes

La planilla separa por hoja mensual y arrastrar “Capital” a mano. La app usa
**una sola tabla de movimientos** con columna `date`. El balance de apertura de
cualquier mes se **calcula** como la suma de movimientos reales anteriores al
inicio del mes. Esto elimina el rollover manual y permite reportes transversales.

### 3.2 Proyección = fecha futura

Un movimiento es **proyectado** si `date > hoy`. No hace falta un flag `is_projected`
separado: la fecha lo determina. La vista de proyección muestra todos los
movimientos (reales + futuros) ordenados por fecha con el balance acumulado.

### 3.3 Pagos recurrentes

Los pagos que en la planilla aparecen sin fecha (“Huancayo 22”, “Falabella 9”) se
modelan como **plantillas recurrentes** (`recurring_transactions`) que generan
movimientos proyectados para N meses hacia adelante. El usuario define una
plantilla (nombre, importe, categoría, día del mes, cantidad de meses) y la app
genera las filas proyectadas.

### 3.4 Cuentas: snapshot manual (decidido)

Las cuentas son un **snapshot manual de saldos**, independientes de los
movimientos. La conciliación es una verificación cruzada:
`SUM(saldos de cuentas) ≈ balance real actual`. Si hay descuadre, la app lo
marca.

**Confirmado por el usuario:** no se vincula cada movimiento a una cuenta (no hay
forma de automatizarlo por ahora). El flujo replica exactamente la planilla:
registrar movimientos por un lado, actualizar saldos de cuentas por otro,
conciliar como verificación.

### 3.5 Cálculo del balance

- **Opción A (simple):** calcular en PHP al cargar la vista (suma acumulada
  ordenada por fecha). Suficiente para el volumen personal (~400 mov/mes).
- **Opción B (escalable):** columna `running_balance` precalculada en la BD,
  actualizada al insertar/editar/eliminar.

Se empieza con **Opción A** y se migra a B solo si el rendimiento lo requiere.

### 3.6 Moneda

**Únicamente PEN (soles).** No se modela multi-moneda ni conversiones. Todo
importe se almacena y formatea en PEN con locale `es_PE`. Algunas cuentas como
Lemon podrían tener saldo en otra moneda en la realidad, pero para esta
iteración el usuario ingresa el equivalente en PEN.

### 3.7 Despliegue

**Solo local en esta iteración.** El usuario gestionará el despliegue futuro por
su cuenta, probablemente en un shared hosting bajo un subdominio nuevo de
`pomareda.dev`. Implicación para el diseño: mantener la app portable (config
`.env`, sin dependencias de servicios gestionados), y considerar que SQLite
puede dar problemas de concurrencia en shared hosting → si más adelante se
migra, MySQL es la opción natural. Por ahora SQLite es suficiente para uso
local mono-usuario.

---

## 4. Modelo de datos

### 4.1 Tablas

```
users
  id, name, email, password, settings (json NULL), timestamps

categories
  id, user_id (FK), name, kind (enum: expense|income|transfer),
  monthly_limit (decimal NULL), color (string NULL), sort_order (int),
  timestamps
  unique(user_id, name)

accounts
  id, user_id (FK), name, kind (enum: bank|wallet|cash|credit|other),
  balance (decimal), exclude_from_reconciliation (bool, default false),
  sort_order (int), timestamps
  unique(user_id, name)

movements
  id, user_id (FK), date (date), description (string),
  category_id (FK NULL), amount (decimal, signed),
  source (enum: manual|recurring|import),
  recurring_id (FK NULL), notes (text NULL), timestamps
  index(user_id, date)

recurring_transactions
  id, user_id (FK), name, category_id (FK NULL),
  amount (decimal, signed), day_of_month (tinyint),
  start_month (date, primer mes), end_month (date NULL, NULL = indefinido),
  active (bool), timestamps
```

### 4.2 Relaciones

- `movements.category_id → categories` (nullable: algunos movimientos no tienen
  categoría, igual que en la planilla).
- `movements.recurring_id → recurring_transactions` (nullable: marca los
  generados por una plantilla).
- `accounts` no se vincula a `movements` en el baseline.

### 4.3 Reglas de negocio clave

- **Balance de apertura del mes M:**
  `SUM(movements.amount WHERE date < M.start AND user_id = X AND source IN (manual, import))`.
  Equivale al “Capital” de la planilla.
- **Balance acumulado en la fila R (dentro del mes M):**
  `apertura(M) + SUM(amount WHERE date <= R.date AND date >= M.start, ordenado por date, id)`.
- **Gasto por categoría en el mes M:**
  `SUM(-amount WHERE category_id = C AND date IN [M.start, M.end] AND amount < 0)`
  (se invierte el signo para mostrarlo como positivo, igual que la col G).
- **Conciliación:**
  `SUM(accounts.balance WHERE exclude_from_reconciliation = false)` vs.
  balance real actual (apertura + movimientos reales hasta hoy). Diferencia →
  alerta.

---

## 5. Fases del plan

Cada fase es **autónoma y entregable**: deja la app funcional hasta ese punto y
puede revisarse/commitearse por separado.

---

### Fase 0 — Setup del stack

**Objetivo:** proyecto Laravel 13 corriendo con el frontend completo.

> **Hallazgo clave:** el **starter kit Vue oficial de Laravel 13 ya incluye
> Inertia 3, Vue 3 Composition API, TypeScript, Tailwind y shadcn-vue**. No hace
> falta configurar cada pieza por separado.

**Tareas**

1. `laravel new caja-diaria` → elegir el starter kit **Vue** (incluye Inertia,
   Tailwind, shadcn-vue y auth).
2. Elegir testing framework (Pest recomendado para sintaxis concisa).
3. Elegir BD: **SQLite** para desarrollo local (suficiente para uso personal);
   MySQL/PostgreSQL opcional para producción.
4. `php artisan migrate` + `npm install && npm run dev`.
5. Verificar login/registro funcionando (vienen con el starter kit).
6. Configurar locale `es_PE` y timezone `America/Lima` en `config/app.php`.
7. Añadir componentes shadcn-vue base que se usarán: `npx shadcn-vue@latest add
table button input select dialog sheet calendar card badge tabs sonner`.

**Criterios de aceptación**

- [x] `npm run dev` + `php artisan serve` muestran la página de inicio y login.
- [x] Registro + login funcionan.
- [x] Un componente shadcn-vue de prueba renderiza.

**Commits sugeridos:** `chore: bootstrap laravel 13 vue starter kit`,
`chore: locale es_PE and timezone America/Lima`,
`chore: add base shadcn-vue components`.

---

### Fase 1 — Modelo de datos y migraciones

**Objetivo:** esquema de BD completo y modelos Eloquent con relaciones.

**Tareas**

1. Migraciones para `categories`, `accounts`, `recurring_transactions`,
   `movements` (en orden de FK).
2. Models con `$fillable`, casts (`date`, `decimal`), relaciones
   (`category`, `user`, `recurring`).
3. Scopes de utilidad:
    - `Movement::forMonth(Carbon $month)`
    - `Movement::actual()` (date <= hoy)
    - `Movement::projected()` (date > hoy)
    - `Movement::openingBalance(Carbon $monthStart)`
4. Factories + seeders para datos de demo.
5. Tests de modelo (relaciones, scopes).

**Criterios de aceptación**

- [x] `php artisan migrate:fresh --seed` corre sin errores.
- [x] Tests de modelo pasan: `php artisan test --filter=ModelTest`.

**Commits:** `feat: migrations and models for categories/accounts/movements`,
`feat: scopes for monthly filtering and projection`,
`test: model and scope tests`.

---

### Fase 2 — Layout y navegación

**Objetivo:** shell de la app con sidebar/nav entre las secciones principales.

**Tareas**

1. Layout autenticado con sidebar: **Tablero · Movimientos · Categorías ·
   Cuentas · Recurrentes**.
2. Usar el layout del starter kit como base; adaptar navegación.
3. Página vacía por sección con título y breadcrumb.
4. Composable `useCurrency` para formatear PEN (`S/.` o `S/`).

**Criterios de aceptación**

- [x] Navegación entre las 5 secciones funciona sin reload (Inertia).
- [x] Los importes se formatean como moneda local.

**Commits:** `feat: authenticated layout with section navigation`,
`feat: currency formatting composable`.

---

### Fase 3 — CRUD de movimientos (núcleo)

**Objetivo:** registrar, editar y eliminar movimientos. La pieza central de la app.

**Tareas**

1. **Resource controller** `MovementController` (index, store, update, destroy).
2. Endpoints Inertia: vista de lista + formularios (o dialog de edición).
3. Vista de lista con:
    - Filtro de mes (selector `Julio-26`, navegable con ←/→).
    - Tabla: Fecha · Movimiento · Tipo · Cantidad · Balance.
    - Separación visual entre **reales** y **proyectados** (badge / color).
4. Formulario de alta/edición: fecha (calendar), descripción, categoría (select
   buscable), importe (con signo, toggle ingreso/gasto).
5. Validación server-side: fecha requerida, importe numérico != 0.
6. **Cálculo del balance acumulado** (Opción A): el controller calcula el
   balance por fila al devolver la lista del mes.
7. Tests feature: alta/edición/borrado, validaciones, cálculo de balance.

**Criterios de aceptación**

- [x] Puedo dar de alta un ingreso y un gasto y verlos en la lista del mes.
- [x] La columna Balance coincide con el cálculo esperado.
- [x] Los movimientos proyectados (fecha futura) se distinguen de los reales.
- [x] Navegar entre meses funciona.

**Commits:** `feat: movement resource controller and validation`,
`feat: monthly movement list with running balance`,
`feat: movement create/edit dialog`,
`test: movement crud and balance calculation`.

---

### Fase 4 — Categorías y presupuestos

**Objetivo:** gestionar categorías con límite mensual y ver gasto acumulado vs.
límite (equivalente a cols F–H).

**Tareas**

1. CRUD `CategoryController` + vistas.
2. Vista de categorías: tabla Tipo · Gasto del mes · Límite · Barra de progreso
   (gasto/límite) con color (verde/ámbar/rojo).
3. Endpoint que calcula `spent` por categoría para el mes seleccionado.
4. Categorías sin límite muestran solo el gasto (como en la planilla).
5. Selección de mes para ver el presupuesto de cualquier mes.
6. Tests.

**Criterios de aceptación**

- [x] Creo una categoría “Mercado” con límite 400 y al registrar gastos se
      actualiza la barra.
- [x] Si supero el límite, la barra se pone roja.

**Commits:** `feat: category crud`,
`feat: monthly budget per category with progress bars`,
`test: category budget calculation`.

---

### Fase 5 — Cuentas y conciliación

**Objetivo:** gestionar saldos de cuentas y conciliar contra el balance real
(equivalente a cols J–K).

**Tareas**

1. CRUD `AccountController` + vistas.
2. Vista de cuentas: tabla Cuenta · Saldo · Total.
3. Marcar cuentas como “excluidas de conciliación”. La cuenta **“Liquidación”**
   se importa ya marcada como excluida (el usuario tiene otros planes para ese
   fondo; fuera del alcance actual).
4. **Panel de conciliación:** muestra
    - Total de cuentas (excluyendo las marcadas).
    - Balance real actual (apertura + movimientos reales hasta hoy).
    - Diferencia con estado: ✅ conciliado / ⚠️ descuadre de `X`.
5. Botón “ajustar balance” por cuenta para corregir saldos rápidamente.
6. Tests.

**Criterios de aceptación**

- [x] Registro las cuentas y sus saldos y el total coincide con el balance real
      → veo ✅.
- [x] Si cambio un saldo y ya no coincide → veo el descuadre exacto.

**Commits:** `feat: account crud with balances`,
`feat: reconciliation panel`,
`test: reconciliation logic`.

---

### Fase 6 — Pagos recurrentes y proyección

**Objetivo:** modelar los pagos recurrentes (Huancayo, Falabella, etc.) y la
proyección a futuro.

**Tareas**

1. CRUD `RecurringTransactionController`.
2. Formulario: nombre, importe, categoría, día del mes, mes de inicio, mes de
   fin (opcional).
3. **Comando / job** `app:generate-projections` que, para cada plantilla activa,
   genera movimientos `source=recurring` con fecha futura hasta el mes de fin
   (o un horizonte configurable, ej. 12 meses).
4. Vista de **Proyección**: timeline mensual con balance proyectado por fecha,
   integrando reales + proyectados + recurrentes. Equivalente a leer la col E
   hacia el futuro.
5. Botón “regenerar proyecciones” tras editar plantillas.
6. Tests.

**Criterios de aceptación**

- [x] Creo una plantilla “Falabella, −300.96, día 5, por 24 meses” y se generan
      24 movimientos proyectados.
- [x] La vista de proyección muestra el balance esperado en cada fecha futura.

**Commits:** `feat: recurring transaction templates`,
`feat: projection generation command`,
`feat: future projection timeline view`,
`test: recurring generation and projection`.

---

### Fase 7 — Dashboard / Tablero

**Objetivo:** vista resumen del mes actual con métricas clave.

**Tareas**

1. Tarjetas: Balance actual · Ingresos del mes · Gastos del mes · Proyección a
   fin de mes.
2. Resumen de presupuesto: top categorías con gasto vs. límite.
3. Mini-conciliación (✅/⚠️).
4. Próximos movimientos proyectados (siguientes 7 días).
5. Gráfico simple de balance a lo largo del mes (librería ligera, ej. Chart.js
   vía `vue-chartjs` o un componente shadcn-vue reutilizable).

**Criterios de aceptación**

- [x] Al abrir la app veo el estado financiero del mes de un vistazo.

**Commits:** `feat: dashboard with monthly summary cards`,
`feat: budget overview and upcoming projections`,
`feat: monthly balance chart`.

---

### Fase 8 — Personalización y preferencias

**Objetivo:** permitir al usuario personalizar la apariencia y el comportamiento
de la app: paleta de colores (8 opciones, incluyendo rosado), foto de perfil,
densidad de tablas, sección de inicio, día de inicio de semana y horizonte de
proyección. Evita el look “todo blanco con líneas negras” del light mode por
defecto.

**Tareas**

1. **Migración:** añadir columna `settings` (JSON, nullable) a la tabla `users`.
   Cast como `array` en el modelo `User`
   (`$casts = ['settings' => 'array']`).
2. **Theming con 8 paletas:**
    - Definir 8 paletas como sets de CSS variables en `resources/css/app.css`,
      cada una con su class: `theme-slate`, `theme-rose`, `theme-blue`,
      `theme-green`, `theme-amber`, `theme-violet`, `theme-teal`, `theme-red`.
    - Cada paleta redefine `--primary`, `--background`, `--foreground`,
      `--accent`, etc., en sus variantes light **y** dark (la paleta es ortogonal
      al toggle light/dark existente).
    - Extender el composable `useAppearance` para aplicar el class de paleta al
      `<html>` además del `dark` existente.
    - Persistir la paleta en `settings.theme` (guardar el KEY, no colores
      crudos): al cargar la app, el servidor pasa `settings.theme` al frontend;
      al cambiar, se persiste vía endpoint.
3. **Endpoint de settings:** `PUT /settings` que valida y persiste el JSON en
   `users.settings`. Validación server-side de claves permitidas:
    - `theme` ∈ {slate, rose, blue, green, amber, violet, teal, red}
    - `density` ∈ {compact, comfortable}
    - `start_section` ∈ {dashboard, movements, categories, accounts, recurring}
    - `week_start` ∈ {monday, sunday}
    - `projection_horizon` ∈ entero 1–24 (meses)
    - `avatar_path` (string, nullable; solo lo setea el upload de foto)
4. **UI de personalización** (extender `settings/Appearance.vue` o nueva página
   `settings/Preferences.vue`):
    - Selector de paleta: 8 swatches visuales con preview.
    - Upload de foto de perfil con preview y validación client-side.
    - Toggle de densidad (compacta/cómoda).
    - Select de sección de inicio.
    - Select de día de inicio de semana (lunes/domingo).
    - Input numérico para horizonte de proyección (meses).
5. **Foto de perfil:**
    - Controller `UserProfilePhotoController` (o action en `ProfileController`)
      que recibe upload, valida imagen (mime jpg/png/webp, size ≤ 2MB), almacena
      en `storage/app/public/avatars/{user_id}.{ext}`, guarda path en
      `settings.avatar_path`.
    - Mostrar avatar en el layout (header/sidebar) con fallback a iniciales.
    - `php artisan storage:link` si no está creado el symlink público.
6. **Aplicar preferences en runtime:**
    - Composable `useSettings` que lee los settings del user (vía Inertia shared
      prop `auth.user.settings`) y los aplica al montar.
    - Densidad: alternar clases Tailwind en tablas (ej. `p-2` vs `p-4`,
      `text-sm` vs `text-base`) según `settings.density`.
    - Día de inicio: pasar `week_start` al componente `Calendar` y date pickers.
    - Horizonte de proyección: usar `settings.projection_horizon` en el comando
      `app:generate-projections` y en la vista de proyección (reemplaza el
      horizonte hardcodeado de 12 meses).
    - Sección de inicio: redirect tras login según `settings.start_section`.
7. **Tests:** paleta se persiste y aplica el class correcto; upload de foto
   valida mime/size y rechaza archivos inválidos; densidad cambia las clases de
   tabla; validación del endpoint rechaza claves/valores no permitidos.

**Criterios de aceptación**

- [x] Cambio la paleta a “Rose” y, al recargar, sigue aplicada (persistida en
      BD, no solo en localStorage).
- [x] En light mode con paleta “Rose” la app ya no se ve blanco-negro: muestra
      acentos rosados.
- [x] Subo una foto de perfil y se ve en el header; si no hay foto, veo mis
      iniciales.
- [x] Cambio a densidad compacta y las tablas muestran más filas por pantalla.
- [x] Configuro “sección de inicio: Movimientos” y al loguear aterrizo ahí.
- [x] Configuro “día de inicio: lunes” y el calendar empieza en lunes.
- [x] Configuro “horizonte: 6 meses” y la proyección solo genera 6 meses.
- [x] El endpoint rechaza valores fuera de la lista permitida.

**Commits:** `feat: user settings json column`,
`feat: eight color palettes via css variables`,
`feat: settings endpoint with validation`,
`feat: profile photo upload`,
`feat: density and start section preferences`,
`feat: week start and projection horizon preferences`,
`test: personalization and preferences`.

---

### Fase 9 — Pulido y tests

**Objetivo:** dejar la app lista para uso diario local.

**Tareas**

1. Tests de feature end-to-end de los flujos principales (Pest + Dusk opcional).
2. Validación de edge cases:
    - Movimiento con fecha anterior al mes visible.
    - Eliminación de un movimiento que afecta el balance de filas posteriores.
    - Categoría eliminada con movimientos asociados (restricción / null).
3. Confirmaciones de borrado con dialog shadcn-vue.
4. Atajos de teclado (nuevo movimiento con `N`, cambiar mes con ←/→).
5. Verificar tema claro/oscuro y paletas (implementados en Fase 8).
6. README con instrucciones de instalación y uso local.
7. Verificar portabilidad para despliegue futuro: config vía `.env`, sin atar a
   servicios gestionados, documento breve con notas para shared hosting
   (subdominio de `pomareda.dev`) — **sin ejecutar el despliegue** (lo gestiona el
   usuario).

**Criterios de aceptación**

- [x] `php artisan test` en verde (242 tests, 1173 assertions).
- [x] App corre localmente con datos persistentes.
- [x] README explica cómo arrancar desde cero (`README.md`).
- [x] Notas de despliegue futuro documentadas (sin ejecutar) (`DEPLOY.md`).

**Commits:** `test: e2e flow coverage`,
`feat: keyboard shortcuts and delete confirmations`,
`docs: README with setup and usage`,
`docs: future deployment notes for shared hosting`.

---

## 6. Orden recomendado y dependencias

```
Fase 0 (setup)
   │
   ▼
Fase 1 (modelo de datos)
   │
   ▼
Fase 2 (layout)  ──► Fase 3 (movimientos)  ──► Fase 4 (categorías)
                                   │                 │
                                   ▼                 ▼
                              Fase 5 (cuentas)   Fase 6 (recurrentes/proyección)
                                   │                 │
                                   └────────┬────────┘
                                            ▼
                                       Fase 7 (dashboard)
                                            │
                                            ▼
                                       Fase 8 (personalización)
                                            │
                                            ▼
                                       Fase 9 (pulido/deploy)
```

- Las fases 4 y 5 pueden hacerse en paralelo (no dependen entre sí).
- La Fase 8 (personalización) depende de tener auth y layout listos (fases 0–7).
- **Las fases 0–9 están completadas.** Primera etapa cerrada.

---

## 7. Estimación orientativa

| Fase      | Esfuerzo aprox. | Notas                               |
| --------- | --------------- | ----------------------------------- |
| 0         | 0.5 día         | El starter kit ya trae el stack     |
| 1         | 1 día           | Migraciones + models + scopes       |
| 2         | 0.5 día         | Adaptar layout del starter kit      |
| 3         | 2–3 días        | Núcleo de la app; el más importante |
| 4         | 1 día           |                                     |
| 5         | 1 día           |                                     |
| 6         | 1.5 días        | Generador de proyecciones           |
| 7         | 1 día           |                                     |
| 8         | 1–1.5 días      | Personalización y preferencias      |
| 9         | 1 día           | Pulido final                        |
| **Total** | **~10–11 días** | Fases 0–7 ya completadas            |

---

## 8. Riesgos y mitigaciones

| Riesgo                                                   | Mitigación                                                        |
| -------------------------------------------------------- | ----------------------------------------------------------------- |
| Paletas personalizadas afecten accesibilidad (contraste) | Validar contraste WCAG AA en cada paleta light/dark               |
| El cálculo de balance sea lento con mucho historial      | Empezar con Opción A; migrar a columna precalculada si hace falta |
| Pérdida de datos al editar/borrar                        | Soft deletes en `movements` + confirmación en UI                  |
| Proyección y recurrentes se desincronizan                | Botón “regenerar proyecciones” tras editar plantillas             |
| shadcn-vue cambie de API entre versiones                 | Fijar versión; usar el CLI oficial `add`                          |

---

## 9. Próximos pasos inmediatos

1. ~~Confirmar las decisiones de arquitectura de la §3~~ → **hecho** (ver §10).
2. ~~Ejecutar Fase 0~~ → **hecho**.
3. ~~Validar el modelo contra datos reales~~ → **hecho** (fases 0–7 completadas).
4. ~~Ejecutar **Fase 8** (personalización y preferencias)~~ → **hecho**.
5. ~~Luego **Fase 9** (pulido y tests finales)~~ → **hecho**.
6. ~~Fase 9 cerrada: 242 tests, README + DEPLOY.md, atajos de teclado~~ → **hecho**.

---

## 10. Decisiones confirmadas

| Decisión     | Valor                                                                               | Impacto                                                                                                                        |
| ------------ | ----------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------ |
| Cuentas      | Snapshot manual de saldos, sin vínculo movimiento↔cuenta                            | Replica el flujo de la planilla; conciliación como verificación cruzada                                                        |
| Moneda       | Únicamente PEN (soles)                                                              | Sin tablas de moneda ni conversiones; locale `es_PE`                                                                           |
| Despliegue   | Solo local en esta iteración                                                        | SQLite suficiente; futuro shared hosting en subdominio de `pomareda.dev` gestionado por el usuario                             |
| Liquidación  | Cuenta creada manualmente y **excluida siempre** de la conciliación                 | `exclude_from_reconciliation = true`; gestión específica del fondo queda como feature futuro fuera de alcance                  |
| Settings     | Columna `settings` (JSON) en `users`, casteada como `array`                         | Un solo campo para todos los preferences; sin migración por setting nuevo; sobrevive a limpieza de caché y cambio de navegador |
| Theming      | 8 paletas preset vía CSS variables + class swap en `<html>`; ortogonal a light/dark | No se almacenan colores crudos; reutiliza el sistema de CSS vars de shadcn-vue                                                 |
| Import Excel | **Descartado**                                                                      | El usuario no necesita migrar datos históricos; la Fase 8 original se reemplaza por personalización                            |

Con estas decisiones consolidadas, las fases 0–9 están completadas y la
primera etapa del proyecto está cerrada.
