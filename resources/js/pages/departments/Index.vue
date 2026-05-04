<script setup lang="ts">
import { index as departmentsIndex, create as departmentsCreate, edit as departmentsEdit, destroy as departmentsDestroy } from '@/routes/departments';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type Creator = { id: number; name: string };

type DepartmentRow = {
    id: number;
    name: string;
    created_at: string;
    creator: Creator | null;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    departments: {
        data: DepartmentRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    } | DepartmentRow[];
    filters: { search: string | null };
}>();

const isPaginated = (v: typeof props.departments): v is Exclude<typeof props.departments, DepartmentRow[]> =>
    !Array.isArray(v);

const filterForm = useForm({
    search: props.filters?.search ?? '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) {
        q.search = filterForm.search;
    }
    filterForm.get(departmentsIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['departments', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());

const removeDepartment = (d: DepartmentRow) => {
    if (!window.confirm(`Delete department "${d.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(departmentsDestroy.url({ department: d.id }));
};
</script>

<template>
    <div>
        <Head title="Departments" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Departments</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search departments…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="departmentsCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New department
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Created by</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="d in isPaginated(departments) ? departments.data : departments"
                                :key="d.id"
                            >
                                <td class="fw-bold text-gray-800">{{ d.name }}</td>
                                <td class="text-gray-700">{{ d.creator?.name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="departmentsEdit.url({ department: d.id })"
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
                                        @click="removeDepartment(d)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>

                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="(isPaginated(departments) ? departments.data : departments).length === 0">
                                <td
                                    colspan="3"
                                    class="text-center text-muted py-10"
                                >
                                    No departments found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(departments) && departments.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ departments.data.length ? (departments.current_page - 1) * departments.per_page + 1 : 0 }}
                        – {{ Math.min(departments.current_page * departments.per_page, departments.total) }} of
                        {{ departments.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in departments.links"
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
