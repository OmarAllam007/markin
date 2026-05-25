<script setup lang="ts">
import { index, store, destroy } from '@/routes/ticketing/admin/form-fields';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type BaseField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
    options: { value: string; label: string }[] | null;
};

type CustomField = {
    id: number;
    field_key: string;
    label: string;
    type: string;
    options: { value: string; label: string }[] | null;
    is_required: boolean;
    sort_order: number;
};

type TicketTypeInfo = { value: string; label: string };

const props = defineProps<{
    ticketType: TicketTypeInfo;
    baseFields: BaseField[];
    customFields: CustomField[];
}>();

const fieldTypes = [
    { value: 'text', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'date', label: 'Date' },
    { value: 'time', label: 'Time' },
    { value: 'select', label: 'Select (dropdown)' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'checkbox', label: 'Checkbox' },
];

const showAddForm = ref(false);

const form = useForm({
    field_key: '',
    label: '',
    type: 'text',
    options: [] as { value: string; label: string }[],
    is_required: false,
    sort_order: 0,
});

const addOption = () => form.options.push({ value: '', label: '' });
const removeOption = (i: number) => form.options.splice(i, 1);

const submit = () => {
    form.post(store.url({ type: props.ticketType.value }), {
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
        },
    });
};

const removeField = (field: CustomField) => {
    if (!window.confirm(`Remove the "${field.label}" field?`)) return;
    router.delete(destroy.url({ type: props.ticketType.value, field: field.id }));
};
</script>

<template>
    <div>
        <Head :title="`Form Fields — ${ticketType.label}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to form fields
            </Link>
        </div>

        <div class="d-flex align-items-center mb-6 gap-3">
            <h1 class="fw-bold mb-0">{{ ticketType.label }}</h1>
            <span class="badge badge-light-primary">Form Fields</span>
        </div>

        <!-- System Required Fields -->
        <div class="card mb-6">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-outline ki-lock fs-2 text-warning me-2" />
                    <h3 class="fw-bold mb-0">System Required Fields</h3>
                </div>
                <div class="card-toolbar">
                    <span class="badge badge-light-warning">Locked — cannot be removed</span>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Field Key</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th>Options</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in baseFields"
                                :key="f.key"
                            >
                                <td><code class="fs-7">{{ f.key }}</code></td>
                                <td class="fw-semibold">{{ f.label }}</td>
                                <td><span class="badge badge-light-dark text-uppercase">{{ f.type }}</span></td>
                                <td>
                                    <i
                                        v-if="f.required"
                                        class="ki-outline ki-check-circle fs-4 text-success"
                                    />
                                    <i
                                        v-else
                                        class="ki-outline ki-minus-circle fs-4 text-muted"
                                    />
                                </td>
                                <td class="text-muted fs-7">
                                    <span v-if="f.options">
                                        {{ f.options.map(o => o.label).join(', ') }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                            <tr v-if="baseFields.length === 0">
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-8"
                                >No system fields for this type.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Custom Fields -->
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <i class="ki-outline ki-plus-circle fs-2 text-primary me-2" />
                    <h3 class="fw-bold mb-0">Custom Fields</h3>
                </div>
                <div class="card-toolbar">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="showAddForm = !showAddForm"
                    >
                        <i class="ki-outline ki-plus fs-2" />
                        Add field
                    </button>
                </div>
            </div>

            <!-- Add form -->
            <div
                v-if="showAddForm"
                class="card-body border-top bg-light-primary"
            >
                <form @submit.prevent="submit">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <label class="form-label required">Field Key</label>
                            <input
                                v-model="form.field_key"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.field_key }"
                                placeholder="notes_from_manager"
                                autocomplete="off"
                            />
                            <div class="form-text">Lowercase letters, numbers, underscores, hyphens.</div>
                            <div
                                v-if="form.errors.field_key"
                                class="invalid-feedback"
                            >{{ form.errors.field_key }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label required">Label</label>
                            <input
                                v-model="form.label"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.label }"
                                placeholder="Notes from Manager"
                            />
                            <div
                                v-if="form.errors.label"
                                class="invalid-feedback"
                            >{{ form.errors.label }}</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label required">Type</label>
                            <select
                                v-model="form.type"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.type }"
                            >
                                <option
                                    v-for="ft in fieldTypes"
                                    :key="ft.value"
                                    :value="ft.value"
                                >{{ ft.label }}</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch ms-2 mb-2">
                                <input
                                    id="field-required"
                                    v-model="form.is_required"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="field-required"
                                >Required</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-2">
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm"
                                :disabled="form.processing"
                            >
                                <span
                                    v-if="form.processing"
                                    class="spinner-border spinner-border-sm me-1"
                                />
                                Save field
                            </button>
                            <button
                                type="button"
                                class="btn btn-light btn-sm"
                                @click="showAddForm = false; form.reset()"
                            >Cancel</button>
                        </div>

                        <!-- Select options builder -->
                        <div
                            v-if="form.type === 'select'"
                            class="col-12"
                        >
                            <label class="form-label required">Dropdown Options</label>
                            <div
                                v-for="(opt, i) in form.options"
                                :key="i"
                                class="d-flex align-items-center gap-2 mb-2"
                            >
                                <input
                                    v-model="opt.value"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="value (e.g. annual)"
                                />
                                <input
                                    v-model="opt.label"
                                    type="text"
                                    class="form-control form-control-sm"
                                    placeholder="Label (e.g. Annual Leave)"
                                />
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light-danger"
                                    @click="removeOption(i)"
                                >
                                    <i class="ki-outline ki-trash fs-6 p-0" />
                                </button>
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-light-primary"
                                @click="addOption"
                            >
                                <i class="ki-outline ki-plus fs-6" /> Add option
                            </button>
                            <div
                                v-if="form.errors.options"
                                class="text-danger fs-7 mt-1"
                            >{{ form.errors.options }}</div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Field Key</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="f in customFields"
                                :key="f.id"
                            >
                                <td><code class="fs-7">{{ f.field_key }}</code></td>
                                <td class="fw-semibold">{{ f.label }}</td>
                                <td><span class="badge badge-light-info text-uppercase">{{ f.type }}</span></td>
                                <td>
                                    <i
                                        v-if="f.is_required"
                                        class="ki-outline ki-check-circle fs-4 text-success"
                                    />
                                    <i
                                        v-else
                                        class="ki-outline ki-minus-circle fs-4 text-muted"
                                    />
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeField(f)"
                                    >
                                        <i class="ki-duotone ki-trash"><span class="path1"></span><span class="path2"></span></i>
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="customFields.length === 0">
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-8"
                                >
                                    No custom fields yet.
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light-primary ms-3"
                                        @click="showAddForm = true"
                                    >Add the first one</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
