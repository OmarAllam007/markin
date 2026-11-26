<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import AppDrawer from '@/components/AppDrawer.vue';
import TicketActivity from '@/components/ticketing/TicketActivity.vue';
import type { TicketLog } from '@/components/ticketing/TicketActivity.vue';
import TicketConversation from '@/components/ticketing/TicketConversation.vue';
import type { TicketMessage } from '@/components/ticketing/TicketConversation.vue';
import TicketReplyDrawer from '@/components/ticketing/TicketReplyDrawer.vue';
import type { ReplyStatus } from '@/components/ticketing/TicketReplyDrawer.vue';
import TicketSlaCard from '@/components/ticketing/TicketSlaCard.vue';
import type { SlaClock } from '@/components/ticketing/TicketSlaCard.vue';
import { formatDateTime } from '@/lib/utils';
import { destroy as destroyAttachment, show as downloadAttachment } from '@/routes/ticketing/attachments';
import { index, edit } from '@/routes/ticketing/tickets';
import { destroy as removeApproval, store as requestApproval } from '@/routes/ticketing/tickets/approvals';

type UserRef = { id: number; name: string };
type Approval = {
    id: number;
    stage_order: number;
    action: string;
    comments: string | null;
    acted_at: string | null;
    approver: UserRef | null;
    approver_employee: { id: number; english_name: string; email: string } | null;
    requested_by: UserRef | null;
    description: string | null;
    role: string | null;
    notified_at: string | null;
    attachments: { id: number; file_name: string; file_size: number }[];
};
type Attachment = { id: number; file_name: string; file_path: string; file_size: number; mime_type: string; created_at: string; uploader: UserRef | null };
type StatusOption = { value: string; label: string; color: string };

type TicketDetail = {
    id: number;
    subject: string;
    description: string | null;
    status: string;
    type: string | null;
    source: string | null;
    overdue: boolean;
    due_date: string | null;
    submitted_at: string | null;
    first_response_date: string | null;
    resolve_date: string | null;
    close_date: string | null;
    time_spent: number;
    form_data: Record<string, unknown> | null;
    created_at: string;
    requester: UserRef | null;
    creator: UserRef | null;
    employee: { id: number; english_name: string } | null;
    technician: UserRef | null;
    group: { id: number; name: string } | null;
    category: { id: number; name: string; color: string; icon: string | null } | null;
    subcategory: { id: number; name: string } | null;
    item: { id: number; name: string } | null;
    priority: { id: number; name: string; color: string } | null;
    sla: { id: number; name: string; first_response_hours: number; resolve_hours: number } | null;
    approvals: Approval[];
    attachments: Attachment[];
    request_status: { id: number; name: string; type: string; color: string } | null;
    messages: TicketMessage[];
};

type DetailField = { key: string; label: string; options: { value: string; label: string }[] | null };

const props = defineProps<{
    ticket: TicketDetail;
    statuses: StatusOption[];
    detailFields: DetailField[];
    canRequestApproval: boolean;
    canReply: boolean;
    canEdit: boolean;
    clock: SlaClock;
    replyStatuses: ReplyStatus[];
    logs: TicketLog[];
    approverOptions: { id: number; english_name: string; email: string }[];
}>();

const showApprovalForm = ref(false);
const showReply = ref(false);
const approvalForm = useForm({ approver_employee_id: '' as number | '', stage_order: '' as number | '', new_stage: false, description: '', file: null as File | null });

/** Stages already on the ticket; the first approval always opens stage 1. */
const stages = computed(() => [...new Set(props.ticket.approvals.map(a => a.stage_order))].sort((a, b) => a - b));

const openApprovalForm = () => {
    approvalForm.reset();
    approvalForm.stage_order = stages.value.at(-1) ?? '';
    showApprovalForm.value = true;
};

const submitApproval = () => {
    approvalForm.post(requestApproval.url({ ticket: props.ticket.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showApprovalForm.value = false;
            approvalForm.reset();
        },
    });
};

const fieldLabels = computed(() => Object.fromEntries(props.detailFields.map(f => [f.key, f.label])));

const deleteApproval = (a: Approval) => {
    if (window.confirm(`Remove the approval of ${a.approver_employee?.english_name ?? 'this approver'}?`)) {
        router.delete(removeApproval.url({ ticket: props.ticket.id, approval: a.id }), { preserveScroll: true });
    }
};

// ---------- files ----------
// Every file on the ticket in one list: the request's own, those sent with
// replies, and those sent to approvers. The source badge says which.
type FileSource = 'request' | 'reply' | 'approval';
type TicketFile = { id: number; file_name: string; file_size: number; source: FileSource; detail: string; deletable: boolean };

const allFiles = computed<TicketFile[]>(() => [
    ...props.ticket.attachments.map(a => ({
        ...a, source: 'request' as const, detail: a.uploader?.name ?? '—', deletable: true,
    })),
    ...props.ticket.messages.flatMap(m => m.attachments.map(a => ({
        ...a, source: 'reply' as const,
        detail: `${m.user?.name ?? m.employee?.english_name ?? 'Unknown'} · ${formatDateTime(m.created_at)}`,
        deletable: false,
    }))),
    ...props.ticket.approvals.flatMap(ap => ap.attachments.map(a => ({
        ...a, source: 'approval' as const,
        detail: `Stage ${ap.stage_order} · ${ap.approver_employee?.english_name ?? ap.approver?.name ?? '—'}`,
        deletable: false,
    }))),
]);

const fileSource: Record<FileSource, { label: string; badge: string }> = {
    request: { label: 'Request', badge: 'badge-light-primary' },
    reply: { label: 'Reply', badge: 'badge-light-info' },
    approval: { label: 'Approval', badge: 'badge-light-warning' },
};

// ---------- tabs ----------
const tabs = computed(() => [
    { key: 'overview', label: 'Request', icon: 'ki-document', count: null },
    { key: 'conversation', label: 'Conversation', icon: 'ki-messages', count: props.ticket.messages.length },
    { key: 'approvals', label: 'Approvals', icon: 'ki-verify', count: props.ticket.approvals.length },
    { key: 'activity', label: 'Activity', icon: 'ki-time', count: props.logs.length },
    { key: 'files', label: 'Files', icon: 'ki-paper-clip', count: allFiles.value.length },
] as const);
type TabKey = (typeof tabs.value)[number]['key'];

const pendingApprovals = computed(() => props.ticket.approvals.filter(a => a.action === 'pending').length);

// The hash keeps the tab across reloads and shared links (…/tickets/1#approvals).
const fromHash = (): TabKey => (tabs.value.find(t => `#${t.key}` === window.location.hash)?.key ?? 'overview');
const tab = ref<TabKey>(fromHash());
watch(tab, key => history.replaceState(history.state, '', `#${key}`));

const tabButtons = ref<HTMLButtonElement[]>([]);
const moveTab = async (by: number) => {
    const i = tabs.value.findIndex(t => t.key === tab.value);
    const next = (i + by + tabs.value.length) % tabs.value.length;
    tab.value = tabs.value[next].key;
    await nextTick();
    tabButtons.value[next]?.focus();
};

const approvalState = (a: Approval) => (a.action === 'pending' ? (a.notified_at ? 'Waiting for response' : 'Queued — after earlier stages') : a.action.toUpperCase());

const detailValue = (f: DetailField) => {
    const v = props.ticket.form_data?.[f.key];

    if (v === null || v === undefined || v === '') {
return '—';
}

    return f.options?.find(o => o.value === v)?.label ?? String(v);
};

const statusColor = (status: string) => props.statuses.find(s => s.value === status)?.color ?? 'secondary';
const statusLabel = (status: string) => props.statuses.find(s => s.value === status)?.label ?? status;
const servicePath = computed(() => [props.ticket.category?.name, props.ticket.subcategory?.name, props.ticket.item?.name].filter(Boolean).join(' / '));

const formatBytes = (bytes: number) => {
    if (bytes < 1024) {
return `${bytes} B`;
}

    if (bytes < 1024 * 1024) {
return `${(bytes / 1024).toFixed(1)} KB`;
}

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const deleteAttachment = (a: { id: number; file_name: string }) => {
    if (!window.confirm(`Remove "${a.file_name}"?`)) {
return;
}

    router.delete(destroyAttachment.url({ attachment: a.id }));
};

const actionColors: Record<string, string> = {
    pending: 'warning', approved: 'success', rejected: 'danger', returned: 'info', delegated: 'secondary',
};
</script>

<template>
    <div>
        <Head :title="`Ticket #${ticket.id}`" />
        <div class="mb-5 d-flex align-items-center gap-3">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to tickets
            </Link>
        </div>

        <div class="ticket-hero mb-7">
            <div class="ticket-hero-top">
            <div class="ticket-hero-main">
                <div class="ticket-overline">SERVICE REQUEST <span>#{{ ticket.id }}</span></div>
                <h1>{{ ticket.subject }}</h1>
                <div class="ticket-hero-meta">
                    <span
                        v-if="ticket.request_status"
                        class="hero-status"
                        :style="{ '--st': ticket.request_status.color }"
                    >{{ ticket.request_status.name }}</span>
                    <span
                        v-if="ticket.approvals.length"
                        :class="`badge badge-light-${statusColor(ticket.status)}`"
                        title="Approval"
                    >Approval: {{ statusLabel(ticket.status) }}</span>
                    <span v-if="ticket.overdue" class="badge badge-light-danger">Overdue</span>
                    <span v-if="servicePath"><i class="ki-outline ki-category me-1" />{{ servicePath }}</span>
                </div>
            </div>
            <Link v-if="canEdit" :href="edit.url({ ticket: ticket.id })" class="btn btn-light ticket-edit"><i class="ki-outline ki-notepad-edit me-1" />Edit ticket</Link>
            </div>

            <div
                class="ticket-tabs"
                role="tablist"
                aria-label="Ticket sections"
                @keydown.right.prevent="moveTab(1)"
                @keydown.left.prevent="moveTab(-1)"
            >
                <button
                    v-for="t in tabs"
                    :id="`tab-${t.key}`"
                    :key="t.key"
                    ref="tabButtons"
                    type="button"
                    role="tab"
                    class="ticket-tab"
                    :aria-selected="tab === t.key"
                    :aria-controls="`panel-${t.key}`"
                    :tabindex="tab === t.key ? 0 : -1"
                    @click="tab = t.key"
                >
                    <i :class="`ki-outline ${t.icon}`" />
                    {{ t.label }}
                    <span
                        v-if="t.count"
                        class="ticket-tab-count"
                    >{{ t.count }}</span>
                    <span
                        v-if="t.key === 'approvals' && pendingApprovals"
                        class="ticket-tab-pending"
                        :title="`${pendingApprovals} waiting for a response`"
                    />
                </button>
            </div>
        </div>

        <div class="row g-6">
            <!-- Main content -->
            <div class="col-lg-8">
                <section
                    v-show="tab === 'overview'"
                    id="panel-overview"
                    role="tabpanel"
                    aria-labelledby="tab-overview"
                >
                <div class="card ticket-panel mb-6">
                    <div class="card-body p-6 p-lg-8">
                        <div class="ticket-panel-title"><span class="ticket-section-icon"><i class="ki-outline ki-document" /></span><div><span class="ticket-kicker">THE REQUEST</span><h2>Description</h2></div></div>
                        <p class="ticket-description white-space-prewrap">{{ ticket.description || 'No description provided.' }}</p>
                    </div>
                </div>

                <!-- Request details (designed form fields) -->
                <div
                    v-if="detailFields.length"
                    class="card ticket-panel mb-6"
                >
                    <div class="ticket-card-heading">
                        <h3>Ticket fields</h3>
                    </div>
                    <div class="card-body ticket-card-content">
                        <div class="row g-4">
                            <div
                                v-for="f in detailFields"
                                :key="f.key"
                                class="col-md-6"
                            >
                                <div class="ticket-detail-field"><div class="text-muted fs-7">{{ f.label }}</div><div class="fw-semibold">{{ detailValue(f) }}</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                </section>

                <section
                    v-show="tab === 'conversation'"
                    id="panel-conversation"
                    role="tabpanel"
                    aria-labelledby="tab-conversation"
                >
                    <TicketConversation
                        :messages="ticket.messages"
                        :can-reply="canReply"
                        @reply="showReply = true"
                    />
                </section>

                <!-- Approvals timeline -->
                <section
                    v-show="tab === 'approvals'"
                    id="panel-approvals"
                    role="tabpanel"
                    aria-labelledby="tab-approvals"
                >
                <div class="card ticket-panel">
                    <div class="ticket-card-heading">
                        <h3>Approvals</h3>
                        <button
                            v-if="canRequestApproval && !showApprovalForm"
                            type="button"
                            class="btn btn-sm btn-primary"
                            @click="openApprovalForm"
                        >Request approval</button>
                    </div>
                    <div class="card-body ticket-card-content">
                        <div
                            v-if="ticket.approvals.length === 0 && !showApprovalForm"
                            class="text-muted text-center py-6"
                        >No approvals on this ticket.</div>

                        <div
                            v-for="a in ticket.approvals"
                            :key="a.id"
                            class="ticket-timeline-item d-flex gap-3"
                        >
                            <div class="flex-shrink-0">
                                <span :class="`badge badge-light-${actionColors[a.action] ?? 'secondary'} fs-7`">
                                    Stage {{ a.stage_order }}
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-800">
                                    {{ a.approver_employee?.english_name ?? a.approver?.name ?? '—' }}
                                    <span
                                        v-if="a.role"
                                        class="badge badge-light-info fw-semibold ms-1"
                                    >{{ a.role }}</span>
                                    <span
                                        v-if="a.approver_employee"
                                        class="text-muted fw-normal fs-8 ms-1"
                                    >{{ a.approver_employee.email }}</span>
                                </div>
                                <div :class="`text-${actionColors[a.action] ?? 'secondary'} fs-7 fw-semibold`">{{ approvalState(a) }}</div>
                                <p
                                    v-if="a.description"
                                    class="text-gray-700 mt-1 mb-0 white-space-prewrap"
                                >{{ a.description }}</p>
                                <p
                                    v-if="a.comments"
                                    class="text-gray-600 mt-1 mb-0"
                                >{{ a.comments }}</p>
                                <div class="text-muted fs-8 mt-1">
                                    <template v-if="a.approver_employee">{{ a.requested_by ? `Requested by ${a.requested_by.name}` : 'Automatic (service approval steps)' }}</template>
                                    <template v-if="a.acted_at"> · {{ formatDateTime(a.acted_at) }}</template>
                                </div>
                                <a
                                    v-for="f in a.attachments"
                                    :key="f.id"
                                    :href="downloadAttachment.url({ attachment: f.id })"
                                    class="d-inline-block fs-8 me-3 mt-1"
                                ><i class="ki-outline ki-paper-clip" /> {{ f.file_name }}</a>
                            </div>
                            <button
                                v-if="canRequestApproval && a.action === 'pending' && a.approver_employee"
                                type="button"
                                class="btn btn-sm btn-icon btn-light btn-active-light-danger flex-shrink-0"
                                title="Remove approval"
                                @click="deleteApproval(a)"
                            ><i class="ki-outline ki-trash" /></button>
                        </div>
                    </div>
                </div>
                </section>

                <section
                    v-show="tab === 'activity'"
                    id="panel-activity"
                    role="tabpanel"
                    aria-labelledby="tab-activity"
                >
                    <TicketActivity
                        :logs="logs"
                        :field-labels="fieldLabels"
                    />
                </section>

                <!-- Attachments -->
                <section
                    v-show="tab === 'files'"
                    id="panel-files"
                    role="tabpanel"
                    aria-labelledby="tab-files"
                >
                <div class="card ticket-panel">
                    <div class="ticket-card-heading">
                        <h3>Attachments <span class="ticket-count">{{ allFiles.length }}</span></h3>
                    </div>
                    <div class="card-body ticket-card-content">
                        <div
                            v-if="allFiles.length === 0"
                            class="text-muted text-center py-6"
                        >No attachments.</div>
                        <div
                            v-for="a in allFiles"
                            :key="a.id"
                            class="ticket-file d-flex align-items-center gap-3 mb-3 p-3"
                        >
                            <i class="ki-outline ki-file fs-2 text-primary" />
                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <a :href="downloadAttachment.url({ attachment: a.id })" class="fw-bold text-gray-800 text-hover-primary fs-7">{{ a.file_name }}</a>
                                    <span :class="['badge', fileSource[a.source].badge]">{{ fileSource[a.source].label }}</span>
                                </div>
                                <div class="text-muted fs-8">{{ formatBytes(a.file_size) }} · {{ a.detail }}</div>
                            </div>
                            <button
                                v-if="a.deletable"
                                type="button"
                                class="btn btn-sm btn-light btn-active-light-danger"
                                @click="deleteAttachment(a)"
                            >
                                <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                        </div>
                    </div>
                </div>
                </section>
            </div>

            <!-- Sidebar metadata -->
            <div class="col-lg-4">
                <div class="ticket-sidebar">
                <TicketSlaCard
                    :clock="clock"
                    :due-date="ticket.due_date"
                    :overdue="ticket.overdue"
                    :sla-name="ticket.sla?.name ?? null"
                    :created-at="ticket.created_at"
                    :first-response-at="ticket.first_response_date"
                />
                <div class="card ticket-panel">
                    <div class="ticket-card-heading"><h3>Details</h3></div>
                    <div class="card-body ticket-card-content">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7 w-50">Requester</td>
                                    <td class="fs-7">{{ ticket.requester?.name ?? ticket.employee?.english_name ?? '—' }}</td>
                                </tr>
                                <tr v-if="ticket.employee">
                                    <td class="text-muted fw-semibold fs-7">Employee</td>
                                    <td class="fs-7">{{ ticket.employee.english_name }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Category</td>
                                    <td>
                                        <span
                                            v-if="ticket.category"
                                            class="badge rounded-pill px-2"
                                            :style="{ backgroundColor: ticket.category.color, color: '#fff' }"
                                        >{{ ticket.category.name }}</span>
                                        <span
                                            v-else
                                            class="text-muted fs-7"
                                        >—</span>
                                    </td>
                                </tr>
                                <tr v-if="ticket.subcategory">
                                    <td class="text-muted fw-semibold fs-7">Subcategory</td>
                                    <td class="fs-7">{{ ticket.subcategory.name }}</td>
                                </tr>
                                <tr v-if="ticket.item">
                                    <td class="text-muted fw-semibold fs-7">Item</td>
                                    <td class="fs-7">{{ ticket.item.name }}</td>
                                </tr>
                                <tr v-if="ticket.priority">
                                    <td class="text-muted fw-semibold fs-7">Priority</td>
                                    <td>
                                        <span
                                            class="badge rounded-pill px-2"
                                            :style="{ backgroundColor: ticket.priority.color, color: '#fff' }"
                                        >{{ ticket.priority.name }}</span>
                                    </td>
                                </tr>
                                <tr v-if="ticket.group">
                                    <td class="text-muted fw-semibold fs-7">Group</td>
                                    <td class="fs-7">{{ ticket.group.name }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Assignee</td>
                                    <td class="fs-7">{{ ticket.technician?.name ?? 'Unassigned' }}</td>
                                </tr>
                                <tr v-if="ticket.sla">
                                    <td class="text-muted fw-semibold fs-7">SLA</td>
                                    <td class="fs-7">{{ ticket.sla.name }}</td>
                                </tr>
                                <tr v-if="ticket.due_date">
                                    <td class="text-muted fw-semibold fs-7">Due Date</td>
                                    <td class="fs-7">{{ formatDateTime(ticket.due_date) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Submitted</td>
                                    <td class="fs-7">{{ formatDateTime(ticket.submitted_at) }}</td>
                                </tr>
                                <tr v-if="ticket.resolve_date">
                                    <td class="text-muted fw-semibold fs-7">Resolved</td>
                                    <td class="fs-7">{{ formatDateTime(ticket.resolve_date) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Created by</td>
                                    <td class="fs-7">{{ ticket.creator?.name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Created at</td>
                                    <td class="fs-7">{{ formatDateTime(ticket.created_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <AppDrawer
            v-model:open="showApprovalForm"
            title="Request approval"
            subtitle="The approver gets an email with a link to approve or reject."
        >
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label required">Approver</label>
                                    <select
                                        v-model="approvalForm.approver_employee_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': approvalForm.errors.approver_employee_id }"
                                    >
                                        <option value="">Select employee…</option>
                                        <option
                                            v-for="e in approverOptions"
                                            :key="e.id"
                                            :value="e.id"
                                        >{{ e.english_name }} — {{ e.email }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ approvalForm.errors.approver_employee_id }}</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Stage</label>
                                    <div
                                        v-if="!stages.length"
                                        class="form-control-plaintext"
                                    >Stage 1</div>
                                    <template v-else>
                                        <select
                                            v-model="approvalForm.stage_order"
                                            class="form-select"
                                            :disabled="approvalForm.new_stage"
                                            :class="{ 'is-invalid': approvalForm.errors.stage_order }"
                                        >
                                            <option
                                                v-for="st in stages"
                                                :key="st"
                                                :value="st"
                                            >Stage {{ st }}</option>
                                        </select>
                                        <div class="invalid-feedback">{{ approvalForm.errors.stage_order }}</div>
                                        <label class="form-check form-check-sm mt-2">
                                            <input
                                                v-model="approvalForm.new_stage"
                                                type="checkbox"
                                                class="form-check-input"
                                            >
                                            <span class="form-check-label">Add as new stage (Stage {{ (stages.at(-1) ?? 0) + 1 }})</span>
                                        </label>
                                    </template>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Description <span class="text-muted fw-normal">(shown to the approver)</span></label>
                                    <textarea
                                        v-model="approvalForm.description"
                                        class="form-control"
                                        :class="{ 'is-invalid': approvalForm.errors.description }"
                                        rows="3"
                                        placeholder="What should the approver check?"
                                    />
                                    <div class="invalid-feedback">{{ approvalForm.errors.description }}</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">File for the approver <span class="text-muted fw-normal">(sent with the email)</span></label>
                                    <input
                                        type="file"
                                        class="form-control"
                                        :class="{ 'is-invalid': approvalForm.errors.file }"
                                        @change="approvalForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                    >
                                    <div class="invalid-feedback">{{ approvalForm.errors.file }}</div>
                                </div>
                            </div>
            <template #footer>
                <button
                    type="button"
                    class="btn btn-light"
                    @click="showApprovalForm = false"
                >Cancel</button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="approvalForm.processing || !approvalForm.approver_employee_id"
                    @click="submitApproval"
                >
                    <span
                        v-if="approvalForm.processing"
                        class="spinner-border spinner-border-sm me-2"
                    />
                    Send request
                </button>
            </template>
        </AppDrawer>

        <TicketReplyDrawer
            v-model:open="showReply"
            :ticket-id="ticket.id"
            :current-status-id="ticket.request_status?.id ?? null"
            :statuses="replyStatuses"
            :people="approverOptions"
        />
    </div>
</template>

<style scoped>
.ticket-hero { padding: 32px 36px 0; border-radius: 16px; background: linear-gradient(115deg, #173c61, #176b81); color: #fff; }
.ticket-hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding-bottom: 26px; }
/* Tabs sit on the hero's bottom edge, so the request and its sections read as one header. */
.ticket-tabs { display: flex; gap: 4px; margin: 0 -12px; overflow-x: auto; border-top: 1px solid rgba(255, 255, 255, .12); scrollbar-width: none; }
.ticket-tab { position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 15px 12px 17px; border: 0; background: none; color: #a9cbd5; font-size: 14px; font-weight: 600; white-space: nowrap; cursor: pointer; transition: color .15s; }
.ticket-tab i { font-size: 17px; }
.ticket-tab:hover { color: #fff; }
.ticket-tab[aria-selected='true'] { color: #fff; }
.ticket-tab::after { content: ''; position: absolute; inset: auto 12px 0; height: 3px; border-radius: 3px 3px 0 0; background: #7fe0d9; transform: scaleX(0); transition: transform .2s ease; }
.ticket-tab[aria-selected='true']::after { transform: scaleX(1); }
.ticket-tab:focus-visible { outline: 2px solid #7fe0d9; outline-offset: -4px; border-radius: 8px; }
.ticket-tab-count { min-width: 20px; padding: 1px 6px; border-radius: 6px; background: rgba(255, 255, 255, .14); color: #e3f3f6; font-size: 11px; text-align: center; }
.ticket-tab[aria-selected='true'] .ticket-tab-count { background: #7fe0d9; color: #123a52; }
.ticket-tab-pending { width: 7px; height: 7px; border-radius: 50%; background: #ffc76b; box-shadow: 0 0 0 3px rgba(255, 199, 107, .25); }
@media (prefers-reduced-motion: reduce) { .ticket-tab, .ticket-tab::after { transition: none; } }
.ticket-hero-main { min-width: 0; }
.ticket-overline { color: #b6dfe6; font-size: 11px; font-weight: 700; letter-spacing: .13em; }
.ticket-overline span { color: #fff; margin-left: 9px; }
.ticket-hero h1 { margin: 12px 0 18px; color: #fff; font-size: clamp(24px, 2.6vw, 34px); line-height: 1.25; font-weight: 750; overflow-wrap: anywhere; }
.ticket-hero-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; color: #cde4e9; font-size: 13px; }
.ticket-hero-meta .badge { font-size: 12px; }
.hero-status { --st: #7e8299; display: inline-flex; align-items: center; gap: 7px; padding: 4px 11px 4px 9px; border-radius: 7px; background: #fff; color: #173c61; font-size: 12px; font-weight: 700; }
.hero-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--st); }
.ticket-edit { flex: 0 0 auto; }
.ticket-panel { border: 1px solid #e5edf1; border-radius: 14px; box-shadow: 0 8px 28px rgba(28, 65, 89, .045); }
.ticket-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; padding: 20px 28px; border-bottom: 1px solid #edf1f3; }
.ticket-card-heading h3 { display: flex; align-items: center; gap: 9px; margin: 0; color: #24465b; font-size: 18px; font-weight: 700; line-height: 1.3; }
.ticket-card-content { padding: 24px 28px 28px; }
.ticket-count { display: inline-grid; place-items: center; min-width: 24px; height: 24px; padding: 0 6px; border-radius: 7px; background: #e2f2f2; color: #16838b; font-size: 12px; }
.ticket-panel-title { display: flex; align-items: center; gap: 14px; margin-bottom: 23px; }
.ticket-section-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 11px; background: #dff2f2; color: #16838b; font-size: 22px; }
.ticket-kicker { color: #8095a3; font-size: 10px; font-weight: 800; letter-spacing: .12em; }
.ticket-panel-title h2 { margin: 2px 0 0; color: #24465b; font-size: 19px; font-weight: 700; }
.ticket-description { margin: 0; padding: 21px 24px; border-left: 3px solid #2aa2a9; border-radius: 0 9px 9px 0; background: #f6fafb; color: #385469; line-height: 1.7; }
.ticket-detail-field { height: 100%; padding: 17px 19px; border: 1px solid #e8eef2; border-radius: 10px; background: #fbfcfd; overflow-wrap: anywhere; }
.ticket-detail-field .fw-semibold { margin-top: 5px; color: #2a485c; }
.ticket-timeline-item { position: relative; padding: 0 0 24px; }
.ticket-timeline-item:not(:last-of-type)::before { content: ''; position: absolute; left: 25px; top: 28px; bottom: 0; width: 1px; background: #dbe7eb; }
.ticket-timeline-item > div:first-child { position: relative; z-index: 1; }
.ticket-file { border: 1px solid #e5edf1; border-radius: 10px; background: #fbfcfd; }
.ticket-file:hover { border-color: #abd3d7; }
.ticket-sidebar { position: sticky; top: 20px; }
.ticket-sidebar :deep(table) { margin-bottom: 0; }
.ticket-sidebar :deep(td) { padding: 11px 4px; border-bottom: 1px solid #edf1f3; vertical-align: top; overflow-wrap: anywhere; }
.ticket-sidebar :deep(tr:last-child td) { border-bottom: 0; }
@media (max-width: 767px) { .ticket-hero { padding: 26px 26px 0; } .ticket-hero-top { flex-direction: column; } .ticket-edit { width: 100%; text-align: center; } .ticket-sidebar { position: static; } }
</style>
