<script setup lang="ts">
import { index, create, edit, destroy } from '@/routes/ticketing/admin/priorities';
import { Head, Link, router } from '@inertiajs/vue3';

type PriorityRow = { id: number; name: string; color: string; icon: string | null; sla_hours: number | null; is_default: boolean; sort_order: number };
type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    priorities: {
        data: PriorityRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
}>();

const removePriority = (p: PriorityRow) => {
    if (!window.confirm(`Delete priority "${p.name}"?`)) return;
    router.delete(destroy.url({ priority: p.id }));
};
</script>

<template>
    <div>
        <Head title="Ticket Priorities" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title"><h2 class="fw-bold">Ticket Priorities</h2></div>
                <div class="card-toolbar">
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New priority
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Priority</th>
                                <th>SLA (hours)</th>
                                <th>Default</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="p in priorities.data"
                                :key="p.id"
                            >
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span
                                            class="badge rounded-pill px-3"
                                            :style="{ backgroundColor: p.color, color: '#fff' }"
                                        >{{ p.name }}</span>
                                    </div>
                                </td>
                                <td class="text-gray-700">{{ p.sla_hours ? `${p.sla_hours}h` : '—' }}</td>
                                <td>
                                    <span
                                        v-if="p.is_default"
                                        class="badge badge-light-primary"
                                    >Default</span>
                                    <span
                                        v-else
                                        class="text-muted"
                                    >—</span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="edit.url({ priority: p.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removePriority(p)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="priorities.data.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-10"
                                >No priorities found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
