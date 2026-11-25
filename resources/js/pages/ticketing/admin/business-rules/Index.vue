<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppDrawer from '@/components/AppDrawer.vue';
import MultiPicker from '@/components/MultiPicker.vue';
import type { PickerOption } from '@/components/MultiPicker.vue';
import { destroy, reorder, store, update } from '@/routes/ticketing/admin/business-rules';

type Field = 'department' | 'employee' | 'location' | 'category' | 'subcategory' | 'item' | 'priority' | 'source';
type Condition = { field: Field; operator: 'in' | 'not_in'; values: (number | string)[] };
type Rule = {
    id: number;
    name: string;
    is_active: boolean;
    match: 'all' | 'any';
    conditions: Condition[];
    group_id: number;
    technician_id: number | null;
    group: { id: number; name: string } | null;
    technician: { id: number; name: string } | null;
};
type Group = { id: number; name: string; is_active: boolean; members: { id: number; name: string }[] };

const props = defineProps<{ rules: Rule[]; options: Record<Field, PickerOption[]>; groups: Group[] }>();

const FIELDS: { value: Field; label: string; hint: string }[] = [
    { value: 'department', label: 'Department', hint: "The requester's department" },
    { value: 'employee', label: 'Employee', hint: 'Specific requesters' },
    { value: 'location', label: 'Location', hint: "The requester's work location" },
    { value: 'category', label: 'Category', hint: 'Service category' },
    { value: 'subcategory', label: 'Subcategory', hint: 'Service subcategory' },
    { value: 'item', label: 'Item', hint: 'Service item' },
    { value: 'priority', label: 'Priority', hint: 'Request priority' },
    { value: 'source', label: 'Source', hint: 'Where the request came from' },
];
const fieldLabel = (f: Field) => FIELDS.find(x => x.value === f)?.label ?? f;
const valueNames = (c: Condition) => c.values.map(v => props.options[c.field]?.find(o => String(o.id) === String(v))?.name ?? `#${v}`);

/** "Department is IT or Finance" — how a condition reads in the list. */
const sentence = (c: Condition) => {
    const names = valueNames(c);
    const list = names.length > 3 ? `${names.slice(0, 3).join(', ')} +${names.length - 3}` : names.join(' or ');

    return { field: fieldLabel(c.field), op: c.operator === 'in' ? 'is' : 'is not', list };
};

// ---------- editor ----------
const drawer = ref(false);
const editingId = ref<number | null>(null);
const form = useForm({
    name: '',
    is_active: true,
    match: 'all' as 'all' | 'any',
    conditions: [] as Condition[],
    group_id: '' as number | '',
    technician_id: null as number | null,
});

const newCondition = (): Condition => ({ field: 'department', operator: 'in', values: [] });

const openEditor = (rule?: Rule) => {
    form.clearErrors();
    editingId.value = rule?.id ?? null;
    form.name = rule?.name ?? '';
    form.is_active = rule?.is_active ?? true;
    form.match = rule?.match ?? 'all';
    form.conditions = rule ? rule.conditions.map(c => ({ ...c, values: [...c.values] })) : [newCondition()];
    form.group_id = rule?.group_id ?? '';
    form.technician_id = rule?.technician_id ?? null;
    drawer.value = true;
};

const members = computed(() => props.groups.find(g => g.id === form.group_id)?.members ?? []);
const onGroupChange = () => {
    if (!members.value.some(m => m.id === form.technician_id)) {
        form.technician_id = null;
    }
};

const conditionError = (i: number) => Object.entries(form.errors).find(([k]) => k.startsWith(`conditions.${i}.`))?.[1];

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (drawer.value = false) };

    if (editingId.value) {
        form.put(update.url({ business_rule: editingId.value }), options);
    } else {
        form.post(store.url(), options);
    }
};

// ---------- list actions ----------
const toggle = (rule: Rule) => router.put(update.url({ business_rule: rule.id }), { is_active: !rule.is_active }, { preserveScroll: true });
const remove = (rule: Rule) => window.confirm(`Delete the rule "${rule.name}"?`) && router.delete(destroy.url({ business_rule: rule.id }), { preserveScroll: true });
const move = (i: number, by: number) => {
    const ids = props.rules.map(r => r.id);
    [ids[i], ids[i + by]] = [ids[i + by], ids[i]];
    router.post(reorder.url(), { ids }, { preserveScroll: true });
};
</script>

<template>
    <div>
        <Head title="Business rules" />
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold">Business rules</h2>
                    <span class="text-muted fs-7 mt-1 mw-650px">When a request is submitted, rules are checked from the top. The first rule whose conditions match decides the group and technician. If none match, the service catalog's assignment is used.</span>
                </div>
                <div class="card-toolbar">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="openEditor()"
                    ><i class="ki-outline ki-plus fs-2" />New rule</button>
                </div>
            </div>

            <div class="card-body pt-2">
                <div
                    v-if="!rules.length"
                    class="empty"
                >
                    <i class="ki-outline ki-route" />
                    <h3>No business rules yet</h3>
                    <p>Requests are assigned by the service catalog. Add a rule to route requests by department, employee, service or priority — for example, HR requests from Infrastructure go to one technician and from Software to another.</p>
                    <button
                        type="button"
                        class="btn btn-sm btn-light-primary"
                        @click="openEditor()"
                    >Create the first rule</button>
                </div>

                <ol
                    v-else
                    class="rules"
                >
                    <li
                        v-for="(r, i) in rules"
                        :key="r.id"
                        :class="['rule', { off: !r.is_active }]"
                    >
                        <span
                            class="order"
                            :title="`Checked ${i + 1}${i === 0 ? 'st' : i === 1 ? 'nd' : i === 2 ? 'rd' : 'th'}`"
                        >{{ i + 1 }}</span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h3 class="rule-name">{{ r.name }}</h3>
                                <span
                                    v-if="!r.is_active"
                                    class="badge badge-light"
                                >Off</span>
                            </div>
                            <div class="when">
                                <span class="kw">When</span>
                                <template
                                    v-for="(c, ci) in r.conditions"
                                    :key="ci"
                                >
                                    <span
                                        v-if="ci"
                                        class="kw"
                                    >{{ r.match === 'all' ? 'and' : 'or' }}</span>
                                    <span class="cond"><b>{{ sentence(c).field }}</b> {{ sentence(c).op }} <em>{{ sentence(c).list }}</em></span>
                                </template>
                            </div>
                            <div class="then">
                                <i class="ki-outline ki-arrow-right" />
                                Assign to <b>{{ r.group?.name ?? '—' }}</b>
                                <template v-if="r.technician"> · <b>{{ r.technician.name }}</b></template>
                                <span
                                    v-else
                                    class="text-muted"
                                > · least busy member</span>
                            </div>
                        </div>
                        <div class="actions">
                            <label
                                class="form-check form-switch form-check-sm mb-0"
                                :title="r.is_active ? 'Turn off' : 'Turn on'"
                            >
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    :checked="r.is_active"
                                    :aria-label="`${r.name} active`"
                                    @change="toggle(r)"
                                >
                            </label>
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-light"
                                title="Check earlier"
                                :disabled="i === 0"
                                @click="move(i, -1)"
                            ><i class="ki-outline ki-arrow-up" /></button>
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-light"
                                title="Check later"
                                :disabled="i === rules.length - 1"
                                @click="move(i, 1)"
                            ><i class="ki-outline ki-arrow-down" /></button>
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-light"
                                title="Edit"
                                @click="openEditor(r)"
                            ><i class="ki-outline ki-pencil" /></button>
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-light btn-active-light-danger"
                                title="Delete"
                                @click="remove(r)"
                            ><i class="ki-outline ki-trash" /></button>
                        </div>
                    </li>
                </ol>
            </div>
        </div>

        <AppDrawer
            v-model:open="drawer"
            :title="editingId ? 'Edit rule' : 'New rule'"
            subtitle="Route matching requests to a group and technician."
            width="640px"
        >
            <div class="mb-6">
                <label
                    class="form-label required"
                    for="rule-name"
                >Rule name</label>
                <input
                    id="rule-name"
                    v-model="form.name"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.name }"
                    placeholder="e.g. HR requests from Infrastructure"
                >
                <div class="invalid-feedback">{{ form.errors.name }}</div>
            </div>

            <!-- Conditions -->
            <section class="block">
                <div class="d-flex align-items-center justify-content-between mb-4">
                    <h4 class="block-title">When</h4>
                    <div
                        class="seg"
                        role="radiogroup"
                        aria-label="How conditions combine"
                    >
                        <label :class="{ on: form.match === 'all' }"><input
                            v-model="form.match"
                            type="radio"
                            value="all"
                        >All conditions match</label>
                        <label :class="{ on: form.match === 'any' }"><input
                            v-model="form.match"
                            type="radio"
                            value="any"
                        >Any condition matches</label>
                    </div>
                </div>

                <div
                    v-for="(c, i) in form.conditions"
                    :key="i"
                    class="cond-row"
                >
                    <span
                        v-if="i"
                        class="joiner"
                    >{{ form.match === 'all' ? 'and' : 'or' }}</span>
                    <div class="row g-2 align-items-start">
                        <div class="col-sm-4">
                            <select
                                v-model="c.field"
                                class="form-select form-select-sm"
                                :aria-label="`Condition ${i + 1} field`"
                                @change="c.values = []"
                            >
                                <option
                                    v-for="f in FIELDS"
                                    :key="f.value"
                                    :value="f.value"
                                >{{ f.label }}</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select
                                v-model="c.operator"
                                class="form-select form-select-sm"
                                :aria-label="`Condition ${i + 1} operator`"
                            >
                                <option value="in">is any of</option>
                                <option value="not_in">is none of</option>
                            </select>
                        </div>
                        <div class="col-sm-5 d-flex gap-2">
                            <MultiPicker
                                v-model="c.values"
                                class="flex-grow-1"
                                :options="options[c.field] ?? []"
                                :placeholder="FIELDS.find(f => f.value === c.field)?.hint"
                                :invalid="!!conditionError(i)"
                            />
                            <button
                                type="button"
                                class="btn btn-sm btn-icon btn-light flex-shrink-0"
                                title="Remove condition"
                                :disabled="form.conditions.length === 1"
                                @click="form.conditions.splice(i, 1)"
                            ><i class="ki-outline ki-cross" /></button>
                        </div>
                    </div>
                    <div
                        v-if="conditionError(i)"
                        class="text-danger fs-8 mt-1"
                    >{{ conditionError(i) }}</div>
                </div>
                <div
                    v-if="form.errors.conditions"
                    class="text-danger fs-7 mb-2"
                >{{ form.errors.conditions }}</div>
                <button
                    type="button"
                    class="btn btn-sm btn-light-primary"
                    :disabled="form.conditions.length >= 20"
                    @click="form.conditions.push(newCondition())"
                ><i class="ki-outline ki-plus" />Add condition</button>
            </section>

            <!-- Action -->
            <section class="block">
                <h4 class="block-title mb-4">Then assign to</h4>
                <div class="row g-4">
                    <div class="col-sm-6">
                        <label
                            class="form-label required"
                            for="rule-group"
                        >Technician group</label>
                        <select
                            id="rule-group"
                            v-model="form.group_id"
                            class="form-select"
                            :class="{ 'is-invalid': form.errors.group_id }"
                            @change="onGroupChange"
                        >
                            <option value="">Select a group…</option>
                            <option
                                v-for="g in groups"
                                :key="g.id"
                                :value="g.id"
                                :disabled="!g.is_active"
                            >{{ g.name }}{{ g.is_active ? '' : ' (inactive)' }}</option>
                        </select>
                        <div class="invalid-feedback">{{ form.errors.group_id }}</div>
                    </div>
                    <div class="col-sm-6">
                        <label
                            class="form-label"
                            for="rule-tech"
                        >Technician</label>
                        <select
                            id="rule-tech"
                            v-model="form.technician_id"
                            class="form-select"
                            :class="{ 'is-invalid': form.errors.technician_id }"
                            :disabled="!form.group_id"
                        >
                            <option :value="null">Least busy member</option>
                            <option
                                v-for="m in members"
                                :key="m.id"
                                :value="m.id"
                            >{{ m.name }}</option>
                        </select>
                        <div class="invalid-feedback">{{ form.errors.technician_id }}</div>
                    </div>
                </div>
            </section>

            <label class="form-check form-switch">
                <input
                    v-model="form.is_active"
                    class="form-check-input"
                    type="checkbox"
                >
                <span class="form-check-label">Rule is on</span>
            </label>

            <template #footer>
                <button
                    type="button"
                    class="btn btn-light"
                    @click="drawer = false"
                >Cancel</button>
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="form.processing || !form.name || !form.group_id"
                    @click="save"
                >
                    <span
                        v-if="form.processing"
                        class="spinner-border spinner-border-sm me-2"
                    />
                    Save rule
                </button>
            </template>
        </AppDrawer>
    </div>
</template>

<style scoped>
.empty { max-width: 520px; margin: 30px auto 40px; text-align: center; color: #6a7f8c; }
.empty i { font-size: 40px; color: #a9c4cf; }
.empty h3 { margin: 10px 0 6px; color: #24465b; font-size: 17px; }
.empty p { margin-bottom: 18px; line-height: 1.6; }

.rules { margin: 0; padding: 0; list-style: none; }
.rule { display: flex; align-items: flex-start; gap: 16px; padding: 18px 4px; border-bottom: 1px dashed #e5edf1; }
.rule:last-child { border-bottom: 0; }
.rule.off { opacity: .55; }
/* Order matters — the number is the checking order, not decoration. */
.order { display: grid; flex: 0 0 32px; place-items: center; height: 32px; border-radius: 9px; background: #173c61; color: #fff; font-size: 13px; font-weight: 800; }
.rule.off .order { background: #a9b6bf; }
.rule-name { margin: 0; color: #24465b; font-size: 15px; font-weight: 700; }
.when { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; color: #3f5668; }
.kw { color: #8095a3; font-size: 12px; font-weight: 700; }
.cond { padding: 3px 9px; border-radius: 6px; background: #f2f6f8; }
.cond em { font-style: normal; color: #135e64; font-weight: 600; }
.then { display: flex; flex-wrap: wrap; align-items: center; gap: 5px; margin-top: 8px; color: #3f5668; font-size: 13px; }
.then i { color: #2aa2a9; }
.actions { display: flex; flex: 0 0 auto; align-items: center; gap: 6px; }

.block { margin-bottom: 24px; padding: 18px; border: 1px solid #e5edf1; border-radius: 12px; background: #fafcfd; }
.block-title { margin: 0; color: #24465b; font-size: 14px; font-weight: 800; }
.seg { display: inline-flex; padding: 3px; border-radius: 8px; background: #eaf0f3; }
.seg label { padding: 5px 10px; border-radius: 6px; color: #5e7383; font-size: 12px; font-weight: 600; cursor: pointer; }
.seg label.on { background: #fff; color: #173c61; box-shadow: 0 1px 3px rgba(23, 60, 97, .12); }
.seg input { position: absolute; opacity: 0; pointer-events: none; }
.seg label:has(input:focus-visible) { outline: 2px solid #2aa2a9; }
.cond-row { margin-bottom: 12px; }
.joiner { display: inline-block; margin: 0 0 8px 2px; color: #8095a3; font-size: 11px; font-weight: 800; text-transform: lowercase; }
@media (max-width: 767px) { .rule { flex-wrap: wrap; } .actions { width: 100%; justify-content: flex-end; } }
</style>
