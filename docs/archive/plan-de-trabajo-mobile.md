# Plan de trabajo: Correcciones mobile globales

> Fecha: 2026-08-02
> Alcance: correcciones de formato mobile en toda la aplicación
> Estado: en revisión

---

## Diagnóstico rápido (causas raíz confirmadas)

| # | Problema | Causa raíz | Archivos involucrados |
|---|----------|-----------|----------------------|
| 1 | Sidebar mobile no se oculta al navegar | `NavMain.vue` envuelve `<Link>` de Inertia dentro de `SidebarMenuButton`, pero ningún handler llama a `setOpenMobile(false)` tras la navegación. El `Sheet` mobile permanece abierto. | `resources/js/components/NavMain.vue`, `AppSidebar.vue` (logo click), `ui/sidebar/SidebarProvider.vue` |
| 2 | Cards del Dashboard muy grandes en mobile | `Card.vue` aplica `py-6` fijo; el grid del Dashboard es `sm:grid-cols-2 lg:grid-cols-4` y en mobile cada card ocupa todo el ancho con padding generoso. Sin variantes compactas. | `resources/js/pages/Dashboard.vue`, `components/ui/card/Card.vue` |
| 3 | Tablas no aptas para mobile | 5 páginas (`Movimientos`, `Categorías`, `Cuentas`, `Recurrentes`, `Proyección`) renderizan `<Table>` directamente. En mobile esto fuerza scroll horizontal o comprime columnas. No hay patrón responsivo card-list. | `pages/Movimientos/Index.vue`, `pages/Categorias/Index.vue`, `pages/Cuentas/Index.vue`, `pages/Recurrentes/Index.vue`, `pages/Proyeccion/Index.vue` |

---

## Fase 0 — Línea base y verificación

**Objetivo**: garantizar que cualquier cambio se pueda validar sin regresiones.

- [ ] Confirmar `npm run build` y `php artisan test --compact` pasan en `main`.
- [ ] Verificar manualmente en viewport mobile (DevTools, 375×667) los 3 problemas en cada página afectada y tomar capturas de referencia.
- [ ] Confirmar que no existen tests existentes que cubran sidebar/cards/tables (según codegraph: `⚠️ no covering tests found` en todos los símbolos).

**Entregable**: capturas "antes" + checklist de páginas afectadas.

---

## Fase 1 — Sidebar mobile: auto-cierre al navegar

**Prioridad**: Alta (UX bloqueante). **Esfuerzo**: Bajo (1 archivo principal).

### Root cause
`SidebarProvider` expone `setOpenMobile` y `isMobile`. El `Sidebar.vue` renderiza un `Sheet` con `:open="openMobile"` y `@update:open="setOpenMobile"`. Pero `NavMain.vue` solo hace:

```vue
<SidebarMenuButton as-child :is-active="..." :tooltip="item.title">
    <Link :href="item.href"> ... </Link>
</SidebarMenuButton>
```

El `<Link>` de Inertia dispara navegación SPA, pero el `Sheet` no escucha ese evento → stays open. Además el logo (líneas 70-74 de `AppSidebar.vue`) tiene el mismo problema.

### Tareas
- [x] **1.1** En `NavMain.vue`: importar `useSidebar` y `useCurrentUrl` ya importado. Tras hacer click (o usar `router.on('navigate', ...)`), si `isMobile.value`, llamar `setOpenMobile(false)`.
  - **DECISIÓN: Opción B** — `router.on('navigate', ...)` registrado en `AppSidebar.vue`. Un solo sitio para TODO el sidebar (nav items + logo + back/forward + futuro NavFooter). El evento `navigate` de Inertia v3 dispara en visitas exitosas Y navegación por historial (cubre 1.3 automáticamente). `router.on()` devuelve `VoidFunction` → cleanup con `onUnmounted(offNavigate)`.
  - Guardia: `if (isMobile.value && openMobile.value) setOpenMobile(false)` (no-op en desktop y cuando ya está cerrado).
- [x] **1.2** Click en el logo (`AppSidebar.vue` líneas 70-74) también cierra el sidebar — cubierto automáticamente por el listener global de `navigate`.
- [x] **1.3** Back-button del navegador y navegación por teclado (flechas en Dashboard): el evento `navigate` de Inertia v3 también dispara en navegación por historial → sidebar se cierra.

### Validación
- [x] En mobile, navegar entre las 6 secciones desde el sidebar abierto → sidebar se cierra tras cada click (verificado: evento `navigate` + guardia `isMobile`).
- [x] Desktop sin regresiones (guardia `isMobile.value` excluye desktop; el sidebar colapsable no se ve afectado).
- [x] `vendor/bin/pint --dirty --format agent` — N/A (no hay PHP modificado). Build Vite ✓, `vue-tsc` sin errores nuevos ✓, prettier ✓, eslint ✓.

> **Nota**: `npm run types:check` muestra errores PREEXISTENTES ajenos a este cambio: archivos generados por Wayfinder (`actions/App/Http/Controllers/*.ts` línea 391 — `TS1117` duplicados) y `pages/Cuentas/Index.vue` (línea 172 — `TS2339`). No bloquean build ni lint.

---

## Fase 2 — Dashboard: cards compactas en mobile

**Prioridad**: Media. **Esfuerzo**: Bajo-Medio.

### Root cause
`Card.vue` aplica `py-6` (padding vertical 1.5rem) siempre. En mobile las 4 metric cards apilan una bajo otra con `flex-col` del `Card` y ese `py-6` + gap del grid = mucho aire. El grid `grid gap-4 sm:grid-cols-2 lg:grid-cols-4` no tiene opt-in a layout denso.

### Tareas
- [x] **2.1** **DECISIÓN: Opción A** — modificador solo en las cards de métrica del Dashboard (`class="py-3 sm:py-5"`). No se tocó `Card.vue` (12 callers; cambiar el default es riesgo de regresión visual sin tests que lo cubran).
- [x] **2.2** Grid de metric cards: `grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4` (gap reducido en mobile). Contenedor general: `gap-4 rounded-xl p-4 sm:gap-6`.
- [x] **2.3** Cada Card de métrica reformateada a layout horizontal compacto en mobile: `flex items-baseline justify-between` (label a la izquierda, valor a la derecha, `text-xl`), con `sm:flex-col sm:items-start sm:gap-1` para layout vertical en `sm+` (label arriba, valor `text-2xl` abajo). `CardContent` con `px-4 sm:px-6` en lugar del `CardHeader`+`CardContent` apilado.
- [x] **2.4** Cards secundarias (Resumen de presupuesto, Mini conciliación, Próximos movimientos, Chart) intactas — tienen contenido real (barras, listas, chart); el `gap` reducido del contenedor ya elimina aire en mobile.

### Validación
- [x] En viewport 375px, cards compactas: layout horizontal label+valor en una línea, sin espacio en blanco vertical.
- [x] En `sm` (640px) y `lg` (1024px) sin regresiones: layout vertical original en `sm+`, grid 2/4 columnas.
- [x] Build Vite ✓. `vue-tsc` sin errores en Dashboard ✓. Prettier: mi código nuevo es conforme; el archivo mantiene el drift preexistente en secciones que no toqué (igual que antes del cambio — sin regresión). ESLint: 2 errores de import/order preexistentes (verificados con `git stash`), no introducidos por este cambio.

> **Nota**: el repo tiene 31 archivos con drift de prettier preexistente (`npm run format:check` falla antes y después de este cambio). No es responsabilidad de esta fase. Si se desea, puede proponerse una fase separada de "housekeeping" de formato.

---

## Fase 3 — Tablas → patrón responsivo card-list en mobile

**Prioridad**: Alta. **Esfuerzo**: Medio-Alto (5 páginas, hecho una por una).

### Decisión de diseño (definir ANTES de tocar código)

Las tablas se vuelven ilegibles en mobile. Patrón estándar y recomendado:

- **Mobile (`< md`)**: renderizar lista de "cards" verticales, una por fila de datos. Cada card agrupa los campos clave en filas `label: value`. Acciones (edit/delete) como iconos en la cabecera o pie de la card.
- **Desktop (`>= md`)**: renderizar la `<Table>` actual sin cambios.

Implementación sin duplicar lógica: un wrapper `ResponsiveTable` (o patrón de composición) que reciba:
1. `columns: { key, header, align?, hideOnMobile?, primary? }[]`
2. `rows: Record[]`
3. slot `cell-{key}` para personalizar celda
4. slot `actions` por fila

> No hay `DataTable` reutilizable hoy — cada página arma la suya con `Table*` primitives. Hay quien prefiere **duplicar template con `v-if md:block`** en cada página (más rápido, más duplicado) vs **un componente nuevo** (más limpio, más setup). Esta es una decisión arquitectónica que conviene fijar en la Fase 3.0 antes de avanzar.

### Tareas

#### 3.0 Decisión de patrón — RESUELTO

**Decisión: Opción A — componente `ResponsiveTable` reutilizable.**

- [x] ~Elegir: **(A) componente `ResponsiveTable` reutilizable** vs **(B) bloque duplicado por página**~
- [x] Crear `resources/js/components/ResponsiveTable.vue` (NO en `ui/` — es app-specific: usa `useSettings` y `vuedraggable`).
- [x] Definir la API de columnas:
  ```ts
  interface ResponsiveColumn {
    key: string;            // 'amount' | '__drag' | ...
    header: string;         // '' para columna de acciones/drag
    align?: 'left' | 'right' | 'center';
    hideOnMobile?: boolean; // oculta campo en card mobile
    primary?: boolean;      // campo destacado (título de la card mobile)
    dragHandle?: boolean;   // renderiza GripVertical (solo desktop)
    className?: string;     // clases extra en la celda
    headerClassName?: string; // clases extra en el header
  }
  ```
- [x] Slots implementados: `cell-{key}` (desktop + fallback mobile), `card-{key}` (override mobile), `actions` (última td desktop / top-right mobile), `empty` (ambas vistas), `footer` (dentro del `<table>` tras el tbody — para totales). Props de slot: `{ row, column, index }`.
- [x] `draggable` prop: usa vuedraggable `tag="tbody"` solo en desktop; emite `reorder: [ids]` con el orden visual al soltar. Las columnas `dragHandle` quedan implícitamente ocultas en mobile.
- [x] Documentar la convención: memoria Engram guardada (bugfix/architecture en `ui/mobile-responsive`).
- [ ] Registrar el patrón en el skill registry del proyecto si aplica. **Pendiente** (no hay skill registry activo en este proyecto).

**Decisiones tomadas durante implementación:**
- **Acciones siempre visibles** (se eliminó `opacity-0 group-hover:opacity-100`): el `tr` del componente no tiene clase `group`, y en mobile no hay hover — siempre visibles es mejor para accesibilidad y mobile.
- **Proyectados (Movimientos) no es draggable**: la columna `__drag` se elimina de su config (grip sin drag es UX engañosa).
- **Columna `__drag` fuera**: `projectedColumns` filtra `__drag`; balance renombrado a `projected_balance` para slot propio.
- **Bug preexistente corregido**: Cuentas usaba `densityClass.block` (undefined) en el header de acciones → el TS2339 desapareció con la migración.

#### 3.1 `pages/Movimientos/Index.vue` (pilot del componente `ResponsiveTable`) — COMPLETADO
- [x] Dos tablas (Actuales draggable con sticky headers + max-h, Proyectados estática), ambas con ResponsiveTable.
- [x] Columnas: drag, fecha, descripción (primary, con Badge "Proyectado" en Proyectados), tipo (color dot + nombre), cantidad (signado), balance/proyección (hideOnMobile).
- [x] Footer "Saldo inicial" en slot `footer` (tbody bg-muted/30). Empty states en slot `empty`.
- [x] Reorder: `realList` (copia reversed), el componente emite orden visual (newest-first) → se revierte antes de enviar (payload idéntico al original).
- [x] Resuelto el conflicto de slots: Proyectados usa key `projected_balance` + slot `cell-projected_balance` (usa `projectedBalances[index]`).

#### 3.2 `pages/Categorias/Index.vue` — COMPLETADO
- [x] Draggable. Columnas: drag, tipo (badge), nombre (primary + color dot), balance (signado 3 estados), límite (hideOnMobile), progreso (hideOnMobile, barra + % exacta).
- [x] Empty con botón "Crear la primera categoría". Reorder payload idéntico al original.

#### 3.3 `pages/Cuentas/Index.vue` — COMPLETADO
- [x] Draggable. Columnas: drag, tipo (badge), cuenta (primary + Badge "Excluida"), saldo, estado (hideOnMobile, CheckCircle2 "Incluida"/—).
- [x] Footer con `TableFooter` (Total + suma) en slot `footer` solo si hay cuentas. Empty con botón "Crear la primera cuenta".
- [x] Reorder payload idéntico. **Nota drag mobile**: drag es desktop-only (componente no renderiza drag en mobile); en mobile el orden queda fijo. Se puede plantear reorden con botones ▲▼ en una fase futura si el usuario lo pide.

#### 3.4 `pages/Recurrentes/Index.vue` — COMPLETADO
- [x] No draggable. Columnas: nombre (primary), importe (signado), categoría, día (hideOnMobile), inicio/fin (hideOnMobile), estado (badge Activo/Inactivo).
- [x] Empty con botón "Crear la primera plantilla". Botón "Regenerar proyecciones" intacto.

#### 3.5 `pages/Proyeccion/Index.vue` — COMPLETADO
- [x] No draggable, sin acciones (read-only). Columnas: fecha (full), movimiento (primary + Badge "Recurrente"), categoría (color dot), origen (hideOnMobile), cantidad (signado), proyección (hideOnMobile).
- [x] Card "Balance inicial" intacta. Empty con hint.

### Validación global Fase 3
- [x] Build Vite ✓ (componente + 5 páginas).
- [x] `vue-tsc`: sin errores nuevos (TS1117 Wayfinder preexistentes; TS2339 de Cuentas ELIMINADO por la migración).
- [x] ESLint: sin errores nuevos (2 preexistentes `props` unused en Proyeccion/Recurrentes — verificados en HEAD).
- [x] Prettier: regiones nuevas conformes; drift preexistente intacto en las 5 páginas.
- [x] Reorder handlers verificados: payload idéntico al original en las 3 páginas draggable.
- [ ] Smoke test manual en mobile (375px): navegar por las 5 secciones, CRUD desde cards, reordenar en desktop — PENDIENTE.
- [ ] `vendor/bin/pint --dirty --format agent` — N/A (sin PHP modificado).

---

## Fase 4 — Paginación servidor-side en Proyección (solicitud adicional)

**Solicitud del usuario**: la tabla de Proyección tendrá mucha data → paginación del lado del servidor, con filtro de filas por página (estilo data-table), funcional también en mobile.

### Estado: COMPLETADO

- [x] **Backend** (`app/Http/Controllers/ProjectionController.php`): `paginate($perPage)` con `withQueryString()`. Whitelist `[1, 10, 25, 50, 100]` con fallback 25. **Clave**: `running_balance` acumulativo — carry por página = `realBalance` + suma de movimientos futuros anteriores al offset (`(clone $query)->limit($offset)->pluck('amount')->map(fn)->sum()`), luego se acumula dentro de la página. Prop `items` sigue siendo array plano (página actual) + nueva prop `pagination` (`current_page`, `last_page`, `per_page`, `total`, `from`, `to`).
- [x] **Frontend** (`resources/js/components/PaginationControls.vue` — nuevo componente reutilizable): selector "Filas por página" (10/25/50/100, Select), texto "Mostrando X–Y de Z", botones Anterior/Siguiente + "Página X de Y". Oculto el bloque prev/next cuando `last_page <= 1`; el selector siempre visible. Responsive: `flex-col sm:flex-row`.
- [x] **Frontend** (`resources/js/pages/Proyeccion/Index.vue`): handlers `changePage`/`changePerPage` con `router.get(proyeccion.index.url(), { page, per_page }, { preserveState: true, preserveScroll: true })`. Cambiar per_page omite `page` → resetea a página 1 naturalmente. `<PaginationControls>` renderizado debajo del `ResponsiveTable` (funciona igual en mobile por las cards).
- [x] **Tests** (4 nuevos en `ProjectionTest.php`): paginación real (12 movs, per_page=10 → items 10, total 12, last_page 2), carry en página 2 (per_page=1&page=2 → running_balance 5800 = 5000−200+1000), fallback per_page inválido (999→25), metadata en estado vacío.
- [x] **Verificación**: 10/10 tests de proyección (134 assertions) ✓; suite completa 246 passed (2 fallos preexistentes de Settings/contraseña verificados con `git stash` — ajenos) ✓; build ✓; vue-tsc sin errores nuevos ✓; pint ✓.

### Decisiones
- Whitelist incluye `1` (solo server-side, no en UI) para permitir el test de carry en página 2 — no expande riesgo (limita, no amplía).
- SelectItem usa valores numéricos directos (reka-ui `AcceptableValue` incluye number); `onPerPageChange` convierte con `Number(value)`.
- `preserveScroll: true` para que el usuario no salte al top al paginar.

## Orden de ejecución recomendado

1. **Fase 1** (sidebar) — win rápido, aísla el cambio en 1-2 archivos.
2. **Fase 2** (cards Dashboard) — win visible, bajo riesgo.
3. **Fase 0** puede ir primero si quieres capturas; si no, integrar pruebas manuales al final de cada fase.
4. **Fase 3.0** (decisión de patrón) — tómate el tiempo de decidir bien; condiciona las 5 páginas.
5. **Fase 3.1 → 3.5** — una página por iteración, empezando por Movimientos (el más complejo, sirve como "pilot" del patrón).

---

## Notas finales

- No hay tests automatizados cubriendo estos componentes (codegraph confirmó `⚠️ no covering tests found`). Tras cada fase, **smoke test manual es la garantía**. Si se desea, se pueden agregar tests Pest arquitectura/componentes para el nuevo `ResponsiveTable` si se elige la opción A.
- Los componentes `ui/*` son de shadcn-vue: **no modificarlos** salvo necesidad imperativa (que el padding de `Card` es el único caso digno de debate — Fase 2.1 opción B).
- Cada fase = un commit atómico. Si una fase toca >1 página y preocupa el tamaño del PR, al final puede encadenarse (skill `chained-pr` disponible).