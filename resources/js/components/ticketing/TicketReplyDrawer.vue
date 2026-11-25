<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppDrawer from '@/components/AppDrawer.vue';
import { store as storeMessage } from '@/routes/ticketing/tickets/messages';

export type ReplyStatus = { id: number; name: string; type: string; color: string };
type Person = { id: number; english_name: string; email: string };

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    ticketId: number;
    currentStatusId: number | null;
    statuses: ReplyStatus[];
    people: Person[];
}>();

const form = useForm({
    body: '',
    request_status_id: null as number | null,
    cc: [] as number[],
    attachments: [] as File[],
});

watch(open, isOpen => isOpen && (form.reset(), form.clearErrors()));

// ---------- CC ----------
const ccSearch = ref('');
const ccPicked = computed(() => props.people.filter(p => form.cc.includes(p.id)));
const ccMatches = computed(() => {
    const q = ccSearch.value.trim().toLowerCase();

    return q
        ? props.people.filter(p => !form.cc.includes(p.id) && `${p.english_name} ${p.email}`.toLowerCase().includes(q)).slice(0, 6)
        : [];
});
const addCc = (p: Person) => {
    form.cc.push(p.id);
    ccSearch.value = '';
};

// ---------- files ----------
const fileInput = ref<HTMLInputElement | null>(null);
const addFiles = (list: FileList | null | undefined) => {
    form.attachments = [...form.attachments, ...Array.from(list ?? [])].slice(0, 10);
};
const dragging = ref(false);
const size = (b: number) => (b < 1024 * 1024 ? `${Math.ceil(b / 1024)} KB` : `${(b / 1024 / 1024).toFixed(1)} MB`);

const firstError = computed(() => Object.entries(form.errors).find(([k]) => k.startsWith('attachments') || k.startsWith('cc'))?.[1]);

const submit = () => {
    form.post(storeMessage.url({ ticket: props.ticketId }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
};
</script>

<template>
    <AppDrawer
        v-model:open="open"
        title="Reply"
        subtitle="The other side gets your message by email, with anyone you copy."
        width="560px"
    >
        <!-- Status -->
        <fieldset class="mb-7">
            <legend class="form-label">Move the request to</legend>
            <div class="status-pills">
                <label :class="['status-pill', { picked: form.request_status_id === null }]">
                    <input
                        v-model="form.request_status_id"
                        type="radio"
                        :value="null"
                    >
                    <span
                        class="dot"
                        style="background: #c4cdd5"
                    />Keep current status
                </label>
                <label
                    v-for="s in statuses"
                    :key="s.id"
                    :class="['status-pill', { picked: form.request_status_id === s.id, current: s.id === currentStatusId }]"
                    :style="{ '--pill': s.color }"
                >
                    <input
                        v-model="form.request_status_id"
                        type="radio"
                        :value="s.id"
                        :disabled="s.id === currentStatusId"
                    >
                    <span class="dot" />{{ s.name }}
                    <small v-if="s.id === currentStatusId">current</small>
                </label>
            </div>
            <div
                v-if="form.errors.request_status_id"
                class="text-danger fs-7 mt-2"
            >{{ form.errors.request_status_id }}</div>
        </fieldset>

        <!-- CC -->
        <div class="mb-7">
            <label
                class="form-label"
                for="reply-cc"
            >Copy (CC)</label>
            <div class="cc-box">
                <span
                    v-for="p in ccPicked"
                    :key="p.id"
                    class="cc-chip"
                >{{ p.english_name }}<button
                    type="button"
                    :aria-label="`Remove ${p.english_name}`"
                    @click="form.cc = form.cc.filter(id => id !== p.id)"
                ><i class="ki-outline ki-cross" /></button></span>
                <input
                    id="reply-cc"
                    v-model="ccSearch"
                    type="text"
                    autocomplete="off"
                    :placeholder="ccPicked.length ? 'Add another…' : 'Search people with an email…'"
                    @keydown.enter.prevent="ccMatches[0] && addCc(ccMatches[0])"
                >
            </div>
            <ul
                v-if="ccMatches.length"
                class="cc-menu"
            >
                <li
                    v-for="p in ccMatches"
                    :key="p.id"
                >
                    <button
                        type="button"
                        @click="addCc(p)"
                    ><span class="fw-semibold">{{ p.english_name }}</span><span class="text-muted fs-8">{{ p.email }}</span></button>
                </li>
            </ul>
        </div>

        <!-- Message -->
        <div class="mb-7">
            <label
                class="form-label required"
                for="reply-body"
            >Message</label>
            <textarea
                id="reply-body"
                v-model="form.body"
                class="form-control"
                :class="{ 'is-invalid': form.errors.body }"
                rows="7"
                placeholder="Write your reply…"
            />
            <div class="invalid-feedback">{{ form.errors.body }}</div>
        </div>

        <!-- Files -->
        <div>
            <span class="form-label d-block">Files</span>
            <button
                type="button"
                :class="['drop', { dragging }]"
                @click="fileInput?.click()"
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="dragging = false; addFiles($event.dataTransfer?.files)"
            >
                <i class="ki-outline ki-paper-clip fs-2" />
                <span>Drop files here or <u>browse</u></span>
                <small>Up to 10 files, 10 MB each</small>
            </button>
            <input
                ref="fileInput"
                type="file"
                multiple
                class="d-none"
                @change="addFiles(($event.target as HTMLInputElement).files); ($event.target as HTMLInputElement).value = ''"
            >
            <ul
                v-if="form.attachments.length"
                class="file-list"
            >
                <li
                    v-for="(f, i) in form.attachments"
                    :key="`${f.name}-${i}`"
                >
                    <i class="ki-outline ki-document" />
                    <span class="flex-grow-1 text-truncate">{{ f.name }}</span>
                    <span class="text-muted fs-8">{{ size(f.size) }}</span>
                    <button
                        type="button"
                        :aria-label="`Remove ${f.name}`"
                        @click="form.attachments = form.attachments.filter((_, j) => j !== i)"
                    ><i class="ki-outline ki-cross" /></button>
                </li>
            </ul>
            <div
                v-if="firstError"
                class="text-danger fs-7 mt-2"
            >{{ firstError }}</div>
        </div>

        <template #footer>
            <button
                type="button"
                class="btn btn-light"
                @click="open = false"
            >Cancel</button>
            <button
                type="button"
                class="btn btn-primary"
                :disabled="form.processing || !form.body.trim()"
                @click="submit"
            >
                <span
                    v-if="form.processing"
                    class="spinner-border spinner-border-sm me-2"
                />
                <i
                    v-else
                    class="ki-outline ki-send me-1"
                />Send message
            </button>
        </template>
    </AppDrawer>
</template>

<style scoped>
fieldset { border: 0; margin: 0; padding: 0; }
.status-pills { display: flex; flex-wrap: wrap; gap: 8px; }
.status-pill { --pill: #7e8299; position: relative; display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border: 1px solid #dde6eb; border-radius: 999px; color: #3f5668; font-size: 13px; font-weight: 600; cursor: pointer; transition: border-color .15s, background .15s; }
.status-pill input { position: absolute; opacity: 0; pointer-events: none; }
.status-pill .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--pill); }
.status-pill small { color: #94a3ae; font-weight: 500; }
.status-pill:hover { border-color: #b8c8d1; }
.status-pill.picked { border-color: var(--pill); background: color-mix(in srgb, var(--pill) 10%, #fff); color: #1d3a4f; box-shadow: 0 0 0 1px var(--pill); }
.status-pill.current { opacity: .6; cursor: default; }
.status-pill:has(input:focus-visible) { outline: 2px solid #2aa2a9; outline-offset: 2px; }

.cc-box { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; min-height: 44px; padding: 6px 8px; border: 1px solid #dbe4ea; border-radius: 8px; }
.cc-box:focus-within { border-color: #2aa2a9; box-shadow: 0 0 0 3px rgba(42, 162, 169, .15); }
.cc-box input { flex: 1; min-width: 140px; border: 0; outline: 0; font-size: 14px; }
.cc-chip { display: inline-flex; align-items: center; gap: 4px; padding: 3px 4px 3px 10px; border-radius: 6px; background: #e2f2f2; color: #135e64; font-size: 12px; font-weight: 600; }
.cc-chip button { display: grid; place-items: center; width: 18px; height: 18px; border: 0; border-radius: 4px; background: none; color: inherit; cursor: pointer; }
.cc-chip button:hover { background: rgba(19, 94, 100, .12); }
.cc-menu { margin: 4px 0 0; padding: 4px; list-style: none; border: 1px solid #e5edf1; border-radius: 8px; box-shadow: 0 10px 24px rgba(28, 65, 89, .1); }
.cc-menu button { display: flex; flex-direction: column; width: 100%; padding: 7px 10px; border: 0; border-radius: 6px; background: none; text-align: start; cursor: pointer; }
.cc-menu button:hover, .cc-menu button:focus-visible { background: #f2f8f9; outline: 0; }

.drop { display: flex; flex-direction: column; align-items: center; gap: 4px; width: 100%; padding: 20px; border: 1.5px dashed #c9d7df; border-radius: 10px; background: #fafcfd; color: #4b6576; font-size: 14px; cursor: pointer; }
.drop small { color: #94a3ae; }
.drop:hover, .drop.dragging { border-color: #2aa2a9; background: #f0f9f9; }
.drop:focus-visible { outline: 2px solid #2aa2a9; outline-offset: 2px; }
.file-list { margin: 10px 0 0; padding: 0; list-style: none; }
.file-list li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid #edf1f3; border-radius: 8px; font-size: 13px; }
.file-list li + li { margin-top: 6px; }
.file-list button { border: 0; background: none; color: #94a3ae; cursor: pointer; }
.file-list button:hover { color: #d9534f; }
</style>
