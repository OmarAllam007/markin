<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { monthlySummary as monthlySummaryRoute } from '@/routes/reports/index';
import { Head, useForm } from '@inertiajs/vue3';
import { onClickOutside } from '@vueuse/core';
import { computed, ref } from 'vue';

type FilterOption = { id: number; name: string };

type DayData = {
    status: string;
    late: number;
    early: number;
    ot: number;
};

type EmployeeRow = {
    id: number;
    employee_number: string | null;
    name: string;
    department: string | null;
    location: string | null;
    days: Record<string, DayData>;
};

const props = defineProps<{
    employees: EmployeeRow[];
    dates: string[];
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
    filterForm.get(monthlySummaryRoute.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['employees', 'dates', 'filters'],
    });
};

const exportUrl = computed(() => {
    const q = new URLSearchParams();
    Object.entries(props.filters).forEach(([k, v]) => {
        if (v !== null && v !== undefined) q.set(k, String(v));
    });
    q.set('export', 'xlsx');
    return monthlySummaryRoute.url() + '?' + q.toString();
});

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

// ── Client-side table search ──────────────────────────────────────────────────
const tableSearch = ref('');
const filteredEmployees = computed(() => {
    const q = tableSearch.value.toLowerCase().trim();
    if (!q) return props.employees;
    return props.employees.filter(
        (e) =>
            (e.name ?? '').toLowerCase().includes(q) ||
            (e.employee_number ?? '').toLowerCase().includes(q),
    );
});

// ── Date helpers ──────────────────────────────────────────────────────────────
const DAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function parseDate(s: string) {
    const d = new Date(s + 'T00:00:00');
    return {
        short: DAY_SHORT[d.getDay()],
        num: String(d.getDate()).padStart(2, '0'),
        isWeekend: d.getDay() === 5 || d.getDay() === 6,
    };
}

function periodLabel(): string {
    if (!props.dates.length) return '';
    const a = new Date(props.dates[0] + 'T00:00:00');
    const b = new Date(props.dates[props.dates.length - 1] + 'T00:00:00');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    if (a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear()) {
        return `${months[a.getMonth()]} ${a.getFullYear()}`;
    }
    return `${months[a.getMonth()]} ${a.getDate()} – ${months[b.getMonth()]} ${b.getDate()}, ${b.getFullYear()}`;
}

// ── Cell logic ────────────────────────────────────────────────────────────────
type CellType = 'off' | 'absent' | 'leave' | 'ok' | 'missing' | 'numbers' | 'empty';

interface CellInfo {
    type: CellType;
    late: number;
    early: number;
    ot: number;
    tip: string;
}

function cellInfo(day: DayData | undefined): CellInfo {
    if (!day) return { type: 'empty', late: 0, early: 0, ot: 0, tip: 'No record' };

    const s = day.status;
    const { late, early, ot } = day;

    if (s === 'weekend' || s === 'holiday') {
        return { type: 'off', late: 0, early: 0, ot: 0, tip: s === 'holiday' ? 'Holiday' : 'Weekend' };
    }
    if (s === 'absent') {
        return { type: 'absent', late: 0, early: 0, ot: 0, tip: 'Absent (AWP)' };
    }
    if (s === 'leave' || s === 'business_trip' || s === 'half_day') {
        const label = s === 'leave' ? 'Leave' : s === 'business_trip' ? 'Business Trip' : 'Half Day';
        return { type: 'leave', late, early, ot, tip: label };
    }
    if (s === 'missing_checkout') {
        const tip = late > 0 ? `Missing Checkout · Late ${late}m` : 'Missing Checkout';
        return { type: 'missing', late, early, ot, tip };
    }
    // present, remote, late
    const hasNums = late > 0 || early > 0 || ot > 0;
    if (!hasNums) {
        return { type: 'ok', late: 0, early: 0, ot: 0, tip: s === 'remote' ? 'Remote – On Time' : 'On Time' };
    }
    const parts: string[] = [];
    if (late > 0) parts.push(`Late ${late}m`);
    if (early > 0) parts.push(`Early leave ${early}m`);
    if (ot > 0) parts.push(`Overtime ${ot}m`);
    return { type: 'numbers', late, early, ot, tip: parts.join(' · ') };
}

// ── Per-employee totals ───────────────────────────────────────────────────────
function empTotals(emp: EmployeeRow) {
    let present = 0, absent = 0, late = 0, leave = 0, off = 0;
    for (const d of props.dates) {
        const info = cellInfo(emp.days[d]);
        if (info.type === 'off') off++;
        else if (info.type === 'absent') absent++;
        else if (info.type === 'leave') leave++;
        else if (info.type === 'ok' || info.type === 'numbers' || info.type === 'missing') {
            present++;
            if (info.type === 'numbers' && info.late > 0) late++;
        }
    }
    return { present, absent, late, leave, off };
}

</script>

<template>
    <div>
        <Head title="Monthly Summary Report" />

        <!--begin::Filter card-->
        <div class="card mb-5 no-print">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-outline ki-calendar fs-1 text-info me-3"></i>
                    <h2 class="fw-bold">Monthly Summary Report</h2>
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
                        class="col-sm-6 col-xl-3 position-relative"
                    >
                        <label class="form-label fs-7">Location</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex align-items-center justify-content-between"
                            @click="locationOpen = !locationOpen"
                        >
                            <span :class="filterForm.location_id ? 'text-gray-800' : 'text-muted'">{{ selectedLocationName }}</span>
                            <i class="ki-outline ki-down fs-5 ms-2 text-gray-500"></i>
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
                                    >
                                        All Locations
                                    </button>
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
                                    >
                                        {{ loc.name }}
                                    </button>
                                </li>
                                <li
                                    v-if="filteredLocations.length === 0"
                                    class="px-4 py-2 fs-7 text-muted"
                                >
                                    No results
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!--end::Location-->

                    <!--begin::Department-->
                    <div
                        ref="departmentDropdownRef"
                        class="col-sm-6 col-xl-3 position-relative"
                    >
                        <label class="form-label fs-7">Department</label>
                        <button
                            type="button"
                            class="form-select form-select-solid text-start d-flex align-items-center justify-content-between"
                            @click="departmentOpen = !departmentOpen"
                        >
                            <span :class="filterForm.department_id ? 'text-gray-800' : 'text-muted'">{{ selectedDepartmentName }}</span>
                            <i class="ki-outline ki-down fs-5 ms-2 text-gray-500"></i>
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
                                    >
                                        All Departments
                                    </button>
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
                                    >
                                        {{ dept.name }}
                                    </button>
                                </li>
                                <li
                                    v-if="filteredDepartments.length === 0"
                                    class="px-4 py-2 fs-7 text-muted"
                                >
                                    No results
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!--end::Department-->

                    <!--begin::Search-->
                    <div class="col-sm-6 col-xl-2">
                        <button
                            type="button"
                            class="btn btn-info w-100"
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
                    <!--end::Search-->
                </div>
            </div>
        </div>
        <!--end::Filter card-->

        <!--begin::Legend card-->
        <div class="card mb-5 no-print">
            <div class="card-body py-5">
                <div class="d-flex align-items-center gap-2 mb-4">
                    <i class="ki-outline ki-information-5 fs-5 text-gray-500"></i>
                    <span class="fw-semibold fs-7 text-gray-600">Legend</span>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <span class="ms-badge-circle ms-badge-ok flex-shrink-0">
                                <i class="ki-outline ki-check text-white fs-7"></i>
                            </span>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">On Time</div>
                                <div class="text-muted fs-8">Regular attendance</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <span class="ms-badge-circle ms-badge-absent flex-shrink-0">A</span>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">Absent (AWP)</div>
                                <div class="text-muted fs-8">No attendance · no leave</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <span class="ms-badge-circle ms-badge-leave flex-shrink-0">V</span>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">On Leave</div>
                                <div class="text-muted fs-8">Leave · Trip · Half day</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <span
                                class="ms-badge-off flex-shrink-0"
                                style="font-size: 10px"
                            >Off</span>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">Off Day</div>
                                <div class="text-muted fs-8">Weekend · Holiday</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <span class="ms-badge-circle ms-badge-missing flex-shrink-0">!</span>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">Missing Checkout</div>
                                <div class="text-muted fs-8">Checked in, no checkout</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex flex-column gap-1 flex-shrink-0">
                                <span class="ms-num ms-num-late">48</span>
                                <span class="ms-num ms-num-early">14</span>
                                <span class="ms-num ms-num-ot">29</span>
                            </div>
                            <div>
                                <div class="fw-semibold text-gray-700 fs-7">Minutes</div>
                                <div class="text-muted fs-8">
                                    <span class="text-danger">Red</span> late ·
                                    <span style="color: #b07d00">Orange</span> early leave ·
                                    <span class="text-success">Green</span> overtime
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::Legend card-->

        <!--begin::Table card-->
        <div class="card">
            <!--begin::Toolbar-->
            <div class="card-header border-0 pt-5 pb-0 no-print">
                <div class="card-title d-flex align-items-center gap-3">
                    <span class="fw-bold text-gray-700">
                        {{ periodLabel() }}
                    </span>
                    <span
                        v-if="employees.length"
                        class="badge badge-light-info fs-8"
                    >{{ filteredEmployees.length }} employee{{ filteredEmployees.length !== 1 ? 's' : '' }}</span>
                </div>
                <div class="card-toolbar d-flex align-items-center gap-2">
                    <!--begin::Search-->
                    <div class="position-relative">
                        <i class="ki-outline ki-magnifier fs-5 text-gray-400 position-absolute"
                           style="top: 50%; left: 10px; transform: translateY(-50%)"></i>
                        <input
                            v-model="tableSearch"
                            type="text"
                            class="form-control form-control-sm form-control-solid ps-9"
                            placeholder="Search employee…"
                            style="min-width: 180px"
                        />
                    </div>
                    <!--end::Search-->

                    <!--begin::Export-->
                    <ExportButtons :export-url="exportUrl" />
                    <!--end::Export-->
                </div>
            </div>
            <!--end::Toolbar-->

            <!--begin::Empty-->
            <div
                v-if="employees.length === 0"
                class="card-body text-center py-16"
            >
                <i class="ki-outline ki-calendar fs-3x text-gray-300 mb-4 d-block"></i>
                <p class="text-muted fs-6 mb-1">No attendance records found for the selected period.</p>
                <p class="text-muted fs-7">Try adjusting your filters or selecting a different date range.</p>
            </div>
            <!--end::Empty-->

            <!--begin::Table-->
            <div
                v-else
                class="card-body p-0"
            >
                <div class="ms-table-wrap">
                    <table class="ms-table">
                        <thead>
                            <tr>
                                <th class="ms-th ms-col-num">#</th>
                                <th class="ms-th ms-col-name">Employee</th>
                                <th
                                    v-for="d in dates"
                                    :key="d"
                                    class="ms-th ms-col-day"
                                    :class="{ 'ms-col-day-weekend': parseDate(d).isWeekend }"
                                    :title="d"
                                >
                                    <span class="ms-day-short">{{ parseDate(d).short }}</span>
                                    <span class="ms-day-num">{{ parseDate(d).num }}</span>
                                </th>
                                <th class="ms-th ms-col-summary no-print">Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="emp in filteredEmployees"
                                :key="emp.id"
                                class="ms-row"
                            >
                                <!--begin::Emp number-->
                                <td class="ms-td ms-col-num">
                                    <span class="text-gray-600 fs-8">{{ emp.employee_number ?? '—' }}</span>
                                </td>
                                <!--end::Emp number-->

                                <!--begin::Name-->
                                <td class="ms-td ms-col-name">
                                    <div class="fw-semibold text-gray-800 fs-7 lh-1 mb-1">{{ emp.name }}</div>
                                    <div
                                        v-if="emp.department"
                                        class="text-muted fs-8"
                                    >{{ emp.department }}</div>
                                </td>
                                <!--end::Name-->

                                <!--begin::Day cells-->
                                <td
                                    v-for="d in dates"
                                    :key="d"
                                    class="ms-td ms-col-day"
                                    :class="{ 'ms-col-day-weekend': parseDate(d).isWeekend }"
                                    :title="cellInfo(emp.days[d]).tip"
                                >
                                    <!--off-->
                                    <span
                                        v-if="cellInfo(emp.days[d]).type === 'off'"
                                        class="ms-badge-off"
                                    >Off</span>

                                    <!--absent-->
                                    <span
                                        v-else-if="cellInfo(emp.days[d]).type === 'absent'"
                                        class="ms-badge-circle ms-badge-absent"
                                    >A</span>

                                    <!--leave-->
                                    <span
                                        v-else-if="cellInfo(emp.days[d]).type === 'leave'"
                                        class="ms-badge-circle ms-badge-leave"
                                    >V</span>

                                    <!--ok-->
                                    <span
                                        v-else-if="cellInfo(emp.days[d]).type === 'ok'"
                                        class="ms-badge-circle ms-badge-ok"
                                    >
                                        <i class="fa fa-check fs-8 text-white"></i>
                                    </span>

                                    <!--missing checkout-->
                                    <span
                                        v-else-if="cellInfo(emp.days[d]).type === 'missing'"
                                        class="ms-badge-circle ms-badge-missing"
                                    >!</span>

                                    <!--numbers-->
                                    <div
                                        v-else-if="cellInfo(emp.days[d]).type === 'numbers'"
                                        class="ms-nums"
                                    >
                                        <span
                                            v-if="cellInfo(emp.days[d]).late > 0"
                                            class="ms-num ms-num-late"
                                        >{{ cellInfo(emp.days[d]).late }}</span>
                                        <span
                                            v-if="cellInfo(emp.days[d]).early > 0"
                                            class="ms-num ms-num-early"
                                        >{{ cellInfo(emp.days[d]).early }}</span>
                                        <span
                                            v-if="cellInfo(emp.days[d]).ot > 0"
                                            class="ms-num ms-num-ot"
                                        >{{ cellInfo(emp.days[d]).ot }}</span>
                                    </div>

                                    <!--empty-->
                                    <span
                                        v-else
                                        class="text-gray-300 fs-8"
                                    >–</span>
                                </td>
                                <!--end::Day cells-->

                                <!--begin::Summary-->
                                <td class="ms-td ms-col-summary no-print">
                                    <div class="d-flex flex-column gap-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="ms-sum-dot" style="background: #50CD89"></span>
                                            <span class="text-gray-600 fs-8">{{ empTotals(emp).present }}d present</span>
                                        </div>
                                        <div
                                            v-if="empTotals(emp).late > 0"
                                            class="d-flex align-items-center gap-2"
                                        >
                                            <span class="ms-sum-dot" style="background: #F1416C"></span>
                                            <span class="text-gray-600 fs-8">{{ empTotals(emp).late }}d late</span>
                                        </div>
                                        <div
                                            v-if="empTotals(emp).absent > 0"
                                            class="d-flex align-items-center gap-2"
                                        >
                                            <span class="ms-sum-dot" style="background: #F1416C"></span>
                                            <span class="text-gray-600 fs-8">{{ empTotals(emp).absent }}d absent</span>
                                        </div>
                                        <div
                                            v-if="empTotals(emp).leave > 0"
                                            class="d-flex align-items-center gap-2"
                                        >
                                            <span class="ms-sum-dot" style="background: #009EF7"></span>
                                            <span class="text-gray-600 fs-8">{{ empTotals(emp).leave }}d leave</span>
                                        </div>
                                    </div>
                                </td>
                                <!--end::Summary-->
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!--end::Table-->
        </div>
        <!--end::Table card-->
    </div>
</template>

<style scoped>
/* ── Table wrapper ─────────────────────────────────────────────────────────── */
.ms-table-wrap {
    overflow-x: auto;
    overflow-y: auto;
    max-height: calc(100vh - 300px);
    min-height: 160px;
}

/* ── Table base ────────────────────────────────────────────────────────────── */
.ms-table {
    border-collapse: separate;
    border-spacing: 0;
    width: max-content;
    min-width: 100%;
}

/* ── Header cells ──────────────────────────────────────────────────────────── */
.ms-th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f9f9f9;
    border-bottom: 2px solid #e4e6ef;
    padding: 8px 6px;
    white-space: nowrap;
    font-size: 11px;
    font-weight: 600;
    color: #7e8299;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* ── Body cells ────────────────────────────────────────────────────────────── */
.ms-td {
    padding: 7px 5px;
    border-bottom: 1px solid #f4f4f4;
    vertical-align: middle;
}

.ms-row:hover .ms-td {
    background: #fafafa;
}

/* ── Sticky employee columns ───────────────────────────────────────────────── */
.ms-col-num {
    position: sticky;
    left: 0;
    z-index: 2;
    min-width: 80px;
    max-width: 80px;
    background: #fff;
}

.ms-th.ms-col-num { z-index: 4; }

.ms-col-name {
    position: sticky;
    left: 80px;
    z-index: 2;
    min-width: 170px;
    max-width: 170px;
    background: #fff;
    box-shadow: 3px 0 8px -2px rgba(0, 0, 0, 0.08);
}

.ms-th.ms-col-name { z-index: 4; }

/* Hover state for sticky body cols */
.ms-row:hover .ms-col-num,
.ms-row:hover .ms-col-name {
    background: #fafafa;
}

/* ── Day columns ───────────────────────────────────────────────────────────── */
.ms-col-day {
    min-width: 58px;
    width: 58px;
    text-align: center;
}

.ms-col-day-weekend {
    background: #fafafa;
}

.ms-th.ms-col-day-weekend {
    background: #f0f0f0;
}

/* Day header two-line label */
.ms-day-short {
    display: block;
    font-size: 9px;
    font-weight: 600;
    color: #b5b5c3;
    text-transform: uppercase;
    line-height: 1.2;
}

.ms-day-num {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #3f4254;
    line-height: 1.3;
}

/* ── Summary column ────────────────────────────────────────────────────────── */
.ms-col-summary {
    min-width: 120px;
    padding-left: 14px;
    border-left: 2px solid #e4e6ef;
}

.ms-sum-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── Badge: circle (A, V, ✓, !) ────────────────────────────────────────────── */
.ms-badge-circle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    flex-shrink: 0;
}

.ms-badge-absent {
    background: #F1416C;
    color: #fff;
}

.ms-badge-leave {
    background: #009EF7;
    color: #fff;
}

.ms-badge-ok {
    background: #50CD89;
    color: #fff;
}

.ms-badge-missing {
    background: #FFC700;
    color: #1b1b29;
}

/* ── Badge: off pill ───────────────────────────────────────────────────────── */
.ms-badge-off {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #3f4254;
    color: #fff;
    border-radius: 5px;
    padding: 3px 8px;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.03em;
}

/* ── Number badges ─────────────────────────────────────────────────────────── */
.ms-nums {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.ms-num {
    display: inline-block;
    border-radius: 4px;
    padding: 2px 5px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    min-width: 28px;
    text-align: center;
}

.ms-num-late {
    background: #fff5f8;
    color: #f1416c;
    border: 1px solid #fcd7e0;
}

.ms-num-early {
    background: #fff8dd;
    color: #b07d00;
    border: 1px solid #fde9a0;
}

.ms-num-ot {
    background: #e8fff3;
    color: #50cd89;
    border: 1px solid #c5f0d6;
}

/* ── Print ─────────────────────────────────────────────────────────────────── */
@media print {
    .no-print {
        display: none !important;
    }

    .ms-table-wrap {
        overflow: visible;
        max-height: none;
    }

    .ms-table {
        width: 100%;
    }

    .ms-th,
    .ms-td {
        font-size: 9px;
        padding: 4px 3px;
    }

    .ms-col-num,
    .ms-col-name {
        position: static;
        box-shadow: none;
    }
}
</style>
