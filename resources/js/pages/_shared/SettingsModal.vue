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
    company_name:                s?.company_name ?? '',
    subdomain:                   s?.subdomain ?? '',
    cr_number:                   s?.cr_number ?? '',
    company_email:               s?.company_email ?? '',
    address:                     s?.address ?? '',
    country:                     s?.country ?? '',
    city:                        s?.city ?? '',
    postal_number:               s?.postal_number ?? '',
    terms:                       s?.terms ?? '',
    policy:                      s?.policy ?? '',
    logo:                        null as File | null,
    timezone:                    s?.timezone ?? 'UTC',

    attendance_via:              s?.attendance_via ?? 'all',
    checkin_before_minutes:      s?.checkin_before_minutes ?? null,
    checkout_after_minutes:      s?.checkout_after_minutes ?? null,
    allow_temporary_shifts:      s?.allow_temporary_shifts ?? false,
    temporary_shift_calculation: s?.temporary_shift_calculation ?? 'record_only',
    check_biometrics:            s?.check_biometrics ?? true,
    send_reminders:              s?.send_reminders ?? true,
    allow_remote_checkin:        s?.allow_remote_checkin ?? false,
    allow_any_location_checkin:  s?.allow_any_location_checkin ?? false,
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
    router.post('/tenant/modules', { module, is_enabled: isEnabled }, { preserveScroll: true });
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
    <div
        class="modal-backdrop-custom"
        @click.self="emit('close')"
    >
        <!-- Dialog -->
        <div class="modal-dialog-custom">
            <!-- Header -->
            <div class="d-flex align-items-center justify-content-between px-8 py-5 border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="w-40px h-40px rounded-circle bg-light-primary d-flex align-items-center justify-content-center">
                        <i class="ki-outline ki-setting-2 fs-4 text-primary"></i>
                    </div>
                    <div>
                        <h4 class="fw-bold text-gray-800 mb-0">Company Settings</h4>
                        <div class="fs-7 text-muted">Manage your company configuration</div>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-icon btn-light"
                    @click="emit('close')"
                >
                    <i class="ki-outline ki-cross fs-4"></i>
                </button>
            </div>

            <!-- Nav Pills -->
            <div class="px-8 pt-5">
                <ul class="nav nav-pills nav-pills-custom gap-2">
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link px-5 py-3 fw-semibold fs-7"
                            :class="activeTab === 'general' ? 'active' : 'text-gray-600'"
                            @click="activeTab = 'general'"
                        >
                            <i class="ki-outline ki-home-2 fs-5 me-2"></i>
                            General
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link px-5 py-3 fw-semibold fs-7"
                            :class="activeTab === 'attendance' ? 'active' : 'text-gray-600'"
                            @click="activeTab = 'attendance'"
                        >
                            <i class="ki-outline ki-time fs-5 me-2"></i>
                            Attendance
                        </button>
                    </li>
                    <li
                        v-if="page.props.tenantModules"
                        class="nav-item"
                    >
                        <button
                            type="button"
                            class="nav-link px-5 py-3 fw-semibold fs-7"
                            :class="activeTab === 'modules' ? 'active' : 'text-gray-600'"
                            @click="activeTab = 'modules'"
                        >
                            <i class="ki-outline ki-element-11 fs-5 me-2"></i>
                            Modules
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Body -->
            <form
                class="modal-body-custom px-8 py-6"
                @submit.prevent="submit"
            >

                <!-- ── General Settings Tab ── -->
                <div v-show="activeTab === 'general'">
                    <!-- Logo upload -->
                    <div class="mb-8">
                        <div class="d-flex align-items-center gap-6">
                            <div
                                class="logo-preview-box rounded border border-dashed border-gray-300 d-flex align-items-center justify-content-center overflow-hidden flex-shrink-0"
                                style="width: 88px; height: 88px;"
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    class="w-100 h-100 object-fit-contain"
                                    alt="Logo"
                                />
                                <i
                                    v-else
                                    class="ki-outline ki-picture fs-2x text-gray-400"
                                ></i>
                            </div>
                            <div>
                                <div class="fw-semibold text-gray-800 mb-1">Company Logo</div>
                                <div class="fs-7 text-muted mb-3">PNG, JPG, WebP up to 2MB. Recommended 200×200px</div>
                                <div class="d-flex gap-2">
                                    <label class="btn btn-sm btn-light-primary cursor-pointer mb-0">
                                        <i class="ki-outline ki-upload fs-6 me-1"></i>
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
                                >{{ form.errors.logo }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="separator mb-7"></div>

                    <!-- Company identity -->
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label class="form-label">Company Name <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.company_name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.company_name }"
                                placeholder="Acme Corp"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.company_name"
                                class="invalid-feedback"
                            >{{ form.errors.company_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Custom Subdomain <span class="text-muted fs-8">(optional)</span></label>
                            <div class="input-group">
                                <input
                                    v-model="form.subdomain"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.subdomain }"
                                    placeholder="mycompany"
                                    autocomplete="off"
                                />
                                <span class="input-group-text text-muted fs-7">.markin.app</span>
                            </div>
                            <div
                                v-if="form.errors.subdomain"
                                class="invalid-feedback d-block"
                            >{{ form.errors.subdomain }}</div>
                        </div>
                    </div>

                    <div class="row g-5 mb-5">
                        <div class="col-md-4">
                            <label class="form-label">CR Number <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.cr_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.cr_number }"
                                placeholder="1234567890"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.cr_number"
                                class="invalid-feedback"
                            >{{ form.errors.cr_number }}</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Company Email <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.company_email"
                                type="email"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.company_email }"
                                placeholder="hello@company.com"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.company_email"
                                class="invalid-feedback"
                            >{{ form.errors.company_email }}</div>
                        </div>
                    </div>

                    <!-- Timezone -->
                    <div class="row g-5 mb-5">
                        <div class="col-12">
                            <label class="form-label">Timezone</label>
                            <select
                                v-model="form.timezone"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.timezone }"
                            >
                                <optgroup
                                    v-for="(zones, region) in groupedTimezones"
                                    :key="region"
                                    :label="String(region)"
                                >
                                    <option
                                        v-for="tz in zones"
                                        :key="tz"
                                        :value="tz"
                                    >{{ tz }}</option>
                                </optgroup>
                            </select>
                            <div
                                v-if="form.errors.timezone"
                                class="invalid-feedback"
                            >{{ form.errors.timezone }}</div>
                            <div class="fs-8 text-muted mt-1">All attendance times are calculated and displayed in this timezone</div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="row g-5 mb-5">
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.address"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.address }"
                                placeholder="123 Main St"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.address"
                                class="invalid-feedback"
                            >{{ form.errors.address }}</div>
                        </div>
                    </div>

                    <div class="row g-5 mb-7">
                        <div class="col-md-4">
                            <label class="form-label">Country <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.country"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.country }"
                                placeholder="Saudi Arabia"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.country"
                                class="invalid-feedback"
                            >{{ form.errors.country }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.city }"
                                placeholder="Riyadh"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.city"
                                class="invalid-feedback"
                            >{{ form.errors.city }}</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postal Code <span class="text-muted fs-8">(optional)</span></label>
                            <input
                                v-model="form.postal_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.postal_number }"
                                placeholder="12345"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.postal_number"
                                class="invalid-feedback"
                            >{{ form.errors.postal_number }}</div>
                        </div>
                    </div>

                    <div class="separator mb-7"></div>

                    <!-- Legal docs -->
                    <div class="row g-5">
                        <div class="col-md-6">
                            <label class="form-label">Terms &amp; Conditions <span class="text-muted fs-8">(optional)</span></label>
                            <textarea
                                v-model="form.terms"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.terms }"
                                rows="4"
                                placeholder="Paste or type your terms and conditions here…"
                            ></textarea>
                            <div
                                v-if="form.errors.terms"
                                class="invalid-feedback"
                            >{{ form.errors.terms }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Privacy Policy <span class="text-muted fs-8">(optional)</span></label>
                            <textarea
                                v-model="form.policy"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.policy }"
                                rows="4"
                                placeholder="Paste or type your privacy policy here…"
                            ></textarea>
                            <div
                                v-if="form.errors.policy"
                                class="invalid-feedback"
                            >{{ form.errors.policy }}</div>
                        </div>
                    </div>
                </div>

                <!-- ── Attendance Settings Tab ── -->
                <div v-show="activeTab === 'attendance'">
                    <div class="row g-0">

                        <!-- Left column: numeric / select controls -->
                        <div class="col-lg-7 pe-lg-8">

                            <!-- Attendance via -->
                            <div class="settings-block mb-6">
                                <div class="settings-block-label">Attendance Method</div>
                                <div class="settings-block-body">
                                    <select
                                        v-model="form.attendance_via"
                                        class="form-select form-select-sm"
                                        :class="{ 'is-invalid': form.errors.attendance_via }"
                                    >
                                        <option value="all">Allow All Options</option>
                                        <option value="mobile">Mobile App Only</option>
                                        <option value="biometric">Biometric Device Only</option>
                                        <option value="web">Web Portal Only</option>
                                    </select>
                                    <div
                                        v-if="form.errors.attendance_via"
                                        class="invalid-feedback"
                                    >{{ form.errors.attendance_via }}</div>
                                    <div class="fs-8 text-muted mt-1">How employees can record their attendance</div>
                                </div>
                            </div>

                            <!-- Inline time buffers -->
                            <div class="settings-block mb-6">
                                <div class="settings-block-label">Check-In / Check-Out Windows</div>
                                <div class="settings-block-body d-flex flex-column gap-4">
                                    <div>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <span class="text-gray-700 fw-semibold fs-7 flex-shrink-0">Allow check-in</span>
                                            <input
                                                v-model.number="form.checkin_before_minutes"
                                                type="number"
                                                min="0"
                                                max="720"
                                                class="form-control form-control-sm w-70px text-center"
                                                :class="{ 'is-invalid': form.errors.checkin_before_minutes }"
                                                placeholder="—"
                                            />
                                            <span class="text-gray-600 fs-7">minutes before shift starts</span>
                                        </div>
                                        <div
                                            v-if="form.errors.checkin_before_minutes"
                                            class="text-danger fs-8 mt-1"
                                        >{{ form.errors.checkin_before_minutes }}</div>
                                    </div>
                                    <div>
                                        <div class="d-flex align-items-center gap-3 flex-wrap">
                                            <span class="text-gray-700 fw-semibold fs-7 flex-shrink-0">Allow check-out</span>
                                            <input
                                                v-model.number="form.checkout_after_minutes"
                                                type="number"
                                                min="0"
                                                max="720"
                                                class="form-control form-control-sm w-70px text-center"
                                                :class="{ 'is-invalid': form.errors.checkout_after_minutes }"
                                                placeholder="—"
                                            />
                                            <span class="text-gray-600 fs-7">minutes after shift ends</span>
                                        </div>
                                        <div
                                            v-if="form.errors.checkout_after_minutes"
                                            class="text-danger fs-8 mt-1"
                                        >{{ form.errors.checkout_after_minutes }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Temporary shifts -->
                            <div class="settings-block mb-6">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <div class="settings-block-label mb-0">Emergency / Temporary Shifts</div>
                                    <div class="form-check form-switch ms-4">
                                        <input
                                            id="allow-temp-shifts"
                                            v-model="form.allow_temporary_shifts"
                                            type="checkbox"
                                            class="form-check-input"
                                            role="switch"
                                        />
                                        <label
                                            class="form-check-label fs-8 text-muted"
                                            for="allow-temp-shifts"
                                        >
                                            {{ form.allow_temporary_shifts ? 'On' : 'Off' }}
                                        </label>
                                    </div>
                                </div>
                                <div v-if="form.allow_temporary_shifts">
                                    <div class="fs-8 text-muted mb-2">How to calculate temporary shift hours</div>
                                    <select
                                        v-model="form.temporary_shift_calculation"
                                        class="form-select form-select-sm"
                                        :class="{ 'is-invalid': form.errors.temporary_shift_calculation }"
                                    >
                                        <option value="record_only">Only record check-in and check-out times</option>
                                        <option value="apply_normal_rules">Apply normal attendance rules</option>
                                        <option value="custom_hours">Use custom hours</option>
                                    </select>
                                    <div
                                        v-if="form.errors.temporary_shift_calculation"
                                        class="invalid-feedback"
                                    >{{ form.errors.temporary_shift_calculation }}</div>
                                </div>
                                <div
                                    v-else
                                    class="fs-8 text-muted"
                                >
                                    Employees cannot add temporary or emergency shifts
                                </div>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="col-lg-1 d-none d-lg-flex justify-content-center">
                            <div class="border-start border-dashed border-gray-200 h-100"></div>
                        </div>

                        <!-- Right column: toggle switches -->
                        <div class="col-lg-4">
                            <div class="fs-8 fw-bold text-uppercase text-gray-400 mb-4">Access &amp; Notifications</div>

                            <div class="d-flex flex-column gap-5">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-gray-800 fs-7">Biometric Verification</div>
                                        <div class="fs-8 text-muted">Fingerprint or Face ID required</div>
                                    </div>
                                    <div class="form-check form-switch flex-shrink-0">
                                        <input
                                            v-model="form.check_biometrics"
                                            type="checkbox"
                                            class="form-check-input"
                                            role="switch"
                                        />
                                    </div>
                                </div>

                                <div class="separator"></div>

                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-gray-800 fs-7">Shift Reminders</div>
                                        <div class="fs-8 text-muted">Notify before check-in/out times</div>
                                    </div>
                                    <div class="form-check form-switch flex-shrink-0">
                                        <input
                                            v-model="form.send_reminders"
                                            type="checkbox"
                                            class="form-check-input"
                                            role="switch"
                                        />
                                    </div>
                                </div>

                                <div class="separator"></div>

                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-gray-800 fs-7">Remote Check-In</div>
                                        <div class="fs-8 text-muted">Allow outside assigned location</div>
                                    </div>
                                    <div class="form-check form-switch flex-shrink-0">
                                        <input
                                            v-model="form.allow_remote_checkin"
                                            type="checkbox"
                                            class="form-check-input"
                                            role="switch"
                                        />
                                    </div>
                                </div>

                                <div class="separator"></div>

                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="fw-semibold text-gray-800 fs-7">Any Branch Check-In</div>
                                        <div class="fs-8 text-muted">
                                            Check in from any saved branch
                                            <i
                                                class="ki-outline ki-information-5 fs-7 text-gray-400 ms-1"
                                                title="Employee can check in from any of the company's registered locations"
                                            ></i>
                                        </div>
                                    </div>
                                    <div class="form-check form-switch flex-shrink-0">
                                        <input
                                            v-model="form.allow_any_location_checkin"
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
                    v-if="activeTab === 'modules' && page.props.tenantModules"
                    v-show="activeTab === 'modules'"
                >
                    <div class="fs-7 text-muted mb-6">
                        Enable or disable optional modules for this tenant. Changes take effect immediately.
                    </div>
                    <div class="d-flex flex-column gap-4">
                        <div
                            v-for="item in page.props.tenantModules"
                            :key="item.module"
                            class="d-flex align-items-center justify-content-between p-5 rounded border border-gray-200"
                        >
                            <div class="d-flex align-items-center gap-4">
                                <div class="w-40px h-40px rounded bg-light-primary d-flex align-items-center justify-content-center flex-shrink-0">
                                    <i class="ki-outline ki-element-11 fs-4 text-primary"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold text-gray-800 fs-6">{{ item.label }}</div>
                                    <div class="fs-8 text-muted">
                                        {{ item.is_enabled ? 'Active — users can access this module' : 'Inactive — access is blocked for all users' }}
                                    </div>
                                </div>
                            </div>
                            <div class="form-check form-switch flex-shrink-0 ms-4">
                                <input
                                    :id="`module-${item.module}`"
                                    type="checkbox"
                                    class="form-check-input"
                                    role="switch"
                                    :checked="item.is_enabled"
                                    @change="toggleModule(item.module, ($event.target as HTMLInputElement).checked)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Footer -->
            <div
                v-if="activeTab !== 'modules'"
                class="d-flex align-items-center justify-content-between gap-3 px-8 py-5 border-top"
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
                class="d-flex align-items-center justify-content-end gap-3 px-8 py-5 border-top"
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
</template>

<style scoped>
.modal-backdrop-custom {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1055;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.modal-dialog-custom {
    background: #fff;
    border-radius: 0.75rem;
    width: 100%;
    max-width: 820px;
    max-height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.modal-body-custom {
    overflow-y: auto;
    flex: 1;
}

.nav-pills-custom .nav-link {
    border-radius: 0.5rem;
    border: 1px solid transparent;
    transition: all 0.15s;
}

.nav-pills-custom .nav-link:not(.active):hover {
    background: #f5f8fa;
    color: var(--kt-primary);
}

.nav-pills-custom .nav-link.active {
    background: var(--kt-primary-light);
    color: var(--kt-primary);
    border-color: var(--kt-primary-light);
}

.settings-block-label {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #a1a5b7;
    margin-bottom: 0.6rem;
}
</style>
