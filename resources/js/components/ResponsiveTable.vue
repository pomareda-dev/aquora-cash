<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useSettings } from '@/composables/useSettings';
import { GripVertical } from '@lucide/vue';
import { computed, ref, useId, useSlots } from 'vue';
import Draggable from 'vuedraggable';

export interface ResponsiveColumn {
  key: string;
  header: string;
  align?: 'left' | 'right' | 'center';
  hideOnMobile?: boolean;
  primary?: boolean;
  dragHandle?: boolean;
  className?: string;
  headerClassName?: string;
}

const props = withDefaults(
  defineProps<{
    columns: ResponsiveColumn[];
    rows: Record<string, unknown>[];
    rowKey: string;
    draggable?: boolean;
    containerClass?: string;
    tableClass?: string;
    mobileInitialCount?: number;
  }>(),
  {
    draggable: false,
    containerClass: 'rounded-md border',
    tableClass: '',
    mobileInitialCount: 0,
  }
);

const emit = defineEmits<{
  reorder: [ids: number[]];
}>();

const slots = useSlots();
const { densityClass } = useSettings();

const visibleDesktopColumns = computed(() => props.columns);

const mobileExpanded = ref(false);
const mobileListId = useId();

const mobileVisibleRows = computed(() =>
  props.mobileInitialCount <= 0 || mobileExpanded.value ? props.rows : props.rows.slice(0, props.mobileInitialCount)
);

const mobileHiddenCount = computed(() => Math.max(props.rows.length - props.mobileInitialCount, 0));

const hasHiddenMobileRows = computed(() => props.mobileInitialCount > 0 && mobileHiddenCount.value > 0);

const hasActions = computed(() => Boolean(slots.actions));

const primaryColumn = computed<ResponsiveColumn | null>(
  () =>
    props.columns.find(column => column.primary) ??
    props.columns.find(column => !column.hideOnMobile && !column.dragHandle) ??
    null
);

const mobileFields = computed(() =>
  props.columns.filter(column => !column.dragHandle && !column.hideOnMobile && column.key !== primaryColumn.value?.key)
);

const colspanDesktop = computed(() => props.columns.length + (hasActions.value ? 1 : 0));

function alignClass(align?: ResponsiveColumn['align']): string {
  switch (align) {
    case 'right':
      return 'text-right';
    case 'center':
      return 'text-center';
    default:
      return '';
  }
}

function rowKeyValue(row: Record<string, unknown>): string {
  return String(row[props.rowKey]);
}

function handleDragEnd() {
  emit(
    'reorder',
    props.rows.map(row => row[props.rowKey] as number)
  );
}
</script>

<template>
  <div>
    <!-- DESKTOP -->
    <div :class="['hidden md:block', containerClass]">
      <table
        class="w-full caption-bottom text-sm"
        :class="tableClass"
      >
        <thead>
          <tr>
            <th
              v-for="col in visibleDesktopColumns"
              :key="col.key"
              scope="col"
              :class="[densityClass.header, col.headerClassName, alignClass(col.align)]"
            >
              {{ col.header }}
            </th>
            <th
              v-if="hasActions"
              :class="densityClass.header"
            />
          </tr>
        </thead>

        <Draggable
          v-if="draggable"
          :list="rows"
          tag="tbody"
          :handle="'.drag-handle'"
          :animation="150"
          :item-key="rowKey"
          @end="handleDragEnd"
        >
          <template #item="{ element, index }">
            <tr :class="['border-b transition-colors hover:bg-muted/50']">
              <template
                v-for="col in visibleDesktopColumns"
                :key="col.key"
              >
                <td
                  v-if="col.dragHandle"
                  :class="[densityClass.cell, 'p-0 pl-2']"
                >
                  <GripVertical
                    class="drag-handle size-4 cursor-grab text-muted-foreground/50 transition-colors hover:text-muted-foreground active:cursor-grabbing"
                  />
                </td>
                <td
                  v-else
                  :class="[densityClass.cell, alignClass(col.align), col.className]"
                >
                  <slot
                    :name="'cell-' + col.key"
                    :row="element"
                    :column="col"
                    :index="index"
                  >
                    {{ element[col.key] ?? '—' }}
                  </slot>
                </td>
              </template>
              <td
                v-if="hasActions"
                :class="densityClass.cell"
              >
                <slot
                  name="actions"
                  :row="element"
                />
              </td>
            </tr>
          </template>
        </Draggable>
        <tbody v-if="draggable && rows.length === 0">
          <tr>
            <td
              :colspan="colspanDesktop"
              class="py-8 text-center text-muted-foreground"
            >
              <slot name="empty" />
            </td>
          </tr>
        </tbody>
        <tbody v-if="!draggable">
          <template
            v-for="(row, index) in rows"
            :key="rowKeyValue(row)"
          >
            <tr :class="['border-b transition-colors hover:bg-muted/50']">
              <template
                v-for="col in visibleDesktopColumns"
                :key="col.key"
              >
                <td
                  v-if="col.dragHandle"
                  :class="[densityClass.cell, 'p-0 pl-2']"
                >
                  <GripVertical
                    class="drag-handle size-4 cursor-grab text-muted-foreground/50 transition-colors hover:text-muted-foreground active:cursor-grabbing"
                  />
                </td>
                <td
                  v-else
                  :class="[densityClass.cell, alignClass(col.align), col.className]"
                >
                  <slot
                    :name="'cell-' + col.key"
                    :row="row"
                    :column="col"
                    :index="index"
                  >
                    {{ row[col.key] ?? '—' }}
                  </slot>
                </td>
              </template>
              <td
                v-if="hasActions"
                :class="densityClass.cell"
              >
                <slot
                  name="actions"
                  :row="row"
                />
              </td>
            </tr>
          </template>
          <tr v-if="rows.length === 0">
            <td
              :colspan="colspanDesktop"
              class="py-8 text-center text-muted-foreground"
            >
              <slot name="empty" />
            </td>
          </tr>
        </tbody>
        <slot name="footer" />
      </table>
    </div>

    <!-- MOBILE -->
    <div
      v-if="rows.length > 0"
      :id="mobileListId"
      class="flex flex-col gap-2 md:hidden"
    >
      <div
        v-for="(row, index) in mobileVisibleRows"
        :key="rowKeyValue(row)"
        class="rounded-lg border p-3"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div
              v-if="primaryColumn"
              :class="['text-sm font-semibold', primaryColumn.className]"
            >
              <slot
                :name="'card-' + primaryColumn.key"
                :row="row"
                :column="primaryColumn"
                :index="index"
              >
                <slot
                  :name="'cell-' + primaryColumn.key"
                  :row="row"
                  :column="primaryColumn"
                  :index="index"
                >
                  {{ row[primaryColumn.key] ?? '—' }}
                </slot>
              </slot>
            </div>
            <div
              v-for="col in mobileFields"
              :key="col.key"
              class="mt-1.5 flex items-baseline justify-between gap-2 text-xs"
            >
              <span class="text-muted-foreground">{{ col.header }}</span>
              <span :class="[col.className, col.align === 'right' ? 'text-right tabular-nums' : '']">
                <slot
                  :name="'card-' + col.key"
                  :row="row"
                  :column="col"
                  :index="index"
                >
                  <slot
                    :name="'cell-' + col.key"
                    :row="row"
                    :column="col"
                    :index="index"
                  >
                    {{ row[col.key] ?? '—' }}
                  </slot>
                </slot>
              </span>
            </div>
          </div>
          <div
            v-if="hasActions"
            class="flex shrink-0 items-center gap-1"
          >
            <slot
              name="actions"
              :row="row"
            />
          </div>
        </div>
      </div>

      <Button
        v-if="hasHiddenMobileRows"
        variant="ghost"
        type="button"
        class="h-11 w-full"
        :aria-expanded="mobileExpanded"
        :aria-controls="mobileListId"
        @click="mobileExpanded = !mobileExpanded"
      >
        {{ mobileExpanded ? 'Ver menos' : `Ver ${mobileHiddenCount} más` }}
      </Button>
    </div>
    <div
      v-else
      class="rounded-lg border p-4 text-center text-sm text-muted-foreground md:hidden"
    >
      <slot name="empty" />
    </div>
  </div>
</template>
