<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import {
  ArrowRight,
  ArrowRightLeft,
  LayoutDashboard,
  Repeat,
  ShieldCheck,
  Smartphone,
  Tags,
  TrendingUp,
  Wallet,
  Zap,
} from '@lucide/vue';
</script>

<template>
  <Head title="Aquora Cash - Gestor de Finanzas Personales" />

  <div class="min-h-screen bg-background text-foreground">
    <!-- Navbar -->
    <header
      class="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
    >
      <div class="container mx-auto flex h-16 items-center justify-between px-4">
        <div class="flex items-center gap-2">
          <div class="flex items-center justify-center rounded-md bg-primary p-1.5">
            <Wallet class="size-5 text-primary-foreground" />
          </div>
          <span class="text-lg font-semibold">Aquora Cash</span>
        </div>
        <nav class="flex items-center gap-3">
          <Link
            v-if="$page.props.auth.user"
            :href="dashboard()"
          >
            <Button variant="ghost">Panel</Button>
          </Link>
          <template v-else>
            <Link :href="login()">
              <Button variant="ghost">Iniciar sesión</Button>
            </Link>
            <Link :href="register()">
              <Button>Comenzar</Button>
            </Link>
          </template>
        </nav>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-br from-primary/5 via-transparent to-primary/10" />
      <div class="relative container mx-auto px-4 py-24 sm:py-32 lg:py-40">
        <div class="mx-auto max-w-3xl text-center">
          <h1 class="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
            Toma el Control de<br />
            <span class="text-primary">Tus Finanzas Personales</span>
          </h1>
          <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted-foreground">
            Aquora Cash te ayuda a registrar cada ingreso y gasto, establecer presupuestos por categoría, conciliar tus
            cuentas y proyectar tu futuro financiero — todo desde un panel limpio y rápido.
          </p>
          <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
            <Link :href="register()">
              <Button
                size="lg"
                class="text-base"
              >
                Regístrate Gratis
                <ArrowRight class="ml-2 size-4" />
              </Button>
            </Link>
            <Link :href="login()">
              <Button
                variant="outline"
                size="lg"
                class="text-base"
              >
                Iniciar sesión
              </Button>
            </Link>
          </div>
        </div>
      </div>
    </section>

    <!-- Features Grid -->
    <section class="border-t bg-muted/30 py-24">
      <div class="container mx-auto px-4">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-3xl font-bold tracking-tight">Todo lo que Necesitas para Mantenerte al Día</h2>
          <p class="mt-4 text-muted-foreground">
            Seis herramientas trabajando juntas para darte visibilidad total sobre tu dinero.
          </p>
        </div>
        <div class="mx-auto mt-16 grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-3">
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <LayoutDashboard class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Panel</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Obtén una visión completa de tus finanzas de un vistazo. Saldo real, resúmenes mensuales, progreso de
              presupuestos y gráficos de saldo diario — todo en un solo lugar.
            </p>
          </div>
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500">
              <ArrowRightLeft class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Movimientos</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Registra cada transacción con facilidad. Haz seguimiento de movimientos reales vs proyectados, ve tu saldo
              acumulado por fila y reordena entradas arrastrando y soltando.
            </p>
          </div>
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
              <Tags class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Categorías</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Organiza gastos, ingresos y transferencias con categorías personalizadas. Define límites de gasto mensual
              y observa cómo las barras de progreso te mantienen dentro del presupuesto.
            </p>
          </div>
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-blue-500/10 text-blue-500">
              <Wallet class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Cuentas</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Mantén estados de tus cuentas bancarias, billetera, efectivo y tarjetas de crédito. Concilia todo contra
              tu saldo real calculado.
            </p>
          </div>
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-violet-500/10 text-violet-500">
              <Repeat class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Transacciones Recurrentes</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Define plantillas para salario, alquiler, suscripciones y facturas. Establece calendarios mensuales y deja
              que la app genere movimientos futuros automáticamente.
            </p>
          </div>
          <div class="group rounded-xl border bg-card p-6 shadow-sm transition-shadow hover:shadow-md">
            <div class="mb-4 flex size-12 items-center justify-center rounded-lg bg-rose-500/10 text-rose-500">
              <TrendingUp class="size-6" />
            </div>
            <h3 class="text-lg font-semibold">Proyección</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
              Ve tu futuro financiero desplegarse. Una línea de tiempo unificada combina movimientos reales, proyectados
              y recurrentes con un saldo proyectado hasta 24 meses adelante.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Benefits -->
    <section class="py-24">
      <div class="container mx-auto px-4">
        <div class="mx-auto max-w-2xl text-center">
          <h2 class="text-3xl font-bold tracking-tight">Diseñada para Cómo Administras tu Dinero</h2>
          <p class="mt-4 text-muted-foreground">
            Diseñada cuidadosamente alrededor de flujos reales de finanzas personales, no de contabilidad corporativa.
          </p>
        </div>
        <div class="mx-auto mt-16 grid max-w-4xl gap-8 sm:grid-cols-2">
          <div class="flex gap-4">
            <div class="mt-1 shrink-0">
              <div class="flex size-8 items-center justify-center rounded-full bg-primary/10">
                <ShieldCheck class="size-4 text-primary" />
              </div>
            </div>
            <div>
              <h3 class="font-semibold">Tus Datos, Siempre Seguros</h3>
              <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Tu información financiera está protegida con los más altos estándares de seguridad. Datos encriptados,
                acceso exclusivo a tu cuenta — sin terceros.
              </p>
            </div>
          </div>
          <div class="flex gap-4">
            <div class="mt-1 shrink-0">
              <div class="flex size-8 items-center justify-center rounded-full bg-primary/10">
                <Smartphone class="size-4 text-primary" />
              </div>
            </div>
            <div>
              <h3 class="font-semibold">Funciona en Todas Partes</h3>
              <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Completo en escritorio. Funciona también en móvil y tablet — la interfaz responsive se adapta a
                cualquier tamaño de pantalla.
              </p>
            </div>
          </div>
          <div class="flex gap-4">
            <div class="mt-1 shrink-0">
              <div class="flex size-8 items-center justify-center rounded-full bg-primary/10">
                <Zap class="size-4 text-primary" />
              </div>
            </div>
            <div>
              <h3 class="font-semibold">Eficiencia con Atajos de Teclado</h3>
              <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Presiona <kbd class="rounded border bg-muted px-1.5 py-0.5 font-mono text-xs">N</kbd> para agregar un
                movimiento, usa las flechas para navegar entre meses. Los usuarios avanzados vuelan a través de los
                registros diarios.
              </p>
            </div>
          </div>
          <div class="flex gap-4">
            <div class="mt-1 shrink-0">
              <div class="flex size-8 items-center justify-center rounded-full bg-primary/10">
                <TrendingUp class="size-4 text-primary" />
              </div>
            </div>
            <div>
              <h3 class="font-semibold">Planifica con Confianza</h3>
              <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                Las plantillas de transacciones recurrentes generan movimientos proyectados automáticamente. Ve
                exactamente dónde estará tu saldo en días, semanas o meses.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="border-t bg-muted/30 py-24">
      <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold tracking-tight">¿Listo para Dominar tus Finanzas?</h2>
        <p class="mx-auto mt-4 max-w-xl text-muted-foreground">
          Deja de preguntarte a dónde va tu dinero. Empieza a registrar cada Sol con claridad y propósito.
        </p>
        <div class="mt-8">
          <Link :href="register()">
            <Button
              size="lg"
              class="text-base"
            >
              Crea tu Cuenta Gratis
            </Button>
          </Link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="border-t py-8">
      <div class="container mx-auto flex flex-col items-center justify-between gap-4 px-4 sm:flex-row">
        <div class="flex items-center gap-2 text-sm text-muted-foreground">
          <Wallet class="size-4" />
          <span>Aquora Cash &mdash; Gestor de Finanzas Personales</span>
        </div>
        <div class="flex items-center gap-6 text-sm text-muted-foreground">
          <span>Construido con por Alexander Pomareda</span>
        </div>
      </div>
    </footer>
  </div>
</template>
