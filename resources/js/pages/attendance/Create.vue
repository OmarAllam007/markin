<script setup lang="ts">
/**
 * Manual attendance entry: metrics are computed exclusively on the server after validation.
 * Keep this form thin—business rules belong in AttendanceCalculatorService / ValidationService.
 */
import { store as attendancesStore, index as attendancesIndex } from '@/routes/attendances';
import { Head, Link, useForm } from '@inertiajs/vue3';

type IdName = { id: number; name: string };
type EmployeeOpt = { id: number; english_name: string; arabic_name: string; employee_number: string | null; work_shift_id: number | null };

defineProps<{
    employees: EmployeeOpt[];
    filterOptions: { locations: IdName[]; shifts: IdName[] };
    enumLabels: {
        status: Record<string, string>;
        attendance_source: Record<string, string>;
    };
}>();

const today = new Date().toISOString().slice(0, 10);

const form = useForm({
    employee_id: null as number | null,
    attendance_date: today,
    check_in_time: '',
    check_out_time: '',
    break_minutes: 0,
    status: 'present',
    shift_id: null as number | null,
    scheduled_check_in: '',
    scheduled_check_out: '',
    attendance_source: 'manual',
    comments: '',
    attachment: null as File | null,
    location_id: null as number | null,
    latitude: '' as string | number | '',
    longitude: '' as string | number | '',
    gps_accuracy: '' as string | number | '',
    address: '',
    device_id: '',
    is_holiday: false,
    edit_reason: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        latitude: data.latitude === '' ? null : data.latitude,
        longitude: data.longitude === '' ? null : data.longitude,
        gps_accuracy: data.gps_accuracy === '' ? null : data.gps_accuracy,
    })).post(attendancesStore.url(), {
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
        <Head title="Add attendance" />
        <div class="mb-5">
            <Link
                :href="attendancesIndex.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to attendance
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0">
                <h2 class="fw-bold">Create attendance</h2>
            </div>
            <div class="card-body">
                <form @submit.prevent="submit">
                    <div class="row g-5">
                        <div class="col-md-6">
                            <label class="form-label required">Employee</label>
                            <select
                                v-model="form.employee_id"
                                class="form-select form-select-solid"
                                required
                            >
                                <option :value="null">Select employee…</option>
                                <option
                                    v-for="e in employees"
                                    :key="e.id"
                                    :value="e.id"
                                >
                                    {{ e.english_name }}
                                    {{ e.employee_number ? `(${e.employee_number})` : '' }}
                                </option>
                            </select>
                            <div
                                v-if="form.errors.employee_id"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.employee_id }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required">Date</label>
                            <input
                                v-model="form.attendance_date"
                                type="date"
                                class="form-control form-control-solid"
                                required
                            />
                            <div
                                v-if="form.errors.attendance_date"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.attendance_date }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Check in</label>
                            <input
                                v-model="form.check_in_time"
                                type="time"
                                class="form-control form-control-solid"
                            />
                            <div
                                v-if="form.errors.check_in_time"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.check_in_time }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Check out</label>
                            <input
                                v-model="form.check_out_time"
                                type="time"
                                class="form-control form-control-solid"
                            />
                            <div
                                v-if="form.errors.check_out_time"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.check_out_time }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Break (minutes)</label>
                            <input
                                v-model.number="form.break_minutes"
                                type="number"
                                min="0"
                                class="form-control form-control-solid"
                            />
                            <div
                                v-if="form.errors.break_minutes"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.break_minutes }}
                            </div>
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
                            <div
                                v-if="form.errors.status"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.status }}
                            </div>
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
                            <div
                                v-if="form.errors.attendance_source"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.attendance_source }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Shift override</label>
                            <select
                                v-model="form.shift_id"
                                class="form-select form-select-solid"
                            >
                                <option :value="null">Employee default</option>
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
                            <label class="form-label">Attachment</label>
                            <input
                                type="file"
                                class="form-control form-control-solid"
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                @change="onFile"
                            />
                            <div
                                v-if="form.errors.attachment"
                                class="text-danger fs-8 mt-1"
                            >
                                {{ form.errors.attachment }}
                            </div>
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
                                    Mark as public holiday (policy context)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mt-10">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="form.processing"
                        >
                            Save
                        </button>
                        <Link
                            :href="attendancesIndex.url()"
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
