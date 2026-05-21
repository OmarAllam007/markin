<script setup lang="ts">
import { store, index } from '@/routes/users';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type StatusOption = { value: string; label: string; color: string };
type ActionOption = { value: string; label: string };
type PermissionModule = { key: string; label: string; actions: ActionOption[] };
type LocationOption = { id: number; name: string };
type DepartmentOption = { id: number; name: string };
type Permission = { module: string; action: string };

const props = defineProps<{
    statuses: StatusOption[];
    permissionModules: PermissionModule[];
    locations: LocationOption[];
    departments: DepartmentOption[];
}>();

const form = useForm({
    name: '',
    email: '',
    country_code: '+966',
    mobile: '',
    password: '',
    password_confirmation: '',
    is_admin: false,
    is_supervisor: false,
    status: 'active',
    permissions: [] as Permission[],
    location_ids: [] as number[],
    department_ids: [] as number[],
});

const hasPermission = (module: string, action: string) =>
    form.permissions.some((p) => p.module === module && p.action === action);

const togglePermission = (module: string, action: string) => {
    const idx = form.permissions.findIndex((p) => p.module === module && p.action === action);
    if (idx === -1) {
        form.permissions.push({ module, action });
    } else {
        form.permissions.splice(idx, 1);
    }
};

const selectAllModule = (module: PermissionModule) => {
    module.actions.forEach(({ value }) => {
        if (!hasPermission(module.key, value)) {
            form.permissions.push({ module: module.key, action: value });
        }
    });
};

const unselectModule = (module: PermissionModule) => {
    form.permissions = form.permissions.filter((p) => p.module !== module.key);
};

const selectAllPermissions = () => {
    form.permissions = [];
    props.permissionModules.forEach((module) => {
        module.actions.forEach(({ value }) => {
            form.permissions.push({ module: module.key, action: value });
        });
    });
};

const unselectAllPermissions = () => {
    form.permissions = [];
};

const allLocationsSelected = computed(
    () => props.locations.length > 0 && form.location_ids.length === props.locations.length,
);

const toggleAllLocations = () => {
    if (allLocationsSelected.value) {
        form.location_ids = [];
    } else {
        form.location_ids = props.locations.map((l) => l.id);
    }
};

const toggleLocation = (id: number) => {
    const idx = form.location_ids.indexOf(id);
    if (idx === -1) {
        form.location_ids.push(id);
    } else {
        form.location_ids.splice(idx, 1);
    }
};

const allDepartmentsSelected = computed(
    () => props.departments.length > 0 && form.department_ids.length === props.departments.length,
);

const toggleAllDepartments = () => {
    if (allDepartmentsSelected.value) {
        form.department_ids = [];
    } else {
        form.department_ids = props.departments.map((d) => d.id);
    }
};

const toggleDepartment = (id: number) => {
    const idx = form.department_ids.indexOf(id);
    if (idx === -1) {
        form.department_ids.push(id);
    } else {
        form.department_ids.splice(idx, 1);
    }
};

const submit = () => {
    form.post(store.url());
};
</script>

<template>
    <div>
        <Head title="New admin user" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to users
            </Link>
        </div>

        <form
            class="form"
            @submit.prevent="submit"
        >
            <!-- Account Information -->
            <div class="card mb-5">
                <div class="card-header border-0 d-flex align-items-center">
                    <div class="card-title">
                        <i class="ki-outline ki-profile-circle fs-2 me-2 text-primary"></i>
                        <h2 class="fw-bold">Account Information</h2>
                    </div>
                </div>
                <div class="card-body border-top p-9">
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="name"
                                >Full Name</label
                            >
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                autocomplete="name"
                            />
                            <div
                                v-if="form.errors.name"
                                class="invalid-feedback"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="email"
                                >Email</label
                            >
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.email }"
                                autocomplete="email"
                            />
                            <div
                                v-if="form.errors.email"
                                class="invalid-feedback"
                            >
                                {{ form.errors.email }}
                            </div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-2">
                            <label
                                class="form-label required"
                                for="cc"
                                >Country code</label
                            >
                            <input
                                id="cc"
                                v-model="form.country_code"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.country_code }"
                                placeholder="+966"
                            />
                            <div
                                v-if="form.errors.country_code"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.country_code }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label required"
                                for="mobile"
                                >Mobile</label
                            >
                            <input
                                id="mobile"
                                v-model="form.mobile"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.mobile }"
                            />
                            <div
                                v-if="form.errors.mobile"
                                class="invalid-feedback"
                            >
                                {{ form.errors.mobile }}
                            </div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="pw"
                                >Password</label
                            >
                            <input
                                id="pw"
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.password }"
                                autocomplete="new-password"
                            />
                            <div
                                v-if="form.errors.password"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.password }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="pwc"
                                >Confirm password</label
                            >
                            <input
                                id="pwc"
                                v-model="form.password_confirmation"
                                type="password"
                                class="form-control"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                    <div class="row g-5">
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="st"
                                >Status</label
                            >
                            <select
                                id="st"
                                v-model="form.status"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.status }"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end gap-6 pb-1">
                            <div class="form-check form-check-custom form-check-solid">
                                <input
                                    id="ia"
                                    v-model="form.is_admin"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="ia"
                                >
                                    Is admin
                                </label>
                            </div>
                            <div class="form-check form-check-custom form-check-solid">
                                <input
                                    id="is"
                                    v-model="form.is_supervisor"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="is"
                                >
                                    Is supervisor
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Permissions -->
            <div class="card mb-5">
                <div class="card-header border-0 d-flex align-items-center justify-content-between flex-wrap gap-3">
                    <div class="card-title">
                        <h2 class="fw-bold">Control Permissions</h2>
                    </div>
                    <div class="d-flex gap-3">
                        <button
                            type="button"
                            class="btn btn-sm btn-light-success"
                            @click="selectAllPermissions"
                        >
                            <i class="ki-outline ki-check fs-5"></i> Select All
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-light-danger"
                            @click="unselectAllPermissions"
                        >
                            <i class="ki-outline ki-cross fs-5"></i> Unselect
                        </button>
                    </div>
                </div>
                <div class="card-body border-top p-9">
                    <div class="row g-5">
                        <div
                            v-for="module in permissionModules"
                            :key="module.key"
                            class="col-auto"
                            style="min-width: 160px"
                        >
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="fw-semibold text-gray-800">{{ module.label }}</span>
                                <button
                                    type="button"
                                    class="btn btn-icon btn-xs btn-light-success"
                                    title="Select all"
                                    @click="selectAllModule(module)"
                                >
                                    <i class="ki-outline ki-check fs-6"></i>
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-icon btn-xs btn-light-danger"
                                    title="Unselect all"
                                    @click="unselectModule(module)"
                                >
                                    <i class="ki-outline ki-cross fs-6"></i>
                                </button>
                            </div>
                            <div class="d-flex flex-column gap-3">
                                <div
                                    v-for="action in module.actions"
                                    :key="action.value"
                                    class="form-check form-check-custom form-check-solid"
                                >
                                    <input
                                        :id="`perm-${module.key}-${action.value}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="hasPermission(module.key, action.value)"
                                        @change="togglePermission(module.key, action.value)"
                                    />
                                    <label
                                        class="form-check-label"
                                        :for="`perm-${module.key}-${action.value}`"
                                    >
                                        {{ action.label }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="form.errors.permissions"
                        class="text-danger fs-7 mt-3"
                    >
                        {{ form.errors.permissions }}
                    </div>
                </div>
            </div>

            <!-- Location & Department Access -->
            <div class="card mb-5">
                <div class="card-header border-0">
                    <div class="card-title">
                        <h2 class="fw-bold">Access Scope</h2>
                    </div>
                </div>
                <div class="card-body border-top p-9">
                    <div class="row g-5">
                        <!-- Locations -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <i class="ki-outline ki-geolocation fs-3 text-primary"></i>
                                <span class="fw-semibold fs-5">Locations &amp; Branches</span>
                            </div>
                            <div
                                class="border rounded p-4 overflow-auto"
                                style="max-height: 300px"
                            >
                                <div class="form-check form-check-custom form-check-solid mb-3 pb-3 border-bottom">
                                    <input
                                        id="loc-all"
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="allLocationsSelected"
                                        @change="toggleAllLocations"
                                    />
                                    <label
                                        class="form-check-label fw-semibold"
                                        for="loc-all"
                                        >All</label
                                    >
                                </div>
                                <div
                                    v-for="loc in locations"
                                    :key="loc.id"
                                    class="form-check form-check-custom form-check-solid mb-3"
                                >
                                    <input
                                        :id="`loc-${loc.id}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="form.location_ids.includes(loc.id)"
                                        @change="toggleLocation(loc.id)"
                                    />
                                    <label
                                        class="form-check-label"
                                        :for="`loc-${loc.id}`"
                                        >{{ loc.name }}</label
                                    >
                                </div>
                                <div
                                    v-if="!locations.length"
                                    class="text-muted fs-7"
                                >
                                    No locations found.
                                </div>
                            </div>
                        </div>

                        <!-- Departments -->
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2 mb-4">
                                <i class="ki-outline ki-element-11 fs-3 text-primary"></i>
                                <span class="fw-semibold fs-5">Departments &amp; Groups</span>
                            </div>
                            <div
                                class="border rounded p-4 overflow-auto"
                                style="max-height: 300px"
                            >
                                <div class="form-check form-check-custom form-check-solid mb-3 pb-3 border-bottom">
                                    <input
                                        id="dept-all"
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="allDepartmentsSelected"
                                        @change="toggleAllDepartments"
                                    />
                                    <label
                                        class="form-check-label fw-semibold"
                                        for="dept-all"
                                        >All</label
                                    >
                                </div>
                                <div
                                    v-for="dept in departments"
                                    :key="dept.id"
                                    class="form-check form-check-custom form-check-solid mb-3"
                                >
                                    <input
                                        :id="`dept-${dept.id}`"
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="form.department_ids.includes(dept.id)"
                                        @change="toggleDepartment(dept.id)"
                                    />
                                    <label
                                        class="form-check-label"
                                        :for="`dept-${dept.id}`"
                                        >{{ dept.name }}</label
                                    >
                                </div>
                                <div
                                    v-if="!departments.length"
                                    class="text-muted fs-7"
                                >
                                    No departments found.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="d-flex justify-content-end gap-2">
                <Link
                    :href="index.url()"
                    class="btn btn-light"
                    >Cancel</Link
                >
                <button
                    type="submit"
                    class="btn btn-primary"
                    :disabled="form.processing"
                >
                    <span
                        v-if="form.processing"
                        class="spinner-border spinner-border-sm me-2"
                    />
                    Create user
                </button>
            </div>
        </form>
    </div>
</template>
