<script setup lang="ts">
import { store, index } from '@/routes/ticketing/tickets';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

type SchemaField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
    options: { value: string; label: string }[] | null;
    is_base?: boolean;
};

type CategoryOption = {
    id: number;
    name: string;
    color: string;
    icon: string | null;
    ticket_type: string | null;
    subcategories: { id: number; category_id: number; name: string }[];
    base_fields: SchemaField[];
    custom_fields: SchemaField[];
};

type PriorityOption = { id: number; name: string; color: string };
type SlaOption = { id: number; name: string; first_response_hours: number; resolve_hours: number };
type GroupOption = { id: number; name: string };
type TechnicianOption = { id: number; name: string };
type EmployeeOption = { id: number; english_name: string; arabic_name: string | null };

const props = defineProps<{
    categories: CategoryOption[];
    priorities: PriorityOption[];
    slas: SlaOption[];
    groups: GroupOption[];
    technicians: TechnicianOption[];
    employees: EmployeeOption[];
    types: { value: string; label: string }[];
}>();

const form = useForm({
    subject: '',
    description: '',
    category_id: '',
    subcategory_id: '',
    type: '',
    priority_id: '',
    sla_id: '',
    group_id: '',
    technician_id: '',
    employee_id: '',
    form_data: {} as Record<string, string>,
    attachments: [] as File[],
});

const activeCategory = computed<CategoryOption | null>(() =>
    props.categories.find(c => c.id === Number(form.category_id)) ?? null,
);

const availableSubcategories = computed(() => activeCategory.value?.subcategories ?? []);

const allSchemaFields = computed<SchemaField[]>(() => {
    if (!activeCategory.value?.ticket_type) return [];
    const base = (activeCategory.value.base_fields ?? []).map(f => ({ ...f, is_base: true }));
    const custom = (activeCategory.value.custom_fields ?? []).map(f => ({ ...f, is_base: false, required: f.required ?? (f as any).is_required }));
    return [...base, ...custom];
});

watch(() => form.category_id, () => {
    form.subcategory_id = '';
    form.form_data = Object.fromEntries(allSchemaFields.value.map(f => [f.key, '']));
});

const handleFiles = (e: Event) => {
    const input = e.target as HTMLInputElement;
    form.attachments = Array.from(input.files ?? []);
};

const submit = () => {
    form.post(store.url(), { forceFormData: true });
};
</script>

<template>
    <div>
        <Head title="New Ticket" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to tickets
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">New Ticket</h2></div>
            </div>
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5">
                        <!-- Subject -->
                        <div class="col-md-12">
                            <label
                                class="form-label required"
                                for="tk-subject"
                            >Subject</label>
                            <input
                                id="tk-subject"
                                v-model="form.subject"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.subject }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.subject"
                                class="invalid-feedback"
                            >{{ form.errors.subject }}</div>
                        </div>

                        <!-- Category + Subcategory -->
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="tk-cat"
                            >Category</label>
                            <select
                                id="tk-cat"
                                v-model="form.category_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.category_id }"
                            >
                                <option value="">Select category…</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >{{ c.name }}</option>
                            </select>
                            <div
                                v-if="form.errors.category_id"
                                class="invalid-feedback"
                            >{{ form.errors.category_id }}</div>
                        </div>
                        <div
                            v-if="availableSubcategories.length"
                            class="col-md-6"
                        >
                            <label
                                class="form-label"
                                for="tk-subcat"
                            >Subcategory</label>
                            <select
                                id="tk-subcat"
                                v-model="form.subcategory_id"
                                class="form-select"
                            >
                                <option value="">Select subcategory…</option>
                                <option
                                    v-for="s in availableSubcategories"
                                    :key="s.id"
                                    :value="s.id"
                                >{{ s.name }}</option>
                            </select>
                        </div>

                        <!-- Dynamic schema fields for the selected category's ticket type -->
                        <template v-if="allSchemaFields.length">
                            <div class="col-12">
                                <div class="separator separator-dashed my-2" />
                                <div class="d-flex align-items-center gap-2 mb-4">
                                    <span class="fw-bold text-gray-700 fs-6">Request Details</span>
                                    <span
                                        v-if="activeCategory?.ticket_type"
                                        class="badge badge-light-primary"
                                    >{{ activeCategory.ticket_type.replace(/_/g, ' ') }}</span>
                                </div>
                            </div>
                            <template
                                v-for="field in allSchemaFields"
                                :key="field.key"
                            >
                                <div :class="['col-md-6', field.type === 'textarea' ? 'col-md-12' : '']">
                                    <label :class="['form-label', field.required ? 'required' : '']">
                                        {{ field.label }}
                                        <i
                                            v-if="field.is_base"
                                            class="ki-outline ki-lock fs-7 text-warning ms-1"
                                            title="System required field"
                                        />
                                    </label>

                                    <!-- select -->
                                    <select
                                        v-if="field.type === 'select'"
                                        v-model="form.form_data[field.key]"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors[`form_data.${field.key}`] }"
                                    >
                                        <option value="">Select…</option>
                                        <option
                                            v-for="opt in field.options"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >{{ opt.label }}</option>
                                    </select>

                                    <!-- textarea -->
                                    <textarea
                                        v-else-if="field.type === 'textarea'"
                                        v-model="form.form_data[field.key]"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors[`form_data.${field.key}`] }"
                                        rows="3"
                                    />

                                    <!-- checkbox -->
                                    <div
                                        v-else-if="field.type === 'checkbox'"
                                        class="form-check mt-2"
                                    >
                                        <input
                                            :id="`fd-${field.key}`"
                                            v-model="form.form_data[field.key]"
                                            class="form-check-input"
                                            type="checkbox"
                                            true-value="1"
                                            false-value="0"
                                        />
                                        <label
                                            :for="`fd-${field.key}`"
                                            class="form-check-label"
                                        >Yes</label>
                                    </div>

                                    <!-- text / number / date / time -->
                                    <input
                                        v-else
                                        v-model="form.form_data[field.key]"
                                        :type="field.type"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors[`form_data.${field.key}`] }"
                                    />

                                    <div
                                        v-if="form.errors[`form_data.${field.key}`]"
                                        class="invalid-feedback d-block"
                                    >{{ form.errors[`form_data.${field.key}`] }}</div>
                                </div>
                            </template>
                            <div class="col-12">
                                <div class="separator separator-dashed my-2" />
                            </div>
                        </template>

                        <!-- Description -->
                        <div class="col-md-12">
                            <label
                                class="form-label required"
                                for="tk-desc"
                            >Description</label>
                            <textarea
                                id="tk-desc"
                                v-model="form.description"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.description }"
                                rows="4"
                            />
                            <div
                                v-if="form.errors.description"
                                class="invalid-feedback"
                            >{{ form.errors.description }}</div>
                        </div>

                        <!-- Priority + SLA -->
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-priority"
                            >Priority</label>
                            <select
                                id="tk-priority"
                                v-model="form.priority_id"
                                class="form-select"
                            >
                                <option value="">Select priority…</option>
                                <option
                                    v-for="p in priorities"
                                    :key="p.id"
                                    :value="p.id"
                                >{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-sla"
                            >SLA Policy</label>
                            <select
                                id="tk-sla"
                                v-model="form.sla_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="s in slas"
                                    :key="s.id"
                                    :value="s.id"
                                >{{ s.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="tk-group"
                            >Group</label>
                            <select
                                id="tk-group"
                                v-model="form.group_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="g in groups"
                                    :key="g.id"
                                    :value="g.id"
                                >{{ g.name }}</option>
                            </select>
                        </div>

                        <!-- Assignee + Employee -->
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="tk-tech"
                            >Assign To</label>
                            <select
                                id="tk-tech"
                                v-model="form.technician_id"
                                class="form-select"
                            >
                                <option value="">Unassigned</option>
                                <option
                                    v-for="u in technicians"
                                    :key="u.id"
                                    :value="u.id"
                                >{{ u.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="tk-emp"
                            >Related Employee</label>
                            <select
                                id="tk-emp"
                                v-model="form.employee_id"
                                class="form-select"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="e in employees"
                                    :key="e.id"
                                    :value="e.id"
                                >{{ e.english_name }}</option>
                            </select>
                        </div>

                        <!-- Attachments -->
                        <div class="col-md-12">
                            <label class="form-label">Attachments</label>
                            <input
                                type="file"
                                class="form-control"
                                multiple
                                accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                                @change="handleFiles"
                            />
                            <div class="form-text">Max 10 MB per file. Allowed: images, PDF, Word, Excel, ZIP.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link
                        :href="index.url()"
                        class="btn btn-light"
                    >Cancel</Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Submit ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
