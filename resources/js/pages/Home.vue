<script setup lang="ts">
import AppLayout from '@/pages/layout/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import type { ApexOptions } from 'apexcharts';
import VueApexCharts from 'vue3-apexcharts';
import { computed, ref } from 'vue';

type KpiData = {
    present: number;
    late: number;
    absent: number;
    on_leave: number;
    overtime: number;
};

type ChartSeries = { name: string; data: number[] }[];

type ChartData = {
    categories: string[];
    series: ChartSeries;
};

type DistributionData = {
    labels: string[];
    series: number[];
};

type LateEmployee = {
    employee_number: string | null;
    name: string;
    late_minutes: number;
};

type HeatmapEntry = { x: string; y: number };
type HeatmapSeries = { name: string; data: HeatmapEntry[] }[];

type HeatmapData = {
    dates: string[];
    series: HeatmapSeries;
};

const props = defineProps<{
    range: number;
    kpis: KpiData;
    attendanceOverview: ChartData;
    lateArrivalsTrend: ChartData;
    departmentChart: ChartData;
    distributionChart: DistributionData;
    todaysLateEmployees: LateEmployee[];
    heatmap: HeatmapData;
}>();

const isDark = ref(document.documentElement.getAttribute('data-bs-theme') === 'dark');

const chartColors = {
    present: '#50cd89',
    absent: '#f1416c',
    late: '#ffc700',
    info: '#009ef7',
};

const selectedRange = ref(props.range);

function applyRange(range: number) {
    selectedRange.value = range;
    router.get('/', { range }, { preserveState: true, replace: true });
}

// ── Attendance Overview ──────────────────────────────────────────────────────

const overviewOptions = computed((): ApexOptions => ({
    chart: { type: 'bar', stacked: false, toolbar: { show: false }, background: 'transparent' },
    colors: [chartColors.present, chartColors.absent, chartColors.late],
    xaxis: { categories: props.attendanceOverview.categories, labels: { style: { fontSize: '11px' } } },
    yaxis: { min: 0, labels: { style: { fontSize: '11px' } } },
    legend: { position: 'top' },
    plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
    dataLabels: { enabled: false },
    grid: { borderColor: '#f1f1f1' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
}));

// ── Late Arrivals Trend ──────────────────────────────────────────────────────

const lateOptions = computed((): ApexOptions => ({
    chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
    colors: [chartColors.late],
    xaxis: { categories: props.lateArrivalsTrend.categories, labels: { style: { fontSize: '11px' } } },
    yaxis: { min: 0, labels: { style: { fontSize: '11px' } } },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.05 } },
    dataLabels: { enabled: false },
    grid: { borderColor: '#f1f1f1' },
    legend: { show: false },
    theme: { mode: isDark.value ? 'dark' : 'light' },
}));

// ── Department Chart ─────────────────────────────────────────────────────────

const departmentOptions = computed((): ApexOptions => ({
    chart: { type: 'bar', stacked: true, toolbar: { show: false }, background: 'transparent' },
    colors: [chartColors.present, chartColors.absent, chartColors.late],
    xaxis: { categories: props.departmentChart.categories, labels: { style: { fontSize: '11px' } } },
    yaxis: { labels: { style: { fontSize: '11px' } } },
    plotOptions: { bar: { horizontal: true, borderRadius: 3 } },
    dataLabels: { enabled: false },
    legend: { position: 'top' },
    grid: { borderColor: '#f1f1f1' },
    theme: { mode: isDark.value ? 'dark' : 'light' },
}));

// ── Distribution Donut ───────────────────────────────────────────────────────

const distributionOptions = computed((): ApexOptions => ({
    chart: { type: 'donut', background: 'transparent' },
    labels: props.distributionChart.labels,
    colors: [chartColors.present, chartColors.absent, chartColors.late, chartColors.info, '#7239ea', '#fd7e14'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val: number) => `${Math.round(val)}%` },
    plotOptions: { pie: { donut: { size: '65%' } } },
    theme: { mode: isDark.value ? 'dark' : 'light' },
}));

// ── Heatmap ──────────────────────────────────────────────────────────────────

const heatmapOptions = computed((): ApexOptions => ({
    chart: { type: 'heatmap', toolbar: { show: false }, background: 'transparent' },
    dataLabels: { enabled: false },
    colors: ['#f1416c'],
    xaxis: {
        labels: {
            style: { fontSize: '10px' },
            rotate: -45,
        },
    },
    yaxis: { labels: { style: { fontSize: '10px' } } },
    plotOptions: {
        heatmap: {
            shadeIntensity: 0.5,
            colorScale: {
                ranges: [
                    { from: -1, to: -1, name: 'No Record', color: '#e9ecef' },
                    { from: 0, to: 0, name: 'Absent', color: '#f1416c' },
                    { from: 1, to: 1, name: 'Late', color: '#ffc700' },
                    { from: 2, to: 2, name: 'Present', color: '#50cd89' },
                ],
            },
        },
    },
    theme: { mode: isDark.value ? 'dark' : 'light' },
    tooltip: {
        custom: ({ series, seriesIndex, dataPointIndex, w }: { series: number[][], seriesIndex: number, dataPointIndex: number, w: Record<string, unknown> }) => {
            const val = series[seriesIndex][dataPointIndex];
            const label = val === -1 ? 'No Record' : val === 0 ? 'Absent' : val === 1 ? 'Late' : 'Present';
            const empName = (w as { globals: { seriesNames: string[] } }).globals.seriesNames[seriesIndex];
            const date = props.heatmap.dates[dataPointIndex] ?? '';
            return `<div class="p-2 small"><strong>${empName}</strong><br/>${date}: ${label}</div>`;
        },
    },
}));

function formatMinutes(minutes: number): string {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}
</script>

<template>
    <Head title="Dashboard" />
   
        <!--begin::Content-->
        <div class="container-fluid py-6 px-6 px-lg-8">

            <!--begin::Toolbar-->
            <div class="d-flex align-items-center justify-content-between mb-6 gap-3 flex-wrap">
                <div>
                    <h1 class="fs-2 fw-bold text-gray-900 mb-1">Attendance Dashboard</h1>
                    <span class="text-muted fs-6">Today's overview &amp; trends</span>
                </div>
                <div class="btn-group">
                    <button
                        class="btn btn-sm"
                        :class="selectedRange === 7 ? 'btn-primary' : 'btn-light'"
                        @click="applyRange(7)"
                    >
                        Last 7 days
                    </button>
                    <button
                        class="btn btn-sm"
                        :class="selectedRange === 30 ? 'btn-primary' : 'btn-light'"
                        @click="applyRange(30)"
                    >
                        Last 30 days
                    </button>
                </div>
            </div>
            <!--end::Toolbar-->

            <!--begin::KPI Cards-->
            <div class="row g-4 mb-6">
                <!--Present Today-->
                <div class="col-6 col-md-4 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-4">
                            <div class="symbol symbol-50px">
                                <span class="symbol-label bg-light-success">
                                    <i class="ki-outline ki-people fs-2x text-success"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-900">{{ kpis.present }}</div>
                                <div class="text-muted fs-7 fw-semibold">Present Today</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Late Employees-->
                <div class="col-6 col-md-4 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-4">
                            <div class="symbol symbol-50px">
                                <span class="symbol-label bg-light-warning">
                                    <i class="ki-outline ki-time fs-2x text-warning"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-900">{{ kpis.late }}</div>
                                <div class="text-muted fs-7 fw-semibold">Late Employees</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Absent Employees-->
                <div class="col-6 col-md-4 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-4">
                            <div class="symbol symbol-50px">
                                <span class="symbol-label bg-light-danger">
                                    <i class="ki-outline ki-user-cross fs-2x text-danger"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-900">{{ kpis.absent }}</div>
                                <div class="text-muted fs-7 fw-semibold">Absent Employees</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--On Leave-->
                <div class="col-6 col-md-4 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-4">
                            <div class="symbol symbol-50px">
                                <span class="symbol-label bg-light-info">
                                    <i class="ki-outline ki-calendar fs-2x text-info"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-900">{{ kpis.on_leave }}</div>
                                <div class="text-muted fs-7 fw-semibold">On Leave</div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Overtime Today-->
                <div class="col-6 col-md-4 col-xl">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex align-items-center gap-4">
                            <div class="symbol symbol-50px">
                                <span class="symbol-label bg-light-primary">
                                    <i class="ki-outline ki-graph-up fs-2x text-primary"></i>
                                </span>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-900">{{ kpis.overtime }}</div>
                                <div class="text-muted fs-7 fw-semibold">Overtime Today</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::KPI Cards-->

            <!--begin::Row 1 Charts-->
            <div class="row g-4 mb-6">
                <!--Attendance Overview-->
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Attendance Overview</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Present / Absent / Late per day</span>
                            </h3>
                        </div>
                        <div class="card-body pt-2">
                            <VueApexCharts
                                v-if="attendanceOverview.categories.length"
                                type="bar"
                                height="280"
                                :options="overviewOptions"
                                :series="attendanceOverview.series"
                            />
                            <div v-else class="d-flex align-items-center justify-content-center" style="height:280px;">
                                <span class="text-muted">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Attendance Distribution-->
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Attendance Distribution</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Status breakdown for period</span>
                            </h3>
                        </div>
                        <div class="card-body pt-2 d-flex align-items-center justify-content-center">
                            <VueApexCharts
                                v-if="distributionChart.series.length"
                                type="donut"
                                height="280"
                                :options="distributionOptions"
                                :series="distributionChart.series"
                            />
                            <div v-else class="d-flex align-items-center justify-content-center" style="height:280px;">
                                <span class="text-muted">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row 1 Charts-->

            <!--begin::Row 2 Charts-->
            <div class="row g-4 mb-6">
                <!--Department Attendance-->
                <div class="col-12 col-xl-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Department Attendance</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Today's breakdown by department</span>
                            </h3>
                        </div>
                        <div class="card-body pt-2">
                            <VueApexCharts
                                v-if="departmentChart.categories.length"
                                type="bar"
                                height="280"
                                :options="departmentOptions"
                                :series="departmentChart.series"
                            />
                            <div v-else class="d-flex align-items-center justify-content-center" style="height:280px;">
                                <span class="text-muted">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--Late Arrivals Trend-->
                <div class="col-12 col-xl-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Late Arrivals Trend</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Number of late employees per day</span>
                            </h3>
                        </div>
                        <div class="card-body pt-2">
                            <VueApexCharts
                                v-if="lateArrivalsTrend.categories.length"
                                type="area"
                                height="280"
                                :options="lateOptions"
                                :series="lateArrivalsTrend.series"
                            />
                            <div v-else class="d-flex align-items-center justify-content-center" style="height:280px;">
                                <span class="text-muted">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row 2 Charts-->

            <!--begin::Row 3 - Table + Heatmap-->
            <div class="row g-4 mb-6">
                <!--Today's Late Employees Table-->
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Today's Late Employees</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Sorted by late minutes</span>
                            </h3>
                        </div>
                        <div class="card-body pt-2">
                            <div v-if="todaysLateEmployees.length" class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                    <thead>
                                        <tr class="fw-bold text-muted">
                                            <th class="min-w-80px">Emp #</th>
                                            <th class="min-w-140px">Name</th>
                                            <th class="min-w-80px text-end">Late</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="emp in todaysLateEmployees" :key="emp.employee_number ?? emp.name">
                                            <td>
                                                <span class="text-muted fs-7">{{ emp.employee_number ?? '—' }}</span>
                                            </td>
                                            <td>
                                                <span class="text-gray-900 fw-semibold fs-7">{{ emp.name }}</span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-light-warning fs-8">
                                                    {{ formatMinutes(emp.late_minutes) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="d-flex flex-column align-items-center justify-content-center py-10 gap-3">
                                <i class="ki-outline ki-check-circle fs-3x text-success"></i>
                                <span class="text-muted fs-6">No late employees today</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!--Attendance Heatmap-->
                <div class="col-12 col-xl-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fw-bold fs-5 text-gray-900">Attendance Heatmap</span>
                                <span class="text-muted mt-1 fw-semibold fs-7">Per-employee status over period</span>
                            </h3>
                            <div class="card-toolbar gap-3">
                                <span class="d-flex align-items-center gap-1 fs-8 text-muted">
                                    <span class="w-12px h-12px rounded-1 bg-success d-inline-block"></span> Present
                                </span>
                                <span class="d-flex align-items-center gap-1 fs-8 text-muted">
                                    <span class="w-12px h-12px rounded-1 d-inline-block" style="background:#ffc700"></span> Late
                                </span>
                                <span class="d-flex align-items-center gap-1 fs-8 text-muted">
                                    <span class="w-12px h-12px rounded-1 bg-danger d-inline-block"></span> Absent
                                </span>
                            </div>
                        </div>
                        <div class="card-body pt-2" style="overflow-x: auto;">
                            <VueApexCharts
                                v-if="heatmap.series.length"
                                type="heatmap"
                                :height="Math.max(200, heatmap.series.length * 28)"
                                :options="heatmapOptions"
                                :series="heatmap.series"
                            />
                            <div v-else class="d-flex align-items-center justify-content-center" style="height:200px;">
                                <span class="text-muted">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Row 3-->

        </div>
        <!--end::Content-->
 
</template>
