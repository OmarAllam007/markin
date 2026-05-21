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
        <div class="mb-5 d-flex flex-wrap gap-3 align-items-center">
            <Link
                :href="attendancesIndex.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to attendance
            </Link>
            <div class="ms-auto d-flex flex-wrap gap-2">
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

        <div class="row g-5">
            <div class="col-xl-8">
                <div class="card mb-5">
                    <div class="card-header border-0">
                        <h2 class="fw-bold">Attendance details</h2>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gs-0 gy-3">
                                <tbody>
                                    <tr>
                                        <td class="text-gray-600 w-200px">Employee</td>
                                        <td class="fw-bold text-gray-800">{{ attendance.employee.english_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Date</td>
                                        <td class="fw-semibold">{{ attendance.attendance_date }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Status</td>
                                        <td>
                                            <span class="badge badge-light-primary">{{
                                                enumLabels.status[attendance.status] ?? attendance.status
                                            }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Source</td>
                                        <td>{{ enumLabels.attendance_source[attendance.attendance_source] }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Shift</td>
                                        <td>{{ attendance.shift?.name ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Scheduled</td>
                                        <td>{{ attendance.scheduled_check_in ?? '—' }} → {{ attendance.scheduled_check_out ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Actual</td>
                                        <td>{{ attendance.check_in_time ?? '—' }} → {{ attendance.check_out_time ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Worked</td>
                                        <td>{{ formatHm(attendance.worked_minutes) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Late</td>
                                        <td>{{ attendance.total_late_minutes }} min</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Early leave</td>
                                        <td>{{ attendance.total_early_leave_minutes }} min</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Overtime</td>
                                        <td>{{ attendance.overtime_minutes }} min</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Break deducted</td>
                                        <td>{{ attendance.break_minutes }} min</td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Weekend / Holiday</td>
                                        <td>
                                            {{ attendance.is_weekend ? 'Weekend' : '—' }}
                                            ·
                                            {{ attendance.is_holiday ? 'Holiday flag' : '—' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-gray-600">Comments</td>
                                        <td>{{ attendance.comments ?? '—' }}</td>
                                    </tr>
                                    <tr v-if="attendance.attachment_url">
                                        <td class="text-gray-600">Attachment</td>
                                        <td>
                                            <a
                                                :href="attendance.attachment_url"
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
            </div>
            <div class="col-xl-4">
                <div class="card mb-5">
                    <div class="card-header border-0">
                        <h3 class="fw-bold fs-4">Audit</h3>
                    </div>
                    <div class="card-body fs-7">
                        <div class="mb-3">
                            <div class="text-gray-500">Created by</div>
                            <div>{{ attendance.creator?.name ?? '—' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-gray-500">Updated by</div>
                            <div>{{ attendance.updater?.name ?? '—' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-gray-500">Approved</div>
                            <div>
                                {{ attendance.approver?.name ?? '—' }}
                                <span
                                    v-if="attendance.approved_at"
                                    class="text-muted"
                                >
                                    · {{ attendance.approved_at }}</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="text-gray-500">Manual edit</div>
                            <div>{{ attendance.is_manual_edit ? 'Yes' : 'No' }}</div>
                            <div
                                v-if="attendance.edit_reason"
                                class="mt-1 text-gray-700"
                            >
                                {{ attendance.edit_reason }}
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="text-gray-500">Locked</div>
                            <div>{{ attendance.is_locked ? 'Yes' : 'No' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-gray-500">Device / IP</div>
                            <div class="text-break">{{ attendance.device_id ?? '—' }}</div>
                            <div>{{ attendance.ip_address ?? '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
