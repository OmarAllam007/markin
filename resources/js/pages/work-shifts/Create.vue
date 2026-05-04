<script setup lang="ts">
import { store, index } from '@/routes/work-shifts';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type TabKey = 'main' | 'overtime' | 'break';

const activeTab = ref<TabKey>('main');

const WEEKDAYS = [
    { value: 'monday', label: 'Mon' },
    { value: 'tuesday', label: 'Tue' },
    { value: 'wednesday', label: 'Wed' },
    { value: 'thursday', label: 'Thu' },
    { value: 'friday', label: 'Fri' },
    { value: 'saturday', label: 'Sat' },
    { value: 'sunday', label: 'Sun' },
];

const form = useForm({
    name: '',
    type: 'fixed' as 'fixed' | 'flexible',
    weekends: [] as string[],
    // Fixed
    checkin_time: '',
    checkout_time: '',
    // Flexible
    working_hours: null as number | null,
    working_minutes: null as number | null,
    limit_checkin_from: '',
    limit_checkin_to: '',
    // Overtime
    overtime_enabled: false,
    overtime_hours: null as number | null,
    overtime_minutes: null as number | null,
    calculate_overtime_early_checkin: false,
    // Break
    break_random_checks: null as number | null,
    break_hours: null as number | null,
    break_minutes: null as number | null,
    break_start_from: '',
    break_start_to: '',
    break_apply_as_overtime: false,
});

const toggleWeekend = (day: string) => {
    const idx = form.weekends.indexOf(day);
    if (idx === -1) {
        form.weekends.push(day);
    } else {
        form.weekends.splice(idx, 1);
    }
};

const submit = () => {
    form.post(store.url());
};
</script>

<template>
    <div>
        <Head title="New work shift" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to work shifts
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">New work shift</h2>
                </div>
            </div>

            <!-- Tabs nav -->
            <div class="card-header border-bottom px-9 pt-0 pb-0">
                <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-5 fw-semibold">
                    <li class="nav-item mt-2">
                        <button
                            type="button"
                            class="nav-link text-active-primary pb-4 me-6"
                            :class="{ active: activeTab === 'main' }"
                            @click="activeTab = 'main'"
                        >
                            Main
                        </button>
                    </li>
                    <li class="nav-item mt-2">
                        <button
                            type="button"
                            class="nav-link text-active-primary pb-4 me-6"
                            :class="{ active: activeTab === 'overtime' }"
                            @click="activeTab = 'overtime'"
                        >
                            Late &amp; Overtime
                        </button>
                    </li>
                    <li class="nav-item mt-2">
                        <button
                            type="button"
                            class="nav-link text-active-primary pb-4 me-6"
                            :class="{ active: activeTab === 'break' }"
                            @click="activeTab = 'break'"
                        >
                            Break Time
                        </button>
                    </li>
                </ul>
            </div>

            <form
                class="form"
                @submit.prevent="submit"
            >
                <!-- Main tab -->
                <div
                    v-show="activeTab === 'main'"
                    class="card-body border-top p-9"
                >
                    <!-- Name -->
                    <div class="row g-5 mb-7">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="ws-name"
                            >Name</label>
                            <input
                                id="ws-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.name"
                                class="invalid-feedback"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>
                    </div>

                    <!-- Shift type -->
                    <div class="row g-5 mb-7">
                        <div class="col-12">
                            <label class="form-label required d-block mb-3">Shift type</label>
                            <div class="d-flex gap-6">
                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.type"
                                        type="radio"
                                        class="form-check-input"
                                        value="fixed"
                                    />
                                    <span class="fw-semibold">Fixed</span>
                                </label>
                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                    <input
                                        v-model="form.type"
                                        type="radio"
                                        class="form-check-input"
                                        value="flexible"
                                    />
                                    <span class="fw-semibold">Flexible</span>
                                </label>
                            </div>
                            <div
                                v-if="form.errors.type"
                                class="text-danger fs-7 mt-1"
                            >
                                {{ form.errors.type }}
                            </div>
                        </div>
                    </div>

                    <!-- Weekends -->
                    <div class="row g-5 mb-7">
                        <div class="col-12">
                            <label class="form-label d-block mb-3">Weekends / Days off</label>
                            <div class="d-flex flex-wrap gap-3">
                                <label
                                    v-for="day in WEEKDAYS"
                                    :key="day.value"
                                    class="d-flex align-items-center gap-2 border rounded px-4 py-2 cursor-pointer"
                                    :class="{
                                        'border-primary bg-light-primary': form.weekends.includes(day.value),
                                        'border-gray-300': !form.weekends.includes(day.value),
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        :checked="form.weekends.includes(day.value)"
                                        @change="toggleWeekend(day.value)"
                                    />
                                    <span class="fw-semibold fs-7">{{ day.label }}</span>
                                </label>
                            </div>
                            <div
                                v-if="form.errors.weekends"
                                class="text-danger fs-7 mt-1"
                            >
                                {{ form.errors.weekends }}
                            </div>
                        </div>
                    </div>

                    <!-- Fixed shift fields -->
                    <template v-if="form.type === 'fixed'">
                        <div class="row g-5 mb-7">
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-checkin"
                                >Check-in time</label>
                                <input
                                    id="ws-checkin"
                                    v-model="form.checkin_time"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.checkin_time }"
                                />
                                <div
                                    v-if="form.errors.checkin_time"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.checkin_time }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-checkout"
                                >Check-out time</label>
                                <input
                                    id="ws-checkout"
                                    v-model="form.checkout_time"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.checkout_time }"
                                />
                                <div
                                    v-if="form.errors.checkout_time"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.checkout_time }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Flexible shift fields -->
                    <template v-if="form.type === 'flexible'">
                        <div class="row g-5 mb-7">
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-hours"
                                >Working hours</label>
                                <input
                                    id="ws-hours"
                                    v-model.number="form.working_hours"
                                    type="number"
                                    min="0"
                                    max="23"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.working_hours }"
                                    placeholder="0"
                                />
                                <div
                                    v-if="form.errors.working_hours"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.working_hours }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-minutes"
                                >Working minutes</label>
                                <input
                                    id="ws-minutes"
                                    v-model.number="form.working_minutes"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.working_minutes }"
                                    placeholder="0"
                                />
                                <div
                                    v-if="form.errors.working_minutes"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.working_minutes }}
                                </div>
                            </div>
                        </div>

                        <!-- Optional limit check-in -->
                        <div class="row g-5 mb-7">
                            <div class="col-12">
                                <div class="text-gray-600 fw-semibold fs-7 mb-3 text-uppercase">
                                    Limit check-in time (optional)
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label
                                    class="form-label"
                                    for="ws-limit-from"
                                >From</label>
                                <input
                                    id="ws-limit-from"
                                    v-model="form.limit_checkin_from"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.limit_checkin_from }"
                                />
                                <div
                                    v-if="form.errors.limit_checkin_from"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.limit_checkin_from }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label
                                    class="form-label"
                                    for="ws-limit-to"
                                >To</label>
                                <input
                                    id="ws-limit-to"
                                    v-model="form.limit_checkin_to"
                                    type="time"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.limit_checkin_to }"
                                />
                                <div
                                    v-if="form.errors.limit_checkin_to"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.limit_checkin_to }}
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Late & Overtime tab -->
                <div
                    v-show="activeTab === 'overtime'"
                    class="card-body border-top p-9"
                >
                    <!-- Enable overtime toggle -->
                    <div class="row g-5 mb-7">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input
                                    id="ws-overtime-enabled"
                                    v-model="form.overtime_enabled"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label fw-semibold"
                                    for="ws-overtime-enabled"
                                >
                                    Enable overtime
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Overtime hours/minutes -->
                    <template v-if="form.overtime_enabled">
                        <div class="row g-5 mb-7">
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-ot-hours"
                                >Overtime hours</label>
                                <input
                                    id="ws-ot-hours"
                                    v-model.number="form.overtime_hours"
                                    type="number"
                                    min="0"
                                    max="23"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.overtime_hours }"
                                    placeholder="0"
                                    @keypress="(e) => !/[0-9]/.test(e.key) && e.preventDefault()"
                                />
                                <div
                                    v-if="form.errors.overtime_hours"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.overtime_hours }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label
                                    class="form-label required"
                                    for="ws-ot-minutes"
                                >Overtime minutes</label>
                                <input
                                    id="ws-ot-minutes"
                                    v-model.number="form.overtime_minutes"
                                    type="number"
                                    min="0"
                                    max="59"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.overtime_minutes }"
                                    placeholder="0"
                                    @keypress="(e) => !/[0-9]/.test(e.key) && e.preventDefault()"
                                />
                                <div
                                    v-if="form.errors.overtime_minutes"
                                    class="invalid-feedback"
                                >
                                    {{ form.errors.overtime_minutes }}
                                </div>
                            </div>
                        </div>

                        <!-- Early check-in overtime -->
                        <div class="row g-5 mb-7">
                            <div class="col-12">
                                <div class="form-check">
                                    <input
                                        id="ws-early-checkin"
                                        v-model="form.calculate_overtime_early_checkin"
                                        class="form-check-input"
                                        type="checkbox"
                                    />
                                    <label
                                        class="form-check-label fw-semibold"
                                        for="ws-early-checkin"
                                    >
                                        Calculate overtime for early check-in before shift time
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Break Time tab -->
                <div
                    v-show="activeTab === 'break'"
                    class="card-body border-top p-9"
                >
                    <!-- Random checks + duration -->
                    <div class="row g-5 mb-7">
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="ws-break-checks"
                            >Number of random checks</label>
                            <input
                                id="ws-break-checks"
                                v-model.number="form.break_random_checks"
                                type="number"
                                min="0"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.break_random_checks }"
                                placeholder="0"
                                @keypress="(e) => !/[0-9]/.test(e.key) && e.preventDefault()"
                            />
                            <div
                                v-if="form.errors.break_random_checks"
                                class="invalid-feedback"
                            >
                                {{ form.errors.break_random_checks }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="ws-break-hours"
                            >Break hours</label>
                            <input
                                id="ws-break-hours"
                                v-model.number="form.break_hours"
                                type="number"
                                min="0"
                                max="23"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.break_hours }"
                                placeholder="0"
                                @keypress="(e) => !/[0-9]/.test(e.key) && e.preventDefault()"
                            />
                            <div
                                v-if="form.errors.break_hours"
                                class="invalid-feedback"
                            >
                                {{ form.errors.break_hours }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="ws-break-minutes"
                            >Break minutes</label>
                            <input
                                id="ws-break-minutes"
                                v-model.number="form.break_minutes"
                                type="number"
                                min="0"
                                max="59"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.break_minutes }"
                                placeholder="0"
                                @keypress="(e) => !/[0-9]/.test(e.key) && e.preventDefault()"
                            />
                            <div
                                v-if="form.errors.break_minutes"
                                class="invalid-feedback"
                            >
                                {{ form.errors.break_minutes }}
                            </div>
                        </div>
                    </div>

                    <!-- Optional start time range -->
                    <div class="row g-5 mb-7">
                        <div class="col-12">
                            <div class="text-gray-600 fw-semibold fs-7 mb-3 text-uppercase">
                                Break start time (optional)
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="ws-break-from"
                            >From</label>
                            <input
                                id="ws-break-from"
                                v-model="form.break_start_from"
                                type="time"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.break_start_from }"
                            />
                            <div
                                v-if="form.errors.break_start_from"
                                class="invalid-feedback"
                            >
                                {{ form.errors.break_start_from }}
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="ws-break-to"
                            >To</label>
                            <input
                                id="ws-break-to"
                                v-model="form.break_start_to"
                                type="time"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.break_start_to }"
                            />
                            <div
                                v-if="form.errors.break_start_to"
                                class="invalid-feedback"
                            >
                                {{ form.errors.break_start_to }}
                            </div>
                        </div>
                    </div>

                    <!-- Apply unused break as overtime -->
                    <div class="row g-5">
                        <div class="col-12">
                            <div class="form-check">
                                <input
                                    id="ws-break-overtime"
                                    v-model="form.break_apply_as_overtime"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label fw-semibold"
                                    for="ws-break-overtime"
                                >
                                    Apply unused break time as overtime
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link
                        :href="index.url()"
                        class="btn btn-light"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Create work shift
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
