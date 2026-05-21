<script setup lang="ts">
/**
 * Updates recalculate metrics server-side on save; never derive minutes in this component.
 */
import {
    update as attendancesUpdate,
    show as attendancesShow,
} from '@/routes/attendances';
import { Head, Link, useForm } from '@inertiajs/vue3';

type IdName = { id: number; name: string };

const props = defineProps<{
    attendance: {
        id: number;
        attendance_date: string;
        check_in_time: string | null;
        check_out_time: string | null;
        break_minutes: number;
        status: string;
        shift_id: number | null;
        scheduled_check_in: string | null;
        scheduled_check_out: string | null;
        attendance_source: string;
        comments: string | null;
        location_id: number | null;
        latitude: string | null;
        longitude: string | null;
        gps_accuracy: string | null;
        address: string | null;
        device_id: string | null;
        is_manual_edit: boolean;
        edit_reason: string | null;
        is_holiday: boolean;
        employee: { english_name: string };
    };
    filterOptions: { locations: IdName[]; shifts: IdName[] };
    enumLabels: {
        status: Record<string, string>;
        attendance_source: Record<string, string>;
    };
}>();

function toTimeInput(v: string | null): string {
    if (!v) {
        return '';
    }

    return v.length >= 5 ? v.slice(0, 5) : v;
}

const form = useForm({
    check_in_time: toTimeInput(props.attendance.check_in_time),
    check_out_time: toTimeInput(props.attendance.check_out_time),
    break_minutes: props.attendance.break_minutes,
    status: props.attendance.status,
    shift_id: props.attendance.shift_id,
    scheduled_check_in: toTimeInput(props.attendance.scheduled_check_in),
    scheduled_check_out: toTimeInput(props.attendance.scheduled_check_out),
    attendance_source: props.attendance.attendance_source,
    comments: props.attendance.comments ?? '',
    attachment: null as File | null,
    location_id: props.attendance.location_id,
    latitude: props.attendance.latitude ?? '',
    longitude: props.attendance.longitude ?? '',
    gps_accuracy: props.attendance.gps_accuracy ?? '',
    address: props.attendance.address ?? '',
    device_id: props.attendance.device_id ?? '',
        is_holiday: props.attendance.is_holiday,
    is_manual_edit: props.attendance.is_manual_edit,
    edit_reason: props.attendance.edit_reason ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        latitude: data.latitude === '' ? null : data.latitude,
        longitude: data.longitude === '' ? null : data.longitude,
        gps_accuracy: data.gps_accuracy === '' ? null : data.gps_accuracy,
    })).put(attendancesUpdate.url({ attendance: props.attendance.id }), {
        preserveScroll: true,
        forceFormData: true,
    });
};

function onFile(e: Event) {
    const input = e.target as HTMLInputElement;
    form.attachment = input.files?.[0] ?? null;
}
</script>

<template>
    <div>
        <Head title="Edit attendance" />
        <div class="mb-5">
            <Link
                :href="attendancesShow.url({ attendance: attendance.id })"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to record
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0">
                <h2 class="fw-bold">Edit attendance · {{ attendance.employee.english_name }}</h2>
                <div class="fs-7 text-muted">{{ attendance.attendance_date }}</div>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row g-5">
                        <div class="col-md-4">
                            <label class="form-label">Check in</label>
                            <input
                                v-model="form.check_in_time"
                                type="time"
                                class="form-control form-control-solid"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Check out</label>
                            <input
                                v-model="form.check_out_time"
                                type="time"
                                class="form-control form-control-solid"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Break (minutes)</label>
                            <input
                                v-model.number="form.break_minutes"
                                type="number"
                                min="0"
                                class="form-control form-control-solid"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Status</label>
                            <select
                                v-model="form.status"
                                class="form-select form-select-solid"
                                required
                            >
                                <option
                                    v-for="(label, key) in enumLabels.status"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Source</label>
                            <select
                                v-model="form.attendance_source"
                                class="form-select form-select-solid"
                                required
                            >
                                <option
                                    v-for="(label, key) in enumLabels.attendance_source"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shift</label>
                            <select
                                v-model="form.shift_id"
                                class="form-select form-select-solid"
                            >
                                <option :value="null">—</option>
                                <option
                                    v-for="s in filterOptions.shifts"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Scheduled in</label>
                            <input
                                v-model="form.scheduled_check_in"
                                type="time"
                                class="form-control form-control-solid"
                            />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Scheduled out</label>
                            <input
                                v-model="form.scheduled_check_out"
                                type="time"
                                class="form-control form-control-solid"
                            />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <select
                                v-model="form.location_id"
                                class="form-select form-select-solid"
                            >
                                <option :value="null">—</option>
                                <option
                                    v-for="l in filterOptions.locations"
                                    :key="l.id"
                                    :value="l.id"
                                >
                                    {{ l.name }}
                                </option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Comments</label>
                            <textarea
                                v-model="form.comments"
                                class="form-control form-control-solid"
                                rows="3"
                            />
                        </div>
                        <div class="col-12">
                            <label class="form-label">Replace attachment</label>
                            <input
                                type="file"
                                class="form-control form-control-solid"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                @change="onFile"
                            />
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-check-custom form-check-solid mt-3">
                                <input
                                    id="is_holiday"
                                    v-model="form.is_holiday"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="is_holiday"
                                >
                                    Holiday context (policy / payroll rules)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-check-custom form-check-solid mt-3">
                                <input
                                    id="is_manual_edit"
                                    v-model="form.is_manual_edit"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="is_manual_edit"
                                >
                                    Manual correction (requires reason)
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Edit reason</label>
                            <textarea
                                v-model="form.edit_reason"
                                class="form-control form-control-solid"
                                rows="2"
                                placeholder="Required when marking as manual correction"
                            />
                            <div
                                v-if="form.errors.edit_reason"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.edit_reason }}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mt-10">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="form.processing"
                        >
                            Save changes
                        </button>
                        <Link
                            :href="attendancesShow.url({ attendance: attendance.id })"
                            class="btn btn-light"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
