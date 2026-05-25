<script setup lang="ts">
import { index, create, edit, destroy } from '@/routes/ticketing/admin/slas';
import { Head, Link, router } from '@inertiajs/vue3';

type SlaRow = { id: number; name: string; first_response_hours: number; resolve_hours: number; business_hours_only: boolean; is_active: boolean };
type PaginatorLink = { url: string | null; label: string; active: boolean };

defineProps<{
    slas: { data: SlaRow[]; current_page: number; last_page: number; per_page: number; total: number; links: PaginatorLink[] };
}>();

const removeSla = (s: SlaRow) => {
    if (!window.confirm(`Delete SLA policy "${s.name}"?`)) return;
    router.delete(destroy.url({ sla: s.id }));
};
</script>

<template>
    <div>
        <Head title="SLA Policies" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title"><h2 class="fw-bold">SLA Policies</h2></div>
                <div class="card-toolbar">
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New SLA policy
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>First Response</th>
                                <th>Resolve By</th>
                                <th>Business Hours</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="s in slas.data"
                                :key="s.id"
                            >
                                <td class="fw-bold text-gray-800">{{ s.name }}</td>
                                <td class="text-gray-700">{{ s.first_response_hours }}h</td>
                                <td class="text-gray-700">{{ s.resolve_hours }}h</td>
                                <td>
                                    <span :class="`badge badge-light-${s.business_hours_only ? 'info' : 'secondary'}`">
                                        {{ s.business_hours_only ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td>
                                    <span :class="`badge badge-light-${s.is_active ? 'success' : 'danger'}`">
                                        {{ s.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="edit.url({ sla: s.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeSla(s)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="slas.data.length === 0">
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-10"
                                >No SLA policies found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
