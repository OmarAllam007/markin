<script setup lang="ts">
import { index, create, edit, destroy } from '@/routes/ticketing/admin/subcategories';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type SubcategoryRow = {
    id: number;
    name: string;
    name_ar: string | null;
    is_active: boolean;
    category: { id: number; name: string } | null;
};

type CategoryOption = { id: number; name: string };
type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    subcategories: {
        data: SubcategoryRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
    categories: CategoryOption[];
    filters: { search: string | null; category_id: string | null };
}>();

const filterForm = useForm({ search: props.filters?.search ?? '', category_id: props.filters?.category_id ?? '' });

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) q.search = filterForm.search;
    if (filterForm.category_id) q.category_id = filterForm.category_id;
    filterForm.get(index.url({ query: q }), { preserveState: true, replace: true, only: ['subcategories', 'filters'] });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());
watch(() => filterForm.category_id, () => submitFilters());

const removeSubcategory = (s: SubcategoryRow) => {
    if (!window.confirm(`Delete subcategory "${s.name}"?`)) return;
    router.delete(destroy.url({ subcategory: s.id }));
};
</script>

<template>
    <div>
        <Head title="Ticket Subcategories" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Ticket Subcategories</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <select
                        v-model="filterForm.category_id"
                        class="form-select form-select-solid w-200px"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="String(c.id)"
                        >{{ c.name }}</option>
                    </select>
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search subcategories…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New subcategory
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in subcategories.data"
                                :key="s.id"
                            >
                                <td>
                                    <div class="fw-bold text-gray-800">{{ s.name }}</div>
                                    <div
                                        v-if="s.name_ar"
                                        class="text-muted fs-7"
                                    >{{ s.name_ar }}</div>
                                </td>
                                <td class="text-gray-700">{{ s.category?.name ?? '—' }}</td>
                                <td>
                                    <span :class="`badge badge-light-${s.is_active ? 'success' : 'danger'}`">
                                        {{ s.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="edit.url({ subcategory: s.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeSubcategory(s)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="subcategories.data.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-10"
                                >No subcategories found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
