<script setup lang="ts">
import {
    index as employeesIndex,
    create as employeesCreate,
    edit as employeesEdit,
    destroy as employeesDestroy,
    importMethod as employeesImport,
} from '@/routes/employees';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type FilterOption = { id: number; name: string };

type EmployeeRow = {
    id: number;
    employee_number: string | null;
    english_name: string;
    arabic_name: string;
    mobile_country_code: string;
    mobile_number: string;
    job_title_en: string | null;
    department: { id: number; name: string } | null;
    location: { id: number; name: string } | null;
    status: string;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
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
    filters: {
        search: string | null;
        department_id: number | null;
        location_id: number | null;
        work_shift_id: number | null;
    };
    departments: FilterOption[];
    locations: FilterOption[];
    workShifts: FilterOption[];
}>();

const isPaginated = (
    v: typeof props.employees,
): v is Exclude<typeof props.employees, EmployeeRow[]> => !Array.isArray(v);

const filterForm = useForm<{
    search: string;
    department_id: number | null;
    location_id: number | null;
    work_shift_id: number | null;
}>({
    search: props.filters?.search ?? '',
    department_id: props.filters?.department_id ?? null,
    location_id: props.filters?.location_id ?? null,
    work_shift_id: props.filters?.work_shift_id ?? null,
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) q.search = filterForm.search;
    if (filterForm.department_id) q.department_id = String(filterForm.department_id);
    if (filterForm.location_id) q.location_id = String(filterForm.location_id);
    if (filterForm.work_shift_id) q.work_shift_id = String(filterForm.work_shift_id);
    filterForm.get(employeesIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['employees', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());
watch(() => filterForm.department_id, () => submitFilters());
watch(() => filterForm.location_id, () => submitFilters());
watch(() => filterForm.work_shift_id, () => submitFilters());

const STATUS_COLOR: Record<string, string> = {
    active: 'badge-light-success',
    inactive: 'badge-light-warning',
    on_leave: 'badge-light-info',
    terminated: 'badge-light-danger',
};

const STATUS_LABEL: Record<string, string> = {
    active: 'Active',
    inactive: 'Inactive',
    on_leave: 'On Leave',
    terminated: 'Terminated',
};

const removeEmployee = (e: EmployeeRow) => {
    if (!window.confirm(`Delete employee "${e.english_name}"? This cannot be undone.`)) return;
    router.delete(employeesDestroy.url({ employee: e.id }));
};
</script>

<template>
    <div>
        <Head title="Employees" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Employees</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search employees…"
                            autocomplete="off"
                        />
                    </div>
                    <select
                        v-if="departments.length"
                        v-model="filterForm.department_id"
                        class="form-select form-select-solid w-175px"
                    >
                        <option :value="null">All Departments</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <select
                        v-if="locations.length"
                        v-model="filterForm.location_id"
                        class="form-select form-select-solid w-175px"
                    >
                        <option :value="null">All Locations</option>
                        <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                    <select
                        v-if="workShifts.length"
                        v-model="filterForm.work_shift_id"
                        class="form-select form-select-solid w-175px"
                    >
                        <option :value="null">All Shifts</option>
                        <option v-for="s in workShifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <Link
                        :href="employeesImport.url()"
                        class="btn btn-sm btn-light-primary"
                    >
                        <i class="ki-outline ki-file-up fs-2"></i>
                        Import
                    </Link>
                    <Link
                        :href="employeesCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New employee
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>ID</th>
                                <th>Arabic Name</th>
                                <th>English Name</th>
                                <th>Job</th>
                                <th>Department</th>
                                <th>Location</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="e in isPaginated(employees) ? employees.data : employees"
                                :key="e.id"
                            >
                                <td class="text-gray-600 fs-7">{{ e.employee_number ?? `#${e.id}` }}</td>
                                <td class="text-gray-700">{{ e.arabic_name }}</td>
                                <td class="fw-bold text-gray-800">{{ e.english_name }}</td>
                                <td class="text-gray-700">{{ e.job_title_en ?? '—' }}</td>
                                <td class="text-gray-700">{{ e.department?.name ?? '—' }}</td>
                                <td class="text-gray-700">{{ e.location?.name ?? '—' }}</td>
                                <td class="text-gray-700 text-nowrap">{{ e.mobile_country_code }} {{ e.mobile_number }}</td>
                                <td>
                                    <span :class="['badge', STATUS_COLOR[e.status] ?? 'badge-light']">
                                        {{ STATUS_LABEL[e.status] ?? e.status }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="employeesEdit.url({ employee: e.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeEmployee(e)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="(isPaginated(employees) ? employees.data : employees).length === 0"
                            >
                                <td
                                    colspan="9"
                                    class="text-center text-muted py-10"
                                >
                                    No employees found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(employees) && employees.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ employees.data.length ? (employees.current_page - 1) * employees.per_page + 1 : 0 }}
                        –
                        {{ Math.min(employees.current_page * employees.per_page, employees.total) }}
                        of {{ employees.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in employees.links"
                            :key="l.label + String(l.url)"
                        >
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                :class="['btn btn-sm border', l.active ? 'btn-primary' : 'btn-light']"
                                preserve-state
                            >
                                <span v-html="l.label" />
                            </Link>
                            <span
                                v-else
                                class="btn btn-sm border btn-light pe-none opacity-50"
                            >
                                <span v-html="l.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
