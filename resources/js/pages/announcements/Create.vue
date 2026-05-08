<script setup lang="ts">
import {
    index as announcementsIndex,
    create as announcementsCreate,
    store as announcementsStore,
} from '@/routes/announcements';
import CkEditor from '@/pages/_shared/CkEditor.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

type FilterOption = { id: number; name: string };

type EmployeeRow = {
    id: number;
    employee_number: string | null;
    english_name: string;
    arabic_name: string;
    job_title_en: string | null;
    department: { id: number; name: string } | null;
    location: { id: number; name: string } | null;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    options: {
        locations: FilterOption[];
        departments: FilterOption[];
    };
    employees:
        | {
              data: EmployeeRow[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              links: PaginatorLink[];
          }
        | EmployeeRow[];
    filters: { search: string | null };
}>();

const isPaginated = (
    v: typeof props.employees,
): v is Exclude<typeof props.employees, EmployeeRow[]> => !Array.isArray(v);

const employeeRows = computed(() =>
    isPaginated(props.employees) ? props.employees.data : props.employees,
);

// ── Form ─────────────────────────────────────────────────────────────────────
const form = useForm({
    type: 'notification' as 'notification' | 'warning',
    title: '',
    description: '',
    target_type: 'locations_departments' as 'locations_departments' | 'employees',
    target_location_id: null as number | null,
    target_department_id: null as number | null,
    target_employee_ids: [] as number[],
    attachment: null as File | null,
});

// ── Employee search / pagination (partial reload) ─────────────────────────
const employeeSearch = ref(props.filters?.search ?? '');

const submitEmployeeSearch = () => {
    router.get(
        announcementsCreate.url({ query: { search: employeeSearch.value || undefined } }),
        {},
        { preserveState: true, replace: true, only: ['employees', 'filters'] },
    );
};

const debouncedSearch = useDebounceFn(submitEmployeeSearch, 400);
watch(employeeSearch, () => debouncedSearch());

// ── Select-all employees ──────────────────────────────────────────────────
const allPageSelected = computed(() =>
    employeeRows.value.length > 0 &&
    employeeRows.value.every((e) => form.target_employee_ids.includes(e.id)),
);

function togglePageAll() {
    if (allPageSelected.value) {
        form.target_employee_ids = form.target_employee_ids.filter(
            (id) => !employeeRows.value.some((e) => e.id === id),
        );
    } else {
        const newIds = employeeRows.value
            .map((e) => e.id)
            .filter((id) => !form.target_employee_ids.includes(id));
        form.target_employee_ids = [...form.target_employee_ids, ...newIds];
    }
}

function toggleEmployee(id: number) {
    const idx = form.target_employee_ids.indexOf(id);
    if (idx === -1) {
        form.target_employee_ids = [...form.target_employee_ids, id];
    } else {
        form.target_employee_ids = form.target_employee_ids.filter((x) => x !== id);
    }
}

// ── File attachment ───────────────────────────────────────────────────────
const attachmentName = ref<string | null>(null);

function onAttachmentChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    form.attachment = file;
    attachmentName.value = file.name;
}

function removeAttachment() {
    form.attachment = null;
    attachmentName.value = null;
}

// ── Submit ────────────────────────────────────────────────────────────────
function submit() {
    form.post(announcementsStore.url(), {
        forceFormData: true,
    });
}
</script>

<template>
    <div>
        <Head title="Create Notification" />

        <div class="card">
            <!-- Header -->
            <div class="card-header border-0 pt-6 d-flex align-items-center gap-3">
                <Link
                    :href="announcementsIndex.url()"
                    class="btn btn-sm btn-icon btn-light"
                >
                    <i class="ki-outline ki-arrow-left fs-4"></i>
                </Link>
                <div class="card-title">
                    <h2 class="fw-bold mb-0">Create Notification / Warning</h2>
                </div>
            </div>

            <form @submit.prevent="submit">
                <div class="card-body py-6">
                    <div class="row g-8">

                        <!-- ── Left column: type + title + description ───────── -->
                        <div class="col-lg-7">

                            <!-- Type -->
                            <div class="mb-7">
                                <label class="form-label fw-semibold required">Type</label>
                                <div class="d-flex gap-4">
                                    <label
                                        class="d-flex align-items-center gap-3 p-4 rounded border cursor-pointer flex-1 transition"
                                        :class="form.type === 'notification' ? 'border-primary bg-light-primary' : 'border-gray-200'"
                                        style="transition: all .15s;"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.type"
                                            value="notification"
                                            class="form-check-input mt-0 flex-shrink-0"
                                        />
                                        <span class="d-flex flex-column">
                                            <span class="fw-bold text-gray-800">
                                                <i class="ki-outline ki-notification-bing fs-5 me-1 text-primary"></i>
                                                Notification
                                            </span>
                                            <span class="fs-8 text-muted">Informational message to employees</span>
                                        </span>
                                    </label>
                                    <label
                                        class="d-flex align-items-center gap-3 p-4 rounded border cursor-pointer flex-1"
                                        :class="form.type === 'warning' ? 'border-warning bg-light-warning' : 'border-gray-200'"
                                        style="transition: all .15s;"
                                    >
                                        <input
                                            type="radio"
                                            v-model="form.type"
                                            value="warning"
                                            class="form-check-input mt-0 flex-shrink-0"
                                        />
                                        <span class="d-flex flex-column">
                                            <span class="fw-bold text-gray-800">
                                                <i class="ki-outline ki-warning-2 fs-5 me-1 text-warning"></i>
                                                Warning
                                            </span>
                                            <span class="fs-8 text-muted">Alert requiring employee attention</span>
                                        </span>
                                    </label>
                                </div>
                                <div v-if="form.errors.type" class="text-danger fs-8 mt-2">{{ form.errors.type }}</div>
                            </div>

                            <!-- Title -->
                            <div class="mb-7">
                                <label class="form-label fw-semibold required">Title</label>
                                <input
                                    v-model="form.title"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.title }"
                                    placeholder="e.g. Upcoming Holiday Notice"
                                    autocomplete="off"
                                    maxlength="255"
                                />
                                <div v-if="form.errors.title" class="invalid-feedback">{{ form.errors.title }}</div>
                            </div>

                            <!-- Description -->
                            <div class="mb-7">
                                <label class="form-label fw-semibold required">Message</label>
                                <CkEditor
                                    v-model="form.description"
                                    placeholder="Write the full message that will be sent to employees…"
                                    :has-error="!!form.errors.description"
                                    min-height="200px"
                                />
                                <div v-if="form.errors.description" class="text-danger fs-8 mt-2">{{ form.errors.description }}</div>
                            </div>

                            <!-- Attachment -->
                            <div class="mb-2">
                                <label class="form-label fw-semibold">Attachment <span class="text-muted fs-8">(optional)</span></label>
                                <div
                                    v-if="attachmentName"
                                    class="d-flex align-items-center gap-3 p-3 rounded border border-dashed bg-light-primary"
                                >
                                    <i class="ki-outline ki-file fs-2 text-primary"></i>
                                    <span class="fw-semibold text-gray-700 flex-grow-1 text-truncate">{{ attachmentName }}</span>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-icon btn-light-danger"
                                        @click="removeAttachment"
                                    >
                                        <i class="ki-outline ki-cross fs-5"></i>
                                    </button>
                                </div>
                                <label v-else class="btn btn-light w-100 border border-dashed border-gray-300 cursor-pointer mb-0">
                                    <i class="ki-outline ki-file-up fs-3 me-2 text-gray-500"></i>
                                    <span class="text-gray-600">Click to upload a file</span>
                                    <input
                                        type="file"
                                        class="d-none"
                                        @change="onAttachmentChange"
                                    />
                                </label>
                                <div v-if="form.errors.attachment" class="text-danger fs-8 mt-2">{{ form.errors.attachment }}</div>
                            </div>
                        </div>

                        <!-- ── Right column: recipients ───────────────────────── -->
                        <div class="col-lg-5">
                            <label class="form-label fw-semibold required">Send To</label>

                            <!-- Target type radios -->
                            <div class="d-flex flex-column gap-3 mb-6">
                                <label
                                    class="d-flex align-items-center gap-3 p-4 rounded border cursor-pointer"
                                    :class="form.target_type === 'locations_departments' ? 'border-primary bg-light-primary' : 'border-gray-200'"
                                    style="transition: all .15s;"
                                >
                                    <input
                                        type="radio"
                                        v-model="form.target_type"
                                        value="locations_departments"
                                        class="form-check-input mt-0 flex-shrink-0"
                                    />
                                    <span class="d-flex flex-column">
                                        <span class="fw-bold text-gray-800">
                                            <i class="ki-outline ki-geolocation fs-5 me-1 text-primary"></i>
                                            Locations &amp; Departments
                                        </span>
                                        <span class="fs-8 text-muted">Filter employees by location or department</span>
                                    </span>
                                </label>
                                <label
                                    class="d-flex align-items-center gap-3 p-4 rounded border cursor-pointer"
                                    :class="form.target_type === 'employees' ? 'border-primary bg-light-primary' : 'border-gray-200'"
                                    style="transition: all .15s;"
                                >
                                    <input
                                        type="radio"
                                        v-model="form.target_type"
                                        value="employees"
                                        class="form-check-input mt-0 flex-shrink-0"
                                    />
                                    <span class="d-flex flex-column">
                                        <span class="fw-bold text-gray-800">
                                            <i class="ki-outline ki-people fs-5 me-1 text-primary"></i>
                                            Employee List
                                        </span>
                                        <span class="fs-8 text-muted">Handpick individual employees</span>
                                    </span>
                                </label>
                            </div>

                            <!-- Locations & Departments panel -->
                            <div v-if="form.target_type === 'locations_departments'">
                                <div class="row g-4">
                                    <!-- Location -->
                                    <div class="col-12">
                                        <div class="p-4 rounded border border-gray-200 bg-light">
                                            <div class="fw-semibold text-gray-700 mb-3 fs-7">
                                                <i class="ki-outline ki-geolocation fs-6 me-1 text-primary"></i>
                                                Location
                                            </div>
                                            <div class="d-flex flex-column gap-2">
                                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        :value="null"
                                                        v-model="form.target_location_id"
                                                        class="form-check-input mt-0"
                                                    />
                                                    <span class="fw-semibold text-gray-700">All Locations</span>
                                                </label>
                                                <div class="separator my-1"></div>
                                                <label
                                                    v-for="loc in options.locations"
                                                    :key="loc.id"
                                                    class="d-flex align-items-center gap-2 cursor-pointer"
                                                >
                                                    <input
                                                        type="radio"
                                                        :value="loc.id"
                                                        v-model="form.target_location_id"
                                                        class="form-check-input mt-0"
                                                    />
                                                    <span class="text-gray-700">{{ loc.name }}</span>
                                                </label>
                                                <div v-if="options.locations.length === 0" class="text-muted fs-8">No locations available</div>
                                            </div>
                                        </div>
                                        <div v-if="form.errors.target_location_id" class="text-danger fs-8 mt-1">{{ form.errors.target_location_id }}</div>
                                    </div>

                                    <!-- Department -->
                                    <div class="col-12">
                                        <div class="p-4 rounded border border-gray-200 bg-light">
                                            <div class="fw-semibold text-gray-700 mb-3 fs-7">
                                                <i class="ki-outline ki-wifi-home fs-6 me-1 text-primary"></i>
                                                Department
                                            </div>
                                            <div class="d-flex flex-column gap-2">
                                                <label class="d-flex align-items-center gap-2 cursor-pointer">
                                                    <input
                                                        type="radio"
                                                        :value="null"
                                                        v-model="form.target_department_id"
                                                        class="form-check-input mt-0"
                                                    />
                                                    <span class="fw-semibold text-gray-700">All Departments</span>
                                                </label>
                                                <div class="separator my-1"></div>
                                                <label
                                                    v-for="dep in options.departments"
                                                    :key="dep.id"
                                                    class="d-flex align-items-center gap-2 cursor-pointer"
                                                >
                                                    <input
                                                        type="radio"
                                                        :value="dep.id"
                                                        v-model="form.target_department_id"
                                                        class="form-check-input mt-0"
                                                    />
                                                    <span class="text-gray-700">{{ dep.name }}</span>
                                                </label>
                                                <div v-if="options.departments.length === 0" class="text-muted fs-8">No departments available</div>
                                            </div>
                                        </div>
                                        <div v-if="form.errors.target_department_id" class="text-danger fs-8 mt-1">{{ form.errors.target_department_id }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Employees panel -->
                            <div v-if="form.target_type === 'employees'">
                                <!-- Search -->
                                <div class="d-flex align-items-center position-relative mb-4">
                                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4 text-gray-500" />
                                    <input
                                        v-model="employeeSearch"
                                        type="search"
                                        class="form-control form-control-solid ps-12"
                                        placeholder="Search by name, Arabic name, or ID…"
                                        autocomplete="off"
                                    />
                                </div>

                                <!-- Selected count badge -->
                                <div v-if="form.target_employee_ids.length > 0" class="mb-3">
                                    <span class="badge badge-light-primary fs-8">
                                        {{ form.target_employee_ids.length }} employee(s) selected
                                    </span>
                                </div>

                                <div class="table-responsive rounded border">
                                    <table class="table table-row-bordered table-row-gray-100 align-middle mb-0 gs-3">
                                        <thead>
                                            <tr class="text-gray-500 text-uppercase fs-8 fw-bold bg-light">
                                                <th class="w-40px ps-4">
                                                    <div class="form-check form-check-sm form-check-custom">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :checked="allPageSelected"
                                                            :indeterminate="!allPageSelected && form.target_employee_ids.length > 0 && employeeRows.some(e => form.target_employee_ids.includes(e.id))"
                                                            @change="togglePageAll"
                                                        />
                                                    </div>
                                                </th>
                                                <th>Employee</th>
                                                <th>Department</th>
                                                <th>Location</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="e in employeeRows"
                                                :key="e.id"
                                                class="cursor-pointer"
                                                :class="{ 'bg-light-primary': form.target_employee_ids.includes(e.id) }"
                                                @click="toggleEmployee(e.id)"
                                            >
                                                <td class="ps-4" @click.stop>
                                                    <div class="form-check form-check-sm form-check-custom">
                                                        <input
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :checked="form.target_employee_ids.includes(e.id)"
                                                            @change="toggleEmployee(e.id)"
                                                        />
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold text-gray-800 fs-7">{{ e.english_name }}</div>
                                                    <div class="text-gray-500 fs-8">{{ e.arabic_name }}</div>
                                                    <div v-if="e.employee_number" class="text-muted fs-8">#{{ e.employee_number }}</div>
                                                    <div v-if="e.job_title_en" class="text-muted fs-8">{{ e.job_title_en }}</div>
                                                </td>
                                                <td class="text-gray-600 fs-7">{{ e.department?.name ?? '—' }}</td>
                                                <td class="text-gray-600 fs-7">{{ e.location?.name ?? '—' }}</td>
                                            </tr>
                                            <tr v-if="employeeRows.length === 0">
                                                <td colspan="4" class="text-center text-muted py-8 fs-7">
                                                    No employees found.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <div
                                    v-if="isPaginated(employees) && employees.last_page > 1"
                                    class="d-flex flex-wrap align-items-center gap-2 mt-4 justify-content-between"
                                >
                                    <span class="text-muted fs-8">
                                        {{ (employees.current_page - 1) * employees.per_page + 1 }}
                                        –
                                        {{ Math.min(employees.current_page * employees.per_page, employees.total) }}
                                        of {{ employees.total }}
                                    </span>
                                    <div class="d-flex flex-wrap gap-1">
                                        <template
                                            v-for="l in employees.links"
                                            :key="l.label + String(l.url)"
                                        >
                                            <button
                                                v-if="l.url"
                                                type="button"
                                                :class="['btn btn-sm border', l.active ? 'btn-primary' : 'btn-light']"
                                                @click="router.get(l.url, {}, { preserveState: true, replace: true, only: ['employees', 'filters'] })"
                                            >
                                                <span v-html="l.label" />
                                            </button>
                                            <span
                                                v-else
                                                class="btn btn-sm border btn-light pe-none opacity-50"
                                            >
                                                <span v-html="l.label" />
                                            </span>
                                        </template>
                                    </div>
                                </div>

                                <div v-if="form.errors.target_employee_ids" class="text-danger fs-8 mt-2">
                                    {{ form.errors.target_employee_ids }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer d-flex align-items-center justify-content-between py-4">
                    <div>
                        <span v-if="form.hasErrors" class="text-danger fs-7">
                            <i class="ki-outline ki-information-5 me-1"></i>
                            Please fix the errors above
                        </span>
                    </div>
                    <div class="d-flex gap-3">
                        <Link
                            :href="announcementsIndex.url()"
                            class="btn btn-light"
                            :class="{ 'pe-none opacity-50': form.processing }"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" />
                            <i v-else class="ki-outline ki-send fs-5 me-1"></i>
                            Send
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
