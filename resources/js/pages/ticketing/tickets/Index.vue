<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, reactive, ref, watch } from 'vue';
import { formatDateTime } from '@/lib/utils';
import { index, create, show, destroy, exportMethod } from '@/routes/ticketing/tickets';

type Ref = { id: number; name: string };
type TicketRow = {
    id: number;
    subject: string;
    status: string;
    overdue: boolean;
    created_at: string;
    requester: Ref | null;
    employee: { id: number; english_name: string } | null;
    category: { id: number; name: string; color: string } | null;
    priority: { id: number; name: string; color: string } | null;
    technician: Ref | null;
    group: Ref | null;
    request_status: { id: number; name: string; color: string } | null;
};
type PaginatorLink = { url: string | null; label: string; active: boolean };
type Filters = {
    search: string; request_status_id: string; status: string; category_id: string; priority_id: string;
    group_id: string; technician_id: string; from: string; to: string; overdue: string;
};

const props = defineProps<{
    tickets: { data: TicketRow[]; current_page: number; last_page: number; per_page: number; total: number; links: PaginatorLink[] };
    categories: Ref[];
    priorities: (Ref & { color: string })[];
    requestStatuses: (Ref & { color: string; type: string })[];
    groups: Ref[];
    technicians: Ref[];
    statuses: { value: string; label: string }[];
    filters: Partial<Filters>;
    seesAll: boolean;
    canCreate: boolean;
}>();

const EMPTY: Filters = { search: '', request_status_id: '', status: '', category_id: '', priority_id: '', group_id: '', technician_id: '', from: '', to: '', overdue: '' };
const f = reactive<Filters>({ ...EMPTY, ...Object.fromEntries(Object.entries(props.filters).map(([k, v]) => [k, String(v ?? '')])) });

/** Only filled filters go in the URL, so links stay short and shareable. */
const query = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')));

const apply = () => router.get(index.url({ query: query.value }), {}, { preserveState: true, preserveScroll: true, replace: true, only: ['tickets', 'filters'] });
const applySoon = useDebounceFn(apply, 400);
watch(() => f.search, applySoon);
// Search waits for typing to pause; every other filter applies at once.
watch(() => (Object.keys(EMPTY) as (keyof Filters)[]).filter(k => k !== 'search').map(k => f[k]).join('|'), apply);

// ---------- quick views ----------
const views = computed(() => [
    { key: 'all', label: props.seesAll ? 'All tickets' : 'My queue', set: {} },
    { key: 'active', label: 'Active', set: { request_status_id: 'active' } },
    { key: 'mine', label: 'Assigned to me', set: { technician_id: 'me' } },
    { key: 'none', label: 'Unassigned', set: { technician_id: 'none' } },
    { key: 'overdue', label: 'Overdue', set: { overdue: '1' } },
]);
const VIEW_KEYS = ['request_status_id', 'technician_id', 'overdue'] as const;
const activeView = computed(() => views.value.find(v => VIEW_KEYS.every(k => (v.set as Partial<Filters>)[k] === undefined ? f[k] === '' : (v.set as Partial<Filters>)[k] === f[k]))?.key);
const pickView = (set: Partial<Filters>) => VIEW_KEYS.forEach(k => (f[k] = set[k] ?? ''));

// ---------- filter panel ----------
const advancedKeys: (keyof Filters)[] = ['status', 'category_id', 'priority_id', 'group_id', 'from', 'to'];
const advancedCount = computed(() => advancedKeys.filter(k => f[k] !== '').length + (f.request_status_id && f.request_status_id !== 'active' ? 1 : 0) + (f.technician_id && !['me', 'none'].includes(f.technician_id) ? 1 : 0));
const showFilters = ref(advancedCount.value > 0);
const anyFilter = computed(() => Object.keys(query.value).length > 0);
const clearAll = () => Object.assign(f, EMPTY);

const STATUS_TYPES = [
    { value: 'open', label: 'Any open status' },
    { value: 'on_hold', label: 'Any on-hold status' },
    { value: 'resolved', label: 'Any resolved status' },
    { value: 'closed', label: 'Any closed status' },
];

const exportUrl = computed(() => exportMethod.url({ query: query.value }));

const removeTicket = (t: TicketRow) => {
    if (window.confirm(`Delete ticket #${t.id}?`)) {
        router.delete(destroy.url({ ticket: t.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <div>
        <Head title="Tickets" />
        <div class="card">
            <div class="card-header border-0 pt-6 flex-wrap gap-3">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold mb-1">Tickets</h2>
                    <span class="text-muted fs-7">{{ tickets.total }} {{ tickets.total === 1 ? 'ticket' : 'tickets' }}{{ anyFilter ? ' match these filters' : '' }}</span>
                </div>
                <div class="card-toolbar gap-2">
                    <a
                        :href="exportUrl"
                        class="btn btn-sm btn-light-success"
                        :class="{ disabled: !tickets.total }"
                        title="Download every ticket in this list, all pages"
                    ><i class="ki-outline ki-file-down fs-3 me-1" />Export Excel</a>
                    <Link
                        v-if="canCreate"
                        :href="create.url()"
                        class="btn btn-sm btn-primary"
                    ><i class="ki-outline ki-plus fs-2" />New ticket</Link>
                </div>
            </div>

            <div class="card-body pt-2 pb-0">
                <!-- Quick views + search -->
                <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                    <div
                        class="views"
                        role="group"
                        aria-label="Quick views"
                    >
                        <button
                            v-for="v in views"
                            :key="v.key"
                            type="button"
                            :class="['view', { on: activeView === v.key }]"
                            :aria-pressed="activeView === v.key"
                            @click="pickView(v.set)"
                        >{{ v.label }}</button>
                    </div>
                    <div class="position-relative ms-auto">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute top-50 translate-middle-y ms-4 text-gray-500" />
                        <input
                            v-model="f.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Subject, #id, requester…"
                            aria-label="Search tickets"
                            autocomplete="off"
                        >
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-light"
                        :aria-expanded="showFilters"
                        aria-controls="ticket-filters"
                        @click="showFilters = !showFilters"
                    >
                        <i class="ki-outline ki-filter fs-4 me-1" />Filters
                        <span
                            v-if="advancedCount"
                            class="badge badge-circle badge-primary ms-1"
                        >{{ advancedCount }}</span>
                    </button>
                </div>

                <!-- Filter panel -->
                <div
                    v-show="showFilters"
                    id="ticket-filters"
                    class="filters mb-5"
                >
                    <div class="row g-4">
                        <div class="col-md-3">
                            <label class="form-label fs-7">Status</label>
                            <select
                                v-model="f.request_status_id"
                                class="form-select form-select-sm"
                            >
                                <option value="">Any status</option>
                                <option value="active">Active (open or on hold)</option>
                                <optgroup label="By type">
                                    <option
                                        v-for="t in STATUS_TYPES"
                                        :key="t.value"
                                        :value="t.value"
                                    >{{ t.label }}</option>
                                </optgroup>
                                <optgroup label="Status">
                                    <option
                                        v-for="s in requestStatuses"
                                        :key="s.id"
                                        :value="String(s.id)"
                                    >{{ s.name }}</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Approval</label>
                            <select
                                v-model="f.status"
                                class="form-select form-select-sm"
                            >
                                <option value="">Any</option>
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >{{ s.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Category</label>
                            <select
                                v-model="f.category_id"
                                class="form-select form-select-sm"
                            >
                                <option value="">Any category</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Priority</label>
                            <select
                                v-model="f.priority_id"
                                class="form-select form-select-sm"
                            >
                                <option value="">Any priority</option>
                                <option
                                    v-for="p in priorities"
                                    :key="p.id"
                                    :value="String(p.id)"
                                >{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Group</label>
                            <select
                                v-model="f.group_id"
                                class="form-select form-select-sm"
                            >
                                <option value="">Any group</option>
                                <option
                                    v-for="g in groups"
                                    :key="g.id"
                                    :value="String(g.id)"
                                >{{ g.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Technician</label>
                            <select
                                v-model="f.technician_id"
                                class="form-select form-select-sm"
                            >
                                <option value="">Anyone</option>
                                <option value="me">Me</option>
                                <option value="none">Unassigned</option>
                                <option
                                    v-for="t in technicians"
                                    :key="t.id"
                                    :value="String(t.id)"
                                >{{ t.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Created from</label>
                            <input
                                v-model="f.from"
                                type="date"
                                class="form-control form-control-sm"
                                :max="f.to || undefined"
                            >
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fs-7">Created to</label>
                            <input
                                v-model="f.to"
                                type="date"
                                class="form-control form-control-sm"
                                :min="f.from || undefined"
                            >
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <label class="form-check form-check-sm mb-0">
                            <input
                                v-model="f.overdue"
                                type="checkbox"
                                class="form-check-input"
                                true-value="1"
                                false-value=""
                            >
                            <span class="form-check-label">Overdue only</span>
                        </label>
                        <button
                            type="button"
                            class="btn btn-sm btn-link text-gray-600"
                            :disabled="!anyFilter"
                            @click="clearAll"
                        >Clear all filters</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle gy-4 gs-3 mb-0">
                        <thead>
                            <tr class="text-start text-gray-500 fs-7 fw-bold">
                                <th>#</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Category</th>
                                <th>Assigned to</th>
                                <th>Requester</th>
                                <th>Created</th>
                                <th class="text-end" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in tickets.data"
                                :key="t.id"
                            >
                                <td class="text-muted fs-7">#{{ t.id }}</td>
                                <td class="mw-300px">
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
                                        v-if="t.request_status"
                                        class="status"
                                        :style="{ '--st': t.request_status.color }"
                                    >{{ t.request_status.name }}</span>
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
                                <td class="text-gray-700 fs-7">{{ t.category?.name ?? '—' }}</td>
                                <td class="fs-7">
                                    <div class="text-gray-800">{{ t.technician?.name ?? 'Unassigned' }}</div>
                                    <div class="text-muted fs-8">{{ t.group?.name }}</div>
                                </td>
                                <td class="text-gray-700 fs-7">{{ t.requester?.name ?? t.employee?.english_name ?? '—' }}</td>
                                <td class="text-gray-600 fs-7 text-nowrap">{{ formatDateTime(t.created_at) }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="show.url({ ticket: t.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >View</Link>
                                    <button
                                        v-if="seesAll"
                                        type="button"
                                        class="btn btn-sm btn-icon btn-light btn-active-light-danger"
                                        title="Delete"
                                        @click="removeTicket(t)"
                                    ><i class="ki-outline ki-trash" /></button>
                                </td>
                            </tr>
                            <tr v-if="tickets.data.length === 0">
                                <td
                                    colspan="9"
                                    class="text-center text-muted py-12"
                                >
                                    <template v-if="anyFilter">
                                        No tickets match these filters.
                                        <button
                                            type="button"
                                            class="btn btn-link btn-sm p-0 ms-1"
                                            @click="clearAll"
                                        >Clear filters</button>
                                    </template>
                                    <template v-else>No tickets yet.</template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="tickets.last_page > 1"
                class="card-footer d-flex flex-wrap align-items-center gap-2 py-3"
            >
                <span class="text-muted fs-7 me-auto">
                    {{ (tickets.current_page - 1) * tickets.per_page + 1 }}–{{ Math.min(tickets.current_page * tickets.per_page, tickets.total) }} of {{ tickets.total }}
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
                            preserve-scroll
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
</template>

<style scoped>
.views { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 10px; background: #f3f6f8; }
.view { padding: 6px 12px; border: 0; border-radius: 7px; background: none; color: #5e7383; font-size: 13px; font-weight: 600; cursor: pointer; }
.view:hover { color: #173c61; }
.view.on { background: #fff; color: #173c61; box-shadow: 0 1px 3px rgba(23, 60, 97, .12); }
.view:focus-visible { outline: 2px solid #2aa2a9; outline-offset: 1px; }
.filters { padding: 18px 20px; border: 1px solid #e5edf1; border-radius: 12px; background: #fafcfd; }
.status { --st: #7e8299; display: inline-flex; align-items: center; gap: 6px; padding: 3px 9px; border-radius: 6px; background: color-mix(in srgb, var(--st) 12%, #fff); color: #24465b; font-size: 12px; font-weight: 600; white-space: nowrap; }
.status::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--st); }
</style>
