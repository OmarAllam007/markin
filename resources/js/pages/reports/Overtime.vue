<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { overtime as overtimeRoute } from '@/routes/reports/index';
import { Head, useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

type FilterOption = { id: number; name: string };

type DailyRecord = {
    date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    location: string | null;
    overtime_minutes: number;
};

type EmployeeRow = {
    employee_id: number;
    employee_number: string | null;
    name: string;
    job_title: string | null;
    location: string | null;
    department: string | null;
    total_overtime_minutes: number;
    records: DailyRecord[];
};

const props = defineProps<{
    employees: EmployeeRow[];
    summary: {
        employee_count: number;
        total_overtime_minutes: number;
    };
    filters: {
        date_from: string;
        date_to: string;
        location_id: number | null;
        department_id: number | null;
    };
    filterOptions: {
        locations: FilterOption[];
        departments: FilterOption[];
    };
}>();

const filterForm = useForm({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    location_id: props.filters.location_id,
    department_id: props.filters.department_id,
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.date_from) q.date_from = filterForm.date_from;
    if (filterForm.date_to) q.date_to = filterForm.date_to;
    if (filterForm.location_id) q.location_id = String(filterForm.location_id);
    if (filterForm.department_id) q.department_id = String(filterForm.department_id);
    filterForm.get(overtimeRoute.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['employees', 'summary', 'filters'],
    });
};

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    Object.entries(props.filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined) q.set(k, String(v));
    });
    q.set('export', 'xlsx');
    return overtimeRoute.url() + '?' + q.toString();
});

// ── Expandable rows ──────────────────────────────────────────────────────────
const expandedRows = ref<Set<number>>(new Set());
const toggleRow = (id: number) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

// ── Column visibility ────────────────────────────────────────────────────────
type ColKey = 'employee_number' | 'name' | 'job_title' | 'location' | 'department' | 'total_overtime';
const columns: { key: ColKey; label: string }[] = [
    { key: 'employee_number', label: 'Employee ID' },
    { key: 'name', label: 'Name' },
    { key: 'job_title', label: 'Job Title' },
    { key: 'location', label: 'Location' },
    { key: 'department', label: 'Department' },
    { key: 'total_overtime', label: 'Total Overtime' },
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

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmtOt(minutes: number): string {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

// ── Searchable selects ───────────────────────────────────────────────────────
const locationSearch = ref('');
const departmentSearch = ref('');
const locationOpen = ref(false);
const departmentOpen = ref(false);
const locationDropdownRef = ref<HTMLElement | null>(null);
const departmentDropdownRef = ref<HTMLElement | null>(null);

onClickOutside(locationDropdownRef, () => {
    locationOpen.value = false;
    locationSearch.value = '';
});
onClickOutside(departmentDropdownRef, () => {
    departmentOpen.value = false;
    departmentSearch.value = '';
});

const filteredLocations = computed(() =>
    props.filterOptions.locations.filter((l) =>
        l.name.toLowerCase().includes(locationSearch.value.toLowerCase()),
    ),
);
const filteredDepartments = computed(() =>
    props.filterOptions.departments.filter((d) =>
        d.name.toLowerCase().includes(departmentSearch.value.toLowerCase()),
    ),
);
const selectedLocationName = computed(
    () => props.filterOptions.locations.find((l) => l.id === filterForm.location_id)?.name ?? 'All Locations',
);
const selectedDepartmentName = computed(
    () => props.filterOptions.departments.find((d) => d.id === filterForm.department_id)?.name ?? 'All Departments',
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
</script>

<template>
    <div>
        <Head title="Overtime Report" />

        <!--begin::Filters card-->
        <div class="card mb-5">
            <div class="card-header border-0 pt-6 no-print">
                <div class="card-title">
                    <i class="ki-outline ki-time fs-1 text-warning me-3"></i>
                    <h2 class="fw-bold">Overtime Report</h2>
                </div>
            </div>
            <div class="card-body pt-2 pb-6">
                <div class="row g-3 align-items-end">
                    <!--begin::Date from-->
                    <div class="col-md-6 col-xl-3">
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
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Date To</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="form-control form-control-solid"
                            @change="submitFilters"
                        />
                    </div>
                    <!--end::Date to-->
                    <!--begin::Location searchable select-->
                    <div
                        ref="locationDropdownRef"
                        class="col-md-6 col-xl-3 position-relative"
                    >
                        <label class="form-label fs-7">Location</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="locationOpen = !locationOpen; departmentOpen = false"
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
                    <!--end::Location searchable select-->
                    <!--begin::Department searchable select-->
                    <div
                        ref="departmentDropdownRef"
                        class="col-md-6 col-xl-3 position-relative"
                    >
                        <label class="form-label fs-7">Department</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex justify-content-between align-items-center w-100"
                            @click="departmentOpen = !departmentOpen; locationOpen = false"
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
                    <!--end::Department searchable select-->
                </div>
            </div>
        </div>
        <!--end::Filters card-->

        <!--begin::Summary stats-->
        <div class="row g-4 g-xl-5 mb-5">
            <div class="col-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-4 py-5">
                        <div class="symbol symbol-50px">
                            <div class="symbol-label bg-light-warning">
                                <i class="ki-outline ki-people fs-2 text-warning"></i>
                            </div>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.employee_count }}</div>
                            <div class="fs-7 text-gray-500 mt-1">Employees with OT</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-4 py-5">
                        <div class="symbol symbol-50px">
                            <div class="symbol-label bg-light-success">
                                <i class="ki-outline ki-time fs-2 text-success"></i>
                            </div>
                        </div>
                        <div>
                            <div class="fs-2 fw-bold text-gray-800">{{ fmtOt(summary.total_overtime_minutes) }}</div>
                            <div class="fs-7 text-gray-500 mt-1">Total Overtime</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Summary stats-->

        <!--begin::Main table card-->
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                <div class="card-title">
                    <h3 class="fw-bold">Overtime Details</h3>
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
                                <th v-if="col('job_title')">Job Title</th>
                                <th v-if="col('location')">Location</th>
                                <th v-if="col('department')">Department</th>
                                <th v-if="col('total_overtime')">Overtime</th>
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
                                        v-if="col('job_title')"
                                        class="text-gray-600 fs-7"
                                    >
                                        {{ emp.job_title ?? '—' }}
                                    </td>
                                    <td
                                        v-if="col('location')"
                                        class="text-gray-600"
                                    >
                                        {{ emp.location ?? '—' }}
                                    </td>
                                    <td
                                        v-if="col('department')"
                                        class="text-gray-600"
                                    >
                                        {{ emp.department ?? '—' }}
                                    </td>
                                    <td v-if="col('total_overtime')">
                                        <span class="badge badge-light-success fs-7 px-3 py-2">
                                            {{ fmtOt(emp.total_overtime_minutes) }}
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
                                                <span class="fw-bold text-gray-700 fs-7 text-uppercase me-2">Daily Breakdown</span>
                                                <span class="badge badge-light-warning fs-8">{{ emp.records.length }} day(s)</span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-row-bordered table-row-gray-200 align-middle gy-3 gs-4 mb-0">
                                                    <thead>
                                                        <tr class="text-start text-gray-400 text-uppercase fs-8 fw-bold">
                                                            <th>Date</th>
                                                            <th>Check-in Time</th>
                                                            <th>Check-out Time</th>
                                                            <th>Overtime</th>
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
                                                            <td>
                                                                <span class="fw-semibold text-gray-800 me-2 fs-7">
                                                                    {{ r.check_in_time ?? '—' }}
                                                                </span>
                                                                <span
                                                                    v-if="r.location"
                                                                    class="badge badge-light-secondary fs-9"
                                                                >
                                                                    <i class="ki-outline ki-geolocation fs-9 me-1"></i>
                                                                    {{ r.location }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-semibold text-gray-800 me-2 fs-7">
                                                                    {{ r.check_out_time ?? '—' }}
                                                                </span>
                                                                <span
                                                                    v-if="r.location"
                                                                    class="badge badge-light-secondary fs-9"
                                                                >
                                                                    <i class="ki-outline ki-geolocation fs-9 me-1"></i>
                                                                    {{ r.location }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-light-success fs-7 px-3">
                                                                    {{ fmtOt(r.overtime_minutes) }}
                                                                </span>
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
                                        <i class="ki-outline ki-time fs-3x text-gray-300"></i>
                                        <span class="fs-6 text-gray-400">No overtime records for the selected period.</span>
                                    </div>
                                </td>
                            </tr>
                            <!--end::Empty state-->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!--end::Main table card-->
    </div>
</template>
