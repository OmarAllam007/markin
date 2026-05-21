<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { departmentAttendance as departmentAttendanceRoute } from '@/routes/reports/index';
import { Head, useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type FilterOption = { id: number; name: string };

type EmployeeStat = {
    employee_id: number;
    employee_number: string | null;
    name: string;
    total_days: number;
    present: number;
    late: number;
    absent: number;
    leave: number;
    off_days: number;
    total_late_minutes: number;
    overtime_minutes: number;
    attendance_rate: number;
};

type DepartmentRow = {
    department: string;
    employee_count: number;
    total_records: number;
    present: number;
    late: number;
    absent: number;
    leave: number;
    off_days: number;
    attendance_rate: number;
    employees: EmployeeStat[];
};

const props = defineProps<{
    departments: DepartmentRow[];
    summary: {
        department_count: number;
        overall_attendance_rate: number;
    };
    filters: {
        date_from: string;
        date_to: string;
        location_id: number | null;
    };
    filterOptions: {
        locations: FilterOption[];
    };
}>();

// ── View tab ──────────────────────────────────────────────────────────────────
const activeView = ref<'detailed' | 'summary'>('detailed');

// ── Filter form ───────────────────────────────────────────────────────────────
const filterForm = useForm({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    location_id: props.filters.location_id,
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.date_from) q.date_from = filterForm.date_from;
    if (filterForm.date_to) q.date_to = filterForm.date_to;
    if (filterForm.location_id) q.location_id = String(filterForm.location_id);
    filterForm.get(departmentAttendanceRoute.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['departments', 'summary', 'filters'],
    });
};

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    Object.entries(props.filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined) q.set(k, String(v));
    });
    q.set('export', 'xlsx');
    return departmentAttendanceRoute.url() + '?' + q.toString();
});

// ── Expandable rows ───────────────────────────────────────────────────────────
const expandedRows = ref<Set<string>>(new Set());
const toggleRow = (dept: string) => {
    if (expandedRows.value.has(dept)) {
        expandedRows.value.delete(dept);
    } else {
        expandedRows.value.add(dept);
    }
};

// ── Location dropdown ─────────────────────────────────────────────────────────
const locationSearch = ref('');
const locationOpen = ref(false);
const locationDropdownRef = ref<HTMLElement | null>(null);
onClickOutside(locationDropdownRef, () => { locationOpen.value = false; locationSearch.value = ''; });

const filteredLocations = computed(() =>
    props.filterOptions.locations.filter((l) => l.name.toLowerCase().includes(locationSearch.value.toLowerCase())),
);
const selectedLocationName = computed(
    () => props.filterOptions.locations.find((l) => l.id === filterForm.location_id)?.name ?? 'All Locations',
);
const selectLocation = (id: number | null) => {
    filterForm.location_id = id; locationOpen.value = false; locationSearch.value = ''; submitFilters();
};

// ── Helpers ───────────────────────────────────────────────────────────────────
function rateBadgeClass(rate: number): string {
    if (rate >= 90) return 'badge-light-success';
    if (rate >= 75) return 'badge-light-warning';
    return 'badge-light-danger';
}

function fmtMinutes(minutes: number): string {
    if (minutes === 0) return '—';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

// ── Charts (summary view) ─────────────────────────────────────────────────────
const rateChartRef = ref<HTMLElement | null>(null);
const stackedChartRef = ref<HTMLElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let rateChartInstance: any = null;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let stackedChartInstance: any = null;

function buildRateChartOptions() {
    const sorted = [...props.departments].sort((a, b) => b.attendance_rate - a.attendance_rate).slice(0, 10);
    return {
        series: [{ name: 'Attendance Rate', data: sorted.map((d) => d.attendance_rate) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '60%' } },
        colors: ['#50CD89'],
        dataLabels: { enabled: true, formatter: (val: number) => `${val}%`, style: { fontSize: '12px' } },
        xaxis: {
            categories: sorted.map((d) => d.department),
            labels: { style: { fontSize: '12px' }, formatter: (val: string) => `${val}%` },
            min: 0,
            max: 100,
        },
        yaxis: { labels: { style: { fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1' },
        tooltip: { y: { formatter: (val: number) => `${val}%` } },
    };
}

function buildStackedChartOptions() {
    const sorted = [...props.departments].slice(0, 8);
    return {
        series: [
            { name: 'Present', data: sorted.map((d) => d.present) },
            { name: 'Late', data: sorted.map((d) => d.late) },
            { name: 'Absent', data: sorted.map((d) => d.absent) },
            { name: 'Leave', data: sorted.map((d) => d.leave) },
            { name: 'Off Days', data: sorted.map((d) => d.off_days) },
        ],
        chart: { type: 'bar', height: 320, stacked: true, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: false, borderRadius: 2, columnWidth: '55%' } },
        colors: ['#50CD89', '#FFC700', '#F1416C', '#3E97FF', '#B5B5C3'],
        dataLabels: { enabled: false },
        xaxis: { categories: sorted.map((d) => d.department), labels: { style: { fontSize: '11px' }, rotate: -30 } },
        yaxis: { labels: { style: { fontSize: '12px' } } },
        legend: { position: 'top', fontSize: '12px' },
        grid: { borderColor: '#f1f1f1' },
        tooltip: { shared: true, intersect: false },
    };
}

function renderCharts() {
    if (rateChartInstance) { rateChartInstance.destroy(); rateChartInstance = null; }
    if (stackedChartInstance) { stackedChartInstance.destroy(); stackedChartInstance = null; }

    if (typeof window === 'undefined' || !(window as any).ApexCharts || props.departments.length === 0) return;

    if (rateChartRef.value) {
        rateChartInstance = new (window as any).ApexCharts(rateChartRef.value, buildRateChartOptions());
        rateChartInstance.render();
    }
    if (stackedChartRef.value) {
        stackedChartInstance = new (window as any).ApexCharts(stackedChartRef.value, buildStackedChartOptions());
        stackedChartInstance.render();
    }
}

watch(() => [activeView.value, props.departments], ([view]) => {
    if (view === 'summary') setTimeout(renderCharts, 50);
}, { deep: true });

onMounted(() => { if (activeView.value === 'summary') setTimeout(renderCharts, 50); });
onUnmounted(() => {
    if (rateChartInstance) rateChartInstance.destroy();
    if (stackedChartInstance) stackedChartInstance.destroy();
});
</script>

<template>
    <div>
        <Head title="Department Attendance Report" />

        <!--begin::Filters card-->
        <div class="card mb-5 no-print">
            <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                <div class="card-title">
                    <i class="ki-outline ki-office-bag fs-1 text-primary me-3"></i>
                    <h2 class="fw-bold">Department Attendance Report</h2>
                </div>
                <div class="no-print">
                    <ExportButtons :export-url="exportUrl" />
                </div>
            </div>
            <div class="card-body pt-2 pb-6">
                <div class="row g-3 align-items-end">
                    <!--begin::Date from-->
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Date From</label>
                        <input v-model="filterForm.date_from" type="date" class="form-control form-control-solid" @change="submitFilters" />
                    </div>
                    <!--end::Date from-->

                    <!--begin::Date to-->
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Date To</label>
                        <input v-model="filterForm.date_to" type="date" class="form-control form-control-solid" @change="submitFilters" />
                    </div>
                    <!--end::Date to-->

                    <!--begin::Location-->
                    <div ref="locationDropdownRef" class="col-md-6 col-xl-3 position-relative">
                        <label class="form-label fs-7">Location</label>
                        <button type="button" class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="locationOpen = !locationOpen">
                            <span :class="filterForm.location_id ? 'text-gray-800' : 'text-muted'">{{ selectedLocationName }}</span>
                            <i class="ki-outline fs-6 text-gray-500 flex-shrink-0" :class="locationOpen ? 'ki-up' : 'ki-down'"></i>
                        </button>
                        <div v-if="locationOpen" class="position-absolute bg-white border rounded shadow-sm mt-1 w-100" style="top: 100%; z-index: 100; max-height: 260px; overflow-y: auto">
                            <div class="p-2 border-bottom bg-white sticky-top">
                                <input v-model="locationSearch" type="text" class="form-control form-control-sm" placeholder="Search location..." @click.stop />
                            </div>
                            <div class="px-4 py-2 fs-7 cursor-pointer text-hover-primary" :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.location_id === null }" @click="selectLocation(null)">All Locations</div>
                            <div v-for="l in filteredLocations" :key="l.id" class="px-4 py-2 fs-7 cursor-pointer text-hover-primary" :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.location_id === l.id }" @click="selectLocation(l.id)">{{ l.name }}</div>
                            <div v-if="filteredLocations.length === 0" class="px-4 py-2 fs-7 text-muted">No results</div>
                        </div>
                    </div>
                    <!--end::Location-->
                </div>
            </div>
        </div>
        <!--end::Filters card-->

        <!--begin::View tabs-->
        <div class="d-flex gap-2 mb-5">
            <button type="button" class="btn btn-sm" :class="activeView === 'detailed' ? 'btn-primary' : 'btn-light btn-active-light-primary'" @click="activeView = 'detailed'">
                <i class="ki-outline ki-office-bag fs-5 me-1"></i>
                Detailed
            </button>
            <button type="button" class="btn btn-sm" :class="activeView === 'summary' ? 'btn-primary' : 'btn-light btn-active-light-primary'" @click="activeView = 'summary'">
                <i class="ki-outline ki-chart-pie-4 fs-5 me-1"></i>
                Summary
            </button>
        </div>
        <!--end::View tabs-->

        <!--begin::Detailed view-->
        <template v-if="activeView === 'detailed'">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold">Attendance by Department</h3>
                        <span class="text-muted ms-3 fs-7">{{ filterForm.date_from }} — {{ filterForm.date_to }}</span>
                    </div>
                </div>
                <div class="card-body py-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gy-4 gs-5">
                            <thead>
                                <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                    <th class="w-40px"></th>
                                    <th>Department</th>
                                    <th>Employees</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                    <th>Leave</th>
                                    <th>Off Days</th>
                                    <th>Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="dept in departments" :key="dept.department">
                                    <!--begin::Department row-->
                                    <tr class="cursor-pointer" @click="toggleRow(dept.department)">
                                        <td>
                                            <i class="ki-outline fs-4 text-gray-400" :class="expandedRows.has(dept.department) ? 'ki-up' : 'ki-down'"></i>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-gray-800">{{ dept.department }}</span>
                                        </td>
                                        <td class="text-gray-600 fs-7">{{ dept.employee_count }}</td>
                                        <td>
                                            <span class="badge badge-light-success fs-7 px-3 py-2">{{ dept.present }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-warning fs-7 px-3 py-2">{{ dept.late }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-danger fs-7 px-3 py-2">{{ dept.absent }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-info fs-7 px-3 py-2">{{ dept.leave }}</span>
                                        </td>
                                        <td class="text-gray-500 fs-7">{{ dept.off_days }}</td>
                                        <td>
                                            <span class="badge fs-7 px-3 py-2" :class="rateBadgeClass(dept.attendance_rate)">
                                                {{ dept.attendance_rate }}%
                                            </span>
                                        </td>
                                    </tr>
                                    <!--end::Department row-->

                                    <!--begin::Employee sub-table-->
                                    <tr v-if="expandedRows.has(dept.department)" :key="`sub-${dept.department}`" class="bg-light">
                                        <td colspan="9" class="px-6 py-4">
                                            <div class="rounded border bg-white p-4">
                                                <div class="d-flex align-items-center mb-4">
                                                    <span class="fw-bold text-gray-700 fs-7 text-uppercase me-2">Employee Breakdown</span>
                                                    <span class="badge badge-light-primary fs-8">{{ dept.employees.length }} employee(s)</span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-row-bordered table-row-gray-200 align-middle gy-3 gs-4 mb-0">
                                                        <thead>
                                                            <tr class="text-start text-gray-400 text-uppercase fs-8 fw-bold">
                                                                <th>Employee ID</th>
                                                                <th>Name</th>
                                                                <th>Total Days</th>
                                                                <th>Present</th>
                                                                <th>Late</th>
                                                                <th>Absent</th>
                                                                <th>Leave</th>
                                                                <th>Off Days</th>
                                                                <th>Late Time</th>
                                                                <th>Overtime</th>
                                                                <th>Rate</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="emp in dept.employees" :key="emp.employee_id">
                                                                <td class="text-gray-700 fw-semibold fs-7">{{ emp.employee_number ?? '—' }}</td>
                                                                <td class="fw-semibold text-gray-800 fs-7">{{ emp.name }}</td>
                                                                <td class="text-gray-600 fs-7">{{ emp.total_days }}</td>
                                                                <td>
                                                                    <span class="badge badge-light-success fs-8 px-2">{{ emp.present }}</span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-light-warning fs-8 px-2">{{ emp.late }}</span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-light-danger fs-8 px-2">{{ emp.absent }}</span>
                                                                </td>
                                                                <td>
                                                                    <span class="badge badge-light-info fs-8 px-2">{{ emp.leave }}</span>
                                                                </td>
                                                                <td class="text-gray-500 fs-8">{{ emp.off_days }}</td>
                                                                <td class="text-gray-600 fs-8">{{ fmtMinutes(emp.total_late_minutes) }}</td>
                                                                <td class="text-gray-600 fs-8">{{ fmtMinutes(emp.overtime_minutes) }}</td>
                                                                <td>
                                                                    <span class="badge fs-8 px-2" :class="rateBadgeClass(emp.attendance_rate)">
                                                                        {{ emp.attendance_rate }}%
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!--end::Employee sub-table-->
                                </template>

                                <!--begin::Empty state-->
                                <tr v-if="departments.length === 0">
                                    <td colspan="9" class="text-center text-muted py-12">
                                        <div class="d-flex flex-column align-items-center gap-3">
                                            <i class="ki-outline ki-office-bag fs-3x text-gray-300"></i>
                                            <span class="fs-6 text-gray-400">No attendance data for the selected period.</span>
                                        </div>
                                    </td>
                                </tr>
                                <!--end::Empty state-->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
        <!--end::Detailed view-->

        <!--begin::Summary view-->
        <template v-if="activeView === 'summary'">
            <!--begin::Summary stats-->
            <div class="row g-4 g-xl-5 mb-5">
                <div class="col-6 col-xl-2">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center gap-3 py-5">
                            <div class="symbol symbol-45px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-outline ki-office-bag fs-3 text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800">{{ summary.department_count }}</div>
                                <div class="fs-8 text-gray-500 mt-1">Departments</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-2">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center gap-3 py-5">
                            <div class="symbol symbol-45px">
                                <div class="symbol-label" :class="summary.overall_attendance_rate >= 90 ? 'bg-light-success' : summary.overall_attendance_rate >= 75 ? 'bg-light-warning' : 'bg-light-danger'">
                                    <i class="ki-outline ki-chart-simple fs-3" :class="summary.overall_attendance_rate >= 90 ? 'text-success' : summary.overall_attendance_rate >= 75 ? 'text-warning' : 'text-danger'"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800">{{ summary.overall_attendance_rate }}%</div>
                                <div class="fs-8 text-gray-500 mt-1">Overall Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Summary stats-->

            <div class="row g-5 mb-5">
                <!--begin::Attendance rate chart-->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <h3 class="fw-bold fs-5">Attendance Rate by Department</h3>
                                <span class="text-muted ms-2 fs-8">(top 10)</span>
                            </div>
                        </div>
                        <div class="card-body pt-2">
                            <div v-if="departments.length > 0" ref="rateChartRef"></div>
                            <div v-else class="d-flex flex-column align-items-center justify-content-center py-10 text-muted">
                                <i class="ki-outline ki-chart-pie-4 fs-3x text-gray-300 mb-3"></i>
                                <span class="fs-7">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Attendance rate chart-->

                <!--begin::Status stacked chart-->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <h3 class="fw-bold fs-5">Status Breakdown by Department</h3>
                                <span class="text-muted ms-2 fs-8">(top 8)</span>
                            </div>
                        </div>
                        <div class="card-body pt-2">
                            <div v-if="departments.length > 0" ref="stackedChartRef"></div>
                            <div v-else class="d-flex flex-column align-items-center justify-content-center py-10 text-muted">
                                <i class="ki-outline ki-chart-pie-4 fs-3x text-gray-300 mb-3"></i>
                                <span class="fs-7">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Status stacked chart-->
            </div>

            <!--begin::Department summary table-->
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold">Department Rankings</h3>
                        <span class="text-muted ms-3 fs-7">{{ filterForm.date_from }} — {{ filterForm.date_to }}</span>
                    </div>
                </div>
                <div class="card-body py-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gy-4 gs-5">
                            <thead>
                                <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                    <th class="w-40px">#</th>
                                    <th>Department</th>
                                    <th>Employees</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                    <th>Leave</th>
                                    <th>Off Days</th>
                                    <th>Attendance Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(dept, index) in departments" :key="dept.department">
                                    <td>
                                        <span class="fw-bold text-gray-500 fs-7">{{ index + 1 }}</span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-gray-800">{{ dept.department }}</span>
                                    </td>
                                    <td class="text-gray-600 fs-7">{{ dept.employee_count }}</td>
                                    <td>
                                        <span class="badge badge-light-success fs-7 px-3 py-2">{{ dept.present }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-warning fs-7 px-3 py-2">{{ dept.late }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-danger fs-7 px-3 py-2">{{ dept.absent }}</span>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-info fs-7 px-3 py-2">{{ dept.leave }}</span>
                                    </td>
                                    <td class="text-gray-500 fs-7">{{ dept.off_days }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="progress h-6px w-75px bg-light-secondary">
                                                <div
                                                    class="progress-bar rounded"
                                                    :class="dept.attendance_rate >= 90 ? 'bg-success' : dept.attendance_rate >= 75 ? 'bg-warning' : 'bg-danger'"
                                                    :style="{ width: `${dept.attendance_rate}%` }"
                                                ></div>
                                            </div>
                                            <span class="badge fs-7 px-3 py-2" :class="rateBadgeClass(dept.attendance_rate)">
                                                {{ dept.attendance_rate }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="departments.length === 0">
                                    <td colspan="9" class="text-center text-muted py-12">
                                        <div class="d-flex flex-column align-items-center gap-3">
                                            <i class="ki-outline ki-office-bag fs-3x text-gray-300"></i>
                                            <span class="fs-6 text-gray-400">No attendance data for the selected period.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!--end::Department summary table-->
        </template>
        <!--end::Summary view-->
    </div>
</template>
