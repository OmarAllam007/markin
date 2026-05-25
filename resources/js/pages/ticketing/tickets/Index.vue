<script setup lang="ts">
import { index, create, show, destroy } from '@/routes/ticketing/tickets';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type TicketRow = {
    id: number;
    subject: string;
    status: string;
    type: string | null;
    overdue: boolean;
    created_at: string;
    due_date: string | null;
    requester: { id: number; name: string } | null;
    category: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string } | null;
    technician: { id: number; name: string } | null;
};

type Option = { value: string; label: string };
type CategoryOption = { id: number; name: string };
type PriorityOption = { id: number; name: string; color: string };
type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    tickets: {
        data: TicketRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
    categories: CategoryOption[];
    priorities: PriorityOption[];
    statuses: Option[];
    filters: { search?: string; status?: string; category_id?: string; priority_id?: string };
}>();

const filterForm = useForm({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? '',
    category_id: props.filters?.category_id ?? '',
    priority_id: props.filters?.priority_id ?? '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) q.search = filterForm.search;
    if (filterForm.status) q.status = filterForm.status;
    if (filterForm.category_id) q.category_id = filterForm.category_id;
    if (filterForm.priority_id) q.priority_id = filterForm.priority_id;
    filterForm.get(index.url({ query: q }), { preserveState: true, replace: true, only: ['tickets', 'filters'] });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());
watch(() => [filterForm.status, filterForm.category_id, filterForm.priority_id], () => submitFilters());

const removeTicket = (t: TicketRow) => {
    if (!window.confirm(`Delete ticket #${t.id}?`)) return;
    router.delete(destroy.url({ ticket: t.id }));
};

const statusColors: Record<string, string> = {
    draft: 'secondary', submitted: 'info', in_review: 'warning',
    approved: 'success', rejected: 'danger', cancelled: 'dark',
};
</script>

<template>
    <div>
        <Head title="Tickets" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Tickets</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-200px ps-12"
                            placeholder="Search tickets…"
                            autocomplete="off"
                        />
                    </div>
                    <select
                        v-model="filterForm.status"
                        class="form-select form-select-solid w-150px"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="s in statuses"
                            :key="s.value"
                            :value="s.value"
                        >{{ s.label }}</option>
                    </select>
                    <select
                        v-model="filterForm.category_id"
                        class="form-select form-select-solid w-170px"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="String(c.id)"
                        >{{ c.name }}</option>
                    </select>
                    <Link
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New ticket
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>#</th>
                                <th>Subject</th>
                                <th>Category</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Assignee</th>
                                <th>Requester</th>
                                <th class="text-end w-120px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in tickets.data"
                                :key="t.id"
                            >
                                <td class="text-muted fs-7">
                                    <Link
                                        :href="show.url({ ticket: t.id })"
                                        class="text-gray-600 text-hover-primary"
                                    >#{{ t.id }}</Link>
                                </td>
                                <td>
                                    <Link
                                        :href="show.url({ ticket: t.id })"
                                        class="fw-bold text-gray-800 text-hover-primary"
                                    >{{ t.subject }}</Link>
                                    <span
                                        v-if="t.overdue"
                                        class="badge badge-light-danger ms-2 fs-8"
                                    >Overdue</span>
                                </td>
                                <td>
                                    <span
                                        v-if="t.category"
                                        class="badge rounded-pill px-3"
                                        :style="{ backgroundColor: t.category.color, color: '#fff' }"
                                    >{{ t.category.name }}</span>
                                    <span
                                        v-else
                                        class="text-muted"
                                    >—</span>
                                </td>
                                <td>
                                    <span
                                        v-if="t.priority"
                                        class="badge rounded-pill px-3"
                                        :style="{ backgroundColor: t.priority.color, color: '#fff' }"
                                    >{{ t.priority.name }}</span>
                                    <span
                                        v-else
                                        class="text-muted"
                                    >—</span>
                                </td>
                                <td>
                                    <span :class="`badge badge-light-${statusColors[t.status] ?? 'secondary'}`">
                                        {{ statuses.find(s => s.value === t.status)?.label ?? t.status }}
                                    </span>
                                </td>
                                <td class="text-gray-700">{{ t.technician?.name ?? '—' }}</td>
                                <td class="text-gray-700">{{ t.requester?.name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="show.url({ ticket: t.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >View</Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeTicket(t)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="tickets.data.length === 0">
                                <td
                                    colspan="8"
                                    class="text-center text-muted py-10"
                                >No tickets found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="tickets.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ (tickets.current_page - 1) * tickets.per_page + 1 }}
                        – {{ Math.min(tickets.current_page * tickets.per_page, tickets.total) }} of {{ tickets.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in tickets.links"
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
