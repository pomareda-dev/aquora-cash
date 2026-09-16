<script setup lang="ts">
import { SidebarGroup, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
  items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
  <SidebarGroup class="px-2 py-0">
    <SidebarMenu>
      <SidebarMenuItem
        v-for="item in items"
        :key="item.title"
        :data-tour="item.tourId"
      >
        <SidebarMenuButton
          as-child
          :is-active="isCurrentUrl(item.href)"
          :tooltip="item.title"
        >
          <Link :href="item.href">
            <component :is="item.icon" />
            <span>{{ item.title }}</span>
          </Link>
        </SidebarMenuButton>
      </SidebarMenuItem>
    </SidebarMenu>
  </SidebarGroup>
</template>
