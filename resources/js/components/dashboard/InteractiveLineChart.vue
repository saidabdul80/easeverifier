<script setup lang="ts">
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

const props = withDefaults(defineProps<{
    labels: string[];
    values: number[];
    color?: string;
    fillColor?: string;
    currency?: boolean;
    showAxes?: boolean;
    label?: string;
}>(), {
    color: '#16ae71',
    fillColor: 'rgba(22, 174, 113, .12)',
    currency: false,
    showAxes: false,
    label: 'Value',
});

const formatValue = (value: number) => props.currency
    ? new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 }).format(value)
    : new Intl.NumberFormat('en-NG').format(value);

const externalTooltip = ({ chart, tooltip }: any) => {
    let element = document.getElementById('dashboard-chart-tooltip');

    if (!element) {
        element = document.createElement('div');
        element.id = 'dashboard-chart-tooltip';
        Object.assign(element.style, {
            position: 'fixed',
            zIndex: '9999',
            pointerEvents: 'none',
            minWidth: '120px',
            padding: '9px 11px',
            borderRadius: '6px',
            background: '#091737',
            color: '#ffffff',
            boxShadow: '0 8px 24px rgba(9, 23, 55, .24)',
            fontSize: '12px',
            lineHeight: '1.4',
            transition: 'opacity .08s ease',
        });
        document.body.appendChild(element);
    }

    if (tooltip.opacity === 0) {
        element.style.opacity = '0';
        return;
    }

    const title = document.createElement('strong');
    title.style.display = 'block';
    title.style.marginBottom = '3px';
    title.textContent = tooltip.title?.[0] || '';

    const body = document.createElement('span');
    const value = Number(tooltip.dataPoints?.[0]?.raw || 0);
    body.textContent = `${props.label}: ${formatValue(value)}`;
    element.replaceChildren(title, body);

    const rect = chart.canvas.getBoundingClientRect();
    const tooltipWidth = element.offsetWidth;
    const tooltipHeight = element.offsetHeight;
    const desiredLeft = rect.left + tooltip.caretX - tooltipWidth / 2;
    const desiredTop = rect.top + tooltip.caretY - tooltipHeight - 12;
    element.style.left = `${Math.max(8, Math.min(desiredLeft, window.innerWidth - tooltipWidth - 8))}px`;
    element.style.top = `${Math.max(8, desiredTop)}px`;
    element.style.opacity = '1';
};

const data = computed(() => ({
    labels: props.labels,
    datasets: [{
        label: props.label,
        data: props.values,
        borderColor: props.color,
        backgroundColor: props.fillColor,
        borderWidth: props.showAxes ? 2.5 : 2,
        fill: true,
        tension: .32,
        pointRadius: props.showAxes ? 3 : 0,
        pointHoverRadius: 5,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: props.color,
        pointBorderWidth: 2,
    }],
}));

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    animation: { duration: 450 },
    plugins: {
        legend: { display: false },
        tooltip: {
            enabled: false,
            external: externalTooltip,
            displayColors: false,
            backgroundColor: '#091737',
            titleColor: '#ffffff',
            bodyColor: '#ffffff',
            padding: 10,
            cornerRadius: 6,
            callbacks: {
                label: (context: any) => `${props.label}: ${formatValue(Number(context.raw || 0))}`,
            },
        },
    },
    scales: {
        x: {
            display: props.showAxes,
            grid: { display: false },
            border: { display: false },
            ticks: { color: '#7483a4', font: { size: 9 }, maxRotation: 0 },
        },
        y: {
            display: props.showAxes,
            beginAtZero: true,
            grid: { color: '#e7edf5' },
            border: { display: false },
            ticks: {
                color: '#7483a4',
                font: { size: 9 },
                maxTicksLimit: 4,
                callback: (value: string | number) => props.currency
                    ? `₦${Number(value) >= 1000 ? `${Math.round(Number(value) / 1000)}k` : value}`
                    : value,
            },
        },
    },
}));
</script>

<template>
    <Line :data="data" :options="options" />
</template>
