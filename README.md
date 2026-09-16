# Aquora Cash

**Sé dueño de tu plata.** Un tracker de gastos personales con proyección
financiera que reemplaza las planillas de Google Sheets con una aplicación
local y rápida. Registra movimientos diarios, proyecta tu saldo futuro,
controla presupuestos por categoría, gestiona deudas con estrategias de
pago, ahorra para tus metas, y simula escenarios antes de decidir — todo
en soles peruanos (PEN).

---

## Índice

- [¿Qué es Aquora Cash?](#qué-es-aquora-cash)
- [¿Por qué Aquora Cash?](#por-qué-aquora-cash)
- [Funciones principales](#funciones-principales)
- [Resumen técnico](#resumen-técnico)
- [Inicio rápido](#inicio-rápido)
- [Atajos de teclado](#atajos-de-teclado)
- [Pruebas](#pruebas)
- [Comandos útiles](#comandos-útiles)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Documentación](#documentación)
- [Licencia](#licencia)

---

## ¿Qué es Aquora Cash?

Aquora Cash es una aplicación web monousuario, local-first, que nace del
dolor real de mantener la planilla `proyeccion-2026.xlsx`: copiar hojas cada
mes, arrastrar fórmulas frágiles, conciliar cuentas a ojo, y perder el hilo
de los gastos recurrentes. La aplicación convierte ese flujo artesanal en
una **línea de tiempo unificada** donde los movimientos reales y los
proyectados conviven, el saldo se calcula automáticamente, y la proyección
se regenera con un solo comando.

**Moneda única:** soles peruanos (PEN).
**Base de datos:** SQLite local (lista para MySQL en producción).

---

## ¿Por qué Aquora Cash?

| Con la planilla… | Con Aquora Cash… |
|---|---|
| Copias hojas y arrastrás fórmulas cada mes | Una sola línea de tiempo; los meses se filtran, no se copian |
| El saldo inicial de cada mes se calcula a mano | El *opening balance* se calcula automáticamente de todos los movimientos reales anteriores |
| Las fórmulas `INDIRECT` se rompen al insertar o borrar filas | El saldo se recalcula en el servidor en cada request — nunca se descuadra |
| Los gastos recurrentes se ingresan manualmente mes a mes | Plantillas de transacciones recurrentes que generan movimientos proyectados automáticamente |
| La conciliación de cuentas es visual («el total de cuentas debería coincidir con el último saldo») | Panel de conciliación automático con ✅/⚠️ y la diferencia exacta |
| Sin gestión de deudas ni metas de ahorro | Deudas con estrategias de pago (avalancha/bola de nieve) y metas con aportes progresivos |
| Sin validación ni control de cambios | Validación completa en servidor, historial de registros, sin pérdida de datos |
| Solo vos podés abrir la planilla | Autenticación con Fortify, datos aislados por usuario, listo para multiusuario |
| Sin personalización | 8 temas visuales, modo claro/oscuro, densidad de tabla configurable, foto de perfil |
| Cambios en la planilla son irreversibles | Modo sandbox: simulá escenarios sin tocar datos reales, guardá o descartá |

---

## Funciones principales

### 📊 Dashboard

Resumen financiero del mes con **cuatro tarjetas KPI**: saldo real al día
de hoy, ingresos del mes, gastos del mes, y proyección de cierre de mes.
Incluye:

- **Resumen de presupuesto** — top 5 categorías de gasto con barras de
  progreso y alertas visuales (verde, ámbar, rojo)
- **Mini panel de conciliación** — suma de cuentas vs. saldo real
- **Resumen de deudas activas** — deudas abiertas con saldo restante,
  cuotas pagadas y próximo pago
- **Resumen de metas activas** — metas en progreso con monto ahorrado,
  porcentaje y días restantes para el objetivo
- **Próximos 7 días** — lista de movimientos proyectados inminentes
- **Gráfico de saldo diario** — evolución del *running balance* durante
  el mes seleccionado
- Navegación entre meses con `←` / `→`

### 💰 Movimientos

El corazón de la aplicación. CRUD completo de ingresos y gastos con
**saldo acumulado por fila** que considera todos los movimientos reales
desde el origen. Dividido en dos secciones:

- **Actuales** — movimientos reales hasta hoy
- **Proyectados** — movimientos futuros o marcados como proyección

Características clave:

- **Arrastrar y reordenar** movimientos reales dentro de la misma fecha
- **Conversión inteligente**: si editas un movimiento proyectado y lo
  vuelves real (o viceversa), el `sort_order` se recalcula automáticamente
- **Opening balance proyectado**: arrastra el cierre proyectado del mes
  anterior como saldo de apertura del mes actual — ves tu futuro
  financiero real, no una foto aislada
- Atajo `N` para nuevo movimiento; `←` / `→` para cambiar de mes

### 🏷️ Categorías

CRUD de categorías con tipo (`expense`, `income`, `transfer`), límite
mensual opcional, color y orden personalizable. Cada categoría muestra:

- **Balance del mes** — ingresos menos egresos para ese rubro
- **Barra de progreso** — gasto actual vs. límite mensual, con umbrales
  de alerta configurables
- **Reembolsos**: si registras un ingreso en una categoría de gasto, el
  sistema lo descuenta del gasto acumulado, no lo ignora

Arrastre para reordenar categorías.

### 🏦 Cuentas

Registro manual de saldos por cuenta (banco, billetera, efectivo,
crédito, otro). Panel de **conciliación automática** que compara la
suma de cuentas activas contra el saldo real de movimientos:

- Muestra el total de cuentas, el saldo real, y la diferencia
- Estado conciliado ✅ o descuadrado ⚠️ con el monto de la diferencia
- **Exclusión de conciliación**: marca cuentas como "Liquidación" u
  otros fondos no disponibles para que no participen en la comparación

### 🔁 Recurrentes

Plantillas de transacciones que se repiten cada mes. Cada plantilla
define nombre, monto, categoría, día del mes, y mes de inicio/fin
(opcional). Dos modos de generación:

- **`app:generate-projections`** — genera movimientos proyectados
  idempotentes (no duplica movimientos ya existentes) para los próximos
  N meses
- **`POST /recurrentes/regenerate`** — borra y regenera todas las
  proyecciones desde las plantillas activas, en una sola transacción

### 💳 Deudas

Gestión completa de préstamos personales con cálculo automático de
cuotas y generación de movimientos. Cada deuda define monto principal,
fecha de desembolso, monto de cuota, número de cuotas y fechas de pago.

- **Desembolso automático** — al crear una deuda se genera el movimiento
  positivo (principal) y las cuotas como movimientos negativos
- **Liquidación anticipada** — paga una deuda antes de tiempo; el sistema
  genera el movimiento de liquidación y elimina las cuotas proyectadas
  restantes
- **Detalle de deuda** — historial de pagos reales vs. calendario de
  cuotas proyectadas, con totales de pagado, restante y cuotas al día
- **Estrategias de pago** — comparación visual de **avalancha** (mayor
  tasa primero) vs. **bola de nieve** (menor saldo primero) con factor
  ponderado entre todas tus deudas activas
- **Categoría de préstamos** — configurable en Preferencias para que los
  movimientos de deuda se clasifiquen correctamente

### 🎯 Metas

Seguimiento de objetivos de ahorro con aportes progresivos y cálculo
automático de progreso. Cada meta define nombre, monto objetivo y fecha
límite opcional.

- **Aportes con fecha** — registra cada aporte con monto, fecha y notas;
  el progreso se recalcula automáticamente
- **Barra de progreso** — porcentaje completado con indicador visual
- **Auto-completado** — cuando los aportes alcanzan el objetivo, la meta
  se marca como completada; si los aportes bajan, se reabre
- **Resumen de apartado** — monto total asignado a metas activas vs.
  saldo real disponible
- **Historial de aportes** — lista cronológica de cada aporte por meta,
  con opción de eliminar

### 🧪 Sandbox (Modo Simulación)

Prueba escenarios financieros sin tocar tus datos reales. Activa el modo
sandbox desde el banner de simulación y experimenta libremente.

- **CRUD completo en sandbox** — crea movimientos, deudas, metas,
  aportes y recurrentes simulados que no afectan tus datos reales
- **Vista unificada** — el Dashboard y las páginas muestran datos reales
  + simulados combinados, con badges que distinguen cada escenario
- **Guardar o revertir** — cuando tu escenario te convence, guárdalo
  como real con un click; si no, descártalo y todo vuelve a la normalidad
- **Isolamiento total** — los datos sandbox viven en las mismas tablas
  con `is_sandbox = true` y se filtran automáticamente por scopes de
  Eloquent

### 🔮 Proyección

Línea de tiempo de todos los movimientos con fecha futura, paginada
(1/10/25/50/100 por página), con saldo proyectado que **se arrastra
entre páginas** — lo que ves en la página 2 es la continuación real
del saldo de la página 1.

Muestra la fuente de cada movimiento (Manual, Recurrente, Importado),
categoría, monto y saldo proyectado acumulado. El horizonte de
proyección es configurable por usuario (1 a 24 meses).

### 🎓 Guía de uso interactiva

Tour guiado con **36 pasos** distribuidos en las 5 páginas principales
(Dashboard, Movimientos, Cuentas, Categorías, Recurrentes). Implementado
con Driver.js.

- **Navegación y shell** — sidebar, perfil, ayuda
- **Features por página** — cada sección muestra sus controles y flujos
- **Pasos condicionales** — se saltan automáticamente si el elemento no
  está presente (ej. sin categorías configuradas)
- **Responsive** — pasos adaptados para mobile y desktop
- **Persistencia** — al completar el tour se guarda; accesible desde el
  botón de ayuda (?) en cualquier momento

### ⚙️ Preferencias

Panel de personalización completo:

- **8 temas visuales** (default, bold-tech, claude, pastel-dreams,
  quantum-rose, sunny-sprout, twitter, violet-bloom), independientes
  del modo claro/oscuro
- **Foto de perfil** con recorte y validación (JPG/PNG/WebP, máx. 2MB)
- **Densidad de tabla**: compacta o comfortable
- **Sección de inicio**: qué página cargar después del login
- **Horizonte de proyección**: de 1 a 24 meses hacia el futuro
- **Día de inicio de semana**: lunes o domingo
- **Categoría de préstamos**: qué categoría usar para movimientos de deuda

---

## Resumen técnico

| Capa | Tecnología |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Autenticación | Laravel Fortify (login, registro, reset de contraseña, actualización de perfil y contraseña) |
| Frontend | Inertia 3, Vue 3 (Composition API + `<script setup>` + TypeScript) |
| Estilos | Tailwind CSS v4, shadcn-vue, Reka UI (componentes headless accesibles) |
| Build | Vite (HMR + SSR en desarrollo) |
| Base de datos | SQLite (desarrollo local), MySQL-ready para producción |
| Pruebas | Pest v4, PHPUnit 12 |
| Tipado frontend | vue-tsc |
| Formateo PHP | Laravel Pint |
| Locale | `es_PE`, zona horaria `America/Lima` |
| Tour interactivo | Driver.js |

### Arquitectura y decisiones de diseño

**Línea de tiempo unificada.** No hay tablas separadas para movimientos
reales y proyectados. Existe una sola tabla `movements` con el flag
`is_projected`. Un movimiento con fecha ≤ hoy es real; con fecha > hoy
es proyectado. Esto simplifica consultas, evita duplicación de lógica,
y permite que un movimiento pase de proyectado a real sin migrar de
tabla.

**Proyección por fecha, no por tipo.** La proyección financiera se
construye consultando todos los movimientos con fecha futura,
independientemente de su fuente (manual, recurrente, importado). Las
plantillas recurrentes son generadoras de movimientos proyectados, no
una entidad separada en la línea de tiempo.

**Cuentas como snapshots manuales.** Los saldos de cuentas bancarias
no se derivan de los movimientos; el usuario los ingresa manualmente.
Esto modela la realidad: el saldo del banco puede diferir del
acumulado de movimientos por demoras en compensación, comisiones, o
errores. El panel de conciliación justamente existe para detectar y
cuantificar esa diferencia.

**Apertura de mes calculada.** El *opening balance* de cualquier mes
es la suma de todos los movimientos reales con fecha anterior al
primer día de ese mes. No se almacena; se calcula en cada consulta.
Esto garantiza que el saldo siempre sea consistente con los datos.

**Idempotencia en proyecciones.** El comando `app:generate-projections`
verifica la existencia de cada movimiento proyectado por par
(`recurring_id`, `date`) antes de crearlo. Puedes ejecutarlo cuantas
veces quieras sin duplicar movimientos. El endpoint `regenerate` es
la variante destructiva: borra todo y regenera desde cero.

**Sandbox con scopes de Eloquent.** Los datos simulados conviven en
las mismas tablas con `is_sandbox = true`. Un global scope (`LiveScope`)
los excluye de todas las consultas por defecto. El modo sandbox
desactiva ese scope y los incluye. Promover (commit) cambia el flag a
`false`; revertir borra todas las filas sandbox del usuario. Esto evita
tablas duplicadas y mantiene la lógica de negocio idéntica.

**Deudas como generadoras de movimientos.** Al crear una deuda, el
sistema genera automáticamente el movimiento de desembolso (+principal)
y los movimientos de cuota (-installment) en las fechas indicadas. Las
cuotas futuras se crean como proyectadas; las pasadas como reales.
Liquidar una deuda genera un movimiento de pago y elimina las cuotas
proyectadas pendientes.

**Metas con sync de completado.** Cada aporte a una meta recalcula el
progreso. Cuando la suma de aportes alcanza el objetivo, `syncCompletion`
marca la meta como completada. Si los aportes se eliminan y el progreso
baja del objetivo, la meta se reabre automáticamente.

### Modelo de datos

| Tabla | Propósito | Relaciones principales |
|---|---|---|
| `users` | Autenticación + preferencias (JSON) | — |
| `categories` | Categorías con tipo y límite mensual | `belongsTo User`, `hasMany Movement`, `hasMany RecurringTransaction` |
| `accounts` | Snapshots manuales de saldo por cuenta | `belongsTo User` |
| `movements` | **Tabla central** — todo ingreso, gasto y proyección | `belongsTo User`, `belongsTo Category` (nullable), `belongsTo RecurringTransaction` (nullable), `belongsTo Debt` (nullable) |
| `recurring_transactions` | Plantillas de transacciones recurrentes | `belongsTo User`, `belongsTo Category` (nullable), `hasMany Movement` |
| `debts` | Préstamos con cuotas y fechas de pago | `belongsTo User`, `hasMany Movement` |
| `goals` | Metas de ahorro con monto objetivo | `belongsTo User`, `hasMany GoalContribution` |
| `goal_contributions` | Aportes individuales a una meta | `belongsTo Goal` |

---

## Inicio rápido

**Requisitos:** PHP 8.3+, Node 22+, npm.

```bash
git clone <repo-url> aquora-cash && cd aquora-cash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run dev          # terminal 1 — Vite dev server
php artisan serve    # terminal 2 — Laravel dev server
```

Abrí `http://localhost:8000`, registrá una cuenta, y empezá a trackear.

---

## Atajos de teclado

| Tecla | Acción | Página |
|---|---|---|
| `N` | Abrir diálogo de nuevo movimiento | Movimientos |
| `←` | Mes anterior | Movimientos, Categorías, Dashboard |
| `→` | Mes siguiente | Movimientos, Categorías, Dashboard |

Los atajos se suprimen al tipear en campos de texto o cuando hay un
diálogo abierto.

---

## Pruebas

```bash
php artisan test --compact                    # suite completa
php artisan test --compact --filter=Movement  # filtrar por nombre
```

La suite cubre **+235 pruebas** organizadas por dominio:

| Archivo | Pruebas | Dominio |
|---|---|---|
| `ModelTest.php` | 39 | Relaciones, casts, scopes, constraints, valores por defecto |
| `MovementTest.php` | 35 | CRUD, validación, saldo acumulado, filtrado por mes, reordenamiento, conversión proyectado↔real |
| `CategoryTest.php` | 32 | CRUD, balance, presupuesto, reembolsos, reordenamiento |
| `AccountTest.php` | 27 | CRUD, conciliación (exacta, descuadre, exclusión), reordenamiento |
| `DashboardTest.php` | 23 | Props de Inertia, KPIs, presupuesto, conciliación, proyecciones próximas, gráfico |
| `RecurringTransactionTest.php` | 19 | CRUD, regeneración idempotente |
| `GenerateProjectionsTest.php` | 12 | Comando Artisan, horizonte, clamping de día, truncado de mes final |
| `ProjectionTest.php` | 10 | Vista de proyección, paginación, arrastre de saldo entre páginas |
| `PreferencesTest.php` | 12 | Temas, densidad, sección de inicio, horizonte, foto de perfil |
| `DebtStrategyTest.php` | — | Estrategias avalancha, bola de nieve, factor ponderado |
| Auth / Security | 17 | Login, registro, reset de contraseña, actualización de perfil, rate limiting |

Casos de borde cubiertos: recálculo del saldo al eliminar un movimiento
del medio, apertura de mes desde movimientos de meses anteriores,
categorías que hacen cascade a null, arrastre de saldo proyectado
entre meses consecutivos, y sync de completado de metas.

---

## Comandos útiles

```bash
php artisan app:generate-projections              # generar movimientos proyectados desde plantillas activas
php artisan app:generate-projections --horizon=24 # con horizonte personalizado
php artisan app:generate-projections --user=1     # para un usuario específico
php artisan tinker                                # REPL en contexto de la app
php artisan route:list --except-vendor            # listar todas las rutas
php artisan storage:link                          # enlace simbólico para archivos públicos (avatares)
npm run dev                                       # Vite dev server (HMR + SSR)
npm run build                                     # build de producción
npm run types:check                               # chequeo de tipos con vue-tsc
vendor/bin/pint --format agent                    # formateo de PHP
```

---

## Estructura del proyecto

```
app/
  Console/Commands/         Comandos Artisan (generate-projections)
  Http/Controllers/         Controladores resource + settings + sandbox
  Http/Requests/            Form requests con validación
  Http/Responses/           Respuesta de login Fortify (redirección start_section)
  Models/                   Modelos Eloquent con scopes y relaciones
  Models/Scopes/            LiveScope (filtro sandbox global)
  Services/                 Servicios de dominio (ProjectionService, DebtStrategy, SandboxService)
database/
  migrations/               Migraciones de esquema
  factories/                Factories de modelos para pruebas
  seeders/                  Seeders demo (CajaDiariaDemoSeeder)
docs/
  plan-de-trabajo.md        Plan de implementación en 10 fases
  analisis-sistema-actual.md Análisis de la planilla original
resources/js/
  pages/                    Páginas Inertia + Vue (Dashboard, Movimientos, Deudas, Metas, etc.)
  components/               Componentes reutilizables (shadcn-vue + propios + debts + goals + sandbox + tour)
  composables/              useAppearance, useSettings, useKeyboardShortcuts, useCurrency, useSandbox, useTour
  css/                      Tailwind + temas CSS (8 paletas tweakcn)
routes/
  web.php                   Rutas de la aplicación + sandbox
  settings.php              Rutas de preferencias
```

---

## Documentación

La carpeta `docs/` contiene el diseño completo y el análisis:

- **`docs/plan-de-trabajo.md`** — decisiones de arquitectura, modelo de
  datos, reglas de negocio, y el plan de 10 fases (completado)
- **`docs/analisis-sistema-actual.md`** — cómo funciona la planilla
  original, sus 7 limitaciones, y los 10 requisitos funcionales que
  motivaron la app
- **`DEPLOY.md`** — notas para despliegue futuro en hosting compartido

---

## Licencia

Proyecto privado de uso personal.
