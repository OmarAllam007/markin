<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { manualAttendance as manualAttendanceRoute } from '@/routes/reports/detailed-report/index';
import { detailedReport as detailedReportRoute } from '@/routes/reports/index';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

type FilterOption = { id: number; name: string };

type DailyRecord = {
    id: number;
    date: string;
    day: string;
    shift: string | null;
    check_in_time: string | null;
    check_in_location: string | null;
    check_out_time: string | null;
    check_out_location: string | null;
    late_minutes: number;
    early_leave_minutes: number;
    overtime_minutes: number;
    status: string;
};

type EmployeeRow = {
    id: number;
    employee_number: string | null;
    name: string;
    department: string | null;
    location: string | null;
    total_late_minutes: number;
    total_early_leave_minutes: number;
    total_overtime_minutes: number;
    absent_days: number;
    records: DailyRecord[];
};

type PaginatedEmployees = {
    data: EmployeeRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    employees: PaginatedEmployees;
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

// ── Filter form ───────────────────────────────────────────────────────────────
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
    filterForm.get(detailedReportRoute.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['employees', 'filters'],
    });
};

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    Object.entries(props.filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined) q.set(k, String(v));
    });
    q.set('export', 'xlsx');
    return detailedReportRoute.url() + '?' + q.toString();
});

// ── Time unit toggle ──────────────────────────────────────────────────────────
const timeUnit = ref<'minutes' | 'hours'>('minutes');

function fmt(minutes: number): string {
    if (timeUnit.value === 'hours') {
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return h > 0 ? `${h}h ${m}m` : `${m}m`;
    }
    return `${minutes}m`;
}

// ── Searchable dropdowns ──────────────────────────────────────────────────────
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

// ── Expandable rows ───────────────────────────────────────────────────────────
const expandedRows = ref<Set<number>>(new Set());
const toggleRow = (id: number) => {
    if (expandedRows.value.has(id)) expandedRows.value.delete(id);
    else expandedRows.value.add(id);
};

// ── Manual attendance modal ───────────────────────────────────────────────────
type ModalEmployee = { id: number; name: string };
const modalEmployee = ref<ModalEmployee | null>(null);
const showModal = ref(false);

const manualForm = useForm({
    employee_id: 0,
    attendance_date: '',
    check_in_time: '',
    check_out_time: '',
    late_minutes: '',
    early_leave_minutes: '',
    overtime_minutes: '',
    notes: '',
});

const openModal = (emp: ModalEmployee) => {
    modalEmployee.value = emp;
    manualForm.reset();
    manualForm.employee_id = emp.id;
    manualForm.attendance_date = props.filters.date_from;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    modalEmployee.value = null;
};

const submitManual = () => {
    manualForm.post(manualAttendanceRoute.url(), {
        preserveState: true,
        onSuccess: () => closeModal(),
    });
};

// ── Status helpers ────────────────────────────────────────────────────────────
const statusConfig: Record<string, { label: string; cls: string }> = {
    present: { label: 'Present', cls: 'badge-light-success' },
    late: { label: 'Late', cls: 'badge-light-danger' },
    absent: { label: 'Absent', cls: 'badge-light-danger' },
    remote: { label: 'Remote', cls: 'badge-light-info' },
    leave: { label: 'Leave', cls: 'badge-light-primary' },
    business_trip: { label: 'Trip', cls: 'badge-light-primary' },
    half_day: { label: 'Half Day', cls: 'badge-light-warning' },
    weekend: { label: 'Weekend', cls: 'badge-light-secondary' },
    holiday: { label: 'Holiday', cls: 'badge-light-secondary' },
    missing_checkout: { label: 'Missing Out', cls: 'badge-light-warning' },
};

function statusBadge(status: string) {
    return statusConfig[status] ?? { label: status, cls: 'badge-light-secondary' };
}
</script>

<template>
    <div>
        <Head title="Full Detailed Report" />

        <!--begin::Filter card-->
        <div class="card mb-5 no-print">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-outline ki-document fs-1 text-dark me-3"></i>
                    <h2 class="fw-bold">Full Detailed Report</h2>
                </div>
            </div>
            <div class="card-body pt-2 pb-6">
                <div class="row g-4 align-items-end">
                    <!--begin::Date From-->
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label fs-7">From</label>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            class="form-control form-control-solid"
                        />
                    </div>
                    <!--end::Date From-->

                    <!--begin::Date To-->
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label fs-7">To</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="form-control form-control-solid"
                        />
                    </div>
                    <!--end::Date To-->

                    <!--begin::Location-->
                    <div
                        ref="locationDropdownRef"
                        class="col-sm-6 col-xl-2 position-relative"
                    >
                        <label class="form-label fs-7">Location</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex align-items-center justify-content-between"
                            @click="locationOpen = !locationOpen; departmentOpen = false"
                        >
                            <span :class="filterForm.location_id ? 'text-gray-800' : 'text-muted'">{{ selectedLocationName }}</span>
                            <i
                                class="ki-outline fs-6 text-gray-500 flex-shrink-0"
                                :class="locationOpen ? 'ki-up' : 'ki-down'"
                            ></i>
                        </button>
                        <div
                            v-if="locationOpen"
                            class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                            style="z-index: 200; max-height: 220px; overflow-y: auto"
                        >
                            <div class="p-2 border-bottom">
                                <input
                                    v-model="locationSearch"
                                    type="text"
                                    class="form-control form-control-sm form-control-solid"
                                    placeholder="Search…"
                                    @click.stop
                                />
                            </div>
                            <ul class="list-unstyled mb-0 py-1">
                                <li>
                                    <button
                                        type="button"
                                        class="dropdown-item px-4 py-2 fs-7 text-muted"
                                        @click="selectLocation(null)"
                                    >All Locations</button>
                                </li>
                                <li
                                    v-for="loc in filteredLocations"
                                    :key="loc.id"
                                >
                                    <button
                                        type="button"
                                        class="dropdown-item px-4 py-2 fs-7"
                                        :class="filterForm.location_id === loc.id ? 'fw-bold text-primary' : ''"
                                        @click="selectLocation(loc.id)"
                                    >{{ loc.name }}</button>
                                </li>
                                <li
                                    v-if="filteredLocations.length === 0"
                                    class="px-4 py-2 fs-7 text-muted"
                                >No results</li>
                            </ul>
                        </div>
                    </div>
                    <!--end::Location-->

                    <!--begin::Department-->
                    <div
                        ref="departmentDropdownRef"
                        class="col-sm-6 col-xl-2 position-relative"
                    >
                        <label class="form-label fs-7">Department</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex align-items-center justify-content-between"
                            @click="departmentOpen = !departmentOpen; locationOpen = false"
                        >
                            <span :class="filterForm.department_id ? 'text-gray-800' : 'text-muted'">{{ selectedDepartmentName }}</span>
                            <i
                                class="ki-outline fs-6 text-gray-500 flex-shrink-0"
                                :class="departmentOpen ? 'ki-up' : 'ki-down'"
                            ></i>
                        </button>
                        <div
                            v-if="departmentOpen"
                            class="position-absolute bg-white border rounded shadow-sm w-100 mt-1"
                            style="z-index: 200; max-height: 220px; overflow-y: auto"
                        >
                            <div class="p-2 border-bottom">
                                <input
                                    v-model="departmentSearch"
                                    type="text"
                                    class="form-control form-control-sm form-control-solid"
                                    placeholder="Search…"
                                    @click.stop
                                />
                            </div>
                            <ul class="list-unstyled mb-0 py-1">
                                <li>
                                    <button
                                        type="button"
                                        class="dropdown-item px-4 py-2 fs-7 text-muted"
                                        @click="selectDepartment(null)"
                                    >All Departments</button>
                                </li>
                                <li
                                    v-for="dept in filteredDepartments"
                                    :key="dept.id"
                                >
                                    <button
                                        type="button"
                                        class="dropdown-item px-4 py-2 fs-7"
                                        :class="filterForm.department_id === dept.id ? 'fw-bold text-primary' : ''"
                                        @click="selectDepartment(dept.id)"
                                    >{{ dept.name }}</button>
                                </li>
                                <li
                                    v-if="filteredDepartments.length === 0"
                                    class="px-4 py-2 fs-7 text-muted"
                                >No results</li>
                            </ul>
                        </div>
                    </div>
                    <!--end::Department-->

                    <!--begin::Time unit-->
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label fs-7">Display Unit</label>
                        <div class="d-flex gap-3 mt-1">
                            <label class="d-flex align-items-center gap-2 cursor-pointer">
                                <input
                                    v-model="timeUnit"
                                    type="radio"
                                    value="minutes"
                                    class="form-check-input mt-0"
                                />
                                <span class="fs-7">Minutes</span>
                            </label>
                            <label class="d-flex align-items-center gap-2 cursor-pointer">
                                <input
                                    v-model="timeUnit"
                                    type="radio"
                                    value="hours"
                                    class="form-check-input mt-0"
                                />
                                <span class="fs-7">Hours</span>
                            </label>
                        </div>
                    </div>
                    <!--end::Time unit-->

                    <!--begin::Search button-->
                    <div class="col-sm-6 col-xl-2">
                        <button
                            type="button"
                            class="btn btn-dark w-100"
                            :disabled="filterForm.processing"
                            @click="submitFilters"
                        >
                            <span
                                v-if="filterForm.processing"
                                class="spinner-border spinner-border-sm me-2"
                            ></span>
                            <i
                                v-else
                                class="ki-outline ki-magnifier fs-4 me-1"
                            ></i>
                            Search
                        </button>
                    </div>
                    <!--end::Search button-->
                </div>
            </div>
        </div>
        <!--end::Filter card-->

        <!--begin::Table card-->
        <div class="card">
            <div class="card-header border-0 pt-5 pb-0 d-flex justify-content-between align-items-center">
                <div class="card-title">
                    <span class="fw-bold text-gray-700">
                        {{ filters.date_from }} — {{ filters.date_to }}
                    </span>
                    <span
                        v-if="employees.total"
                        class="badge badge-light-dark ms-3 fs-8"
                    >{{ employees.total }} employee{{ employees.total !== 1 ? 's' : '' }}</span>
                </div>
                <div class="no-print">
                    <ExportButtons :export-url="exportUrl" />
                </div>
            </div>

            <!--begin::Empty-->
            <div
                v-if="employees.data.length === 0"
                class="card-body text-center py-16"
            >
                <i class="ki-outline ki-document fs-3x text-gray-300 mb-4 d-block"></i>
                <p class="text-muted fs-6 mb-1">No attendance records found for the selected period.</p>
                <p class="text-muted fs-7">Try adjusting your filters or selecting a different date range.</p>
            </div>
            <!--end::Empty-->

            <!--begin::Table-->
            <div
                v-else
                class="card-body py-0"
            >
                <div class="table-responsive">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th class="w-30px"></th>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Late</th>
                                <th>Early Leave</th>
                                <th>Overtime</th>
                                <th>Absent</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="emp in employees.data"
                                :key="emp.id"
                            >
                                <!--begin::Summary row-->
                                <tr
                                    class="cursor-pointer"
                                    @click="toggleRow(emp.id)"
                                >
                                    <td>
                                        <i
                                            class="ki-outline fs-4 text-gray-400"
                                            :class="expandedRows.has(emp.id) ? 'ki-up' : 'ki-down'"
                                        ></i>
                                    </td>
                                    <td class="text-gray-600 fw-semibold fs-7">
                                        {{ emp.employee_number ?? '—' }}
                                    </td>
                                    <td>
                                        <div class="fw-bold text-gray-800">{{ emp.name }}</div>
                                        <div
                                            v-if="emp.department"
                                            class="text-muted fs-8"
                                        >{{ emp.department }}</div>
                                    </td>
                                    <td>
                                        <span
                                            v-if="emp.total_late_minutes > 0"
                                            class="badge badge-light-danger fs-7"
                                        >{{ fmt(emp.total_late_minutes) }}</span>
                                        <span
                                            v-else
                                            class="text-muted fs-8"
                                        >—</span>
                                    </td>
                                    <td>
                                        <span
                                            v-if="emp.total_early_leave_minutes > 0"
                                            class="badge fs-7"
                                            style="background:#fff8dd;color:#b07d00;border:1px solid #fde9a0"
                                        >{{ fmt(emp.total_early_leave_minutes) }}</span>
                                        <span
                                            v-else
                                            class="text-muted fs-8"
                                        >—</span>
                                    </td>
                                    <td>
                                        <span
                                            v-if="emp.total_overtime_minutes > 0"
                                            class="badge badge-light-success fs-7"
                                        >{{ fmt(emp.total_overtime_minutes) }}</span>
                                        <span
                                            v-else
                                            class="text-muted fs-8"
                                        >—</span>
                                    </td>
                                    <td>
                                        <span
                                            v-if="emp.absent_days > 0"
                                            class="badge badge-light-danger fs-7"
                                        >{{ emp.absent_days }}d</span>
                                        <span
                                            v-else
                                            class="text-muted fs-8"
                                        >—</span>
                                    </td>
                                    <td
                                        class="text-end"
                                        @click.stop
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-primary"
                                            @click="openModal({ id: emp.id, name: emp.name })"
                                        >
                                            <i class="ki-outline ki-plus fs-6 me-1"></i>
                                            Add Record
                                        </button>
                                    </td>
                                </tr>
                                <!--end::Summary row-->

                                <!--begin::Detail row-->
                                <tr
                                    v-if="expandedRows.has(emp.id)"
                                    :key="`detail-${emp.id}`"
                                    class="bg-light"
                                >
                                    <td
                                        colspan="8"
                                        class="px-6 py-4"
                                    >
                                        <div class="rounded border bg-white p-4">
                                            <div class="d-flex align-items-center mb-4">
                                                <span class="fw-bold text-gray-700 fs-7 text-uppercase me-2">Daily Records</span>
                                                <span class="badge badge-light-dark fs-8">{{ emp.records.length }} day(s)</span>
                                            </div>
                                            <div class="table-responsive">
                                                <table class="table table-row-bordered table-row-gray-200 align-middle gy-3 gs-4 mb-0">
                                                    <thead>
                                                        <tr class="text-start text-gray-400 text-uppercase fs-8 fw-bold">
                                                            <th>Date</th>
                                                            <th>Day</th>
                                                            <th>Shift</th>
                                                            <th>Check In</th>
                                                            <th>Check Out</th>
                                                            <th>Late</th>
                                                            <th>Early Leave</th>
                                                            <th>Overtime</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr
                                                            v-for="r in emp.records"
                                                            :key="r.id"
                                                        >
                                                            <td class="text-gray-700 fw-semibold text-nowrap fs-7">{{ r.date }}</td>
                                                            <td class="text-gray-500 fs-7">{{ r.day }}</td>
                                                            <td class="text-gray-600 fs-7">{{ r.shift ?? '—' }}</td>
                                                            <td>
                                                                <span class="fw-semibold text-gray-800 me-2 fs-7">{{ r.check_in_time ?? '—' }}</span>
                                                                <span
                                                                    v-if="r.check_in_location && r.check_in_time"
                                                                    class="badge badge-light-secondary fs-9"
                                                                >
                                                                    <i class="ki-outline ki-geolocation fs-9 me-1"></i>
                                                                    {{ r.check_in_location }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-semibold text-gray-800 me-2 fs-7">{{ r.check_out_time ?? '—' }}</span>
                                                                <span
                                                                    v-if="r.check_out_location && r.check_out_time"
                                                                    class="badge badge-light-secondary fs-9"
                                                                >
                                                                    <i class="ki-outline ki-geolocation fs-9 me-1"></i>
                                                                    {{ r.check_out_location }}
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    v-if="r.late_minutes > 0"
                                                                    class="badge badge-light-danger fs-8"
                                                                >{{ fmt(r.late_minutes) }}</span>
                                                                <span
                                                                    v-else
                                                                    class="text-muted fs-8"
                                                                >—</span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    v-if="r.early_leave_minutes > 0"
                                                                    class="badge fs-8"
                                                                    style="background:#fff8dd;color:#b07d00;border:1px solid #fde9a0"
                                                                >{{ fmt(r.early_leave_minutes) }}</span>
                                                                <span
                                                                    v-else
                                                                    class="text-muted fs-8"
                                                                >—</span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    v-if="r.overtime_minutes > 0"
                                                                    class="badge badge-light-success fs-8"
                                                                >{{ fmt(r.overtime_minutes) }}</span>
                                                                <span
                                                                    v-else
                                                                    class="text-muted fs-8"
                                                                >—</span>
                                                            </td>
                                                            <td>
                                                                <span
                                                                    class="badge fs-8"
                                                                    :class="statusBadge(r.status).cls"
                                                                >{{ statusBadge(r.status).label }}</span>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!--end::Detail row-->
                            </template>
                        </tbody>
                    </table>
                </div>

                <!--begin::Pagination-->
                <div
                    v-if="employees.last_page > 1"
                    class="d-flex justify-content-center py-5"
                >
                    <ul class="pagination">
                        <li
                            v-for="link in employees.links"
                            :key="link.label"
                            class="page-item"
                            :class="{ active: link.active, disabled: !link.url }"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="page-link"
                                preserve-state
                                :only="['employees']"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="page-link"
                                v-html="link.label"
                            ></span>
                        </li>
                    </ul>
                </div>
                <!--end::Pagination-->
            </div>
            <!--end::Table-->
        </div>
        <!--end::Table card-->

        <!--begin::Manual attendance modal-->
        <div
            v-if="showModal"
            class="modal fade show d-block"
            tabindex="-1"
            style="background:rgba(0,0,0,0.5)"
            @click.self="closeModal"
        >
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Add Manual Attendance</h5>
                        <button
                            type="button"
                            class="btn-close"
                            @click="closeModal"
                        ></button>
                    </div>
                    <form @submit.prevent="submitManual">
                        <div class="modal-body">
                            <div
                                v-if="modalEmployee"
                                class="alert alert-light-primary d-flex align-items-center gap-3 mb-5 p-3"
                            >
                                <i class="ki-outline ki-profile-circle fs-2 text-primary"></i>
                                <span class="fw-semibold text-gray-800">{{ modalEmployee.name }}</span>
                            </div>

                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label required fs-7">Date</label>
                                    <input
                                        v-model="manualForm.attendance_date"
                                        type="date"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.attendance_date }"
                                        :min="filters.date_from"
                                        :max="filters.date_to"
                                    />
                                    <div
                                        v-if="manualForm.errors.attendance_date"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.attendance_date }}</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-7">Check In</label>
                                    <input
                                        v-model="manualForm.check_in_time"
                                        type="time"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.check_in_time }"
                                    />
                                    <div
                                        v-if="manualForm.errors.check_in_time"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.check_in_time }}</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fs-7">Check Out</label>
                                    <input
                                        v-model="manualForm.check_out_time"
                                        type="time"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.check_out_time }"
                                    />
                                    <div
                                        v-if="manualForm.errors.check_out_time"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.check_out_time }}</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fs-7">Late (min)</label>
                                    <input
                                        v-model="manualForm.late_minutes"
                                        type="number"
                                        min="0"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.late_minutes }"
                                        placeholder="0"
                                    />
                                    <div
                                        v-if="manualForm.errors.late_minutes"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.late_minutes }}</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fs-7">Early Leave (min)</label>
                                    <input
                                        v-model="manualForm.early_leave_minutes"
                                        type="number"
                                        min="0"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.early_leave_minutes }"
                                        placeholder="0"
                                    />
                                    <div
                                        v-if="manualForm.errors.early_leave_minutes"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.early_leave_minutes }}</div>
                                </div>
                                <div class="col-4">
                                    <label class="form-label fs-7">Overtime (min)</label>
                                    <input
                                        v-model="manualForm.overtime_minutes"
                                        type="number"
                                        min="0"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.overtime_minutes }"
                                        placeholder="0"
                                    />
                                    <div
                                        v-if="manualForm.errors.overtime_minutes"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.overtime_minutes }}</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fs-7">Notes <span class="text-muted">(optional)</span></label>
                                    <textarea
                                        v-model="manualForm.notes"
                                        class="form-control form-control-solid"
                                        :class="{ 'is-invalid': manualForm.errors.notes }"
                                        rows="2"
                                        placeholder="Reason or additional info…"
                                    ></textarea>
                                    <div
                                        v-if="manualForm.errors.notes"
                                        class="invalid-feedback"
                                    >{{ manualForm.errors.notes }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button
                                type="button"
                                class="btn btn-light"
                                @click="closeModal"
                            >Cancel</button>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="manualForm.processing"
                            >
                                <span
                                    v-if="manualForm.processing"
                                    class="spinner-border spinner-border-sm me-2"
                                ></span>
                                Save Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!--end::Manual attendance modal-->
    </div>
</template>
