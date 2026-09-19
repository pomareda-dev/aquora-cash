# Panel de administración — diseño y arquitectura

> Fecha: 2026-09-18
> Alcance: definir la arquitectura, las funciones y el enfoque técnico del
> panel de administración de Aquora Cash como plataforma SaaS.
> Prerrequisito de: R1 (SaaS core) del roadmap en `analisis-agente-ia-y-saas.md`.

---

## 1. Decisión arquitectónica: misma app vs. app separada

### Veredicto: misma aplicación, rutas y middleware separados

El panel de admin debe vivir **dentro de la misma aplicación Laravel**, bajo un
prefijo de ruta `/admin`, protegido por middleware de rol, con páginas Inertia
separadas en `resources/js/pages/Admin/` y un layout propio `AdminLayout.vue`.

**No usar** tabla `admins` separada ni guard independiente.
**No usar** aplicación/subdominio separado (`panel.aquoracash.com`).

### ¿Por qué NO una app separada?

| Factor | Misma app | App separada (subdominio) |
| --- | --- | --- |
| Acceso a datos | Eloquent directo, zero latency | Necesita API interna o DB compartida con modelos duplicados |
| Reutilización de código | Modelos, services, components, casts, scopes — todo compartido | Copiar modelos o crear paquete Composer; duplicación inevitable |
| Infraestructura | Un deploy, un server, un CI/CD | Dos deploys, dos pipelines, sesiones cross-subdomain, CORS |
| Costo operativo | Cero overhead adicional | VPS extra o configuración nginx/caddy por subdominio |
| Tiempo de desarrollo | ~40 % menos que la alternativa | Más setup inicial, más mantenimiento continuo |
| Seguridad | Middleware + policies; el admin es una capa, no otra app | Aislamiento real pero overkill para esta escala |
| Escalabilidad futura | Se puede extraer a app separada si algún día se necesita | Ya separada desde el inicio sin necesidad |

**El argumento de "separar por seguridad" solo tiene peso con equipos grandes
(10+ admins) o cuando el admin tiene operaciones destructivas sobre
infraestructura.** Con lectura + gestión de usuarios en un SaaS que arranca, la
separación es costo puro sin beneficio real.

### ¿Por qué NO una tabla `admins` con guard separado?

- Un admin probablemente también quiera usar la app como usuario normal (probar
  la experiencia, ver cómo se ve un dashboard de usuario). Con tabla separada
  necesita dos cuentas.
- Dos guards complican la autenticación: sesiones separadas, middleware
  condicional, Fortify configurado para dos modelos.
- Laravel maneja roles dentro de una misma tabla de forma nativa y limpia.
- Escalar de roles simples a permisos granulares no requiere cambiar de tabla.

### Enfoque recomendado

```
users (tabla existente)
  + role: enum('user', 'admin', 'super_admin') default 'user'

Middleware: EnsureUserIsAdmin → verifica role in ['admin', 'super_admin']
Rutas:     /admin/* → middleware(['auth', 'verified', 'admin'])
Páginas:   resources/js/pages/Admin/
Layout:    AdminLayout.vue (sidebar propia, branding admin)
Guard:     web (el mismo, sin guard adicional)
```

Cuando en el futuro se necesiten permisos granulares (ej: "soporte puede ver
usuarios pero no desactivarlos"), se puede escalar a `spatie/laravel-permission`
sin migrar de tabla ni cambiar el guard. El `role` enum se mantiene como
fallback rápido y el paquete agrega la capa fina de permisos por encima.

---

## 2. Funciones recomendadas por fase

Las funciones están organizadas en tres fases alineadas con el roadmap SaaS.

### Fase 1 — Pre-lanzamiento (junto con R1 SaaS core)

> Objetivo: tener visibilidad operativa antes de abrir registro público.

#### 2.1 Dashboard de administrador

Vista resumen con métricas clave de la plataforma:

- **Usuarios totales** — registrados en la plataforma
- **Usuarios activos** — con al menos 1 login en los últimos 30 días
- **Nuevos registros** — esta semana y este mes, con gráfico de tendencia
- **Movimientos totales** — volumen de la plataforma (indicador de engagement)
- **Tasa de verificación de email** — % de usuarios que completaron
  verificación (importante para la salud del funnel)

#### 2.2 Gestión de usuarios

CRUD de lectura + acciones administrativas:

- **Listado paginado** con búsqueda por nombre/email y filtros por:
  - Estado: activo / desactivado / sin verificar
  - Rol: user / admin / super_admin
  - Fecha de registro (rango)
  - Último login
- **Detalle de usuario** (vista de solo lectura):
  - Datos de perfil (nombre, email, fecha de registro, último login)
  - Estadísticas de uso: total de movimientos, categorías, cuentas, deudas,
    metas
  - Estado de verificación de email
  - Estado de 2FA
  - Preferencias (tema, densidad, sección de inicio)
- **Acciones sobre usuario:**
  - Desactivar / reactivar cuenta
  - Forzar re-verificación de email
  - Resetear contraseña (enviar email de reset)
  - Cambiar rol (solo super_admin puede promover/degradar)

#### 2.3 Log de actividad administrativa

Registro de acciones de los admins para auditoría:

- Quién hizo qué, cuándo, sobre qué usuario
- Acciones registradas: desactivación, reactivación, cambio de rol, reset de
  contraseña
- Listado paginado con filtros por admin y tipo de acción

#### 2.4 Configuración de plataforma

Ajustes globales que afectan a todos los usuarios:

- **Registro público**: abrir/cerrar registro de nuevos usuarios
- **Mantenimiento**: activar/desactivar modo mantenimiento con mensaje
  personalizado
- **Límites por defecto**: horizonte de proyección default para nuevos usuarios

### Fase 2 — Post-lanzamiento (junto con R2 IA MVP + R3 Billing)

> Objetivo: gestionar la monetización y el uso del agente de IA.

#### 2.5 Gestión de planes y suscripciones

- **Vista de suscripciones activas** — usuarios por plan (free, pro, etc.)
- **Métricas de conversión** — free → pro, tasa de churn mensual
- **Acciones**: otorgar plan pro manualmente (early adopters, testers),
  extender período de prueba
- **Ingresos** — MRR, ARR, tickets promedio (datos del gateway de pago)

#### 2.6 Monitoreo del agente de IA

- **Uso de créditos** — consumo global y por usuario
- **Costo de tokens** — gasto real en proveedores de IA por día/semana/mes
- **Análisis fallidos** — listado con error, modelo, usuario, para debugging
- **Análisis más usados** — qué tipos de análisis piden más los usuarios
- **Acciones**: otorgar créditos extra a un usuario, ajustar cuota mensual
  gratuita

#### 2.7 Monitoreo del sistema

- **Colas** — jobs pendientes, fallidos, tiempo promedio de procesamiento
- **Errores** — integración con Sentry o dashboard de errores recientes
- **Almacenamiento** — uso de disco por avatares y datos

### Fase 3 — Crecimiento (post-validación)

> Objetivo: herramientas para escalar operaciones y soporte.

#### 2.8 Comunicaciones

- **Anuncios del sistema** — enviar notificaciones a todos los usuarios
  o segmentos (ej: "nueva función disponible", "mantenimiento programado")
- **Email masivo** — campañas simples a usuarios activos/inactivos

#### 2.9 Reportes y exportación

- **Reporte de crecimiento** — usuarios, engagement, retención por cohorte
- **Reporte financiero** — ingresos, costos de IA, margen por usuario
- **Export CSV** — datos agregados para análisis externo

#### 2.10 Permisos granulares

- Migrar de `role` enum a `spatie/laravel-permission` cuando haya 3+ roles
  diferenciados (ej: super_admin, admin, soporte, moderador)
- Pantalla de gestión de roles y permisos en el admin

---

## 3. Modelo de datos — cambios necesarios

### 3.1 Migración: agregar `role` a `users`

```php
// database/migrations/xxxx_add_role_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->string('role')->default('user')->after('email');
    $table->boolean('is_active')->default(true)->after('role');
    $table->timestamp('last_login_at')->nullable()->after('is_active');
});
```

### 3.2 Tabla `admin_audit_logs`

```php
Schema::create('admin_audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('action');          // user.deactivated, user.role_changed, etc.
    $table->json('metadata')->nullable(); // { old_role: 'user', new_role: 'admin' }
    $table->ipAddress('ip_address')->nullable();
    $table->timestamps();
});
```

### 3.3 Tabla `platform_settings`

```php
Schema::create('platform_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->timestamps();
});
```

Claves iniciales: `registration_open`, `maintenance_mode`,
`maintenance_message`, `default_projection_horizon`.

### 3.4 Enum `UserRole`

```php
// app/Enums/UserRole.php
enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
```

---

## 4. Arquitectura técnica

### 4.1 Estructura de archivos

```
app/
  Enums/
    UserRole.php
  Http/
    Controllers/Admin/
      AdminDashboardController.php
      AdminUserController.php
      AdminAuditLogController.php
      AdminPlatformSettingsController.php
    Middleware/
      EnsureUserIsAdmin.php
  Models/
    AdminAuditLog.php
    PlatformSetting.php
  Services/
    AdminAuditService.php
    PlatformSettingsService.php

resources/js/
  layouts/
    AdminLayout.vue             // sidebar admin, branding diferente
  pages/Admin/
    Dashboard.vue
    Users/
      Index.vue                 // listado + búsqueda + filtros
      Show.vue                  // detalle de usuario
    AuditLog/
      Index.vue
    Settings/
      Index.vue                 // configuración de plataforma

routes/
  admin.php                     // rutas del panel de admin
```

### 4.2 Rutas

```php
// routes/admin.php
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('users', AdminUserController::class)->only(['index', 'show']);
    Route::post('users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
    Route::post('users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('users/{user}/reverify', [AdminUserController::class, 'reverify'])->name('users.reverify');
    Route::patch('users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');

    Route::get('audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');

    Route::get('settings', [AdminPlatformSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('settings', [AdminPlatformSettingsController::class, 'update'])->name('settings.update');
});
```

### 4.3 Middleware

```php
// app/Http/Middleware/EnsureUserIsAdmin.php
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->user()?->role, [UserRole::Admin, UserRole::SuperAdmin])) {
            abort(403);
        }

        return $next($request);
    }
}
```

### 4.4 Layout del admin

El `AdminLayout.vue` debe:

- Usar una sidebar diferente a la del usuario (navegación admin)
- Mantener el mismo sistema de temas (shadcn-vue + Tailwind v4) para
  consistencia visual
- Mostrar un indicador visual claro de que se está en modo admin (badge,
  color de sidebar diferente)
- Incluir un enlace "Volver a la app" para que el admin pueda ir a su
  vista de usuario normal

### 4.5 Compartir datos de admin con Inertia

En `HandleInertiaRequests`, agregar condicionalmente la info de admin:

```php
'auth' => [
    'user' => $request->user(),
    'isAdmin' => $request->user()?->isAdmin(), // bool helper en User model
],
```

---

## 5. Consideraciones de seguridad

1. **El primer super_admin se crea por seeder o comando Artisan**, nunca por
   registro público. Crear `php artisan admin:create` que solicite
   nombre, email y contraseña.
2. **Solo super_admin puede cambiar roles.** Un admin no puede
   promoverse ni promover a otros.
3. **Rate limiting** en todas las acciones destructivas del admin
   (desactivar usuario, cambiar rol).
4. **Audit log** registra TODA acción administrativa con IP, timestamp y
   metadata del cambio.
5. **Proteger contra auto-desactivación**: un admin no puede
   desactivarse a sí mismo ni degradar su propio rol.
6. **CSP y sesiones**: al estar en la misma app, no hay complejidad de
   cookies cross-subdomain ni CORS.

---

## 6. Estimación de esfuerzo

| Componente | Esfuerzo estimado |
| --- | --- |
| Migración + enum + modelo User actualizado | 0.5 sesión |
| Middleware + rutas + layout admin | 0.5 sesión |
| Dashboard admin (métricas de plataforma) | 1 sesión |
| CRUD de usuarios (listado + detalle + acciones) | 1.5 sesiones |
| Audit log (modelo + servicio + vista) | 0.5 sesión |
| Configuración de plataforma | 0.5 sesión |
| Comando `admin:create` | 0.25 sesión |
| Tests (Pest) | 1 sesión |

**Total Fase 1: ~5.5–6 sesiones**

Se integra dentro del bloque R1 del roadmap SaaS sin agregar overhead
significativo.

---

## 7. Relación con el roadmap SaaS existente

```
R0. Features planificadas (ya completadas o en progreso)
    v1.4 Metas ✅ → v1.2 Sandbox ✅ → v1.1 PWA (pendiente)

R1. SaaS core (5–6 sesiones) ← EL PANEL DE ADMIN ENTRA AQUÍ
    Policies + 2FA + verificación email + VPS + worker + backups +
    emails + ToS/privacidad + Sentry
    + Panel de admin Fase 1 (dashboard, usuarios, audit log, settings)
    Hito: registro público seguro con panel de admin operativo

R2. IA MVP (4 sesiones)
    + Panel de admin Fase 2 (monitoreo IA, créditos)

R3. Billing (2–3 sesiones)
    + Panel de admin Fase 2 (gestión de suscripciones)

R4. Beta pública
    + Panel de admin Fase 3 (comunicaciones, reportes, permisos granulares)
```

---

## 8. Futuro: cuándo considerar separar

La separación a app independiente o subdominio solo se justificaría si:

- Hay **10+ admins/agentes de soporte** con carga de trabajo que compite con
  el tráfico de usuarios
- El admin necesita **operaciones de infraestructura** (migrations en vivo,
  gestión de servers) que no deben compartir proceso
- Se necesita **deploy independiente** del admin sin afectar la app de usuario

Ninguno de estos escenarios es probable en los primeros 12–18 meses de
operación. Cuando llegue el momento, la extracción es mecánica: mover
controllers, vistas y rutas a un nuevo proyecto Laravel que comparte la misma
base de datos.
