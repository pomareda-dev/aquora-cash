<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Filler,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler);

const props = defineProps<{
    data: { date: string; balance: number }[];
    simulatedData?: { date: string; balance: number }[] | null;
}>();

// Canvas does not resolve CSS var() — and this project's tokens store
// `hsl(...)` literals (not bare H S L triplets), so wrapping them again
// produces `hsl(hsl(...))` which addColorStop rejects. Use the dummy-element
// trick: set `color: var(--token)` on a hidden node and read the computed
// value back, which the browser normalises to `rgb(r, g, b)`. Then convert to
// `rgba()` for alpha. This works regardless of how the variable is declared.
function resolveTokenColor(name: string, fallback: string): string {
    if (typeof window === 'undefined') {
        return fallback;
    }
    const el = document.createElement('div');
    el.style.color = `var(${name})`;
    el.style.display = 'none';
    document.body.appendChild(el);
    const resolved = getComputedStyle(el).color;
    document.body.removeChild(el);
    return resolved || fallback;
}

function resolveTokenWithAlpha(name: string, alpha: number, fallback: string): string {
    const rgb = resolveTokenColor(name, '');
    if (!rgb) {
        return fallback;
    }
    const match = rgb.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
    if (match) {
        return `rgba(${match[1]}, ${match[2]}, ${match[3]}, ${alpha})`;
    }
    return fallback;
}

const chartData = computed(() => ({
    labels: props.data.map((d) => d.date.split('-')[2]),
    datasets: [
        {
            label: 'Balance real',
            data: props.data.map((d) => d.balance),
            borderColor: resolveTokenColor('--primary', 'rgb(23, 23, 23)'),
            backgroundColor: (ctx: any) => {
                const { chart } = ctx;
                if (!chart.chartArea) {
                    return resolveTokenWithAlpha('--primary', 0.3, 'rgba(23, 23, 23, 0.3)');
                }
                const gradient = chart.ctx.createLinearGradient(
                    0,
                    chart.chartArea.top,
                    0,
                    chart.chartArea.bottom,
                );
                gradient.addColorStop(
                    0,
                    resolveTokenWithAlpha('--primary', 0.3, 'rgba(23, 23, 23, 0.3)'),
                );
                gradient.addColorStop(
                    1,
                    resolveTokenWithAlpha('--primary', 0.02, 'rgba(23, 23, 23, 0.02)'),
                );
                return gradient;
            },
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointHoverRadius: 5,
            borderWidth: 2,
        },
        ...(props.simulatedData && props.simulatedData.length > 0
            ? [
                {
                    label: 'Con simulación',
                    data: props.simulatedData.map((d) => d.balance),
                    borderColor: resolveTokenColor('--amber-500', 'rgb(245, 158, 11)'),
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.3,
                    fill: false,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                },
            ]
            : []),
    ],
}));

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: !!(props.simulatedData && props.simulatedData.length > 0),
            position: 'top' as const,
            labels: {
                boxWidth: 12,
                boxHeight: 2,
                font: { size: 11 },
                usePointStyle: false,
            },
        },
        tooltip: {
            callbacks: {
                title: (items: any[]) => {
                    if (items.length > 0) {
                        const idx = items[0].dataIndex;
                        return props.data[idx]?.date ?? '';
                    }
                    return '';
                },
                label: (item: any) => {
                    const value = item.raw as number;
                    return new Intl.NumberFormat('es-PE', {
                        style: 'currency',
                        currency: 'PEN',
                        minimumFractionDigits: 2,
                    }).format(value);
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: false,
            },
            ticks: {
                maxTicksLimit: 10,
                font: {
                    size: 11,
                },
            },
        },
        y: {
            grid: {
                color: resolveTokenWithAlpha('--border', 0.5, 'rgba(23, 23, 23, 0.1)'),
            },
            ticks: {
                maxTicksLimit: 6,
                font: {
                    size: 11,
                },
                callback: (value: any) => {
                    return new Intl.NumberFormat('es-PE', {
                        style: 'currency',
                        currency: 'PEN',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0,
                    }).format(value);
                },
            },
        },
    },
    interaction: {
        intersect: false,
        mode: 'index' as const,
    },
}));
</script>

<template>
    <div
        v-if="data.length === 0"
        class="flex h-64 items-center justify-center text-sm text-muted-foreground"
    >
        No hay datos para este mes.
    </div>
    <div v-else class="relative h-64 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
