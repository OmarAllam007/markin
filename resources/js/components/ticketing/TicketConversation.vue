<script setup lang="ts">
import { formatDateTime } from '@/lib/utils';
import { show as downloadAttachment } from '@/routes/ticketing/attachments';

export type TicketMessage = {
    id: number;
    body: string;
    from_requester: boolean;
    created_at: string;
    user: { id: number; name: string } | null;
    employee: { id: number; english_name: string } | null;
    request_status: { id: number; name: string; color: string } | null;
    cc_names: string[];
    attachments: { id: number; file_name: string; file_size: number }[];
};

defineProps<{ messages: TicketMessage[]; canReply: boolean }>();
defineEmits<{ reply: [] }>();

const author = (m: TicketMessage) => m.user?.name ?? m.employee?.english_name ?? 'Unknown';
const initials = (name: string) => name.split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
</script>

<template>
    <div class="card ticket-panel">
        <div class="ticket-card-heading">
            <h3>Conversation</h3>
            <button
                v-if="canReply"
                type="button"
                class="btn btn-sm btn-primary"
                @click="$emit('reply')"
            ><i class="ki-outline ki-message-text-2 me-1" />Reply</button>
        </div>
        <div class="card-body ticket-card-content">
            <div
                v-if="!messages.length"
                class="empty"
            >
                <i class="ki-outline ki-messages" />
                <p>No messages yet. Reply to update the requester, ask for details, or change the status.</p>
            </div>

            <ol
                v-else
                class="thread"
            >
                <li
                    v-for="m in messages"
                    :key="m.id"
                    :class="['msg', m.from_requester ? 'from-requester' : 'from-staff']"
                >
                    <span
                        class="avatar"
                        aria-hidden="true"
                    >{{ initials(author(m)) }}</span>
                    <div class="message-content">
                        <div class="message-meta">
                            <span class="author fw-bold text-gray-800">{{ author(m) }}</span>
                            <span class="side">{{ m.from_requester ? 'Requester' : 'Support' }}</span>
                        </div>
                    <article class="bubble">
                        <p class="body">{{ m.body }}</p>
                        <div
                            v-if="m.request_status"
                            class="moved"
                            :style="{ '--st': m.request_status.color }"
                        ><i class="ki-outline ki-arrow-right" />Status moved to <strong>{{ m.request_status.name }}</strong></div>
                        <div
                            v-if="m.attachments.length"
                            class="files"
                        >
                            <a
                                v-for="f in m.attachments"
                                :key="f.id"
                                :href="downloadAttachment.url({ attachment: f.id })"
                            ><i class="ki-outline ki-paper-clip" />{{ f.file_name }}</a>
                        </div>
                    </article>
                        <div class="message-footer">
                            <time :datetime="m.created_at">{{ formatDateTime(m.created_at) }}</time>
                            <span v-if="m.cc_names.length" class="cc">Copied: {{ m.cc_names.join(', ') }}</span>
                        </div>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</template>

<style scoped>
.ticket-panel { border: 1px solid #e5edf1; border-radius: 14px; box-shadow: 0 8px 28px rgba(28, 65, 89, .045); }
.ticket-card-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 72px; padding: 20px 28px; border-bottom: 1px solid #edf1f3; }
.ticket-card-heading h3 { margin: 0; color: #24465b; font-size: 18px; font-weight: 700; line-height: 1.3; }
.ticket-card-content { padding: 28px; }
.empty { padding: 36px 0; color: #7a8d99; text-align: center; }
.empty i { font-size: 34px; color: #b6cbd5; }
.empty p { max-width: 380px; margin: 10px auto 0; }

.thread { display: flex; flex-direction: column; gap: 24px; margin: 0; padding: 0; list-style: none; }
.msg { display: flex; align-items: flex-start; gap: 12px; width: 100%; }
/* Support speaks from the start edge, the requester from the end edge, like a chat. */
.msg.from-requester { flex-direction: row-reverse; }
.avatar { display: grid; flex: 0 0 34px; place-items: center; height: 34px; border-radius: 11px; background: #e6f3f4; color: #16838b; font-size: 11px; font-weight: 800; }
.from-requester .avatar { background: #eceff8; color: #4b5a8a; }
.message-content { min-width: 0; max-width: min(78%, 680px); }
.from-requester .message-content { text-align: end; }
.message-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; margin: 0 0 7px; font-size: 12px; }
.from-requester .message-meta { justify-content: flex-end; }
.bubble { min-width: 0; padding: 12px 15px; border: 1px solid #e3ebed; border-radius: 5px 15px 15px 15px; background: #f7fafb; text-align: start; }
.from-requester .bubble { border-color: #e3e9f2; border-radius: 15px 5px 15px 15px; background: #f3f6fb; }
.author { overflow-wrap: anywhere; }
.side { color: #16838b; font-size: 11px; font-weight: 600; }
.from-requester .side { color: #4b5a8a; }
.message-footer { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 6px; color: #8294a0; font-size: 11px; }
.from-requester .message-footer { justify-content: flex-end; }
time { color: inherit; }
.body { margin: 0; color: #2f4a5c; line-height: 1.65; white-space: pre-wrap; overflow-wrap: anywhere; }
.moved { --st: #7e8299; display: inline-flex; align-items: center; gap: 6px; margin-top: 10px; padding: 4px 9px; border-radius: 6px; background: color-mix(in srgb, var(--st) 9%, #fff); color: #2f4a5c; font-size: 11px; }
.moved i { color: var(--st); }
.files { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.files a { display: inline-flex; align-items: center; gap: 5px; padding: 4px 8px; border: 1px solid #dfe8ed; border-radius: 6px; background: #fff; color: #2f4a5c; font-size: 11px; overflow-wrap: anywhere; }
.files a:hover { border-color: #2aa2a9; color: #16838b; }
.cc { color: #8a9ba6; }
@media (max-width: 575px) { .ticket-card-heading { padding: 16px 18px; } .ticket-card-content { padding: 18px; } .message-content { max-width: calc(100% - 46px); } }
</style>
