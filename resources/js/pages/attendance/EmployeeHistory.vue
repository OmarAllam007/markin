<script setup lang="ts">
import { index as employeeAttendanceIndex } from '@/routes/employees/attendance';
import { show as attendancesShow } from '@/routes/attendances';
import { index as employeesIndex } from '@/routes/employees';
import { Head, Link, useForm } from '@inertiajs/vue3';

type PaginatorLink = { url: string | null; label: string; active: boolean };

type Row = {
    id: number;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    worked_minutes: number;
    total_late_minutes: number;
    overtime_minutes: number;
    status: string;
    shift: { id: number; name: string } | null;
};

const props = defineProps<{
    employee: {
        id: number;
        english_name: string;
        arabic_name: string;
        employee_number: string | null;
    };
    attendances:
        | {
              data: Row[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              links: PaginatorLink[];
          }
        | Row[];
    filters: {
        status: string | null;
        date_from: string;
        date_to: string;
    };
    enumLabels: { status: Record<string, string> };
}>();

const isPaginated = (
    v: typeof props.attendances,
): v is Exclude<typeof props.attendances, Row[]> => !Array.isArray(v);

const filterForm = useForm({
    status: props.filters.status ?? null as string | null,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
});

const applyFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.status) {
        q.status = filterForm.status;
    }
    if (filterForm.date_from) {
        q.date_from = filterForm.date_from;
    }
    if (filterForm.date_to) {
        q.date_to = filterForm.date_to;
    }
    filterForm.get(employeeAttendanceIndex.url({ employee: props.employee.id, query: q }), {
        preserveState: true,
        replace: true,
        only: ['attendances', 'filters'],
    });
};

function formatHm(m: number): string {
    const h = Math.floor(m / 60);
    const mm = m % 60;

    return `${h}h ${mm}m`;
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
    missing_checkout: 'badge-light-danger',
};
</script>

<template>
    <div>
        <Head :title="`Attendance · ${employee.english_name}`" />
        <div class="mb-5">
            <Link
                :href="employeesIndex.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to employees
            </Link>
        </div>
        <div class="card mb-5">
            <div class="card-header border-0">
                <h2 class="fw-bold">{{ employee.english_name }}</h2>
                <div class="fs-7 text-muted">
                    {{ employee.employee_number ?? `ID ${employee.id}` }} · {{ employee.arabic_name }}
                </div>
            </div>
            <div class="card-body border-top pb-6">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fs-7">From</label>
                        <input
                            v-model="filterForm.date_from"
                            type="date"
                            class="form-control form-control-solid"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fs-7">To</label>
                        <input
                            v-model="filterForm.date_to"
                            type="date"
                            class="form-control form-control-solid"
                            @change="applyFilters"
                        />
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fs-7">Status</label>
                        <select
                            v-model="filterForm.status"
                            class="form-select form-select-solid"
                            @change="applyFilters"
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
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Date</th>
                                <th>In</th>
                                <th>Out</th>
                                <th>Worked</th>
                                <th>Late</th>
                                <th>OT</th>
                                <th>Status</th>
                                <th>Shift</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in isPaginated(attendances) ? attendances.data : attendances"
                                :key="row.id"
                            >
                                <td class="fw-semibold">{{ row.attendance_date }}</td>
                                <td>{{ formatClock(row.check_in_time) }}</td>
                                <td>{{ formatClock(row.check_out_time) }}</td>
                                <td>{{ formatHm(row.worked_minutes) }}</td>
                                <td>{{ row.total_late_minutes }}m</td>
                                <td>{{ row.overtime_minutes }}m</td>
                                <td>
                                    <span :class="['badge', STATUS_BADGE[row.status] ?? 'badge-light']">
                                        {{ enumLabels.status[row.status] ?? row.status }}
                                    </span>
                                </td>
                                <td>{{ row.shift?.name ?? '—' }}</td>
                                <td class="text-end">
                                    <Link
                                        :href="attendancesShow.url({ attendance: row.id })"
                                        class="btn btn-sm btn-light-primary"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr
                                v-if="(isPaginated(attendances) ? attendances.data : attendances).length === 0"
                            >
                                <td
                                    colspan="9"
                                    class="text-center text-muted py-10"
                                >
                                    No records in this range.
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
