<script setup lang="ts">
import {
    index as attendancesIndex,
    create as attendancesCreate,
    show as attendancesShow,
    edit as attendancesEdit,
} from '@/routes/attendances';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type PaginatorLink = { url: string | null; label: string; active: boolean };

type FilterOption = { id: number; name: string };
type EmployeeOpt = { id: number; english_name: string; employee_number: string | null };

type AttendanceRow = {
    id: number;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    worked_minutes: number;
    total_late_minutes: number;
    overtime_minutes: number;
    status: string;
    attendance_source: string;
    is_locked: boolean;
    employee: {
        english_name: string;
        arabic_name: string;
        employee_number: string | null;
        department: { id: number; name: string } | null;
        location: { id: number; name: string } | null;
    };
    shift: { id: number; name: string } | null;
};

const props = defineProps<{
    attendances:
        | {
              data: AttendanceRow[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              links: PaginatorLink[];
          }
        | AttendanceRow[];
    summary: {
        present: number;
        absent: number;
        late: number;
        overtime_records: number;
        missing_checkout: number;
    };
    abilities: { create: boolean };
    filters: {
        employee_id: number | null;
        department_id: number | null;
        business_unit_id: number | null;
        location_id: number | null;
        status: string | null;
        attendance_source: string | null;
        date_from: string;
        date_to: string;
    };
    filterOptions: {
        employees: EmployeeOpt[];
        departments: FilterOption[];
        businessUnits: FilterOption[];
        locations: FilterOption[];
    };
    enumLabels: {
        status: Record<string, string>;
        attendance_source: Record<string, string>;
    };
}>();

const isPaginated = (
    v: typeof props.attendances,
): v is Exclude<typeof props.attendances, AttendanceRow[]> => !Array.isArray(v);

const filterForm = useForm({
    employee_id: props.filters?.employee_id ?? null as number | null,
    department_id: props.filters?.department_id ?? null as number | null,
    business_unit_id: props.filters?.business_unit_id ?? null as number | null,
    location_id: props.filters?.location_id ?? null as number | null,
    status: props.filters?.status ?? null as string | null,
    attendance_source: props.filters?.attendance_source ?? null as string | null,
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.employee_id) q.employee_id = String(filterForm.employee_id);
    if (filterForm.department_id) q.department_id = String(filterForm.department_id);
    if (filterForm.business_unit_id) q.business_unit_id = String(filterForm.business_unit_id);
    if (filterForm.location_id) q.location_id = String(filterForm.location_id);
    if (filterForm.status) q.status = filterForm.status;
    if (filterForm.attendance_source) q.attendance_source = filterForm.attendance_source;
    if (filterForm.date_from) q.date_from = filterForm.date_from;
    if (filterForm.date_to) q.date_to = filterForm.date_to;
    filterForm.get(attendancesIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['attendances', 'summary', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 350);
watch(
    () => [
        filterForm.employee_id,
        filterForm.department_id,
        filterForm.business_unit_id,
        filterForm.location_id,
        filterForm.status,
        filterForm.attendance_source,
    ],
    () => debouncedSubmit(),
);

function formatHm(totalMinutes: number): string {
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    return `${h}h ${m}m`;
}

function formatClock(t: string | null): string {
    if (!t) {
        return '—';
    }

    return t.length >= 5 ? t.slice(0, 5) : t;
}

const STATUS_BADGE: Record<string, string> = {
    present: 'badge-light-success',
    absent: 'badge-light-danger',
    late: 'badge-light-warning',
    half_day: 'badge-light-info',
    weekend: 'badge-light-secondary',
    holiday: 'badge-light-primary',
    leave: 'badge-light-info',
    business_trip: 'badge-light-primary',
    remote: 'badge-light-success',
    missing_checkout: 'badge-light-danger',
};
</script>

<template>
    <div>
        <Head title="Attendance" />
        <div class="card mb-5">
            <div class="card-body py-6">
                <div class="row g-4 g-xl-5">
                    <div class="col-6 col-lg">
                        <div class="border border-gray-200 rounded p-4 h-100">
                            <div class="fs-8 text-gray-500 text-uppercase mb-1">Present</div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.present }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="border border-gray-200 rounded p-4 h-100">
                            <div class="fs-8 text-gray-500 text-uppercase mb-1">Absent</div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.absent }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="border border-gray-200 rounded p-4 h-100">
                            <div class="fs-8 text-gray-500 text-uppercase mb-1">Late</div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.late }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="border border-gray-200 rounded p-4 h-100">
                            <div class="fs-8 text-gray-500 text-uppercase mb-1">Overtime</div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.overtime_records }}</div>
                            <div class="fs-8 text-muted mt-1">records with OT &gt; 0</div>
                        </div>
                    </div>
                    <div class="col-6 col-lg">
                        <div class="border border-gray-200 rounded p-4 h-100">
                            <div class="fs-8 text-gray-500 text-uppercase mb-1">Missing checkout</div>
                            <div class="fs-2 fw-bold text-gray-800">{{ summary.missing_checkout }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Attendance</h2>
                </div>
                <div class="card-toolbar">
                    <Link
                        v-if="abilities.create"
                        :href="attendancesCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        Add attendance
                    </Link>
                </div>
            </div>
            <div class="card-body border-bottom pt-0 pb-6">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Date from</label>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            class="form-control form-control-solid"
                            @change="submitFilters"
                        />
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Date to</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="form-control form-control-solid"
                            @change="submitFilters"
                        />
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Employee</label>
                        <select
                            v-model="filterForm.employee_id"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="e in filterOptions.employees"
                                :key="e.id"
                                :value="e.id"
                            >
                                {{ e.english_name }}
                                {{ e.employee_number ? `(${e.employee_number})` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Department</label>
                        <select
                            v-model="filterForm.department_id"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="d in filterOptions.departments"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Business unit</label>
                        <select
                            v-model="filterForm.business_unit_id"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="b in filterOptions.businessUnits"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Location</label>
                        <select
                            v-model="filterForm.location_id"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="l in filterOptions.locations"
                                :key="l.id"
                                :value="l.id"
                            >
                                {{ l.name }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Status</label>
                        <select
                            v-model="filterForm.status"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="(label, key) in enumLabels.status"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label fs-7">Source</label>
                        <select
                            v-model="filterForm.attendance_source"
                            class="form-select form-select-solid"
                        >
                            <option :value="null">All</option>
                            <option
                                v-for="(label, key) in enumLabels.attendance_source"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Worked</th>
                                <th>Late</th>
                                <th>OT</th>
                                <th>Status</th>
                                <th>Source</th>
                                <th class="text-end w-175px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in isPaginated(attendances) ? attendances.data : attendances"
                                :key="row.id"
                            >
                                <td class="text-gray-800 fw-semibold text-nowrap">{{ row.attendance_date }}</td>
                                <td>
                                    <div class="fw-bold text-gray-800">{{ row.employee.english_name }}</div>
                                    <div class="fs-8 text-muted">
                                        {{ row.employee.employee_number ?? `#${row.id}` }}
                                    </div>
                                </td>
                                <td class="text-gray-700">{{ row.employee.department?.name ?? '—' }}</td>
                                <td class="text-gray-700">{{ formatClock(row.check_in_time) }}</td>
                                <td class="text-gray-700">{{ formatClock(row.check_out_time) }}</td>
                                <td class="text-gray-700">{{ formatHm(row.worked_minutes) }}</td>
                                <td class="text-gray-700">{{ row.total_late_minutes }}m</td>
                                <td class="text-gray-700">{{ row.overtime_minutes }}m</td>
                                <td>
                                    <span :class="['badge', STATUS_BADGE[row.status] ?? 'badge-light']">
                                        {{ enumLabels.status[row.status] ?? row.status }}
                                    </span>
                                    <span
                                        v-if="row.is_locked"
                                        class="badge badge-light-dark ms-1"
                                    >
                                        Locked
                                    </span>
                                </td>
                                <td class="fs-8 text-gray-600">
                                    {{ enumLabels.attendance_source[row.attendance_source] ?? row.attendance_source }}
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="attendancesShow.url({ attendance: row.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        View
                                    </Link>
                                    <Link
                                        v-if="abilities.create"
                                        :href="attendancesEdit.url({ attendance: row.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary"
                                    >
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr
                                v-if="(isPaginated(attendances) ? attendances.data : attendances).length === 0"
                            >
                                <td
                                    colspan="11"
                                    class="text-center text-muted py-10"
                                >
                                    No attendance records for this range.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(attendances) && attendances.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ attendances.data.length ? (attendances.current_page - 1) * attendances.per_page + 1 : 0 }}
                        –
                        {{ Math.min(attendances.current_page * attendances.per_page, attendances.total) }}
                        of {{ attendances.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in attendances.links"
                            :key="l.label + String(l.url)"
                        >
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                :class="['btn btn-sm border', l.active ? 'btn-primary' : 'btn-light']"
                                preserve-state
                            >
                                <span v-html="l.label" />
                            </Link>
                            <span
                                v-else
                                class="btn btn-sm border btn-light pe-none opacity-50"
                            >
                                <span v-html="l.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
