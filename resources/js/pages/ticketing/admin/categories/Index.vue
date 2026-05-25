<script setup lang="ts">
import { index, create, edit, destroy } from '@/routes/ticketing/admin/categories';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type CategoryRow = {
    id: number;
    name: string;
    name_ar: string | null;
    color: string;
    icon: string | null;
    is_active: boolean;
    subcategories_count: number;
    created_at: string;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    categories: {
        data: CategoryRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
    filters: { search: string | null };
}>();

const filterForm = useForm({ search: props.filters?.search ?? '' });

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) q.search = filterForm.search;
    filterForm.get(index.url({ query: q }), { preserveState: true, replace: true, only: ['categories', 'filters'] });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());

const removeCategory = (c: CategoryRow) => {
    if (!window.confirm(`Delete category "${c.name}"? This cannot be undone.`)) return;
    router.delete(destroy.url({ category: c.id }));
};
</script>

<template>
    <div>
        <Head title="Ticket Categories" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Ticket Categories</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search categories…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New category
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Subcategories</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="c in categories.data"
                                :key="c.id"
                            >
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="badge rounded-circle p-2"
                                            :style="{ backgroundColor: c.color }"
                                        >
                                            <i
                                                v-if="c.icon"
                                                :class="`ki-outline ${c.icon} fs-6 text-white`"
                                            />
                                        </span>
                                        <div>
                                            <div class="fw-bold text-gray-800">{{ c.name }}</div>
                                            <div
                                                v-if="c.name_ar"
                                                class="text-muted fs-7"
                                            >{{ c.name_ar }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-gray-700">{{ c.subcategories_count }}</td>
                                <td>
                                    <span :class="`badge badge-light-${c.is_active ? 'success' : 'danger'}`">
                                        {{ c.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="edit.url({ category: c.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeCategory(c)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="categories.data.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-10"
                                >No categories found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="categories.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ (categories.current_page - 1) * categories.per_page + 1 }}
                        – {{ Math.min(categories.current_page * categories.per_page, categories.total) }} of {{ categories.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in categories.links"
                            :key="l.label + String(l.url)"
                        >
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                :class="['btn btn-sm border', l.active ? 'btn-primary' : 'btn-light']"
                                preserve-state
                            ><span v-html="l.label" /></Link>
                            <span
                                v-else
                                class="btn btn-sm border btn-light pe-none opacity-50"
                            ><span v-html="l.label" /></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
