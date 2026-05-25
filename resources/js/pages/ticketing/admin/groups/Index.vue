<script setup lang="ts">
import { index, create, edit, destroy } from '@/routes/ticketing/admin/groups';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type GroupRow = { id: number; name: string; description: string | null; is_active: boolean; tickets_count: number };
type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    groups: { data: GroupRow[]; current_page: number; last_page: number; per_page: number; total: number; links: PaginatorLink[] };
    filters: { search: string | null };
}>();

const filterForm = useForm({ search: props.filters?.search ?? '' });
const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) q.search = filterForm.search;
    filterForm.get(index.url({ query: q }), { preserveState: true, replace: true, only: ['groups', 'filters'] });
};
const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());

const removeGroup = (g: GroupRow) => {
    if (!window.confirm(`Delete group "${g.name}"?`)) return;
    router.delete(destroy.url({ group: g.id }));
};
</script>

<template>
    <div>
        <Head title="Ticket Groups" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title"><h2 class="fw-bold">Ticket Groups</h2></div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search groups…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New group
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Tickets</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="g in groups.data"
                                :key="g.id"
                            >
                                <td>
                                    <div class="fw-bold text-gray-800">{{ g.name }}</div>
                                    <div
                                        v-if="g.description"
                                        class="text-muted fs-7 text-truncate"
                                        style="max-width: 300px;"
                                    >{{ g.description }}</div>
                                </td>
                                <td class="text-gray-700">{{ g.tickets_count }}</td>
                                <td>
                                    <span :class="`badge badge-light-${g.is_active ? 'success' : 'danger'}`">
                                        {{ g.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="edit.url({ group: g.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeGroup(g)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="groups.data.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-10"
                                >No groups found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
