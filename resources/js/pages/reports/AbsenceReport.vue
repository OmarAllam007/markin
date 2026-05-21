<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { absenceReport as absenceReportRoute } from '@/routes/reports/index';
import { Head, useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type FilterOption = { id: number; name: string };

type DailyRecord = {
    date: string;
    day: string;
};

type EmployeeRow = {
    employee_id: number;
    employee_number: string | null;
    name: string;
    department: string | null;
    shift_name: string | null;
    absent_days: number;
    max_consecutive: number;
    records: DailyRecord[];
};

type DepartmentChartItem = {
    department: string;
    absent_days: number;
    employee_count: number;
};

const props = defineProps<{
    employees: EmployeeRow[];
    summary: {
        employee_count: number;
        total_absent_days: number;
        max_consecutive: number;
    };
    departmentChart: DepartmentChartItem[];
    filters: {
        date_from: string;
        date_to: string;
        location_id: number | null;
        department_id: number | null;
        shift_id: number | null;
    };
    filterOptions: {
        locations: FilterOption[];
        departments: FilterOption[];
        shifts: FilterOption[];
    };
}>();

// ── Active view tab ───────────────────────────────────────────────────────────
const activeView = ref<'detailed' | 'summary'>('detailed');

// ── Filter form ───────────────────────────────────────────────────────────────
const filterForm = useForm({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    location_id: props.filters.location_id,
    department_id: props.filters.department_id,
    shift_id: props.filters.shift_id,
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.date_from) q.date_from = filterForm.date_from;
    if (filterForm.date_to) q.date_to = filterForm.date_to;
    if (filterForm.location_id) q.location_id = String(filterForm.location_id);
    if (filterForm.department_id) q.department_id = String(filterForm.department_id);
    if (filterForm.shift_id) q.shift_id = String(filterForm.shift_id);
    filterForm.get(absenceReportRoute.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['employees', 'summary', 'departmentChart', 'filters'],
    });
};

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    Object.entries(props.filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined) q.set(k, String(v));
    });
    q.set('export', 'xlsx');
    return absenceReportRoute.url() + '?' + q.toString();
});

// ── Expandable rows (detailed view) ──────────────────────────────────────────
const expandedRows = ref<Set<number>>(new Set());
const toggleRow = (id: number) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

// ── Column visibility (detailed view) ────────────────────────────────────────
type ColKey = 'employee_number' | 'name' | 'department' | 'shift' | 'absent_days' | 'max_consecutive';
const columns: { key: ColKey; label: string }[] = [
    { key: 'employee_number', label: 'Employee ID' },
    { key: 'name', label: 'Name' },
    { key: 'department', label: 'Department' },
    { key: 'shift', label: 'Shift' },
    { key: 'absent_days', label: 'Absent Days' },
    { key: 'max_consecutive', label: 'Max Consecutive' },
];
const visibleCols = ref<Set<ColKey>>(new Set(columns.map((c) => c.key)));
const colDropdownOpen = ref(false);
const colDropdownRef = ref<HTMLElement | null>(null);
onClickOutside(colDropdownRef, () => {
    colDropdownOpen.value = false;
});
const toggleCol = (key: ColKey) => {
    if (visibleCols.value.has(key)) {
        visibleCols.value.delete(key);
    } else {
        visibleCols.value.add(key);
    }
};
const col = (key: ColKey) => visibleCols.value.has(key);
const visibleColCount = computed(() => 1 + columns.filter((c) => visibleCols.value.has(c.key)).length);

// ── Searchable selects ────────────────────────────────────────────────────────
const locationSearch = ref('');
const departmentSearch = ref('');
const shiftSearch = ref('');
const locationOpen = ref(false);
const departmentOpen = ref(false);
const shiftOpen = ref(false);
const locationDropdownRef = ref<HTMLElement | null>(null);
const departmentDropdownRef = ref<HTMLElement | null>(null);
const shiftDropdownRef = ref<HTMLElement | null>(null);

onClickOutside(locationDropdownRef, () => {
    locationOpen.value = false;
    locationSearch.value = '';
});
onClickOutside(departmentDropdownRef, () => {
    departmentOpen.value = false;
    departmentSearch.value = '';
});
onClickOutside(shiftDropdownRef, () => {
    shiftOpen.value = false;
    shiftSearch.value = '';
});

const filteredLocations = computed(() =>
    props.filterOptions.locations.filter((l) => l.name.toLowerCase().includes(locationSearch.value.toLowerCase())),
);
const filteredDepartments = computed(() =>
    props.filterOptions.departments.filter((d) => d.name.toLowerCase().includes(departmentSearch.value.toLowerCase())),
);
const filteredShifts = computed(() =>
    props.filterOptions.shifts.filter((s) => s.name.toLowerCase().includes(shiftSearch.value.toLowerCase())),
);

const selectedLocationName = computed(
    () => props.filterOptions.locations.find((l) => l.id === filterForm.location_id)?.name ?? 'All Locations',
);
const selectedDepartmentName = computed(
    () => props.filterOptions.departments.find((d) => d.id === filterForm.department_id)?.name ?? 'All Departments',
);
const selectedShiftName = computed(
    () => props.filterOptions.shifts.find((s) => s.id === filterForm.shift_id)?.name ?? 'All Shifts',
);

const selectLocation = (id: number | null) => {
    filterForm.location_id = id;
    locationOpen.value = false;
    locationSearch.value = '';
    submitFilters();
};
const selectDepartment = (id: number | null) => {
    filterForm.department_id = id;
    departmentOpen.value = false;
    departmentSearch.value = '';
    submitFilters();
};
const selectShift = (id: number | null) => {
    filterForm.shift_id = id;
    shiftOpen.value = false;
    shiftSearch.value = '';
    submitFilters();
};

// ── Chart (summary view) ──────────────────────────────────────────────────────
const deptChartRef = ref<HTMLElement | null>(null);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
let deptChartInstance: any = null;

function buildDeptChartOptions() {
    const sorted = [...props.departmentChart].slice(0, 10);
    return {
        series: [{ name: 'Absent Days', data: sorted.map((d) => d.absent_days) }],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: { show: false },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                barHeight: '60%',
            },
        },
        colors: ['#F1416C'],
        dataLabels: { enabled: true, style: { fontSize: '12px' } },
        xaxis: {
            categories: sorted.map((d) => d.department),
            labels: { style: { fontSize: '12px' } },
        },
        yaxis: { labels: { style: { fontSize: '12px' } } },
        grid: { borderColor: '#f1f1f1' },
        tooltip: {
            y: {
                formatter: (val: number) => `${val} day(s)`,
            },
        },
    };
}

function renderDeptChart() {
    if (deptChartInstance) {
        deptChartInstance.destroy();
        deptChartInstance = null;
    }
    if (!deptChartRef.value || typeof window === 'undefined' || !(window as any).ApexCharts) {
        return;
    }
    deptChartInstance = new (window as any).ApexCharts(deptChartRef.value, buildDeptChartOptions());
    deptChartInstance.render();
}

watch(
    () => [activeView.value, props.departmentChart],
    ([view]) => {
        if (view === 'summary') {
            // wait for DOM
            setTimeout(renderDeptChart, 50);
        }
    },
    { deep: true },
);

onMounted(() => {
    if (activeView.value === 'summary') {
        setTimeout(renderDeptChart, 50);
    }
});

onUnmounted(() => {
    if (deptChartInstance) {
        deptChartInstance.destroy();
    }
});

// ── Consecutive badge color ───────────────────────────────────────────────────
function consecutiveBadgeClass(n: number): string {
    if (n >= 5) return 'badge-light-danger';
    if (n >= 3) return 'badge-light-warning';
    return 'badge-light-secondary';
}
</script>

<template>
    <div>
        <Head title="Absence Report" />

        <!--begin::Filters card-->
        <div class="card mb-5 no-print">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-outline ki-user-cross fs-1 text-danger me-3"></i>
                    <h2 class="fw-bold">Absence Report</h2>
                </div>
            </div>
            <div class="card-body pt-2 pb-6">
                <div class="row g-3 align-items-end">
                    <!--begin::Date from-->
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label fs-7">Date From</label>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            class="form-control form-control-solid"
                            @change="submitFilters"
                        />
                    </div>
                    <!--end::Date from-->

                    <!--begin::Date to-->
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label fs-7">Date To</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="form-control form-control-solid"
                            @change="submitFilters"
                        />
                    </div>
                    <!--end::Date to-->

                    <!--begin::Department-->
                    <div
                        ref="departmentDropdownRef"
                        class="col-md-6 col-xl-2 position-relative"
                    >
                        <label class="form-label fs-7">Department</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="departmentOpen = !departmentOpen; locationOpen = false; shiftOpen = false"
                        >
                            <span :class="filterForm.department_id ? 'text-gray-800' : 'text-muted'">
                                {{ selectedDepartmentName }}
                            </span>
                            <i
                                class="ki-outline fs-6 text-gray-500 flex-shrink-0"
                                :class="departmentOpen ? 'ki-up' : 'ki-down'"
                            ></i>
                        </button>
                        <div
                            v-if="departmentOpen"
                            class="position-absolute bg-white border rounded shadow-sm mt-1 w-100"
                            style="top: 100%; z-index: 100; max-height: 260px; overflow-y: auto"
                        >
                            <div class="p-2 border-bottom bg-white sticky-top">
                                <input
                                    v-model="departmentSearch"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Search department..."
                                    @click.stop
                                />
                            </div>
                            <div
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.department_id === null }"
                                @click="selectDepartment(null)"
                            >
                                All Departments
                            </div>
                            <div
                                v-for="d in filteredDepartments"
                                :key="d.id"
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.department_id === d.id }"
                                @click="selectDepartment(d.id)"
                            >
                                {{ d.name }}
                            </div>
                            <div
                                v-if="filteredDepartments.length === 0"
                                class="px-4 py-2 fs-7 text-muted"
                            >
                                No results
                            </div>
                        </div>
                    </div>
                    <!--end::Department-->

                    <!--begin::Location-->
                    <div
                        ref="locationDropdownRef"
                        class="col-md-6 col-xl-2 position-relative"
                    >
                        <label class="form-label fs-7">Location</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="locationOpen = !locationOpen; departmentOpen = false; shiftOpen = false"
                        >
                            <span :class="filterForm.location_id ? 'text-gray-800' : 'text-muted'">
                                {{ selectedLocationName }}
                            </span>
                            <i
                                class="ki-outline fs-6 text-gray-500 flex-shrink-0"
                                :class="locationOpen ? 'ki-up' : 'ki-down'"
                            ></i>
                        </button>
                        <div
                            v-if="locationOpen"
                            class="position-absolute bg-white border rounded shadow-sm mt-1 w-100"
                            style="top: 100%; z-index: 100; max-height: 260px; overflow-y: auto"
                        >
                            <div class="p-2 border-bottom bg-white sticky-top">
                                <input
                                    v-model="locationSearch"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Search location..."
                                    @click.stop
                                />
                            </div>
                            <div
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.location_id === null }"
                                @click="selectLocation(null)"
                            >
                                All Locations
                            </div>
                            <div
                                v-for="l in filteredLocations"
                                :key="l.id"
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.location_id === l.id }"
                                @click="selectLocation(l.id)"
                            >
                                {{ l.name }}
                            </div>
                            <div
                                v-if="filteredLocations.length === 0"
                                class="px-4 py-2 fs-7 text-muted"
                            >
                                No results
                            </div>
                        </div>
                    </div>
                    <!--end::Location-->

                    <!--begin::Shift-->
                    <div
                        ref="shiftDropdownRef"
                        class="col-md-6 col-xl-2 position-relative"
                    >
                        <label class="form-label fs-7">Shift</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="shiftOpen = !shiftOpen; locationOpen = false; departmentOpen = false"
                        >
                            <span :class="filterForm.shift_id ? 'text-gray-800' : 'text-muted'">
                                {{ selectedShiftName }}
                            </span>
                            <i
                                class="ki-outline fs-6 text-gray-500 flex-shrink-0"
                                :class="shiftOpen ? 'ki-up' : 'ki-down'"
                            ></i>
                        </button>
                        <div
                            v-if="shiftOpen"
                            class="position-absolute bg-white border rounded shadow-sm mt-1 w-100"
                            style="top: 100%; z-index: 100; max-height: 260px; overflow-y: auto"
                        >
                            <div class="p-2 border-bottom bg-white sticky-top">
                                <input
                                    v-model="shiftSearch"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Search shift..."
                                    @click.stop
                                />
                            </div>
                            <div
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.shift_id === null }"
                                @click="selectShift(null)"
                            >
                                All Shifts
                            </div>
                            <div
                                v-for="s in filteredShifts"
                                :key="s.id"
                                class="px-4 py-2 fs-7 cursor-pointer text-hover-primary"
                                :class="{ 'bg-light-primary text-primary fw-semibold': filterForm.shift_id === s.id }"
                                @click="selectShift(s.id)"
                            >
                                {{ s.name }}
                            </div>
                            <div
                                v-if="filteredShifts.length === 0"
                                class="px-4 py-2 fs-7 text-muted"
                            >
                                No results
                            </div>
                        </div>
                    </div>
                    <!--end::Shift-->
                </div>
            </div>
        </div>
        <!--end::Filters card-->

        <!--begin::View tabs-->
        <div class="d-flex gap-2 mb-5">
            <button
                type="button"
                class="btn btn-sm"
                :class="activeView === 'detailed' ? 'btn-danger' : 'btn-light btn-active-light-danger'"
                @click="activeView = 'detailed'"
            >
                <i class="ki-outline ki-profile-user fs-5 me-1"></i>
                Detailed
            </button>
            <button
                type="button"
                class="btn btn-sm"
                :class="activeView === 'summary' ? 'btn-danger' : 'btn-light btn-active-light-danger'"
                @click="activeView = 'summary'"
            >
                <i class="ki-outline ki-chart-pie-4 fs-5 me-1"></i>
                Summary
            </button>
        </div>
        <!--end::View tabs-->

        <!--begin::Detailed view-->
        <template v-if="activeView === 'detailed'">
            <div class="card">
                <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                    <div class="card-title">
                        <h3 class="fw-bold">Absence Details</h3>
                        <span class="text-muted ms-3 fs-7">
                            {{ filterForm.date_from }} — {{ filterForm.date_to }}
                        </span>
                    </div>
                    <div class="d-flex align-items-center gap-2 no-print">
                        <ExportButtons :export-url="exportUrl" />
                        <div
                            ref="colDropdownRef"
                            class="card-toolbar position-relative"
                        >
                            <button
                                type="button"
                                class="btn btn-sm btn-light btn-active-light-primary"
                                @click="colDropdownOpen = !colDropdownOpen"
                            >
                                <i class="ki-outline ki-setting-4 fs-5 me-1"></i>
                                Columns
                        </button>
                        <div
                            v-if="colDropdownOpen"
                            class="position-absolute bg-white border rounded shadow p-4"
                            style="top: calc(100% + 4px); right: 0; min-width: 190px; z-index: 100"
                        >
                            <div
                                v-for="c in columns"
                                :key="c.key"
                                class="form-check mb-2 last-mb-0"
                            >
                                <input
                                    :id="`col-${c.key}`"
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="visibleCols.has(c.key)"
                                    @change="toggleCol(c.key)"
                                />
                                <label
                                    :for="`col-${c.key}`"
                                    class="form-check-label fs-7 cursor-pointer"
                                >
                                    {{ c.label }}
                                </label>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                <div class="card-body py-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gy-4 gs-5">
                            <thead>
                                <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                    <th class="w-40px"></th>
                                    <th v-if="col('employee_number')">Employee ID</th>
                                    <th v-if="col('name')">Name</th>
                                    <th v-if="col('department')">Department</th>
                                    <th v-if="col('shift')">Shift</th>
                                    <th v-if="col('absent_days')">Absent Days</th>
                                    <th v-if="col('max_consecutive')">Max Consecutive</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-for="emp in employees"
                                    :key="emp.employee_id"
                                >
                                    <!--begin::Parent row-->
                                    <tr
                                        class="cursor-pointer"
                                        @click="toggleRow(emp.employee_id)"
                                    >
                                        <td>
                                            <i
                                                class="ki-outline fs-4 text-gray-400"
                                                :class="expandedRows.has(emp.employee_id) ? 'ki-up' : 'ki-down'"
                                            ></i>
                                        </td>
                                        <td
                                            v-if="col('employee_number')"
                                            class="text-gray-700 fw-semibold"
                                        >
                                            {{ emp.employee_number ?? '—' }}
                                        </td>
                                        <td v-if="col('name')">
                                            <span class="fw-bold text-gray-800">{{ emp.name }}</span>
                                        </td>
                                        <td
                                            v-if="col('department')"
                                            class="text-gray-600"
                                        >
                                            {{ emp.department ?? '—' }}
                                        </td>
                                        <td
                                            v-if="col('shift')"
                                            class="text-gray-600"
                                        >
                                            {{ emp.shift_name ?? '—' }}
                                        </td>
                                        <td v-if="col('absent_days')">
                                            <span class="badge badge-light-danger fs-7 px-3 py-2">
                                                {{ emp.absent_days }} day(s)
                                            </span>
                                        </td>
                                        <td v-if="col('max_consecutive')">
                                            <span
                                                class="badge fs-7 px-3 py-2"
                                                :class="consecutiveBadgeClass(emp.max_consecutive)"
                                            >
                                                {{ emp.max_consecutive }} consecutive
                                            </span>
                                        </td>
                                    </tr>
                                    <!--end::Parent row-->

                                    <!--begin::Sub-table row-->
                                    <tr
                                        v-if="expandedRows.has(emp.employee_id)"
                                        :key="`sub-${emp.employee_id}`"
                                        class="bg-light"
                                    >
                                        <td
                                            :colspan="visibleColCount"
                                            class="px-6 py-4"
                                        >
                                            <div class="rounded border bg-white p-4">
                                                <div class="d-flex align-items-center mb-4">
                                                    <span class="fw-bold text-gray-700 fs-7 text-uppercase me-2">
                                                        Absent Dates
                                                    </span>
                                                    <span class="badge badge-light-danger fs-8">
                                                        {{ emp.records.length }} day(s)
                                                    </span>
                                                </div>
                                                <div class="table-responsive">
                                                    <table class="table table-row-bordered table-row-gray-200 align-middle gy-3 gs-4 mb-0">
                                                        <thead>
                                                            <tr class="text-start text-gray-400 text-uppercase fs-8 fw-bold">
                                                                <th>Date</th>
                                                                <th>Day</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr
                                                                v-for="r in emp.records"
                                                                :key="r.date"
                                                            >
                                                                <td class="text-gray-700 fw-semibold text-nowrap fs-7">
                                                                    {{ r.date }}
                                                                </td>
                                                                <td class="text-gray-500 fs-7">
                                                                    {{ r.day }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!--end::Sub-table row-->
                                </template>

                                <!--begin::Empty state-->
                                <tr v-if="employees.length === 0">
                                    <td
                                        :colspan="visibleColCount"
                                        class="text-center text-muted py-12"
                                    >
                                        <div class="d-flex flex-column align-items-center gap-3">
                                            <i class="ki-outline ki-user-cross fs-3x text-gray-300"></i>
                                            <span class="fs-6 text-gray-400">No absences for the selected period.</span>
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
                <div class="col-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center gap-4 py-5">
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-danger">
                                    <i class="ki-outline ki-people fs-2 text-danger"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800">{{ summary.employee_count }}</div>
                                <div class="fs-7 text-gray-500 mt-1">Employees with Absences</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center gap-4 py-5">
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-warning">
                                    <i class="ki-outline ki-calendar-2 fs-2 text-warning"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800">{{ summary.total_absent_days }}</div>
                                <div class="fs-7 text-gray-500 mt-1">Total Absent Days</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex align-items-center gap-4 py-5">
                            <div class="symbol symbol-50px">
                                <div class="symbol-label bg-light-primary">
                                    <i class="ki-outline ki-abstract-26 fs-2 text-primary"></i>
                                </div>
                            </div>
                            <div>
                                <div class="fs-2 fw-bold text-gray-800">{{ summary.max_consecutive }}</div>
                                <div class="fs-7 text-gray-500 mt-1">Longest Consecutive Absence</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Summary stats-->

            <div class="row g-5 mb-5">
                <!--begin::Department chart-->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <h3 class="fw-bold fs-5">Absences by Department</h3>
                            </div>
                        </div>
                        <div class="card-body pt-2">
                            <div
                                v-if="departmentChart.length > 0"
                                ref="deptChartRef"
                            ></div>
                            <div
                                v-else
                                class="d-flex flex-column align-items-center justify-content-center py-10 text-muted"
                            >
                                <i class="ki-outline ki-chart-pie-4 fs-3x text-gray-300 mb-3"></i>
                                <span class="fs-7">No data available</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Department chart-->

                <!--begin::Department breakdown table-->
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <h3 class="fw-bold fs-5">Department Breakdown</h3>
                            </div>
                        </div>
                        <div class="card-body py-0">
                            <div class="table-responsive">
                                <table class="table table-row-dashed table-row-gray-300 align-middle gy-3 gs-4">
                                    <thead>
                                        <tr class="text-start text-gray-500 text-uppercase fs-8 fw-bold">
                                            <th>Department</th>
                                            <th>Employees</th>
                                            <th>Absent Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="d in departmentChart"
                                            :key="d.department"
                                        >
                                            <td class="text-gray-700 fw-semibold fs-7">{{ d.department }}</td>
                                            <td class="text-gray-600 fs-7">{{ d.employee_count }}</td>
                                            <td>
                                                <span class="badge badge-light-danger fs-8 px-3">
                                                    {{ d.absent_days }} day(s)
                                                </span>
                                            </td>
                                        </tr>
                                        <tr v-if="departmentChart.length === 0">
                                            <td
                                                colspan="3"
                                                class="text-center text-muted py-6 fs-7"
                                            >
                                                No data
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Department breakdown table-->
            </div>

            <!--begin::Employee summary table-->
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h3 class="fw-bold">Employee Summary</h3>
                        <span class="text-muted ms-3 fs-7">
                            {{ filterForm.date_from }} — {{ filterForm.date_to }}
                        </span>
                    </div>
                </div>
                <div class="card-body py-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gy-4 gs-5">
                            <thead>
                                <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                    <th>Employee ID</th>
                                    <th>Name</th>
                                    <th>Department</th>
                                    <th>Absent Days</th>
                                    <th>Consecutive Absence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="emp in employees"
                                    :key="emp.employee_id"
                                >
                                    <td class="text-gray-700 fw-semibold fs-7">
                                        {{ emp.employee_number ?? '—' }}
                                    </td>
                                    <td>
                                        <span class="fw-bold text-gray-800">{{ emp.name }}</span>
                                    </td>
                                    <td class="text-gray-600 fs-7">{{ emp.department ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-light-danger fs-7 px-3 py-2">
                                            {{ emp.absent_days }} day(s)
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="badge fs-7 px-3 py-2"
                                            :class="consecutiveBadgeClass(emp.max_consecutive)"
                                        >
                                            {{ emp.max_consecutive }} consecutive
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="employees.length === 0">
                                    <td
                                        colspan="5"
                                        class="text-center text-muted py-12"
                                    >
                                        <div class="d-flex flex-column align-items-center gap-3">
                                            <i class="ki-outline ki-user-cross fs-3x text-gray-300"></i>
                                            <span class="fs-6 text-gray-400">No absences for the selected period.</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!--end::Employee summary table-->
        </template>
        <!--end::Summary view-->
    </div>
</template>
