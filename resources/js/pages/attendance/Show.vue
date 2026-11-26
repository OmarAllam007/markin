<script setup lang="ts">
import {
    approve as attendancesApprove,
    lock as attendancesLock,
    index as attendancesIndex,
    edit as attendancesEdit,
    destroy as attendancesDestroy,
} from '@/routes/attendances';
import { Head, Link, router } from '@inertiajs/vue3';

type UserLite = { id: number; name: string } | null;

const props = defineProps<{
    attendance: {
        id: number;
        attendance_date: string;
        check_in_time: string | null;
        check_out_time: string | null;
        scheduled_check_in: string | null;
        scheduled_check_out: string | null;
        worked_minutes: number;
        total_late_minutes: number;
        total_early_leave_minutes: number;
        overtime_minutes: number;
        break_minutes: number;
        status: string;
        attendance_source: string;
        comments: string | null;
        attachment_url: string | null;
        location_id: number | null;
        latitude: string | null;
        longitude: string | null;
        gps_accuracy: string | null;
        address: string | null;
        device_id: string | null;
        ip_address: string | null;
        user_agent: string | null;
        approved_at: string | null;
        is_manual_edit: boolean;
        edit_reason: string | null;
        is_weekend: boolean;
        is_holiday: boolean;
        is_locked: boolean;
        payroll_exported_at: string | null;
        employee: {
            english_name: string;
            arabic_name: string;
            employee_number: string | null;
            department: { id: number; name: string } | null;
            location: { id: number; name: string } | null;
        };
        shift: { id: number; name: string; type: string } | null;
        location: { id: number; name: string } | null;
        creator: UserLite;
        updater: UserLite;
        approver: UserLite;
    };
    punches: {
        id: number;
        type: string;
        punched_at: string;
        source: string;
        location: string | null;
        latitude: string | null;
        longitude: string | null;
        gps_accuracy: string | null;
        is_mocked: boolean;
        is_rooted: boolean;
        is_emulator: boolean;
        location_status: 'inside' | 'outside' | 'unknown';
        distance_from_location_m: number | null;
        device_name: string | null;
        note: string | null;
    }[];
    enumLabels: {
        status: Record<string, string>;
        attendance_source: Record<string, string>;
    };
    abilities: {
        update: boolean;
        delete: boolean;
        approve: boolean;
        lock: boolean;
    };
}>();

function formatHm(m: number): string {
    const h = Math.floor(m / 60);
    const mm = m % 60;

    return `${h}h ${mm}m`;
}

function punchTime(iso: string): string {
    return new Date(iso).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDistance(metres: number): string {
    return metres < 1000
        ? `${metres} m`
        : `${Math.round(metres / 1000).toLocaleString()} km`;
}

function mapsUrl(lat: string, lng: string): string {
    return `https://www.google.com/maps?q=${lat},${lng}`;
}

function approve(): void {
    router.post(attendancesApprove.url({ attendance: props.attendance.id }));
}

function lock(): void {
    router.post(attendancesLock.url({ attendance: props.attendance.id }));
}

function remove(): void {
    if (!window.confirm('Delete this attendance record?')) {
        return;
    }
    router.delete(attendancesDestroy.url({ attendance: props.attendance.id }));
}
</script>

<template>
    <div>
        <Head :title="`Attendance · ${attendance.employee.english_name}`" />
        <div
            class="d-flex align-items-center justify-content-between mb-5 flex-wrap gap-3"
        >
            <Link
                :href="attendancesIndex.url()"
                class="text-hover-primary text-gray-600"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to attendance
            </Link>
            <div class="d-flex attendance-actions flex-wrap gap-2">
                <button
                    v-if="abilities.approve && !attendance.approved_at"
                    type="button"
                    class="btn btn-sm btn-light-success"
                    @click="approve"
                >
                    Approve
                </button>
                <button
                    v-if="abilities.lock && !attendance.is_locked"
                    type="button"
                    class="btn btn-sm btn-light-warning"
                    @click="lock"
                >
                    Lock
                </button>
                <Link
                    v-if="abilities.update"
                    :href="attendancesEdit.url({ attendance: attendance.id })"
                    class="btn btn-sm btn-primary"
                >
                    Edit
                </Link>
                <button
                    v-if="abilities.delete"
                    type="button"
                    class="btn btn-sm btn-light-danger"
                    @click="remove"
                >
                    Delete
                </button>
            </div>
        </div>

        <div class="card attendance-summary mb-5">
            <div class="card-body d-flex align-items-center flex-wrap gap-5">
                <div class="flex-grow-1">
                    <div class="fs-7 mb-1 text-gray-500">
                        Attendance record · {{ attendance.attendance_date }}
                    </div>
                    <h1 class="fs-2 fw-bold mb-2 text-gray-900">
                        {{ attendance.employee.english_name }}
                    </h1>
                    <div
                        class="d-flex align-items-center fs-7 flex-wrap gap-2 text-gray-600"
                    >
                        <span v-if="attendance.employee.employee_number"
                            >#{{ attendance.employee.employee_number }}</span
                        >
                        <span v-if="attendance.employee.department">{{
                            attendance.employee.department.name
                        }}</span>
                        <span v-if="attendance.shift">{{
                            attendance.shift.name
                        }}</span>
                    </div>
                </div>
                <span class="badge badge-light-primary fs-7 px-4 py-3">{{
                    enumLabels.status[attendance.status] ?? attendance.status
                }}</span>
            </div>
        </div>

        <div class="row g-5 align-items-start">
            <div class="col-xl-8">
                <div class="card mb-5">
                    <div class="card-header align-items-center border-0">
                        <h2 class="card-title fw-bold fs-4 mb-0">
                            Attendance details
                        </h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table
                                class="table-row-dashed gs-0 gy-3 attendance-details table align-middle"
                            >
                                <tbody>
                                    <tr>
                                        <td class="text-gray-600">Employee</td>
                                        <td class="fw-bold text-gray-800">
                                            {{
                                                attendance.employee.english_name
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Date</td>
                                        <td class="fw-semibold">
                                            {{ attendance.attendance_date }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Status</td>
                                        <td>
                                            <span
                                                class="badge badge-light-primary"
                                                >{{
                                                    enumLabels.status[
                                                        attendance.status
                                                    ] ?? attendance.status
                                                }}</span
                                            >
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Source</td>
                                        <td>
                                            {{
                                                enumLabels.attendance_source[
                                                    attendance.attendance_source
                                                ]
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Shift</td>
                                        <td>
                                            {{ attendance.shift?.name ?? '—' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Scheduled</td>
                                        <td>
                                            {{
                                                attendance.scheduled_check_in ??
                                                '—'
                                            }}
                                            →
                                            {{
                                                attendance.scheduled_check_out ??
                                                '—'
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Actual</td>
                                        <td>
                                            {{
                                                attendance.check_in_time ?? '—'
                                            }}
                                            →
                                            {{
                                                attendance.check_out_time ?? '—'
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Worked</td>
                                        <td>
                                            {{
                                                formatHm(
                                                    attendance.worked_minutes,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Late</td>
                                        <td>
                                            {{
                                                attendance.total_late_minutes
                                            }}
                                            min
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">
                                            Early leave
                                        </td>
                                        <td>
                                            {{
                                                attendance.total_early_leave_minutes
                                            }}
                                            min
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Overtime</td>
                                        <td>
                                            {{
                                                attendance.overtime_minutes
                                            }}
                                            min
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">
                                            Break deducted
                                        </td>
                                        <td>
                                            {{ attendance.break_minutes }} min
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">
                                            Weekend / Holiday
                                        </td>
                                        <td>
                                            {{
                                                attendance.is_weekend
                                                    ? 'Weekend'
                                                    : '—'
                                            }}
                                            ·
                                            {{
                                                attendance.is_holiday
                                                    ? 'Holiday flag'
                                                    : '—'
                                            }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Comments</td>
                                        <td>
                                            {{ attendance.comments ?? '—' }}
                                        </td>
                                    </tr>
                                    <tr v-if="attendance.attachment_url">
                                        <td class="text-gray-600">
                                            Attachment
                                        </td>
                                        <td>
                                            <a
                                                :href="
                                                    attendance.attachment_url
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="link-primary"
                                            >
                                                Download
                                            </a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-5">
                    <div class="card-header align-items-center border-0">
                        <h3 class="card-title fw-bold fs-4 mb-0">Punches</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div v-if="!punches.length" class="fs-7 text-gray-500">
                            No individual punches recorded for this day.
                        </div>
                        <div v-else class="table-responsive">
                            <table
                                class="table-row-dashed gs-0 gy-3 fs-7 attendance-punches table align-middle"
                            >
                                <thead>
                                    <tr class="fw-semibold text-gray-500">
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Source</th>
                                        <th>Where</th>
                                        <th>Accuracy</th>
                                        <th>Device</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="punch in punches"
                                        :key="punch.id"
                                    >
                                        <td class="fw-bold text-gray-800">
                                            {{ punchTime(punch.punched_at) }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    punch.type === 'check_in'
                                                        ? 'badge-light-success'
                                                        : 'badge-light-primary'
                                                "
                                            >
                                                {{
                                                    punch.type === 'check_in'
                                                        ? 'Check in'
                                                        : 'Check out'
                                                }}
                                            </span>
                                        </td>
                                        <td class="text-gray-600">
                                            {{ punch.source }}
                                        </td>
                                        <td>
                                            <a
                                                v-if="
                                                    punch.latitude &&
                                                    punch.longitude
                                                "
                                                :href="
                                                    mapsUrl(
                                                        punch.latitude,
                                                        punch.longitude,
                                                    )
                                                "
                                                target="_blank"
                                                rel="noopener"
                                                class="link-primary"
                                            >
                                                {{ punch.location ?? 'On map' }}
                                            </a>
                                            <span v-else class="text-gray-500"
                                                >—</span
                                            >
                                            <div
                                                v-if="
                                                    punch.location_status ===
                                                        'outside' ||
                                                    punch.is_mocked
                                                "
                                                class="d-flex mt-1 flex-wrap gap-1"
                                            >
                                                <span
                                                    v-if="
                                                        punch.location_status ===
                                                        'outside'
                                                    "
                                                    class="badge badge-light-warning"
                                                >
                                                    Outside location<template
                                                        v-if="
                                                            punch.distance_from_location_m !==
                                                            null
                                                        "
                                                    >
                                                        ·
                                                        {{
                                                            formatDistance(
                                                                punch.distance_from_location_m,
                                                            )
                                                        }}</template
                                                    >
                                                </span>
                                                <span
                                                    v-if="punch.is_mocked"
                                                    class="badge badge-light-danger"
                                                    >Faked GPS</span
                                                >
                                            </div>
                                        </td>
                                        <td class="text-gray-600">
                                            {{
                                                punch.gps_accuracy
                                                    ? `±${Math.round(Number(punch.gps_accuracy))}m`
                                                    : '—'
                                            }}
                                        </td>
                                        <td>
                                            <div class="text-gray-600">
                                                {{ punch.device_name ?? '—' }}
                                            </div>
                                            <div
                                                class="d-flex mt-1 flex-wrap gap-1"
                                            >
                                                <span
                                                    v-if="punch.is_rooted"
                                                    class="badge badge-light-warning"
                                                    >Rooted</span
                                                >
                                                <span
                                                    v-if="punch.is_emulator"
                                                    class="badge badge-light-warning"
                                                    >Emulator</span
                                                >
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card mb-5">
                    <div class="card-header align-items-center border-0">
                        <h3 class="card-title fw-bold fs-4 mb-0">Audit</h3>
                    </div>
                    <div class="card-body fs-7">
                        <div class="audit-item">
                            <div class="text-gray-500">Created by</div>
                            <div>{{ attendance.creator?.name ?? '—' }}</div>
                        </div>
                        <div class="audit-item">
                            <div class="text-gray-500">Updated by</div>
                            <div>{{ attendance.updater?.name ?? '—' }}</div>
                        </div>
                        <div class="audit-item">
                            <div class="text-gray-500">Approved</div>
                            <div>
                                {{ attendance.approver?.name ?? '—' }}
                                <span
                                    v-if="attendance.approved_at"
                                    class="text-muted"
                                >
                                    · {{ attendance.approved_at }}</span
                                >
                            </div>
                        </div>
                        <div class="audit-item">
                            <div class="text-gray-500">Manual edit</div>
                            <div>
                                {{ attendance.is_manual_edit ? 'Yes' : 'No' }}
                            </div>
                            <div
                                v-if="attendance.edit_reason"
                                class="mt-1 text-gray-700"
                            >
                                {{ attendance.edit_reason }}
                            </div>
                        </div>
                        <div class="audit-item">
                            <div class="text-gray-500">Locked</div>
                            <div>{{ attendance.is_locked ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="audit-item">
                            <div class="text-gray-500">Device / IP</div>
                            <div class="text-break">
                                {{ attendance.device_id ?? '—' }}
                            </div>
                            <div>{{ attendance.ip_address ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.attendance-summary {
    border-inline-start: 4px solid var(--bs-primary);
}

.attendance-details {
    width: 100%;
    table-layout: fixed;
}

.attendance-details td:first-child {
    width: 12rem;
    vertical-align: top;
}

.attendance-details td:last-child {
    overflow-wrap: anywhere;
}

.attendance-punches th,
.attendance-punches td {
    min-width: 5rem;
    vertical-align: top;
}

.attendance-punches th:nth-child(4),
.attendance-punches td:nth-child(4) {
    min-width: 10rem;
}

.audit-item {
    display: grid;
    grid-template-columns: minmax(6rem, 40%) minmax(0, 1fr);
    gap: 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--bs-gray-200);
    overflow-wrap: anywhere;
}

.audit-item:first-child {
    padding-top: 0;
}

.audit-item:last-child {
    padding-bottom: 0;
    border-bottom: 0;
}

@media (max-width: 575.98px) {
    .attendance-actions {
        width: 100%;
    }

    .attendance-details td:first-child {
        width: 8rem;
    }

    .audit-item {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
}
</style>
