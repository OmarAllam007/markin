<script setup lang="ts">
import { computed } from 'vue';
import { formatDateTime } from '@/lib/utils';

type Change = { old: unknown; new: unknown };
export type TicketLog = {
    id: number;
    type: string;
    created_at: string;
    user: { id: number; name: string } | null;
    employee: { id: number; english_name: string } | null;
    changes: Record<string, Change> | null;
    meta: Record<string, unknown> | null;
};

const props = defineProps<{
    logs: TicketLog[];
    /** Labels of the designed form fields, for form_data.* changes. */
    fieldLabels: Record<string, string>;
}>();

const LABELS: Record<string, string> = {
    subject: 'Subject', description: 'Description', request_status_id: 'Status', status: 'Approval', priority_id: 'Priority',
    technician_id: 'Technician', group_id: 'Group', category_id: 'Category', subcategory_id: 'Subcategory',
    item_id: 'Item', sla_id: 'SLA', employee_id: 'Employee', due_date: 'Due date',
    resolve_date: 'Resolved', close_date: 'Closed', source: 'Source',
};

/** Per type: icon, colour and the sentence shown. */
const TYPES: Record<string, { icon: string; color: string; text: (l: TicketLog) => string }> = {
    created: { icon: 'ki-plus-square', color: 'primary', text: () => 'created the ticket' },
    rule_applied: { icon: 'ki-route', color: 'info', text: l => `Business rule “${l.meta?.rule}” assigned it to ${l.meta?.group}` },
    updated: { icon: 'ki-pencil', color: 'info', text: l => `changed ${Object.keys(l.changes ?? {}).length} field(s)` },
    deleted: { icon: 'ki-trash', color: 'danger', text: () => 'deleted the ticket' },
    restored: { icon: 'ki-arrows-circle', color: 'success', text: () => 'restored the ticket' },
    approval_requested: {
        icon: 'ki-send', color: 'warning',
        text: l => (l.meta?.automatic ? `${l.meta?.role} step sent to ${l.meta?.approver}` : `requested approval from ${l.meta?.approver}`),
    },
    approval_skipped: { icon: 'ki-arrow-right', color: 'secondary', text: l => `${l.meta?.role} step skipped` },
    approval_removed: { icon: 'ki-cross-circle', color: 'secondary', text: l => `removed the approval of ${l.meta?.approver}` },
    approval_approved: { icon: 'ki-check-circle', color: 'success', text: () => 'approved' },
    approval_rejected: { icon: 'ki-cross-square', color: 'danger', text: () => 'rejected' },
    message_added: { icon: 'ki-message-text-2', color: 'primary', text: l => (l.meta?.from_requester ? 'replied as the requester' : 'sent a message') },
    attachment_added: { icon: 'ki-paper-clip', color: 'primary', text: l => `attached ${l.meta?.file_name}` },
    attachment_removed: { icon: 'ki-paper-clip', color: 'secondary', text: l => `removed ${l.meta?.file_name}` },
};

const typeOf = (l: TicketLog) => TYPES[l.type] ?? { icon: 'ki-information', color: 'secondary', text: () => l.type };
const actor = (l: TicketLog) => (l.meta?.automatic || l.type === 'approval_skipped' || l.type === 'rule_applied' ? '' : l.user?.name ?? l.employee?.english_name ?? 'System');
const label = (key: string) => (key.startsWith('form_data.') ? props.fieldLabels[key.slice(10)] ?? key.slice(10) : LABELS[key] ?? key);
const show = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'boolean' ? (v ? '✓' : '✗') : String(v));

/** formatDateTime() is "Y-m-d H:i": the date heads each day group, the time sits on the entry. */
const time = (iso: string) => formatDateTime(iso).slice(11);

/** Newest day first, entries under their date. */
const days = computed(() => {
    const groups: { day: string; logs: TicketLog[] }[] = [];

    for (const l of props.logs) {
        const day = formatDateTime(l.created_at).slice(0, 10);

        if (groups.at(-1)?.day !== day) {
            groups.push({ day, logs: [] });
        }

        groups.at(-1)!.logs.push(l);
    }

    return groups;
});
</script>

<template>
    <div class="card ticket-panel mb-6">
        <div class="ticket-card-heading">
            <h3>Activity <span class="ticket-count">{{ logs.length }}</span></h3>
        </div>
        <div class="card-body ticket-card-content">
            <div
                v-if="!logs.length"
                class="text-muted text-center py-6"
            >No activity yet.</div>

            <div
                v-for="g in days"
                :key="g.day"
                class="mb-6"
            >
                <div class="log-day">{{ g.day }}</div>

                <div
                    v-for="l in g.logs"
                    :key="l.id"
                    class="log-entry"
                >
                    <span :class="`log-icon bg-light-${typeOf(l).color} text-${typeOf(l).color}`"><i :class="`ki-outline ${typeOf(l).icon}`" /></span>
                    <div class="log-content">
                        <div class="log-summary">
                            <div class="log-description fs-7">
                                <span class="fw-bold text-gray-800">{{ actor(l) }}</span>
                                {{ typeOf(l).text(l) }}
                                <span
                                    v-if="l.meta?.stage"
                                    :class="`badge badge-light-${typeOf(l).color} ms-1`"
                                >Stage {{ l.meta.stage }}</span>
                                <span
                                    v-if="l.meta?.automatic"
                                    class="badge badge-light-secondary ms-1"
                                >Automatic</span>
                            </div>
                            <time class="log-time" :datetime="l.created_at">{{ time(l.created_at) }}</time>
                        </div>

                        <!-- updated: old → new per field -->
                        <table
                            v-if="l.type === 'updated' && l.changes"
                            class="log-diff"
                        >
                            <tbody>
                                <tr
                                    v-for="(c, key) in l.changes"
                                    :key="key"
                                >
                                    <td class="text-muted">{{ label(String(key)) }}</td>
                                    <td><span class="log-old">{{ show(c.old) }}</span></td>
                                    <td class="text-muted">→</td>
                                    <td><span class="log-new">{{ show(c.new) }}</span></td>
                                </tr>
                            </tbody>
                        </table>


                        <!-- message: excerpt, files, CC -->
                        <template v-else-if="l.type === 'message_added'">
                            <div class="log-quote">{{ l.meta?.excerpt }}</div>
                            <div
                                v-if="(l.meta?.files as string[] | undefined)?.length"
                                class="fs-8 text-gray-600 mt-1"
                            ><i class="ki-outline ki-paper-clip" /> {{ (l.meta?.files as string[]).join(', ') }}</div>
                            <div
                                v-if="(l.meta?.cc as string[] | undefined)?.length"
                                class="fs-8 text-gray-600 mt-1"
                            >Copied: {{ (l.meta?.cc as string[]).join(', ') }}</div>
                        </template>

                        <!-- approvals: who + note -->
                        <template v-else-if="l.type.startsWith('approval_')">
                            <div
                                v-if="l.type !== 'approval_requested' && l.type !== 'approval_removed'"
                                class="text-gray-600 fs-8"
                            >{{ l.meta?.approver }}</div>
                            <div
                                v-if="l.meta?.reason"
                                class="text-gray-600 fs-8"
                            >Because {{ l.meta.reason }}.</div>
                            <div
                                v-if="l.meta?.description || l.meta?.comments"
                                class="log-quote"
                            >{{ l.meta?.description ?? l.meta?.comments }}</div>
                            <div
                                v-if="l.meta?.file_name"
                                class="fs-8 text-gray-600 mt-1"
                            ><i class="ki-outline ki-paper-clip" /> {{ l.meta.file_name }}</div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ticket-panel { border: 1px solid #e5edf1; border-radius: 14px; box-shadow: 0 8px 28px rgba(28, 65, 89, .045); }
.ticket-card-heading { display: flex; align-items: center; min-height: 72px; padding: 20px 28px; border-bottom: 1px solid #edf1f3; }
.ticket-card-heading h3 { display: flex; align-items: center; gap: 9px; margin: 0; color: #24465b; font-size: 18px; font-weight: 700; line-height: 1.3; }
.ticket-card-content { padding: 24px 28px 28px; }
.ticket-count { display: inline-grid; place-items: center; min-width: 24px; height: 24px; padding: 0 6px; border-radius: 7px; background: #e2f2f2; color: #16838b; font-size: 12px; }
.log-day { margin-bottom: 10px; color: #8095a3; font-size: 12px; font-weight: 700; }
.log-entry { position: relative; display: flex; gap: 12px; padding: 0 0 16px; }
.log-entry:not(:last-child)::before { content: ''; position: absolute; top: 32px; bottom: 0; inset-inline-start: 15px; width: 1px; background: #e5edf1; }
.log-icon { display: grid; flex: 0 0 32px; place-items: center; height: 32px; border-radius: 9px; font-size: 16px; }
.log-content { flex: 1 1 0; min-width: 0; }
.log-summary { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px 16px; }
.log-description { min-width: 0; overflow-wrap: anywhere; }
.log-time { flex: 0 0 auto; color: #8295a2; font-size: 12px; white-space: nowrap; }
.log-diff { display: block; max-width: 100%; overflow-x: auto; margin-top: 8px; border-collapse: separate; border-spacing: 0 4px; font-size: 12px; }
.log-diff td { padding-inline-end: 10px; vertical-align: top; overflow-wrap: anywhere; }
.log-old { padding: 1px 6px; border-radius: 5px; background: #fdeaea; color: #a33; text-decoration: line-through; }
.log-new { padding: 1px 6px; border-radius: 5px; background: #e3f6ee; color: #11734f; font-weight: 600; }
.log-quote { margin-top: 6px; padding: 8px 12px; border-inline-start: 3px solid #d6e2e8; background: #f6fafb; color: #385469; font-size: 12px; white-space: pre-wrap; }
@media (max-width: 575px) { .ticket-card-heading { padding: 16px 18px; } .ticket-card-content { padding: 18px; } .log-summary { flex-direction: column; } .log-time { order: -1; } }
</style>
