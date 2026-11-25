<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ApprovalSteps from '@/components/ticketing/ApprovalSteps.vue';
import type { ApprovalRoleOption } from '@/components/ticketing/ApprovalSteps.vue';
import TranslationInputs from '@/components/TranslationInputs.vue';
import { FIELD_TYPES, FORMULAS, STANDARD_KEYS, isRtl, labelIn, languageName } from '@/lib/ticketFields';
import type { Translations, TicketField } from '@/lib/ticketFields';
import { index, update } from '@/routes/ticketing/admin/service-catalog';
import { destroy as destroyItem, store as storeItem } from '@/routes/ticketing/admin/service-catalog/items';

type Config = { group_id: number | null; technician_id: number | null; sla_id: number | null; form_fields: unknown[] | null };
type Item = Config & { id: number; name: string; is_active: boolean };
type Subcategory = Config & { id: number; name: string; is_active: boolean; items: Item[] };
type Category = Config & { id: number; name: string; is_active: boolean; ticket_type: string | null; subcategories: Subcategory[] };
type Group = { id: number; name: string; members: { id: number; name: string }[] };
type Sla = { id: number; name: string; first_response_hours: number; resolve_hours: number };

type Selected = {
    level: 'category' | 'subcategory' | 'item';
    id: number;
    name: string;
    path: string[];
    ticket_type: string | null;
    group_id: number | null;
    technician_id: number | null;
    sla_id: number | null;
    has_own_form: boolean;
    inherited: { group_id: number | null; technician_id: number | null; sla_id: number | null; approval_steps: string[] | null };
    fields: TicketField[];
    inherited_fields: TicketField[];
    approval_steps: string[] | null;
};

const props = defineProps<{
    categories: Category[];
    selected: Selected | null;
    groups: Group[];
    slas: Sla[];
    priorities: { id: number; name: string }[];
    approvalRoles: ApprovalRoleOption[];
}>();

/** Content languages shared by HandleInertiaRequests; the first is the default `label`. */
const locales = computed(() => (usePage().props.contentLocales as string[] | undefined) ?? ['en']);

// ---------- languages ----------
const defaultLocale = computed(() => locales.value[0] ?? 'en');
const extraLocales = computed(() => locales.value.slice(1));
const canvasLocale = ref(locales.value[0] ?? 'en');
const tr = (x: { translations?: Translations }) => (x.translations ??= {});

const nodeKey = (level: string, id: number) => `${level}:${id}`;
const isSelected = (level: string, id: number) => props.selected && nodeKey(props.selected.level, props.selected.id) === nodeKey(level, id);
const configured = (n: Config) => n.group_id || n.sla_id || n.form_fields;

// ---------- business rule + designer state ----------
const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v));

const form = useForm({
    group_id: '' as number | '',
    technician_id: '' as number | '',
    sla_id: '' as number | '',
    form_fields: null as TicketField[] | null,
    approval_steps: null as string[] | null,
});
const ownForm = ref(false);
const fields = ref<TicketField[]>([]);
const editing = ref<TicketField | null>(null);

watch(
    () => props.selected,
    s => {
        if (!s) {
return;
}

        form.defaults({ group_id: s.group_id ?? '', technician_id: s.technician_id ?? '', sla_id: s.sla_id ?? '', form_fields: null, approval_steps: s.approval_steps });
        form.reset();
        form.clearErrors();
        ownForm.value = s.has_own_form;
        fields.value = clone(s.fields);
        editing.value = null;
    },
    { immediate: true },
);

const groupName = (id: number | null) => props.groups.find(g => g.id === id)?.name;
const slaName = (id: number | null) => props.slas.find(s => s.id === id)?.name;
const members = computed(() => props.groups.find(g => g.id === Number(form.group_id))?.members ?? []);
const technicianName = (id: number | null) =>
    props.groups.flatMap(g => g.members).find(m => m.id === id)?.name;

const customize = () => {
    ownForm.value = true;
};
const inherit = () => {
    if (!props.selected) {
return;
}

    ownForm.value = false;
    fields.value = clone(props.selected.inherited_fields);
    editing.value = null;
};

const move = (i: number, dir: -1 | 1) => {
    const j = i + dir;

    if (j < 0 || j >= fields.value.length) {
return;
}

    [fields.value[i], fields.value[j]] = [fields.value[j], fields.value[i]];
};

const addField = (type = 'text', at = fields.value.length) => {
    let n = fields.value.length + 1;

    while (fields.value.some(f => f.key === `field_${n}`)) {
n++;
}

    const field: TicketField = { key: `field_${n}`, label: FIELD_TYPES.find(t => t.value === type)?.label ?? 'New field', type, required: false, width: ['textarea', 'attachments'].includes(type) ? 'full' : 'half' };
    onTypeChange(field);
    fields.value.splice(at, 0, field);
    editing.value = field;
};

type DraggedField = { kind: 'new'; type: string } | { kind: 'existing'; index: number };
const dragging = ref<DraggedField | null>(null);
const dropIndex = ref<number | null>(null);
const paletteTypes = FIELD_TYPES.filter(t => !['priority', 'attachments'].includes(t.value));

const startNewFieldDrag = (event: DragEvent, type: string) => {
    dragging.value = { kind: 'new', type };

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('text/plain', type);
    }
};

const startFieldDrag = (event: DragEvent, index: number) => {
    dragging.value = { kind: 'existing', index };

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    }
};

const dragOverField = (event: DragEvent, index: number) => {
    if (!ownForm.value || !dragging.value) {
return;
}

    event.preventDefault();
    const card = event.currentTarget as HTMLElement;
    const bounds = card.getBoundingClientRect();
    const isFull = fields.value[index]?.width === 'full';
    const pastMiddleX = event.clientX > bounds.left + bounds.width / 2;
    // In an RTL canvas the "after" side of a half-width field is the left one.
    const after = isFull ? event.clientY > bounds.top + bounds.height / 2 : pastMiddleX !== isRtl(canvasLocale.value);
    dropIndex.value = index + (after ? 1 : 0);

    if (event.dataTransfer) {
event.dataTransfer.dropEffect = dragging.value.kind === 'new' ? 'copy' : 'move';
}
};

const endDrag = () => {
    dragging.value = null;
    dropIndex.value = null;
};

const dropField = (event: DragEvent, index = dropIndex.value ?? fields.value.length) => {
    event.preventDefault();

    if (!ownForm.value || !dragging.value) {
return;
}

    if (dragging.value.kind === 'new') {
        addField(dragging.value.type, index);
    } else {
        const from = dragging.value.index;
        const [field] = fields.value.splice(from, 1);
        fields.value.splice(from < index ? index - 1 : index, 0, field);
    }

    endDrag();
};

const fieldPlaceholder = (f: TicketField) => {
    if (f.type === 'select' || f.type === 'priority') {
return 'Select…';
}

    if (f.type === 'computed') {
return 'Calculated automatically';
}

    if (f.type === 'attachments') {
return 'Choose files';
}

    return `Enter ${f.label.toLowerCase()}…`;
};

const standardDefaults: Record<string, TicketField> = {
    description: { key: 'description', label: 'Description', translations: { ar: 'الوصف' }, type: 'textarea', required: false, width: 'full' },
    priority_id: { key: 'priority_id', label: 'Priority', translations: { ar: 'الأولوية' }, type: 'priority', required: false, width: 'half' },
    attachments: { key: 'attachments', label: 'Attachments', translations: { ar: 'المرفقات' }, type: 'attachments', required: false, width: 'full' },
};
const missingStandard = computed(() => Object.values(standardDefaults).filter(d => !fields.value.some(f => f.key === d.key)));

const removeField = (i: number) => fields.value.splice(i, 1);
const addOption = (f: TicketField) => (f.options ??= []).push({ value: '', label: '' });

const onTypeChange = (f: TicketField) => {
    if (f.type === 'select' && !f.options?.length) {
f.options = [{ value: '', label: '' }];
}

    if (f.type === 'computed') {
        f.formula ??= 'days_between';
        f.args ??= ['', ''];
        f.required = false;
    }
};

const argCandidates = (f: TicketField) => {
    const want = FORMULAS.find(x => x.value === f.formula)?.argType;

    return fields.value.filter(x => x.type === want);
};

const typeLabel = (t: string) => FIELD_TYPES.find(x => x.value === t)?.label ?? t;
// Reserved keys keep the column they map to; their type cannot change.
const typeLocked = (f: TicketField) => f.locked || STANDARD_KEYS.includes(f.key);

const previewFields = computed(() => (ownForm.value ? fields.value : props.selected?.inherited_fields ?? []));

const save = () => {
    if (!props.selected) {
return;
}

    form.form_fields = ownForm.value ? fields.value : null; // server drops the "locked" flag
    form.put(update.url({ level: props.selected.level, id: props.selected.id }), { preserveScroll: true });
};

const fieldError = (i: number) =>
    Object.entries(form.errors)
        .filter(([k]) => k.startsWith(`form_fields.${i}.`))
        .map(([, v]) => v)
        .join(' ');

// ---------- items ----------
const itemForm = useForm({ name: '', name_translations: {} as Record<string, string> });
const addItem = () => {
    if (!props.selected) {
return;
}

    itemForm.post(storeItem.url({ subcategory: props.selected.id }), { onSuccess: () => itemForm.reset() });
};
const removeItem = () => {
    if (!props.selected || !window.confirm(`Delete item "${props.selected.name}"?`)) {
return;
}

    router.delete(destroyItem.url({ item: props.selected.id }));
};
</script>

<template>
    <div>
        <Head title="Service Catalog" />
        <div class="d-flex align-items-center mb-6 gap-3">
            <h1 class="fw-bold mb-0">Service Catalog</h1>
            <span class="text-muted">Routing, SLA and request form for every service level</span>
        </div>

        <div class="row g-6">
            <!-- Hierarchy -->
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-body p-5">
                        <div
                            v-if="!categories.length"
                            class="text-muted"
                        >No categories yet. Create them under Ticketing → Categories.</div>
                        <div
                            v-for="c in categories"
                            :key="c.id"
                            class="mb-3"
                        >
                            <Link
                                :href="index.url({ query: { node: nodeKey('category', c.id) } })"
                                preserve-scroll
                                class="d-flex align-items-center rounded px-3 py-2 fw-bold"
                                :class="isSelected('category', c.id) ? 'bg-light-primary text-primary' : 'text-gray-800 bg-hover-light'"
                            >
                                <i class="ki-outline ki-folder fs-4 me-2" />
                                <span class="flex-grow-1">{{ c.name }}</span>
                                <i
                                    v-if="configured(c)"
                                    class="ki-outline ki-setting-2 fs-6 text-success"
                                    title="Has own settings"
                                />
                            </Link>
                            <div
                                v-for="s in c.subcategories"
                                :key="s.id"
                                class="ms-5"
                            >
                                <Link
                                    :href="index.url({ query: { node: nodeKey('subcategory', s.id) } })"
                                    preserve-scroll
                                    class="d-flex align-items-center rounded px-3 py-2"
                                    :class="isSelected('subcategory', s.id) ? 'bg-light-primary text-primary' : 'text-gray-700 bg-hover-light'"
                                >
                                    <span class="flex-grow-1">{{ s.name }}</span>
                                    <i
                                        v-if="configured(s)"
                                        class="ki-outline ki-setting-2 fs-6 text-success"
                                    />
                                </Link>
                                <Link
                                    v-for="it in s.items"
                                    :key="it.id"
                                    :href="index.url({ query: { node: nodeKey('item', it.id) } })"
                                    preserve-scroll
                                    class="d-flex align-items-center rounded ms-5 px-3 py-1 fs-7"
                                    :class="isSelected('item', it.id) ? 'bg-light-primary text-primary' : 'text-gray-600 bg-hover-light'"
                                >
                                    <i class="ki-outline ki-right fs-8 me-1" />
                                    <span class="flex-grow-1">{{ it.name }}</span>
                                    <i
                                        v-if="configured(it)"
                                        class="ki-outline ki-setting-2 fs-7 text-success"
                                    />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Selected node -->
            <div class="col-xl-9">
                <div
                    v-if="!selected"
                    class="card"
                >
                    <div class="card-body text-center text-muted py-20">
                        Pick a category, subcategory or item to configure it.<br />
                        Settings on a lower level override the level above; empty means “inherit”.
                    </div>
                </div>

                <template v-else>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-5">
                        <template
                            v-for="(p, i) in selected.path"
                            :key="i"
                        >
                            <i
                                v-if="i"
                                class="ki-outline ki-right fs-6 text-muted"
                            />
                            <span :class="i === selected.path.length - 1 ? 'fw-bold fs-3' : 'text-gray-600'">{{ p }}</span>
                        </template>
                        <span class="badge badge-light-info text-capitalize ms-2">{{ selected.level }}</span>
                        <span
                            v-if="selected.ticket_type"
                            class="badge badge-light-warning"
                        >{{ selected.ticket_type }}</span>
                        <button
                            v-if="selected.level === 'item'"
                            type="button"
                            class="btn btn-sm btn-light-danger ms-auto"
                            @click="removeItem"
                        >Delete item</button>
                    </div>

                    <!-- Business rule -->
                    <div class="card mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold">Assignment &amp; SLA</h3>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-5">
                                <div class="col-md-4">
                                    <label class="form-label">Technician group</label>
                                    <select
                                        v-model="form.group_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.group_id }"
                                        @change="form.technician_id = ''"
                                    >
                                        <option value="">{{ selected.inherited.group_id ? `Inherit — ${groupName(selected.inherited.group_id)}` : 'Not set' }}</option>
                                        <option
                                            v-for="g in groups"
                                            :key="g.id"
                                            :value="g.id"
                                        >{{ g.name }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ form.errors.group_id }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Technician</label>
                                    <select
                                        v-model="form.technician_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.technician_id }"
                                        :disabled="!form.group_id"
                                    >
                                        <option value="">
                                            {{ form.group_id ? 'Auto — least busy member' : selected.inherited.technician_id ? `Inherit — ${technicianName(selected.inherited.technician_id)}` : 'Auto — least busy member' }}
                                        </option>
                                        <option
                                            v-for="m in members"
                                            :key="m.id"
                                            :value="m.id"
                                        >{{ m.name }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ form.errors.technician_id }}</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SLA</label>
                                    <select
                                        v-model="form.sla_id"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.sla_id }"
                                    >
                                        <option value="">{{ selected.inherited.sla_id ? `Inherit — ${slaName(selected.inherited.sla_id)}` : 'No SLA' }}</option>
                                        <option
                                            v-for="s in slas"
                                            :key="s.id"
                                            :value="s.id"
                                        >{{ s.name }} ({{ s.resolve_hours }}h)</option>
                                    </select>
                                </div>
                            </div>
                            <div
                                v-if="!groups.length"
                                class="notice bg-light-warning rounded p-4 mt-5 fs-7"
                            >No active technician groups. Create one under Ticketing → Groups and add members.</div>
                        </div>
                    </div>

                    <ApprovalSteps
                        v-model="form.approval_steps"
                        :roles="approvalRoles"
                        :inherited="selected.inherited.approval_steps"
                        :top-level="selected.level === 'category'"
                        :errors="form.errors"
                    />

                    <!-- Items (subcategory level) -->
                    <div
                        v-if="selected.level === 'subcategory'"
                        class="card mb-6"
                    >
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold">Items <span class="text-muted fs-7 fw-normal ms-2">optional third level</span></h3>
                        </div>
                        <div class="card-body pt-0">
                            <form
                                class="d-flex gap-3"
                                @submit.prevent="addItem"
                            >
                                <input
                                    v-model="itemForm.name"
                                    class="form-control"
                                    :class="{ 'is-invalid': itemForm.errors.name }"
                                    placeholder="Item name"
                                />
                                <TranslationInputs
                                    v-model="itemForm.name_translations"
                                    label="Item name"
                                    id-prefix="item-name"
                                    inline
                                />
                                <button
                                    type="submit"
                                    class="btn btn-light-primary text-nowrap"
                                    :disabled="itemForm.processing"
                                >Add item</button>
                            </form>
                        </div>
                    </div>

                    <!-- Form designer -->
                    <div class="card mb-6">
                        <div class="card-header border-0 pt-5">
                            <h3 class="card-title fw-bold">Request form</h3>
                            <div class="card-toolbar gap-2">
                                <template v-if="ownForm">
                                    <span class="badge badge-light-success">Custom form for this level</span>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light"
                                        @click="inherit"
                                    >Use inherited form</button>
                                </template>
                                <template v-else>
                                    <span class="badge badge-light">Inherited</span>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        @click="customize"
                                    >Customize</button>
                                </template>
                            </div>
                        </div>
                        <div class="card-body pt-0">
                            <div class="row g-6">
                                <!-- Field list -->
                                <div class="col-12">
                                    <div v-if="ownForm" class="mb-5 rounded border bg-light p-4">
                                        <div class="fw-bold mb-1">Add a field</div>
                                        <div class="text-muted fs-7 mb-3">Drag a field into the form, or click to add it at the end.</div>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button
                                                v-for="type in paletteTypes"
                                                :key="type.value"
                                                type="button"
                                                draggable="true"
                                                class="btn btn-sm btn-light-primary"
                                                @dragstart="startNewFieldDrag($event, type.value)"
                                                @dragend="endDrag"
                                                @click="addField(type.value)"
                                            ><i class="ki-outline ki-plus fs-6 me-1" />{{ type.label }}</button>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap gap-3 mb-3">
                                        <div class="text-gray-500 fs-7 fw-bold text-uppercase">Form canvas · drag fields to arrange them</div>
                                        <div
                                            v-if="locales.length > 1"
                                            class="btn-group btn-group-sm ms-auto"
                                            role="group"
                                            aria-label="Preview language"
                                        >
                                            <button
                                                v-for="loc in locales"
                                                :key="loc"
                                                type="button"
                                                class="btn"
                                                :class="canvasLocale === loc ? 'btn-primary' : 'btn-light'"
                                                :aria-pressed="canvasLocale === loc"
                                                @click="canvasLocale = loc"
                                            >{{ languageName(loc) }}</button>
                                        </div>
                                    </div>
                                    <div
                                        class="designer-canvas"
                                        :dir="isRtl(canvasLocale) ? 'rtl' : 'ltr'"
                                        @dragover.prevent="dropIndex = fields.length"
                                        @drop="dropField($event)"
                                    >
                                    <div v-if="!previewFields.length" class="designer-empty">Drop fields here to build the request form</div>
                                    <template v-for="(f, i) in previewFields" :key="f.key">
                                    <div
                                        class="designer-field"
                                        :class="{ 'border-danger': fieldError(i), 'designer-field-full': f.width === 'full' || editing === f, 'designer-field-before': dropIndex === i && dragging, 'designer-field-after': dropIndex === i + 1 && dragging }"
                                        @dragover.stop="dragOverField($event, i)"
                                        @drop.stop="dropField($event)"
                                    >
                                        <div class="d-flex align-items-center gap-2 p-3">
                                            <button
                                                v-if="ownForm"
                                                type="button"
                                                class="btn btn-icon btn-sm btn-light cursor-grab"
                                                draggable="true"
                                                aria-label="Drag to reorder field"
                                                title="Drag to reorder"
                                                @dragstart="startFieldDrag($event, i)"
                                                @dragend="endDrag"
                                            ><i class="ki-outline ki-abstract-14 fs-5" /></button>
                                            <div
                                                v-if="ownForm"
                                                class="d-flex flex-column"
                                            >
                                                <button
                                                    type="button"
                                                    class="btn btn-icon btn-xs btn-light h-20px"
                                                    :disabled="i === 0"
                                                    aria-label="Move up"
                                                    @click="move(i, -1)"
                                                ><i class="ki-outline ki-up fs-7" /></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-icon btn-xs btn-light h-20px mt-1"
                                                    :disabled="i === previewFields.length - 1"
                                                    aria-label="Move down"
                                                    @click="move(i, 1)"
                                                ><i class="ki-outline ki-down fs-7" /></button>
                                            </div>
                                            <div class="flex-grow-1 text-muted fs-8">{{ typeLabel(f.type) }} · {{ f.width === 'full' ? 'Full width' : 'Half width' }}</div>
                                            <template v-if="ownForm">
                                                <button
                                                    type="button"
                                                    class="btn btn-icon btn-sm btn-light"
                                                    aria-label="Edit field"
                                                    @click="editing = editing === f ? null : f"
                                                ><i class="ki-outline ki-pencil fs-5" /></button>
                                                <button
                                                    type="button"
                                                    class="btn btn-icon btn-sm btn-light-danger"
                                                    :disabled="f.locked"
                                                    aria-label="Remove field"
                                                    @click="removeField(i)"
                                                ><i class="ki-outline ki-trash fs-5" /></button>
                                            </template>
                                        </div>

                                        <div class="px-4 pb-4">
                                            <label class="form-label fw-semibold" :class="{ required: f.required }">{{ labelIn(f, canvasLocale) }}</label>
                                            <div v-if="f.type === 'checkbox'" class="form-check"><input class="form-check-input" type="checkbox" disabled /><span class="form-check-label">Yes</span></div>
                                            <textarea v-else-if="f.type === 'textarea'" class="form-control" rows="2" :placeholder="fieldPlaceholder(f)" disabled />
                                            <select v-else-if="f.type === 'select' || f.type === 'priority'" class="form-select" disabled>
                                                <option>{{ fieldPlaceholder(f) }}</option>
                                                <option v-for="o in f.options ?? []" :key="o.value">{{ labelIn(o, canvasLocale) }}</option>
                                            </select>
                                            <input v-else class="form-control" :type="['number', 'date', 'time'].includes(f.type) ? f.type : 'text'" :placeholder="fieldPlaceholder(f)" disabled />
                                        </div>

                                        <!-- Field editor -->
                                        <div
                                            v-if="ownForm && editing === f"
                                            class="border-top bg-light p-4"
                                            dir="ltr"
                                        >
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Label · {{ languageName(defaultLocale) }}</label>
                                                    <input
                                                        v-model="f.label"
                                                        class="form-control form-control-sm"
                                                        :dir="isRtl(defaultLocale) ? 'rtl' : 'ltr'"
                                                    />
                                                </div>
                                                <div
                                                    v-for="loc in extraLocales"
                                                    :key="loc"
                                                    class="col-md-6"
                                                >
                                                    <label class="form-label fs-7">Label · {{ languageName(loc) }}</label>
                                                    <input
                                                        v-model="tr(f)[loc]"
                                                        class="form-control form-control-sm"
                                                        :dir="isRtl(loc) ? 'rtl' : 'ltr'"
                                                        :placeholder="f.label"
                                                    />
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label fs-7">Key</label>
                                                    <input
                                                        v-model="f.key"
                                                        class="form-control form-control-sm"
                                                        :disabled="typeLocked(f)"
                                                    />
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fs-7">Type</label>
                                                    <select
                                                        v-model="f.type"
                                                        class="form-select form-select-sm"
                                                        :disabled="typeLocked(f)"
                                                        @change="onTypeChange(f)"
                                                    >
                                                        <option
                                                            v-for="t in FIELD_TYPES"
                                                            :key="t.value"
                                                            :value="t.value"
                                                        >{{ t.label }}</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label fs-7">Width</label>
                                                    <select
                                                        v-model="f.width"
                                                        class="form-select form-select-sm"
                                                    >
                                                        <option value="half">Half</option>
                                                        <option value="full">Full</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    <div class="form-check form-switch mb-1">
                                                        <input
                                                            :id="`req-${f.key}`"
                                                            v-model="f.required"
                                                            class="form-check-input"
                                                            type="checkbox"
                                                            :disabled="f.locked || f.type === 'computed'"
                                                        />
                                                        <label
                                                            class="form-check-label fs-7"
                                                            :for="`req-${f.key}`"
                                                        >Required</label>
                                                    </div>
                                                </div>

                                                <!-- Dropdown options -->
                                                <div
                                                    v-if="f.type === 'select' && !f.locked"
                                                    class="col-12"
                                                >
                                                    <label class="form-label fs-7">Options</label>
                                                    <div
                                                        v-for="(o, oi) in f.options ?? []"
                                                        :key="oi"
                                                        class="d-flex gap-2 mb-2"
                                                    >
                                                        <input
                                                            v-model="o.value"
                                                            class="form-control form-control-sm"
                                                            placeholder="value"
                                                        />
                                                        <input
                                                            v-model="o.label"
                                                            class="form-control form-control-sm"
                                                            :placeholder="`Label · ${languageName(defaultLocale)}`"
                                                        />
                                                        <input
                                                            v-for="loc in extraLocales"
                                                            :key="loc"
                                                            v-model="tr(o)[loc]"
                                                            class="form-control form-control-sm"
                                                            :dir="isRtl(loc) ? 'rtl' : 'ltr'"
                                                            :placeholder="languageName(loc)"
                                                        />
                                                        <button
                                                            type="button"
                                                            class="btn btn-icon btn-sm btn-light-danger"
                                                            aria-label="Remove option"
                                                            @click="f.options!.splice(oi, 1)"
                                                        ><i class="ki-outline ki-cross fs-5" /></button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-light-primary"
                                                        @click="addOption(f)"
                                                    >Add option</button>
                                                </div>

                                                <!-- Formula -->
                                                <template v-if="f.type === 'computed' && !f.locked">
                                                    <div class="col-12">
                                                        <label class="form-label fs-7">Calculation</label>
                                                        <select
                                                            v-model="f.formula"
                                                            class="form-select form-select-sm"
                                                        >
                                                            <option
                                                                v-for="fm in FORMULAS"
                                                                :key="fm.value"
                                                                :value="fm.value"
                                                            >{{ fm.label }}</option>
                                                        </select>
                                                    </div>
                                                    <div
                                                        v-for="(label, ai) in ['From', 'To']"
                                                        :key="ai"
                                                        class="col-md-6"
                                                    >
                                                        <label class="form-label fs-7">{{ label }}</label>
                                                        <select
                                                            v-model="f.args![ai]"
                                                            class="form-select form-select-sm"
                                                        >
                                                            <option value="">Select field…</option>
                                                            <option
                                                                v-for="c in argCandidates(f)"
                                                                :key="c.key"
                                                                :value="c.key"
                                                            >{{ c.label }}</option>
                                                        </select>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                        <div
                                            v-if="fieldError(i)"
                                            class="text-danger fs-8 px-3 pb-2"
                                        >{{ fieldError(i) }}</div>
                                    </div>
                                    </template>
                                    </div>

                                    <div
                                        v-if="ownForm"
                                        class="d-flex flex-wrap gap-2 mt-3"
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-primary"
                                            @click="addField()"
                                        ><i class="ki-outline ki-plus fs-5" /> Add field</button>
                                        <button
                                            v-for="d in missingStandard"
                                            :key="d.key"
                                            type="button"
                                            class="btn btn-sm btn-light"
                                            @click="fields.push({ ...d })"
                                        ><i class="ki-outline ki-plus fs-5" /> {{ d.label }}</button>
                                    </div>
                                    <div
                                        v-else
                                        class="text-muted fs-7 mt-3"
                                    >This level uses the form from the level above. Click “Customize” to design its own.</div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button
                            type="button"
                            class="btn btn-primary"
                            :disabled="form.processing"
                            @click="save"
                        >
                            <span
                                v-if="form.processing"
                                class="spinner-border spinner-border-sm me-2"
                            />
                            Save settings
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>
.designer-canvas {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    min-height: 180px;
    padding: 20px;
    border: 1px dashed var(--bs-gray-400);
    border-radius: 8px;
    background: var(--bs-gray-100);
}

.designer-empty,
.designer-field-full {
    grid-column: 1 / -1;
}

.designer-empty {
    display: grid;
    place-items: center;
    color: var(--bs-gray-600);
}

.designer-field {
    min-width: 0;
    border: 1px solid var(--bs-gray-300);
    border-radius: 8px;
    background: var(--bs-body-bg);
}

.designer-field-before {
    box-shadow: -5px 0 0 var(--bs-primary);
}

.designer-field-after {
    box-shadow: 5px 0 0 var(--bs-primary);
}

@media (max-width: 767px) {
    .designer-canvas {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
