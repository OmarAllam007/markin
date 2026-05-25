<script setup lang="ts">
import { index, edit, approve } from '@/routes/ticketing/tickets';
import { destroy as destroyAttachment } from '@/routes/ticketing/attachments';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type UserRef = { id: number; name: string };
type Approval = {
    id: number;
    stage_order: number;
    action: string;
    comments: string | null;
    acted_at: string | null;
    approver: UserRef | null;
    delegated_to: UserRef | null;
};
type Attachment = { id: number; file_name: string; file_path: string; file_size: number; mime_type: string; created_at: string; uploader: UserRef | null };
type StatusOption = { value: string; label: string; color: string };

type TicketDetail = {
    id: number;
    subject: string;
    description: string;
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
    priority: { id: number; name: string; color: string } | null;
    sla: { id: number; name: string; first_response_hours: number; resolve_hours: number } | null;
    approvals: Approval[];
    attachments: Attachment[];
};

const props = defineProps<{ ticket: TicketDetail; statuses: StatusOption[] }>();

const showApprovalForm = ref(false);
const approvalForm = useForm({ action: '', comments: '', delegated_to_user_id: '' });

const submitApproval = () => {
    approvalForm.post(approve.url({ ticket: props.ticket.id }), {
        onSuccess: () => { showApprovalForm.value = false; approvalForm.reset(); },
    });
};

const statusColor = (status: string) => props.statuses.find(s => s.value === status)?.color ?? 'secondary';
const statusLabel = (status: string) => props.statuses.find(s => s.value === status)?.label ?? status;

const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const deleteAttachment = (a: Attachment) => {
    if (!window.confirm(`Remove "${a.file_name}"?`)) return;
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

        <div class="row g-5">
            <!-- Main content -->
            <div class="col-lg-8">
                <!-- Ticket header card -->
                <div class="card mb-5">
                    <div class="card-header border-0 d-flex align-items-center gap-3 flex-wrap">
                        <span class="text-muted fs-7">#{{ ticket.id }}</span>
                        <h2 class="fw-bold mb-0 flex-grow-1">{{ ticket.subject }}</h2>
                        <span :class="`badge badge-light-${statusColor(ticket.status)} fs-7`">
                            {{ statusLabel(ticket.status) }}
                        </span>
                        <span
                            v-if="ticket.overdue"
                            class="badge badge-light-danger fs-7"
                        >Overdue</span>
                        <Link
                            :href="edit.url({ ticket: ticket.id })"
                            class="btn btn-sm btn-light"
                        >
                            <i class="ki-duotone ki-notepad-edit"><span class="path1"></span><span class="path2"></span></i>
                            Edit
                        </Link>
                    </div>
                    <div class="card-body pt-3">
                        <p class="text-gray-700 fs-6 mb-0 white-space-prewrap">{{ ticket.description }}</p>
                    </div>
                </div>

                <!-- Approvals timeline -->
                <div class="card mb-5">
                    <div class="card-header border-0 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0">Approval History</h5>
                        <button
                            v-if="!showApprovalForm"
                            type="button"
                            class="btn btn-sm btn-primary"
                            @click="showApprovalForm = true"
                        >Add Approval Action</button>
                    </div>
                    <div class="card-body pt-3">
                        <div
                            v-if="ticket.approvals.length === 0 && !showApprovalForm"
                            class="text-muted text-center py-6"
                        >No approval actions yet.</div>

                        <div
                            v-for="a in ticket.approvals"
                            :key="a.id"
                            class="d-flex gap-3 mb-5 pb-5 border-bottom"
                        >
                            <div class="flex-shrink-0">
                                <span :class="`badge badge-light-${actionColors[a.action] ?? 'secondary'} fs-7`">
                                    Stage {{ a.stage_order }}
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-800">{{ a.approver?.name ?? '—' }}</div>
                                <div :class="`text-${actionColors[a.action] ?? 'secondary'} fs-7 fw-semibold`">
                                    {{ a.action.replace('_', ' ').toUpperCase() }}
                                    <span
                                        v-if="a.delegated_to"
                                        class="text-muted fw-normal"
                                    > → {{ a.delegated_to.name }}</span>
                                </div>
                                <p
                                    v-if="a.comments"
                                    class="text-gray-600 mt-1 mb-0"
                                >{{ a.comments }}</p>
                                <div class="text-muted fs-8 mt-1">{{ a.acted_at ?? 'Pending' }}</div>
                            </div>
                        </div>

                        <!-- Inline approval form -->
                        <div
                            v-if="showApprovalForm"
                            class="border rounded p-5 bg-light-secondary"
                        >
                            <h6 class="fw-bold mb-4">Record Approval Action</h6>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label required">Action</label>
                                    <select
                                        v-model="approvalForm.action"
                                        class="form-select"
                                        :class="{ 'is-invalid': approvalForm.errors.action }"
                                    >
                                        <option value="">Select action…</option>
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                        <option value="returned">Return for revision</option>
                                        <option value="delegated">Delegate</option>
                                    </select>
                                    <div
                                        v-if="approvalForm.errors.action"
                                        class="invalid-feedback"
                                    >{{ approvalForm.errors.action }}</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Comments</label>
                                    <textarea
                                        v-model="approvalForm.comments"
                                        class="form-control"
                                        rows="3"
                                        placeholder="Optional comments…"
                                    />
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light"
                                    @click="showApprovalForm = false"
                                >Cancel</button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary"
                                    :disabled="approvalForm.processing"
                                    @click="submitApproval"
                                >
                                    <span
                                        v-if="approvalForm.processing"
                                        class="spinner-border spinner-border-sm me-2"
                                    />
                                    Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="card">
                    <div class="card-header border-0">
                        <h5 class="fw-bold mb-0">Attachments ({{ ticket.attachments.length }})</h5>
                    </div>
                    <div class="card-body pt-3">
                        <div
                            v-if="ticket.attachments.length === 0"
                            class="text-muted text-center py-6"
                        >No attachments.</div>
                        <div
                            v-for="a in ticket.attachments"
                            :key="a.id"
                            class="d-flex align-items-center gap-3 mb-3 p-3 border rounded"
                        >
                            <i class="ki-outline ki-file fs-2 text-primary" />
                            <div class="flex-grow-1">
                                <div class="fw-bold text-gray-800 fs-7">{{ a.file_name }}</div>
                                <div class="text-muted fs-8">{{ formatBytes(a.file_size) }} · {{ a.uploader?.name ?? '—' }}</div>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-light btn-active-light-danger"
                                @click="deleteAttachment(a)"
                            >
                                <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar metadata -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header border-0"><h5 class="fw-bold mb-0">Details</h5></div>
                    <div class="card-body pt-3">
                        <table class="table table-sm table-borderless">
                            <tbody>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7 w-50">Requester</td>
                                    <td class="fs-7">{{ ticket.requester?.name ?? '—' }}</td>
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
                                    <td class="fs-7">{{ ticket.due_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Submitted</td>
                                    <td class="fs-7">{{ ticket.submitted_at ?? '—' }}</td>
                                </tr>
                                <tr v-if="ticket.resolve_date">
                                    <td class="text-muted fw-semibold fs-7">Resolved</td>
                                    <td class="fs-7">{{ ticket.resolve_date }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Created by</td>
                                    <td class="fs-7">{{ ticket.creator?.name ?? '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted fw-semibold fs-7">Created at</td>
                                    <td class="fs-7">{{ ticket.created_at }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
