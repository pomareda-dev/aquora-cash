# Onboarding Wizard — Plan de Implementación

> **Estado:** Borrador para revisión
> **Fecha:** 2026-09-19
> **Relación con tour existente:** Independiente. El tour de driver.js (botón `?`) sigue funcionando por separado.

---

## 1. Objetivo

Guiar al usuario nuevo a través de un wizard interactivo para que ingrese la data mínima necesaria antes de usar la plataforma:

- Categorías básicas de ingreso y gasto
- Su cuenta principal con saldo inicial
- Su capital inicial como movimiento de ingreso
- Su primer gasto

El resultado: al terminar el onboarding, el dashboard ya muestra data real y el usuario entiende el modelo mental de la app.

---

## 2. Decisiones de Diseño

| Decisión | Resolución |
|---|---|
| Relación con el tour | Independientes. Onboarding crea data, tour señala UI |
| Navegación | Una sola ruta `/onboarding`, wizard multi-step client-side (Vue) |
| Enforcement | Suave — redirección automática solo en primer login post-registro, con opción de omitir |
| Persistencia de progreso | Parcial por step vía `settings.onboarding_wizard` en el JSON del User |

---

## 3. Pasos del Wizard

### Step 1 — Bienvenida
- Sin inputs
- Título: "¡Bienvenido a Aquora Cash!"
- Breve explicación de qué va a pasar en los próximos pasos (crear categorías, cuenta, movimientos)
- Botón: "Empezar" → avanza al step 2
- Botón secundario: "Omitir configuración" → marca onboarding como skipped y redirige al dashboard

### Step 2 — Categorías
- El usuario crea entre 1 y 5 categorías (mínimo 1 de gasto y 1 de ingreso)
- Cada categoría tiene:
  - `name` (input text)
  - `kind` (selector: ingreso / gasto)
  - `color` (color picker opcional, se puede dejar en null)
- Se muestran como tarjetas/chips que el usuario va agregando
- Se sugieren ejemplos pre-populados (ej: "Sueldo" tipo ingreso, "Alimentación" tipo gasto, "Transporte" tipo gasto) que el usuario puede usar o personalizar
- Validación inline: nombre obligatorio, no duplicados, al menos 1 de ingreso y 1 de gasto
- **POST real** a `/categorias` por cada categoría creada (reutiliza `CategoryController@store` y `CategoryRequest`)
- Las categorías creadas se muestran como lista acumulativa en el step

### Step 3 — Cuenta Principal
- El usuario crea su cuenta principal:
  - `name` (input text, placeholder: "Mi banco", "Efectivo")
  - `kind` (selector: bank / wallet / cash / credit / other)
  - `balance` (input numérico — este será su saldo actual real)
- **POST real** a `/cuentas` (reutiliza `AccountController@store` y `AccountRequest`)
- Explicación contextual: "Este saldo es lo que tenés HOY en esta cuenta"

### Step 4 — Capital Inicial (Ingreso)
- Movimiento automático o guiado:
  - `date` → fecha de hoy (pre-rellenada, editable)
  - `description` → "Capital inicial" (pre-rellenado, editable)
  - `amount` → positivo (input numérico). Se sugiere usar el saldo de la cuenta del step 3
  - `category_id` → selector con las categorías de ingreso creadas en step 2
- **POST real** a `/movimientos` (reutiliza `MovementController@store` y `MovementRequest`)
- Explicación: "Este movimiento representa tu punto de partida en Aquora Cash"

### Step 5 — Primer Gasto
- Similar al step 4 pero para un gasto:
  - `date` → fecha de hoy (pre-rellenada, editable)
  - `description` → placeholder "Ej: Almuerzo, Supermercado"
  - `amount` → negativo (el usuario ingresa positivo, el frontend lo envía negativo)
  - `category_id` → selector con las categorías de gasto creadas en step 2
- **POST real** a `/movimientos`
- Explicación: "Registrá un gasto reciente para ver cómo funciona el seguimiento"

### Step 6 — Cierre
- Sin inputs
- Resumen visual de lo que se creó:
  - X categorías
  - 1 cuenta con saldo $Y
  - Capital inicial: $Z
  - Primer gasto: $W
- Mensaje de felicitación
- Botón: "Ir al Dashboard" → marca onboarding como completado y redirige

---

## 4. Arquitectura Backend

### 4.1 Ruta

```php
// routes/web.php — dentro del grupo auth+verified
Route::get('onboarding', [OnboardingController::class, 'index'])->name('onboarding');
Route::post('onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
Route::post('onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
```

### 4.2 Controller

```php
class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        // Si ya completó/omitió el onboarding, redirigir al dashboard
        $wizard = $request->user()->settings['onboarding_wizard'] ?? null;
        if ($wizard && ($wizard['completed_at'] ?? $wizard['skipped_at'] ?? null)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Onboarding/Index', [
            'categories' => $request->user()->categories()->orderBy('sort_order')->get(['id', 'name', 'kind', 'color']),
            'accounts' => $request->user()->accounts()->orderBy('sort_order')->get(['id', 'name', 'kind', 'balance']),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $settings = $request->user()->settings ?? [];
        $settings['onboarding_wizard'] = [
            'completed_at' => now()->toIso8601String(),
            'skipped_at' => null,
        ];
        $request->user()->update(['settings' => $settings]);

        return redirect()->route('dashboard');
    }

    public function skip(Request $request): RedirectResponse
    {
        $settings = $request->user()->settings ?? [];
        $settings['onboarding_wizard'] = [
            'completed_at' => null,
            'skipped_at' => now()->toIso8601String(),
        ];
        $request->user()->update(['settings' => $settings]);

        return redirect()->route('dashboard');
    }
}
```

### 4.3 Redirección Post-Registro

En el `CreateNewUser` de Fortify (o en `LoginResponse`), si el usuario no tiene `settings.onboarding_wizard.completed_at` ni `skipped_at`, redirigir a `/onboarding` en lugar de `/dashboard`.

### 4.4 Data Real — Reutilización de Endpoints

El wizard NO necesita endpoints propios para crear categorías, cuentas o movimientos. Cada step usa los endpoints existentes:

| Step | Endpoint | Controller | Request |
|---|---|---|---|
| Categorías | `POST /categorias` | `CategoryController@store` | `CategoryRequest` |
| Cuenta | `POST /cuentas` | `AccountController@store` | `AccountRequest` |
| Capital Inicial | `POST /movimientos` | `MovementController@store` | `MovementRequest` |
| Primer Gasto | `POST /movimientos` | `MovementController@store` | `MovementRequest` |

Se usa `useForm` o `useHttp` de Inertia v3 para las peticiones, sin reload de página.

---

## 5. Arquitectura Frontend

### 5.1 Estructura de Archivos

```
resources/js/pages/Onboarding/
  Index.vue              ← Página principal (renderless wizard container)
  steps/
    WelcomeStep.vue      ← Step 1
    CategoriesStep.vue   ← Step 2
    AccountStep.vue      ← Step 3
    InitialCapitalStep.vue ← Step 4
    FirstExpenseStep.vue ← Step 5
    CompletionStep.vue   ← Step 6
  composables/
    useOnboardingWizard.ts ← Estado del wizard (currentStep, data, navigation)
```

### 5.2 Layout

El wizard usa un layout propio (sin sidebar, sin header completo). Solo muestra:
- Logo de Aquora Cash centrado arriba
- Indicador de progreso (step X de 6)
- La card del step actual
- Botones de navegación (Anterior / Siguiente)

### 5.3 Composable `useOnboardingWizard`

```typescript
interface OnboardingState {
  currentStep: number;          // 1-6
  totalSteps: number;           // 6
  categories: Category[];       // Creadas en step 2
  account: Account | null;      // Creada en step 3
  initialCapital: Movement | null; // Creado en step 4
  firstExpense: Movement | null;   // Creado en step 5
}
```

- `nextStep()` → valida step actual, avanza
- `prevStep()` → retrocede (no deshace la data ya creada)
- `canAdvance` → computed que indica si el step actual tiene los requisitos cumplidos
- `skipOnboarding()` → POST a `/onboarding/skip`
- `completeOnboarding()` → POST a `/onboarding/complete`

### 5.4 Componentes UI

Se reutilizan los componentes shadcn/vue existentes:
- `Card`, `CardHeader`, `CardContent`, `CardFooter`
- `Input`, `Label`, `Select`
- `Button`
- `Badge` (para chips de categorías creadas)
- `Separator`
- `Spinner` (para loading states)
- `ColorPicker` (para colores de categoría)

### 5.5 Categorías Sugeridas

Pre-populadas como opciones rápidas (el usuario puede usarlas tal cual o modificarlas):

| Sugerencia | Tipo |
|---|---|
| Sueldo | Ingreso |
| Freelance | Ingreso |
| Alimentación | Gasto |
| Transporte | Gasto |
| Servicios | Gasto |
| Entretenimiento | Gasto |

---

## 6. Persistencia del Estado del Wizard

El progreso del wizard se trackea en `settings.onboarding_wizard`:

```json
{
  "onboarding_wizard": {
    "completed_at": "2026-09-19T12:00:00Z",  // null si no completó
    "skipped_at": null                         // null si no omitió
  }
}
```

La data creada durante el wizard (categorías, cuenta, movimientos) se persiste inmediatamente en cada step a través de los endpoints existentes. Si el usuario abandona a mitad del wizard y vuelve, las categorías/cuentas ya creadas siguen existiendo — el wizard detecta esto y permite avanzar.

### Actualización de `UserSettings` y validación

Se extiende la interfaz `UserSettings` en `useSettings.ts` para incluir `onboarding_wizard`, y la validación en `UpdateSettingsRequest.php` para aceptar los nuevos campos.

---

## 7. UX / Diseño

### 7.1 Principios
- **Progreso visible:** barra o stepper que muestra en qué paso está
- **Reversible:** el usuario puede ir hacia atrás (no se borra data ya creada)
- **Skippable:** botón "Omitir" visible en todo momento
- **Mobile-first:** el wizard funciona igual de bien en pantalla chica
- **Feedback inmediato:** tras cada POST exitoso se muestra confirmación inline (no toast)

### 7.2 Layout Visual

```
┌──────────────────────────────────────┐
│          [Logo Aquora Cash]          │
│                                      │
│     ● ● ● ○ ○ ○   Paso 3 de 6      │
│                                      │
│  ┌────────────────────────────────┐  │
│  │                                │  │
│  │    [Contenido del Step]        │  │
│  │                                │  │
│  │                                │  │
│  └────────────────────────────────┘  │
│                                      │
│   [← Anterior]          [Siguiente →]│
│                                      │
│          [Omitir configuración]      │
└──────────────────────────────────────┘
```

---

## 8. Testing

### Feature Tests (Pest)
- `OnboardingController@index` → renderiza la página para usuarios sin onboarding
- `OnboardingController@index` → redirige al dashboard para usuarios con onboarding completado
- `OnboardingController@index` → redirige al dashboard para usuarios que omitieron
- `OnboardingController@complete` → persiste `completed_at` en settings
- `OnboardingController@skip` → persiste `skipped_at` en settings
- Redirección post-login: usuario nuevo → `/onboarding`, usuario existente → `/dashboard`

### Validación de integración
- Crear categorías desde el wizard usa el mismo endpoint que la página normal
- Crear cuenta desde el wizard usa el mismo endpoint que la página normal
- Crear movimientos desde el wizard usa el mismo endpoint que la página normal

---

## 9. Alcance y Estimación

| Componente | Archivos | Complejidad |
|---|---|---|
| `OnboardingController` | 1 | Baja |
| Rutas | 1 (modificación) | Baja |
| Redirección post-registro | 1 (modificación) | Baja |
| Settings extension | 2 (modificación) | Baja |
| `Onboarding/Index.vue` | 1 | Media |
| 6 step components | 6 | Media |
| `useOnboardingWizard.ts` | 1 | Media |
| Layout de onboarding | 1 | Baja |
| Tests Pest | 1 | Media |
| Wayfinder regeneration | — | Automático |
| **Total** | **~14 archivos** | **Media** |

---

## 10. Consideraciones Abiertas

1. **¿Categorías pre-creadas?** El plan actual sugiere categorías pero no las pre-crea. Alternativa: pre-crear un set por defecto al registrarse y que el wizard las muestre como editables.

2. **¿Segundo intento?** Si el usuario omitió el onboarding y luego quiere completarlo, ¿se muestra un botón en settings o dashboard para relanzarlo?

3. **¿Eliminar data del wizard?** Si el usuario vuelve al step 2 (categorías) después de ya haber creado, ¿puede borrar las que creó? El plan actual dice que no se deshace, pero se podría permitir.

4. **¿Tour automático después del onboarding?** Al terminar el wizard y llegar al dashboard, ¿se lanza automáticamente el tour de driver.js?
