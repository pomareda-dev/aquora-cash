<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import categorias from '@/routes/categorias';
import cuentas from '@/routes/cuentas';
import deudas from '@/routes/deudas';
import metas from '@/routes/metas';
import movimientos from '@/routes/movimientos';
import proyeccion from '@/routes/proyeccion';
import recurrentes from '@/routes/recurrentes';
import type { NavItem } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, HandCoins, LayoutDashboard, LineChart, Repeat, Tags, Target, Wallet } from '@lucide/vue';
import { onUnmounted } from 'vue';

const mainNavItems: NavItem[] = [
  {
    title: 'Tablero',
    href: dashboard(),
    icon: LayoutDashboard,
  },
  {
    title: 'Movimientos',
    href: movimientos.index(),
    icon: ArrowLeftRight,
    tourId: 'nav.movimientos',
  },
  {
    title: 'Categorías',
    href: categorias.index(),
    icon: Tags,
    tourId: 'nav.categorias',
  },
  {
    title: 'Cuentas',
    href: cuentas.index(),
    icon: Wallet,
    tourId: 'nav.cuentas',
  },
  {
    title: 'Recurrentes',
    href: recurrentes.index(),
    icon: Repeat,
    tourId: 'nav.recurrentes',
  },
  {
    title: 'Deudas',
    href: deudas.index(),
    icon: HandCoins,
  },
  {
    title: 'Metas',
    href: metas.index(),
    icon: Target,
  },
  {
    title: 'Proyección',
    href: proyeccion.index(),
    icon: LineChart,
  },
];

// Close the mobile sidebar whenever Inertia navigates (nav item clicks, logo
// click, back/forward history). The mobile sidebar renders as a Sheet, which
// would otherwise stay open on top of the new page.
const { isMobile, openMobile, setOpenMobile } = useSidebar();

const offNavigate = router.on('navigate', () => {
  if (isMobile.value && openMobile.value) {
    setOpenMobile(false);
  }
});

onUnmounted(offNavigate);
</script>

<template>
  <Sidebar
    collapsible="icon"
    variant="inset"
    data-tour="shell.sidebar"
  >
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton
            size="lg"
            as-child
          >
            <Link :href="dashboard()">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter>
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>
