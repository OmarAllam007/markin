<script setup lang="ts">
import { update as settingsUpdate } from '@/routes/tenant/settings';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Tab = 'general' | 'attendance' | 'modules';

type TenantModuleItem = {
    module: string;
    label: string;
    is_enabled: boolean;
};

type CompanySettings = {
    company_name: string | null;
    subdomain: string | null;
    cr_number: string | null;
    company_email: string | null;
    address: string | null;
    country: string | null;
    city: string | null;
    postal_number: string | null;
    terms: string | null;
    policy: string | null;
    logo_url: string | null;
    timezone: string;
    attendance_via: string;
    checkin_before_minutes: number | null;
    checkout_after_minutes: number | null;
    allow_temporary_shifts: boolean;
    temporary_shift_calculation: string | null;
    check_biometrics: boolean;
    send_reminders: boolean;
    allow_remote_checkin: boolean;
    allow_any_location_checkin: boolean;
};

type CurrentTenant = {
    id: number;
    name: string;
    parent_id: number | null;
    settings: CompanySettings | null;
};

const page = usePage<{
    auth: { currentTenant: CurrentTenant | null };
    timezones: string[];
    tenantModules: TenantModuleItem[] | null;
}>();
const s = page.props.auth?.currentTenant?.settings;

const groupedTimezones = computed(() => {
    const groups: Record<string, string[]> = {};
    for (const tz of page.props.timezones) {
        const group = tz.includes('/') ? tz.split('/')[0] : 'Other';
        if (!groups[group]) groups[group] = [];
        groups[group].push(tz);
    }
    return groups;
});

const activeTab = ref<Tab>('general');
const logoPreview = ref<string | null>(s?.logo_url ?? null);

const emit = defineEmits<{ close: [] }>();

const form = useForm({
    company_name: s?.company_name ?? '',
    subdomain: s?.subdomain ?? '',
    cr_number: s?.cr_number ?? '',
    company_email: s?.company_email ?? '',
    address: s?.address ?? '',
    country: s?.country ?? '',
    city: s?.city ?? '',
    postal_number: s?.postal_number ?? '',
    terms: s?.terms ?? '',
    policy: s?.policy ?? '',
    logo: null as File | null,
    timezone: s?.timezone ?? 'UTC',

    attendance_via: s?.attendance_via ?? 'all',
    checkin_before_minutes: s?.checkin_before_minutes ?? null,
    checkout_after_minutes: s?.checkout_after_minutes ?? null,
    allow_temporary_shifts: s?.allow_temporary_shifts ?? false,
    temporary_shift_calculation:
        s?.temporary_shift_calculation ?? 'record_only',
    check_biometrics: s?.check_biometrics ?? true,
    send_reminders: s?.send_reminders ?? true,
    allow_remote_checkin: s?.allow_remote_checkin ?? false,
    allow_any_location_checkin: s?.allow_any_location_checkin ?? false,
});

function onLogoChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    form.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

function removeLogo() {
    form.logo = null;
    logoPreview.value = null;
}

function toggleModule(module: string, isEnabled: boolean) {
    router.post(
        '/tenant/modules',
        { module, is_enabled: isEnabled },
        { preserveScroll: true },
    );
}

function submit() {
    form.post(settingsUpdate.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <!-- Backdrop -->
    <div class="modal-backdrop-custom" @click.self="emit('close')">
        <!-- Dialog -->
        <div
            class="modal-dialog-custom"
            role="dialog"
            aria-modal="true"
            aria-labelledby="company-settings-title"
        >
            <!-- Header -->
            <div
                class="settings-header d-flex align-items-center justify-content-between gap-4 px-8 py-6"
            >
                <div class="d-flex align-items-center gap-3">
                    <div
                        class="settings-header-icon d-flex align-items-center justify-content-center flex-shrink-0"
                    >
                        <i
                            class="ki-outline ki-setting-2 fs-2 text-primary"
                        ></i>
                    </div>
                    <div>
                        <h4
                            id="company-settings-title"
                            class="settings-title fw-bold mb-1"
                        >
                            Company Settings
                        </h4>
                        <div class="fs-7 text-muted">
                            Manage your company profile, attendance and modules
                        </div>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-icon btn-light settings-close"
                    aria-label="Close company settings"
                    @click="emit('close')"
                >
                    <i class="ki-outline ki-cross fs-4"></i>
                </button>
            </div>

            <div class="settings-workspace">
                <!-- Nav Pills -->
                <div class="settings-nav px-8">
                    <div class="settings-nav-label">WORKSPACE</div>
                    <ul class="nav nav-pills nav-pills-custom gap-2">
                        <li class="nav-item">
                            <button
                                type="button"
                                class="nav-link fw-semibold fs-7 px-5 py-3"
                                :class="
                                    activeTab === 'general'
                                        ? 'active'
                                        : 'text-gray-600'
                                "
                                @click="activeTab = 'general'"
                            >
                                <i class="ki-outline ki-home-2 fs-5 me-2"></i>
                                General
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                type="button"
                                class="nav-link fw-semibold fs-7 px-5 py-3"
                                :class="
                                    activeTab === 'attendance'
                                        ? 'active'
                                        : 'text-gray-600'
                                "
                                @click="activeTab = 'attendance'"
                            >
                                <i class="ki-outline ki-time fs-5 me-2"></i>
                                Attendance
                            </button>
                        </li>
                        <li v-if="page.props.tenantModules" class="nav-item">
                            <button
                                type="button"
                                class="nav-link fw-semibold fs-7 px-5 py-3"
                                :class="
                                    activeTab === 'modules'
                                        ? 'active'
                                        : 'text-gray-600'
                                "
                                @click="activeTab = 'modules'"
                            >
                                <i
                                    class="ki-outline ki-element-11 fs-5 me-2"
                                ></i>
                                Modules
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="settings-main">
                    <!-- Body -->
                    <form
                        class="modal-body-custom px-8 py-7"
                        @submit.prevent="submit"
                    >
                        <!-- ── General Settings Tab ── -->
                        <div v-show="activeTab === 'general'">
                            <div class="settings-page-heading">
                                <span>01 / COMPANY PROFILE</span>
                                <h2>General information</h2>
                                <p>
                                    Keep your company details and public
                                    information up to date.
                                </p>
                            </div>
                            <!-- Logo upload -->
                            <div class="logo-upload-panel mb-8">
                                <div class="d-flex align-items-center gap-6">
                                    <div
                                        class="logo-preview-box d-flex align-items-center justify-content-center flex-shrink-0 overflow-hidden rounded border border-dashed border-gray-300"
                                        style="width: 88px; height: 88px"
                                    >
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            class="object-fit-contain h-100 w-100"
                                            alt="Logo"
                                        />
                                        <i
                                            v-else
                                            class="ki-outline ki-picture fs-2x text-gray-400"
                                        ></i>
                                    </div>
                                    <div>
                                        <div
                                            class="fw-semibold mb-1 text-gray-800"
                                        >
                                            Company Logo
                                        </div>
                                        <div class="fs-7 text-muted mb-3">
                                            PNG, JPG, WebP up to 2MB.
                                            Recommended 200×200px
                                        </div>
                                        <div class="d-flex gap-2">
                                            <label
                                                class="btn btn-sm btn-light-primary mb-0 cursor-pointer"
                                            >
                                                <i
                                                    class="ki-outline ki-upload fs-6 me-1"
                                                ></i>
                                                Upload
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    class="d-none"
                                                    @change="onLogoChange"
                                                />
                                            </label>
                                            <button
                                                v-if="logoPreview"
                                                type="button"
                                                class="btn btn-sm btn-light-danger"
                                                @click="removeLogo"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <div
                                            v-if="form.errors.logo"
                                            class="text-danger fs-8 mt-2"
                                        >
                                            {{ form.errors.logo }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-section-heading">
                                <h3>Company identity</h3>
                                <p>
                                    The details your team will see across
                                    Markin.
                                </p>
                            </div>

                            <!-- Company identity -->
                            <div class="row g-5 mb-5">
                                <div class="col-md-6">
                                    <label class="form-label"
                                        >Company Name
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.company_name"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid':
                                                form.errors.company_name,
                                        }"
                                        placeholder="Acme Corp"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.company_name"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.company_name }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"
                                        >Custom Subdomain
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <div class="input-group">
                                        <input
                                            v-model="form.subdomain"
                                            type="text"
                                            class="form-control"
                                            :class="{
                                                'is-invalid':
                                                    form.errors.subdomain,
                                            }"
                                            placeholder="mycompany"
                                            autocomplete="off"
                                        />
                                        <span
                                            class="input-group-text text-muted fs-7"
                                            >.markin.app</span
                                        >
                                    </div>
                                    <div
                                        v-if="form.errors.subdomain"
                                        class="invalid-feedback d-block"
                                    >
                                        {{ form.errors.subdomain }}
                                    </div>
                                </div>
                            </div>

                            <div class="row g-5 mb-5">
                                <div class="col-md-4">
                                    <label class="form-label"
                                        >CR Number
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.cr_number"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.cr_number,
                                        }"
                                        placeholder="1234567890"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.cr_number"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.cr_number }}
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label"
                                        >Company Email
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.company_email"
                                        type="email"
                                        class="form-control"
                                        :class="{
                                            'is-invalid':
                                                form.errors.company_email,
                                        }"
                                        placeholder="hello@company.com"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.company_email"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.company_email }}
                                    </div>
                                </div>
                            </div>

                            <!-- Timezone -->
                            <div class="row g-5 mb-5">
                                <div class="col-12">
                                    <label class="form-label">Timezone</label>
                                    <select
                                        v-model="form.timezone"
                                        class="form-select"
                                        :class="{
                                            'is-invalid': form.errors.timezone,
                                        }"
                                    >
                                        <optgroup
                                            v-for="(
                                                zones, region
                                            ) in groupedTimezones"
                                            :key="region"
                                            :label="String(region)"
                                        >
                                            <option
                                                v-for="tz in zones"
                                                :key="tz"
                                                :value="tz"
                                            >
                                                {{ tz }}
                                            </option>
                                        </optgroup>
                                    </select>
                                    <div
                                        v-if="form.errors.timezone"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.timezone }}
                                    </div>
                                    <div class="fs-8 text-muted mt-1">
                                        All attendance times are calculated and
                                        displayed in this timezone
                                    </div>
                                </div>
                            </div>

                            <div class="settings-section-heading">
                                <h3>Location</h3>
                                <p>
                                    Set your company address and default region.
                                </p>
                            </div>
                            <!-- Address -->
                            <div class="row g-5 mb-5">
                                <div class="col-12">
                                    <label class="form-label"
                                        >Address
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.address"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.address,
                                        }"
                                        placeholder="123 Main St"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.address"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.address }}
                                    </div>
                                </div>
                            </div>

                            <div class="row g-5 mb-7">
                                <div class="col-md-4">
                                    <label class="form-label"
                                        >Country
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.country"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.country,
                                        }"
                                        placeholder="Saudi Arabia"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.country"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.country }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"
                                        >City
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.city,
                                        }"
                                        placeholder="Riyadh"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.city"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.city }}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"
                                        >Postal Code
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <input
                                        v-model="form.postal_number"
                                        type="text"
                                        class="form-control"
                                        :class="{
                                            'is-invalid':
                                                form.errors.postal_number,
                                        }"
                                        placeholder="12345"
                                        autocomplete="off"
                                    />
                                    <div
                                        v-if="form.errors.postal_number"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.postal_number }}
                                    </div>
                                </div>
                            </div>

                            <div class="settings-section-heading">
                                <h3>Legal documents</h3>
                                <p>Optional policies shared with your team.</p>
                            </div>

                            <!-- Legal docs -->
                            <div class="row g-5">
                                <div class="col-md-6">
                                    <label class="form-label"
                                        >Terms &amp; Conditions
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <textarea
                                        v-model="form.terms"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.terms,
                                        }"
                                        rows="4"
                                        placeholder="Paste or type your terms and conditions here…"
                                    ></textarea>
                                    <div
                                        v-if="form.errors.terms"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.terms }}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label"
                                        >Privacy Policy
                                        <span class="text-muted fs-8"
                                            >(optional)</span
                                        ></label
                                    >
                                    <textarea
                                        v-model="form.policy"
                                        class="form-control"
                                        :class="{
                                            'is-invalid': form.errors.policy,
                                        }"
                                        rows="4"
                                        placeholder="Paste or type your privacy policy here…"
                                    ></textarea>
                                    <div
                                        v-if="form.errors.policy"
                                        class="invalid-feedback"
                                    >
                                        {{ form.errors.policy }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ── Attendance Settings Tab ── -->
                        <div v-show="activeTab === 'attendance'">
                            <div class="settings-page-heading">
                                <span>02 / ATTENDANCE</span>
                                <h2>Attendance rules</h2>
                                <p>
                                    Choose how your team records time and
                                    receives reminders.
                                </p>
                            </div>
                            <div class="row g-0">
                                <!-- Left column: numeric / select controls -->
                                <div class="col-lg-7 pe-lg-8">
                                    <!-- Attendance via -->
                                    <div class="settings-block mb-6">
                                        <div class="settings-block-label">
                                            Attendance Method
                                        </div>
                                        <div class="settings-block-body">
                                            <select
                                                v-model="form.attendance_via"
                                                class="form-select form-select-sm"
                                                :class="{
                                                    'is-invalid':
                                                        form.errors
                                                            .attendance_via,
                                                }"
                                            >
                                                <option value="all">
                                                    Allow All Options
                                                </option>
                                                <option value="mobile">
                                                    Mobile App Only
                                                </option>
                                                <option value="biometric">
                                                    Biometric Device Only
                                                </option>
                                                <option value="web">
                                                    Web Portal Only
                                                </option>
                                            </select>
                                            <div
                                                v-if="
                                                    form.errors.attendance_via
                                                "
                                                class="invalid-feedback"
                                            >
                                                {{ form.errors.attendance_via }}
                                            </div>
                                            <div class="fs-8 text-muted mt-1">
                                                How employees can record their
                                                attendance
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Inline time buffers -->
                                    <div class="settings-block mb-6">
                                        <div class="settings-block-label">
                                            Check-In / Check-Out Windows
                                        </div>
                                        <div
                                            class="settings-block-body d-flex flex-column gap-4"
                                        >
                                            <div>
                                                <div
                                                    class="d-flex align-items-center flex-wrap gap-3"
                                                >
                                                    <span
                                                        class="fw-semibold fs-7 flex-shrink-0 text-gray-700"
                                                        >Allow check-in</span
                                                    >
                                                    <input
                                                        v-model.number="
                                                            form.checkin_before_minutes
                                                        "
                                                        type="number"
                                                        min="0"
                                                        max="720"
                                                        class="form-control form-control-sm w-70px text-center"
                                                        :class="{
                                                            'is-invalid':
                                                                form.errors
                                                                    .checkin_before_minutes,
                                                        }"
                                                        placeholder="—"
                                                    />
                                                    <span
                                                        class="fs-7 text-gray-600"
                                                        >minutes before shift
                                                        starts</span
                                                    >
                                                </div>
                                                <div
                                                    v-if="
                                                        form.errors
                                                            .checkin_before_minutes
                                                    "
                                                    class="text-danger fs-8 mt-1"
                                                >
                                                    {{
                                                        form.errors
                                                            .checkin_before_minutes
                                                    }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="d-flex align-items-center flex-wrap gap-3"
                                                >
                                                    <span
                                                        class="fw-semibold fs-7 flex-shrink-0 text-gray-700"
                                                        >Allow check-out</span
                                                    >
                                                    <input
                                                        v-model.number="
                                                            form.checkout_after_minutes
                                                        "
                                                        type="number"
                                                        min="0"
                                                        max="720"
                                                        class="form-control form-control-sm w-70px text-center"
                                                        :class="{
                                                            'is-invalid':
                                                                form.errors
                                                                    .checkout_after_minutes,
                                                        }"
                                                        placeholder="—"
                                                    />
                                                    <span
                                                        class="fs-7 text-gray-600"
                                                        >minutes after shift
                                                        ends</span
                                                    >
                                                </div>
                                                <div
                                                    v-if="
                                                        form.errors
                                                            .checkout_after_minutes
                                                    "
                                                    class="text-danger fs-8 mt-1"
                                                >
                                                    {{
                                                        form.errors
                                                            .checkout_after_minutes
                                                    }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Temporary shifts -->
                                    <div class="settings-block mb-6">
                                        <div
                                            class="d-flex align-items-center justify-content-between mb-3"
                                        >
                                            <div
                                                class="settings-block-label mb-0"
                                            >
                                                Emergency / Temporary Shifts
                                            </div>
                                            <div
                                                class="form-check form-switch ms-4"
                                            >
                                                <input
                                                    id="allow-temp-shifts"
                                                    v-model="
                                                        form.allow_temporary_shifts
                                                    "
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    role="switch"
                                                />
                                                <label
                                                    class="form-check-label fs-8 text-muted"
                                                    for="allow-temp-shifts"
                                                >
                                                    {{
                                                        form.allow_temporary_shifts
                                                            ? 'On'
                                                            : 'Off'
                                                    }}
                                                </label>
                                            </div>
                                        </div>
                                        <div v-if="form.allow_temporary_shifts">
                                            <div class="fs-8 text-muted mb-2">
                                                How to calculate temporary shift
                                                hours
                                            </div>
                                            <select
                                                v-model="
                                                    form.temporary_shift_calculation
                                                "
                                                class="form-select form-select-sm"
                                                :class="{
                                                    'is-invalid':
                                                        form.errors
                                                            .temporary_shift_calculation,
                                                }"
                                            >
                                                <option value="record_only">
                                                    Only record check-in and
                                                    check-out times
                                                </option>
                                                <option
                                                    value="apply_normal_rules"
                                                >
                                                    Apply normal attendance
                                                    rules
                                                </option>
                                                <option value="custom_hours">
                                                    Use custom hours
                                                </option>
                                            </select>
                                            <div
                                                v-if="
                                                    form.errors
                                                        .temporary_shift_calculation
                                                "
                                                class="invalid-feedback"
                                            >
                                                {{
                                                    form.errors
                                                        .temporary_shift_calculation
                                                }}
                                            </div>
                                        </div>
                                        <div v-else class="fs-8 text-muted">
                                            Employees cannot add temporary or
                                            emergency shifts
                                        </div>
                                    </div>
                                </div>

                                <!-- Divider -->
                                <div
                                    class="col-lg-1 d-none d-lg-flex justify-content-center"
                                >
                                    <div
                                        class="border-start h-100 border-dashed border-gray-200"
                                    ></div>
                                </div>

                                <!-- Right column: toggle switches -->
                                <div class="col-lg-4">
                                    <div
                                        class="fs-8 fw-bold text-uppercase mb-4 text-gray-400"
                                    >
                                        Access &amp; Notifications
                                    </div>

                                    <div class="d-flex flex-column gap-5">
                                        <div
                                            class="d-flex align-items-start justify-content-between gap-3"
                                        >
                                            <div>
                                                <div
                                                    class="fw-semibold fs-7 text-gray-800"
                                                >
                                                    Biometric Verification
                                                </div>
                                                <div class="fs-8 text-muted">
                                                    Fingerprint or Face ID
                                                    required
                                                </div>
                                            </div>
                                            <div
                                                class="form-check form-switch flex-shrink-0"
                                            >
                                                <input
                                                    v-model="
                                                        form.check_biometrics
                                                    "
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    role="switch"
                                                />
                                            </div>
                                        </div>

                                        <div class="separator"></div>

                                        <div
                                            class="d-flex align-items-start justify-content-between gap-3"
                                        >
                                            <div>
                                                <div
                                                    class="fw-semibold fs-7 text-gray-800"
                                                >
                                                    Shift Reminders
                                                </div>
                                                <div class="fs-8 text-muted">
                                                    Notify before check-in/out
                                                    times
                                                </div>
                                            </div>
                                            <div
                                                class="form-check form-switch flex-shrink-0"
                                            >
                                                <input
                                                    v-model="
                                                        form.send_reminders
                                                    "
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    role="switch"
                                                />
                                            </div>
                                        </div>

                                        <div class="separator"></div>

                                        <div
                                            class="d-flex align-items-start justify-content-between gap-3"
                                        >
                                            <div>
                                                <div
                                                    class="fw-semibold fs-7 text-gray-800"
                                                >
                                                    Remote Check-In
                                                </div>
                                                <div class="fs-8 text-muted">
                                                    Allow outside assigned
                                                    location
                                                </div>
                                            </div>
                                            <div
                                                class="form-check form-switch flex-shrink-0"
                                            >
                                                <input
                                                    v-model="
                                                        form.allow_remote_checkin
                                                    "
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    role="switch"
                                                />
                                            </div>
                                        </div>

                                        <div class="separator"></div>

                                        <div
                                            class="d-flex align-items-start justify-content-between gap-3"
                                        >
                                            <div>
                                                <div
                                                    class="fw-semibold fs-7 text-gray-800"
                                                >
                                                    Any Branch Check-In
                                                </div>
                                                <div class="fs-8 text-muted">
                                                    Check in from any saved
                                                    branch
                                                    <i
                                                        class="ki-outline ki-information-5 fs-7 ms-1 text-gray-400"
                                                        title="Employee can check in from any of the company's registered locations"
                                                    ></i>
                                                </div>
                                            </div>
                                            <div
                                                class="form-check form-switch flex-shrink-0"
                                            >
                                                <input
                                                    v-model="
                                                        form.allow_any_location_checkin
                                                    "
                                                    type="checkbox"
                                                    class="form-check-input"
                                                    role="switch"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- ── Modules Tab ── -->
                        <div
                            v-if="
                                activeTab === 'modules' &&
                                page.props.tenantModules
                            "
                            v-show="activeTab === 'modules'"
                        >
                            <div class="settings-page-heading">
                                <span>03 / MODULES</span>
                                <h2>Available modules</h2>
                                <p>Control which tools your team can access.</p>
                            </div>
                            <div class="fs-7 text-muted mb-6">
                                Enable or disable optional modules for this
                                tenant. Changes take effect immediately.
                            </div>
                            <div class="d-flex flex-column gap-4">
                                <div
                                    v-for="item in page.props.tenantModules"
                                    :key="item.module"
                                    class="d-flex align-items-center justify-content-between rounded border border-gray-200 p-5"
                                >
                                    <div
                                        class="d-flex align-items-center gap-4"
                                    >
                                        <div
                                            class="w-40px h-40px bg-light-primary d-flex align-items-center justify-content-center flex-shrink-0 rounded"
                                        >
                                            <i
                                                class="ki-outline ki-element-11 fs-4 text-primary"
                                            ></i>
                                        </div>
                                        <div>
                                            <div
                                                class="fw-semibold fs-6 text-gray-800"
                                            >
                                                {{ item.label }}
                                            </div>
                                            <div class="fs-8 text-muted">
                                                {{
                                                    item.is_enabled
                                                        ? 'Active — users can access this module'
                                                        : 'Inactive — access is blocked for all users'
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="form-check form-switch ms-4 flex-shrink-0"
                                    >
                                        <input
                                            :id="`module-${item.module}`"
                                            type="checkbox"
                                            class="form-check-input"
                                            role="switch"
                                            :checked="item.is_enabled"
                                            @change="
                                                toggleModule(
                                                    item.module,
                                                    (
                                                        $event.target as HTMLInputElement
                                                    ).checked,
                                                )
                                            "
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- Footer -->
                    <div
                        v-if="activeTab !== 'modules'"
                        class="settings-footer d-flex align-items-center justify-content-between gap-3 px-8 py-5"
                    >
                        <div>
                            <span
                                v-if="form.hasErrors"
                                class="text-danger fs-7"
                            >
                                <i class="ki-outline ki-information-5 me-1"></i>
                                Please fix the errors above
                            </span>
                        </div>
                        <div class="d-flex gap-3">
                            <button
                                type="button"
                                class="btn btn-light"
                                :disabled="form.processing"
                                @click="emit('close')"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="btn btn-primary"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                <span
                                    v-if="form.processing"
                                    class="spinner-border spinner-border-sm me-2"
                                />
                                <i
                                    v-else
                                    class="ki-outline ki-check fs-5 me-1"
                                ></i>
                                Save Settings
                            </button>
                        </div>
                    </div>

                    <!-- Modules footer (just close) -->
                    <div
                        v-if="activeTab === 'modules'"
                        class="settings-footer d-flex align-items-center justify-content-end gap-3 px-8 py-5"
                    >
                        <button
                            type="button"
                            class="btn btn-light"
                            @click="emit('close')"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(21, 34, 52, 0.56);
    z-index: 1055;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.modal-dialog-custom {
    background: #fff;
    border: 1px solid rgba(255, 255, 255, 0.75);
    border-radius: 1.25rem;
    width: 100%;
    max-width: 900px;
    max-height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
    box-shadow: 0 28px 90px rgba(12, 30, 55, 0.28);
    overflow: hidden;
}

.settings-header {
    background: linear-gradient(115deg, #f3f8ff 0%, #fff 72%);
    border-bottom: 1px solid #e5eaf2;
}

.settings-header-icon {
    width: 52px;
    height: 52px;
    border: 1px solid #bdcbed;
    border-radius: 15px;
    background: #dce5fa;
}

.settings-title {
    color: #19243e;
    font-size: 1.35rem;
    letter-spacing: -0.025em;
}

.settings-close {
    border-radius: 10px;
}

.settings-nav {
    border-bottom: 1px solid #e5eaf2;
    background: #fff;
}

.settings-nav .nav {
    flex-wrap: nowrap;
    overflow-x: auto;
    padding-top: 0.75rem;
    scrollbar-width: none;
}

.settings-nav .nav::-webkit-scrollbar {
    display: none;
}

.modal-body-custom {
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    background: #fcfdff;
}

.logo-upload-panel {
    padding: 1.25rem;
    border: 1px solid #e6edf6;
    border-radius: 0.9rem;
    background: #fff;
}

.logo-preview-box {
    background: #f7faff;
}

.settings-footer {
    border-top: 1px solid #e5eaf2;
    background: #fff;
}

.settings-footer .btn {
    border-radius: 0.65rem;
}

.nav-pills-custom .nav-link {
    border-radius: 0;
    border: 0;
    border-bottom: 3px solid transparent;
    white-space: nowrap;
    transition: all 0.15s;
}

.nav-pills-custom .nav-link:not(.active):hover {
    background: #f5f8fa;
    color: var(--kt-primary);
}

.nav-pills-custom .nav-link.active {
    background: transparent;
    color: var(--kt-primary);
    border-color: var(--kt-primary);
}

@media (max-width: 767px) {
    .modal-backdrop-custom {
        padding: 0;
        align-items: flex-end;
    }
    .modal-dialog-custom {
        max-height: 100dvh;
        border-radius: 1.25rem 1.25rem 0 0;
    }
    .settings-header,
    .settings-nav,
    .modal-body-custom,
    .settings-footer {
        padding-left: 1.25rem !important;
        padding-right: 1.25rem !important;
    }
    .settings-footer {
        flex-wrap: wrap;
    }
    .settings-footer > .d-flex:last-child {
        width: 100%;
        justify-content: flex-end;
    }
}

.settings-block-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #a1a5b7;
    margin-bottom: 0.6rem;
}

/* Settings workspace */
.modal-dialog-custom {
    max-width: 1120px;
    height: min(790px, calc(100dvh - 3rem));
    border-radius: 18px;
}
.settings-header {
    padding-top: 1.35rem !important;
    padding-bottom: 1.35rem !important;
    background: #fff;
}
.settings-header-icon {
    width: 42px;
    height: 42px;
    border-radius: 11px;
    background: #dce5fa;
}
.settings-title {
    font-size: 1.2rem;
}
.settings-workspace {
    display: flex;
    min-height: 0;
    flex: 1;
}
.settings-nav {
    flex: 0 0 225px;
    padding: 1.75rem 0.8rem !important;
    border-right: 1px solid #e5eaf2;
    border-bottom: 0;
    background: #f0f4fc;
}
.settings-nav-label {
    padding: 0 1rem 1rem;
    color: #738099;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.13em;
}
.settings-nav .nav {
    display: flex;
    flex-direction: column;
    gap: 0.35rem !important;
    overflow: visible;
    padding: 0;
}
.settings-nav .nav-item {
    width: 100%;
}
.nav-pills-custom .nav-link {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.85rem 1rem !important;
    border: 0;
    border-radius: 9px;
    color: #738099 !important;
    text-align: left;
}
.nav-pills-custom .nav-link i {
    width: 24px;
}
.nav-pills-custom .nav-link.active {
    background: #dce5fa;
    color: #19243e !important;
    box-shadow: inset 3px 0 #19243e;
}
.nav-pills-custom .nav-link:not(.active):hover {
    background: #e6ecfa;
}
.settings-main {
    display: flex;
    flex: 1;
    min-width: 0;
    min-height: 0;
    flex-direction: column;
}
.modal-body-custom {
    padding: 2.25rem 2.5rem !important;
    background: #fff;
}
.settings-page-heading {
    margin-bottom: 2rem;
}
.settings-page-heading span {
    color: var(--bs-primary);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.13em;
}
.settings-page-heading h2 {
    margin: 0.45rem 0 0.3rem;
    color: #19243e;
    font-size: 1.65rem;
    font-weight: 700;
    letter-spacing: -0.035em;
}
.settings-page-heading p,
.settings-section-heading p {
    margin: 0;
    color: #738099;
    font-size: 0.85rem;
}
.settings-section-heading {
    margin: 2.2rem 0 1.25rem;
    padding-top: 1.6rem;
    border-top: 1px solid #e5eaf2;
}
.settings-section-heading h3 {
    margin: 0 0 0.2rem;
    color: #19243e;
    font-size: 1rem;
    font-weight: 700;
}
.logo-upload-panel {
    margin-bottom: 0 !important;
    padding: 1.2rem;
    border-color: #e5eaf2;
    background: #f0f4fc;
}
.logo-preview-box {
    border-radius: 12px !important;
    background: #fff;
}
.modal-body-custom .form-label {
    margin-bottom: 0.5rem;
    color: #394762;
    font-weight: 600;
}
.modal-body-custom .form-control,
.modal-body-custom .form-select,
.modal-body-custom .input-group-text {
    min-height: 42px;
    border-color: #dbe2ee;
    border-radius: 8px;
}
.modal-body-custom .input-group .form-control {
    border-radius: 8px 0 0 8px;
}
.modal-body-custom .input-group-text {
    border-radius: 0 8px 8px 0;
}
.modal-body-custom .form-control:focus,
.modal-body-custom .form-select:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 3px rgba(27, 132, 255, 0.12);
}
.settings-footer {
    padding: 1rem 2.5rem !important;
}
@media (max-width: 767px) {
    .modal-dialog-custom {
        height: min(100dvh, 900px);
    }
    .settings-workspace {
        flex-direction: column;
    }
    .settings-nav {
        flex: none;
        padding: 0.45rem 1rem !important;
        border-right: 0;
        border-bottom: 1px solid #e5eaf2;
    }
    .settings-nav-label {
        display: none;
    }
    .settings-nav .nav {
        flex-direction: row;
        flex-wrap: nowrap;
        overflow-x: auto;
    }
    .settings-nav .nav-item {
        width: auto;
    }
    .nav-pills-custom .nav-link {
        width: auto;
        white-space: nowrap;
    }
    .nav-pills-custom .nav-link.active {
        box-shadow: inset 0 -3px #19243e;
    }
    .modal-body-custom {
        padding: 1.5rem !important;
    }
    .settings-footer {
        padding: 1rem 1.5rem !important;
    }
}
</style>
