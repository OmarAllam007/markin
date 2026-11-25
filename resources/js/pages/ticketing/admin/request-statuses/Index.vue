<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TranslationInputs from '@/components/TranslationInputs.vue';
import { destroy, reorder, store, update } from '@/routes/ticketing/admin/request-statuses';

type Status = { id: number; name: string; name_translations: Record<string, string> | null; type: string; color: string; is_default: boolean; tickets_count: number };

const props = defineProps<{ statuses: Status[]; types: { value: string; label: string }[] }>();

const locales = computed(() => (usePage().props.contentLocales as string[] | undefined) ?? ['en']);
const typeLabel = (t: string) => props.types.find(x => x.value === t)?.label ?? t;

const editingId = ref<number | 'new' | null>(null);
const form = useForm({ name: '', name_translations: {} as Record<string, string>, type: 'open', color: '#3e97ff', is_default: false });

const start = (s?: Status) => {
    form.clearErrors();
    form.name = s?.name ?? '';
    form.name_translations = { ...(s?.name_translations ?? {}) };
    form.type = s?.type ?? 'open';
    form.color = s?.color ?? '#3e97ff';
    form.is_default = s?.is_default ?? false;
    editingId.value = s?.id ?? 'new';
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (editingId.value = null) };

    if (editingId.value === 'new') {
        form.post(store.url(), options);
    } else if (editingId.value) {
        form.put(update.url({ request_status: editingId.value }), options);
    }
};

const remove = (s: Status) => {
    const note = s.tickets_count ? ` ${s.tickets_count} request(s) use it and will have no status.` : '';

    if (window.confirm(`Delete "${s.name}"?${note}`)) {
        router.delete(destroy.url({ request_status: s.id }), { preserveScroll: true });
    }
};

const move = (i: number, by: number) => {
    const ids = props.statuses.map(s => s.id);
    [ids[i], ids[i + by]] = [ids[i + by], ids[i]];
    router.post(reorder.url(), { ids }, { preserveScroll: true });
};
</script>

<template>
    <div>
        <Head title="Request statuses" />
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold">Request statuses</h2>
                    <span class="text-muted fs-7 mt-1">The statuses a request moves through. The type decides how time is counted later: open counts, on hold pauses, resolved and closed stop. Only the technician side can set a resolved status.</span>
                </div>
                <div class="card-toolbar">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="editingId === 'new'"
                        @click="start()"
                    ><i class="ki-outline ki-plus fs-2" />New status</button>
                </div>
            </div>
            <div class="card-body pt-0">
                <div
                    v-if="editingId"
                    class="border rounded p-5 bg-light-secondary mb-6"
                >
                    <h6 class="fw-bold mb-4">{{ editingId === 'new' ? 'New status' : 'Edit status' }}</h6>
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label required">Name</label>
                            <input
                                v-model="form.name"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                placeholder="e.g. Waiting for parts"
                            >
                            <div class="invalid-feedback">{{ form.errors.name }}</div>
                            <TranslationInputs
                                v-if="locales.length > 1"
                                v-model="form.name_translations"
                                label="Name"
                                id-prefix="status-name"
                                wrapper-class="mt-2"
                            />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Type</label>
                            <select
                                v-model="form.type"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.type }"
                            >
                                <option
                                    v-for="t in types"
                                    :key="t.value"
                                    :value="t.value"
                                >{{ t.label }}</option>
                            </select>
                            <div class="invalid-feedback">{{ form.errors.type }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Color</label>
                            <input
                                v-model="form.color"
                                type="color"
                                class="form-control form-control-color w-100"
                            >
                        </div>
                        <div class="col-12">
                            <label class="form-check">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="form-check-input"
                                    :disabled="form.type !== 'open'"
                                >
                                <span class="form-check-label">New requests start in this status <span class="text-muted">(open type only)</span></span>
                            </label>
                            <div
                                v-if="form.errors.is_default"
                                class="text-danger fs-7 mt-1"
                            >{{ form.errors.is_default }}</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button
                            type="button"
                            class="btn btn-sm btn-light"
                            @click="editingId = null"
                        >Cancel</button>
                        <button
                            type="button"
                            class="btn btn-sm btn-primary"
                            :disabled="form.processing || !form.name"
                            @click="save"
                        >Save status</button>
                    </div>
                </div>

                <table class="table table-row-dashed align-middle gy-3 fs-6">
                    <thead>
                        <tr class="text-muted fw-bold fs-7">
                            <th>Status</th>
                            <th>Type</th>
                            <th class="text-end">Requests</th>
                            <th class="text-end w-200px" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(s, i) in statuses"
                            :key="s.id"
                        >
                            <td>
                                <span class="d-inline-flex align-items-center gap-2 fw-bold text-gray-800">
                                    <span
                                        class="rounded-circle"
                                        :style="{ width: '10px', height: '10px', background: s.color }"
                                    />{{ s.name }}
                                </span>
                                <span
                                    v-if="s.is_default"
                                    class="badge badge-light-primary ms-2"
                                >Default</span>
                            </td>
                            <td class="text-gray-700 fs-7">{{ typeLabel(s.type) }}</td>
                            <td class="text-end text-gray-700">{{ s.tickets_count }}</td>
                            <td class="text-end text-nowrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-light me-1"
                                    title="Move up"
                                    :disabled="i === 0"
                                    @click="move(i, -1)"
                                ><i class="ki-outline ki-arrow-up" /></button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-light me-1"
                                    title="Move down"
                                    :disabled="i === statuses.length - 1"
                                    @click="move(i, 1)"
                                ><i class="ki-outline ki-arrow-down" /></button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-light me-1"
                                    title="Edit"
                                    @click="start(s)"
                                ><i class="ki-outline ki-pencil" /></button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-icon btn-light btn-active-light-danger"
                                    title="Delete"
                                    :disabled="s.is_default"
                                    @click="remove(s)"
                                ><i class="ki-outline ki-trash" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
