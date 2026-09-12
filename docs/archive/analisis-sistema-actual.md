# Análisis del sistema actual (Google Sheets)

> Documento de referencia. Describe cómo funciona hoy la planilla `proyeccion-2026.xlsx`
> para que el plan de trabajo pueda replicar (y mejorar) cada comportamiento.

---

## 1. Estructura general del libro

El archivo contiene **una hoja por mes**, nombradas como `Enero-25`, `Febrero-25`, …,
`Julio-26`. Cada hoja tiene el mismo layout y **tres zonas funcionales** dispuestas
en columnas contiguas:

| Zona                      | Columnas | Función                                   |
| ------------------------- | -------- | ----------------------------------------- |
| Libro mayor de movimientos | A–E      | Registro de ingresos/gastos + proyección  |
| Presupuesto por categoría | F–H      | Gasto acumulado vs. límite mensual         |
| Saldo de cuentas          | J–K      | Balance de bancos/billeteras + totals      |

---

## 2. Zona A–E: Libro mayor de movimientos

### 2.1 Columnas

| Col | Nombre      | Contenido                                                        |
| --- | ----------- | ---------------------------------------------------------------- |
| A   | Fecha       | Fecha del movimiento. Vacía en los pagos recurrentes proyectados |
| B   | Movimiento  | Descripción corta del gasto o ingreso                            |
| C   | Tipo        | Categoría (ver §5). Puede quedar vacía                           |
| D   | Cantidad    | Importe con signo: `+` ingreso, `−` gasto                        |
| E   _(proy.)_ | Balance | Fórmula `=SUM($D$2:INDIRECT("R[0]C[-1]", FALSE))` — suma acumulada |

### 2.2 Comportamiento de la columna E (proyección)

- Es un **balance acumulado** que corre desde la primera fila de la hoja hasta la
  fila actual.
- Como la fila 2 de cada hoja es un movimiento de **“Capital”** (saldo inicial
  arrastrado del mes anterior), la columna E reproduce el efecto de un saldo de
  apertura + todos los movimientos posteriores.
- Sirve tanto para ver el saldo **actual** (última fila con fecha real) como
  para **proyectar** el saldo en cualquier fecha futura, porque debajo de los
  movimientos reales se anotan movimientos **con fecha futura**.

### 2.3 Movimientos reales vs. proyectados

En una misma hoja conviven:

1. **Movimientos reales** — fecha ≤ hoy, ya ocurridos.
2. **Movimientos proyectados** — fecha > hoy, planeados (ej. “Sueldo” el
   `2026-12-30`, “Internet WIN” el `2026-12-27`).
3. **Pagos recurrentes sin fecha** — filas como `Huancayo 22`, `Falabella 9`,
   donde el número indica el **mes ordinal** del pago recurrente. Tienen importe
   pero **sin fecha**, y solo alimentan la proyección de la columna E.

> **Implicancia para la app:** estos tres tipos deben modelarse por separado para
> no perder la semántica. Ver `02-arquitectura-modelo-datos.md`.

### 2.4 Fila “Capital” (rollover mensual)

- La primera fila de datos de cada hoja siempre es `Capital` con un importe
  positivo que representa el **saldo arrastrado** del mes anterior.
- Equivale a: `Capital(mes N) = balance final real(mes N−1)`.
- En la app esto se **deriva automáticamente** como suma de movimientos reales
  previos al inicio del periodo; no necesita almacenarse.

---

## 3. Zona F–H: Presupuesto por categoría

| Col | Nombre | Contenido                                                       |
| --- | ------ | --------------------------------------------------------------- |
| F   | Tipo   | Nombre de la categoría                                          |
| G   | Gasto  | `=SUM(SUMIF(C2:INDIRECT(...), "Mercado", D2:...) * -1)` — suma de los importes negativos de esa categoría, invertida a positivo |
| H   | Límite | Límite mensual presupuestado por el usuario (opcional)          |

### 3.1 Comportamiento

- `G` es **gasto acumulado** del mes (suma de los negativos, en valor absoluto).
- `H` es el **techo** que el usuario se autoimpone.
- No todas las categorías tienen límite (ej. `Gato`, `Fiorella`, `Ganancias` no
  lo tienen).
- Permite comparar cuánto se ha gastado vs. cuánto está permitido.

---

## 4. Zona J–K: Saldo de cuentas

| Col | Nombre   | Contenido                                                  |
| --- | -------- | ---------------------------------------------------------- |
| J   | Cuentas  | Nombre de banco/billetera/efectivo                        |
| K   | Total    | Saldo actual de esa cuenta (ingresado a mano)             |

- Hay una fila de **total** con `=SUM(K3:K13)` que suma todas las cuentas.
- Existe una fila especial **“Liquidación”** con un importe grande (19500):
  representa un saldo NO disponible inmediatamente (ej. fondo de liquidación
  laboral).
- **Conciliación:** el usuario compara `SUM(cuentas)` con la **última fila real**
  de la columna E. Si coinciden, no se olvidó de registrar nada. Si no coinciden,
  hay un gasto no registrado o un movimiento extra.

### 4.1 Cuentas detectadas (hoja Julio-26)

```
BCP1        758.23
BCP2        1000
Interbank   2.10
Lemon       0.08
CMR         5.36
Huancayo    2.09
Piura       0.67
Pichincha   0.85
Agora       0.11
Efectiva    1.41
Cash        1.80
Liquidación 19500   (saldo no disponible, se excluye de la conciliación activa)
```

> **Nota de diseño:** los movimientos del libro mayor **no están vinculados a una
> cuenta**. El usuario actualiza las cuentas por separado y usa la conciliación
> como verificación cruzada. La app puede replicar esto (baseline) o vincular
> movimientos a cuentas (mejora opcional, ver plan).

---

## 5. Categorías (Tipo) detectadas

Lista consolidada de valores únicos en la columna C a lo largo de las hojas:

```
Mercado          Menu              Comida calle      Limpieza
Salud            Pasajes           Servicios         Servicios fijos
Gato             Fiorella          Karina            Lenia
Lena y Mica      Karen             Ganancias         Cripto
Limite de rango  Mov. Cripto
```

Observaciones:

- La mayoría son categorías de **gasto**.
- `Ganancias` es categoría de **ingreso**.
- `Cripto` / `Mov. Cripto` son **movimientos de transferencia** (no son gasto ni
  ingreso reales; mueven dinero entre cuentas/activos).
- `Limite de rango` parece ser una categoría auxiliar de control, no un gasto.
- Algunas categorías son **personas** (`Fiorella`, `Karina`, `Lenia`, `Karen`,
  `Lena y Mica`) — probablemente préstamos o gastos cubiertos para terceros.

---

## 6. Patrones de uso identificados

1. **Registro diario:** el usuario carga movimientos del día (gastos/ingresos).
2. **Actualización de cuentas:** al mismo tiempo actualiza los saldos de col K.
3. **Proyección:** debajo de los movimientos reales anota gastos/ingresos futuros
   con fecha para ver hacia dónde va el saldo (col E).
4. **Recurrentes:** anota pagos que se repiten cada mes (créditos, deudas) sin
   fecha exacta, solo con el número de mes ordinal.
5. **Conciliación:** compara total de cuentas vs. última E real.
6. **Rollover:** al cambiar de mes, copia el saldo final como “Capital” del
   siguiente y abre una hoja nueva.

---

## 7. Limitaciones del sistema actual que la app puede resolver

| Limitación en Sheets                          | Mejora en la app                                   |
| --------------------------------------------- | -------------------------------------------------- |
| Rollear “Capital” a mano cada mes            | Balance de apertura calculado automáticamente      |
| Mantener fórmulas INDIRECT frágiles          | Balance calculado por la app o por la BD           |
| Formato de fecha inconsistente                | Validación y formato locale (es-PE) consistente   |
| Sin historial de cambios                      | Timestamps + soft deletes en movimientos            |
| Proyectar recurrentes a mano cada mes        | Plantillas recurrentes que generan proyecciones   |
| Conciliación manual visual                    | Alerta automática de descuadre                     |
| Copiar hojas y fórmulas al nuevo mes         | Una sola línea temporal; vistas filtradas por mes |
| Difícil buscar/agrupar entre meses           | Filtros y reportes transversales                   |

---

## 8. Resumen de requisitos funcionales

Para que la app ofrezca **las mismas características** que el sistema actual:

1. **RF-1** Registrar movimientos (fecha, descripción, categoría, importe con
   signo).
2. **RF-2** Mostrar balance acumulado por fila (equivalente a col E).
3. **RF-3** Filtrar/mostrar por mes (equivalente a una hoja).
4. **RF-4** Gestionar categorías con límite mensual y ver gasto acumulado vs.
   límite.
5. **RF-5** Gestionar cuentas con su saldo actual.
6. **RF-6** Conciliar suma de cuentas vs. balance actual.
7. **RF-7** Registrar movimientos proyectados (fecha futura) que alimenten la
   proyección.
8. **RF-8** Registrar pagos recurrentes (sin fecha fija, mes ordinal) que
   alimenten la proyección.
9. **RF-9** Proyectar el balance en una fecha futura cualquiera.
10. **RF-10** Importar el historial existente desde el Excel.