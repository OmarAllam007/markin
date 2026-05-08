<script setup lang="ts">
import { store, index } from '@/routes/employees';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

type Option = { value: string; label: string };
type IdName = { id: number; name: string };
type ShiftOption = {
    id: number;
    name: string;
    type: 'fixed' | 'flexible';
    employees_count: number;
    working_hours_label: string;
    off_days_label: string;
};

const props = defineProps<{
    options: {
        genders: Option[];
        marital_statuses: Option[];
        contract_types: Option[];
        religions: Option[];
        departments: IdName[];
        locations: IdName[];
        work_shifts: ShiftOption[];
    };
}>();

const TOTAL_STEPS = 4;
const currentStep = ref(1);

const STEP_FIELDS: Record<number, string[]> = {
    1: ['arabic_name', 'english_name', 'mobile_country_code', 'mobile_number', 'email', 'nationality', 'marital_status', 'birth_date', 'gender', 'religion'],
    2: ['job_title_ar', 'job_title_en', 'employee_number', 'social_security_number', 'id_number', 'working_start_date', 'contract_end_date', 'contract_type'],
    3: ['department_id', 'location_id', 'check_biometrics', 'send_reminders', 'allow_remote_checkin', 'allow_any_location_checkin'],
    4: ['work_shift_id'],
};

const form = useForm({
    // Step 1
    arabic_name: '',
    english_name: '',
    mobile_country_code: '+966',
    mobile_number: '',
    email: '',
    nationality: '',
    marital_status: '',
    birth_date: '',
    gender: '',
    religion: '',
    // Step 2
    job_title_ar: '',
    job_title_en: '',
    employee_number: '',
    social_security_number: '',
    id_number: '',
    working_start_date: '',
    contract_end_date: '',
    contract_type: '',
    // Step 3
    department_id: null as number | null,
    location_id: null as number | null,
    check_biometrics: false,
    send_reminders: false,
    allow_remote_checkin: false,
    allow_any_location_checkin: false,
    // Step 4
    work_shift_id: null as number | null,
});

watch(
    () => form.errors,
    (errors) => {
        const keys = Object.keys(errors);
        for (let step = 1; step <= TOTAL_STEPS; step++) {
            if (keys.some((k) => STEP_FIELDS[step].includes(k))) {
                currentStep.value = step;
                break;
            }
        }
    },
);

const selectShift = (id: number) => {
    form.work_shift_id = form.work_shift_id === id ? null : id;
};

const submit = () => form.post(store.url());

const STEPS = [
    { label: 'Employee Details', sub: 'Personal information' },
    { label: 'Job Details', sub: 'Employment information' },
    { label: 'Settings', sub: 'Department & attendance' },
    { label: 'Shift', sub: 'Assign work shift' },
];
</script>

<template>
    <div>
        <Head title="New employee" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to employees
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">New employee</h2>
                </div>
            </div>

            <!-- Step indicator -->
            <div class="card-body border-top pb-0 pt-8">
                <div class="d-flex align-items-center">
                    <template
                        v-for="(step, i) in STEPS"
                        :key="i"
                    >
                        <div class="d-flex align-items-center gap-3 flex-shrink-0">
                            <div
                                class="w-35px h-35px rounded-circle d-flex align-items-center justify-content-center fw-bold fs-6"
                                :class="currentStep > i + 1 ? 'bg-success text-white' : currentStep === i + 1 ? 'bg-primary text-white' : 'bg-light text-muted'"
                            >
                                <i
                                    v-if="currentStep > i + 1"
                                    class="ki-outline ki-check fs-4 text-white"
                                />
                                <span v-else>{{ i + 1 }}</span>
                            </div>
                            <div class="d-none d-md-block">
                                <div
                                    class="fw-bold fs-7"
                                    :class="currentStep >= i + 1 ? 'text-gray-800' : 'text-muted'"
                                >
                                    {{ step.label }}
                                </div>
                                <div class="fs-8 text-muted">{{ step.sub }}</div>
                            </div>
                        </div>
                        <div
                            v-if="i < STEPS.length - 1"
                            class="flex-grow-1 border-top border-dashed border-2 mx-4"
                        ></div>
                    </template>
                </div>
            </div>

            <form
                class="form"
                @submit.prevent="submit"
            >
                <!-- ── Step 1: Employee Details ── -->
                <div
                    v-show="currentStep === 1"
                    class="card-body pt-8"
                >
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="emp-en-name"
                            >English Name</label>
                            <input
                                id="emp-en-name"
                                v-model="form.english_name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.english_name }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.english_name"
                                class="invalid-feedback"
                            >{{ form.errors.english_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="emp-ar-name"
                            >Arabic Name</label>
                            <input
                                id="emp-ar-name"
                                v-model="form.arabic_name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.arabic_name }"
                                dir="rtl"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.arabic_name"
                                class="invalid-feedback"
                            >{{ form.errors.arabic_name }}</div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-3">
                            <label
                                class="form-label required"
                                for="emp-cc"
                            >Country Code</label>
                            <input
                                id="emp-cc"
                                v-model="form.mobile_country_code"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.mobile_country_code }"
                                placeholder="+966"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.mobile_country_code"
                                class="invalid-feedback"
                            >{{ form.errors.mobile_country_code }}</div>
                        </div>
                        <div class="col-md-5">
                            <label
                                class="form-label required"
                                for="emp-mobile"
                            >Mobile Number</label>
                            <input
                                id="emp-mobile"
                                v-model="form.mobile_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.mobile_number }"
                                placeholder="5xxxxxxxx"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.mobile_number"
                                class="invalid-feedback"
                            >{{ form.errors.mobile_number }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-email"
                            >Email</label>
                            <input
                                id="emp-email"
                                v-model="form.email"
                                type="email"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.email }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.email"
                                class="invalid-feedback"
                            >{{ form.errors.email }}</div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-nationality"
                            >Nationality</label>
                            <input
                                id="emp-nationality"
                                v-model="form.nationality"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.nationality }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.nationality"
                                class="invalid-feedback"
                            >{{ form.errors.nationality }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-marital"
                            >Marital Status</label>
                            <select
                                id="emp-marital"
                                v-model="form.marital_status"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.marital_status }"
                            >
                                <option value="">— Select —</option>
                                <option
                                    v-for="opt in options.marital_statuses"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</option>
                            </select>
                            <div
                                v-if="form.errors.marital_status"
                                class="invalid-feedback"
                            >{{ form.errors.marital_status }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-birth"
                            >Birth Date</label>
                            <input
                                id="emp-birth"
                                v-model="form.birth_date"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.birth_date }"
                            />
                            <div
                                v-if="form.errors.birth_date"
                                class="invalid-feedback"
                            >{{ form.errors.birth_date }}</div>
                        </div>
                    </div>
                    <div class="row g-5">
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-gender"
                            >Gender</label>
                            <select
                                id="emp-gender"
                                v-model="form.gender"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.gender }"
                            >
                                <option value="">— Select —</option>
                                <option
                                    v-for="opt in options.genders"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</option>
                            </select>
                            <div
                                v-if="form.errors.gender"
                                class="invalid-feedback"
                            >{{ form.errors.gender }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-religion"
                            >Religion</label>
                            <select
                                id="emp-religion"
                                v-model="form.religion"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.religion }"
                            >
                                <option value="">— Select —</option>
                                <option
                                    v-for="opt in options.religions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</option>
                            </select>
                            <div
                                v-if="form.errors.religion"
                                class="invalid-feedback"
                            >{{ form.errors.religion }}</div>
                        </div>
                    </div>
                </div>

                <!-- ── Step 2: Job Details ── -->
                <div
                    v-show="currentStep === 2"
                    class="card-body pt-8"
                >
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="emp-title-en"
                            >Job Title (English)</label>
                            <input
                                id="emp-title-en"
                                v-model="form.job_title_en"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.job_title_en }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.job_title_en"
                                class="invalid-feedback"
                            >{{ form.errors.job_title_en }}</div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="emp-title-ar"
                            >Job Title (Arabic)</label>
                            <input
                                id="emp-title-ar"
                                v-model="form.job_title_ar"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.job_title_ar }"
                                dir="rtl"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.job_title_ar"
                                class="invalid-feedback"
                            >{{ form.errors.job_title_ar }}</div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-number"
                            >Employee Number</label>
                            <input
                                id="emp-number"
                                v-model="form.employee_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.employee_number }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.employee_number"
                                class="invalid-feedback"
                            >{{ form.errors.employee_number }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-ssn"
                            >Social Security Number</label>
                            <input
                                id="emp-ssn"
                                v-model="form.social_security_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.social_security_number }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.social_security_number"
                                class="invalid-feedback"
                            >{{ form.errors.social_security_number }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-id"
                            >ID Number</label>
                            <input
                                id="emp-id"
                                v-model="form.id_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.id_number }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.id_number"
                                class="invalid-feedback"
                            >{{ form.errors.id_number }}</div>
                        </div>
                    </div>
                    <div class="row g-5">
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="emp-start"
                            >Working Start Date</label>
                            <input
                                id="emp-start"
                                v-model="form.working_start_date"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.working_start_date }"
                            />
                            <div
                                v-if="form.errors.working_start_date"
                                class="invalid-feedback"
                            >{{ form.errors.working_start_date }}</div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="emp-contract-end"
                            >Contract End Date</label>
                            <input
                                id="emp-contract-end"
                                v-model="form.contract_end_date"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.contract_end_date }"
                            />
                            <div
                                v-if="form.errors.contract_end_date"
                                class="invalid-feedback"
                            >{{ form.errors.contract_end_date }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="emp-contract-type"
                            >Contract Type</label>
                            <select
                                id="emp-contract-type"
                                v-model="form.contract_type"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.contract_type }"
                            >
                                <option value="">— Select —</option>
                                <option
                                    v-for="opt in options.contract_types"
                                    :key="opt.value"
                                    :value="opt.value"
                                >{{ opt.label }}</option>
                            </select>
                            <div
                                v-if="form.errors.contract_type"
                                class="invalid-feedback"
                            >{{ form.errors.contract_type }}</div>
                        </div>
                    </div>
                </div>

                <!-- ── Step 3: Employee Settings ── -->
                <div
                    v-show="currentStep === 3"
                    class="card-body pt-8"
                >
                    <!-- General Settings -->
                    <div class="mb-8">
                        <div class="text-gray-600 fw-semibold fs-7 text-uppercase mb-4">General Settings</div>
                        <div class="row g-5">
                            <div class="col-md-4">
                                <label
                                    class="form-label"
                                    for="emp-dept"
                                >Department</label>
                                <select
                                    id="emp-dept"
                                    v-model="form.department_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors.department_id }"
                                >
                                    <option :value="null">— None —</option>
                                    <option
                                        v-for="d in options.departments"
                                        :key="d.id"
                                        :value="d.id"
                                    >{{ d.name }}</option>
                                </select>
                                <div
                                    v-if="form.errors.department_id"
                                    class="invalid-feedback"
                                >{{ form.errors.department_id }}</div>
                            </div>
                            <div class="col-md-4">
                                <label
                                    class="form-label"
                                    for="emp-loc"
                                >Location</label>
                                <select
                                    id="emp-loc"
                                    v-model="form.location_id"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors.location_id }"
                                >
                                    <option :value="null">— None —</option>
                                    <option
                                        v-for="l in options.locations"
                                        :key="l.id"
                                        :value="l.id"
                                    >{{ l.name }}</option>
                                </select>
                                <div
                                    v-if="form.errors.location_id"
                                    class="invalid-feedback"
                                >{{ form.errors.location_id }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Settings -->
                    <div>
                        <div class="text-gray-600 fw-semibold fs-7 text-uppercase mb-4">Attendance Settings</div>
                        <div class="d-flex flex-column gap-4">
                            <label class="d-flex align-items-start gap-3 cursor-pointer">
                                <input
                                    v-model="form.check_biometrics"
                                    type="checkbox"
                                    class="form-check-input mt-1"
                                />
                                <div>
                                    <div class="fw-semibold text-gray-800">Check Biometrics (Fingerprint / Face ID)</div>
                                    <div class="fs-7 text-muted">Require biometric verification for attendance</div>
                                </div>
                            </label>
                            <label class="d-flex align-items-start gap-3 cursor-pointer">
                                <input
                                    v-model="form.send_reminders"
                                    type="checkbox"
                                    class="form-check-input mt-1"
                                />
                                <div>
                                    <div class="fw-semibold text-gray-800">Send Reminders for Check-In/Out Times</div>
                                    <div class="fs-7 text-muted">Notify the employee before their shift starts and ends</div>
                                </div>
                            </label>
                            <label class="d-flex align-items-start gap-3 cursor-pointer">
                                <input
                                    v-model="form.allow_remote_checkin"
                                    type="checkbox"
                                    class="form-check-input mt-1"
                                />
                                <div>
                                    <div class="fw-semibold text-gray-800">Allow Check-In/Out from Anywhere</div>
                                    <div class="fs-7 text-muted">Employee can check in outside their assigned location</div>
                                </div>
                            </label>
                            <label class="d-flex align-items-start gap-3 cursor-pointer">
                                <input
                                    v-model="form.allow_any_location_checkin"
                                    type="checkbox"
                                    class="form-check-input mt-1"
                                />
                                <div>
                                    <div class="fw-semibold text-gray-800">Allow Check-In from Any Company Location</div>
                                    <div class="fs-7 text-muted">Employee can check in from any saved company branch</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- ── Step 4: Shift ── -->
                <div
                    v-show="currentStep === 4"
                    class="card-body pt-8"
                >
                    <div
                        v-if="options.work_shifts.length === 0"
                        class="text-center text-muted py-10"
                    >
                        No work shifts have been created yet.
                    </div>
                    <div
                        v-else
                        class="row g-4"
                    >
                        <div
                            v-for="shift in options.work_shifts"
                            :key="shift.id"
                            class="col-md-6 col-lg-4"
                        >
                            <div
                                class="border rounded p-5 cursor-pointer h-100"
                                :class="form.work_shift_id === shift.id
                                    ? 'border-primary bg-light-primary'
                                    : 'border-gray-200 bg-white'"
                                @click="selectShift(shift.id)"
                            >
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div class="fw-bold text-gray-800 fs-6">{{ shift.name }}</div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="badge"
                                            :class="shift.type === 'fixed' ? 'badge-light-primary' : 'badge-light-success'"
                                        >
                                            {{ shift.type === 'fixed' ? 'Fixed' : 'Flexible' }}
                                        </span>
                                        <div
                                            class="w-20px h-20px rounded-circle border-2 d-flex align-items-center justify-content-center flex-shrink-0"
                                            :class="form.work_shift_id === shift.id ? 'border-primary bg-primary' : 'border-gray-300'"
                                        >
                                            <i
                                                v-if="form.work_shift_id === shift.id"
                                                class="ki-outline ki-check fs-8 text-white"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-2 fs-7">
                                    <div class="d-flex align-items-center gap-2 text-gray-600">
                                        <i class="ki-outline ki-time fs-6 text-gray-400"></i>
                                        <span>{{ shift.working_hours_label }} working hours</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-gray-600">
                                        <i class="ki-outline ki-calendar fs-6 text-gray-400"></i>
                                        <span>Off: {{ shift.off_days_label }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 text-gray-600">
                                        <i class="ki-outline ki-people fs-6 text-gray-400"></i>
                                        <span>{{ shift.employees_count }} employee{{ shift.employees_count !== 1 ? 's' : '' }} assigned</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="form.errors.work_shift_id"
                        class="text-danger fs-7 mt-3"
                    >
                        {{ form.errors.work_shift_id }}
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer d-flex justify-content-between gap-2">
                    <div>
                        <button
                            v-if="currentStep > 1"
                            type="button"
                            class="btn btn-light"
                            @click="currentStep--"
                        >
                            <i class="ki-outline ki-left fs-4"></i> Back
                        </button>
                        <Link
                            v-else
                            :href="index.url()"
                            class="btn btn-light"
                        >Cancel</Link>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="text-muted fs-7">Step {{ currentStep }} of {{ TOTAL_STEPS }}</span>
                        <button
                            v-if="currentStep < TOTAL_STEPS"
                            type="button"
                            class="btn btn-primary"
                            @click="currentStep++"
                        >
                            Next <i class="ki-outline ki-right fs-4"></i>
                        </button>
                        <button
                            v-else
                            type="submit"
                            class="btn btn-primary"
                            :disabled="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="spinner-border spinner-border-sm me-2"
                            />
                            Create employee
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
