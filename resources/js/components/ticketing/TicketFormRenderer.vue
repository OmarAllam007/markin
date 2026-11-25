<script setup lang="ts">
/**
 * Renders a service form exactly as designed in the service catalog.
 * Used by the designer preview and by the New Ticket page, so both look the same.
 */
import { watchEffect } from 'vue';
import { evaluateFormula, STANDARD_KEYS  } from '@/lib/ticketFields';
import type {TicketField} from '@/lib/ticketFields';

type FormState = {
    subject: string;
    description: string;
    priority_id: string | number;
    attachments: File[];
    form_data: Record<string, unknown>;
    errors: Record<string, string | undefined>;
};

const props = defineProps<{
    fields: TicketField[];
    form: FormState;
    priorities: { id: number; name: string }[];
}>();

const isStandard = (f: TicketField) => STANDARD_KEYS.includes(f.key);

/* eslint-disable vue/no-mutating-props -- the parent passes its useForm() object for the renderer to fill */
const model = (f: TicketField) => (isStandard(f) ? (props.form as Record<string, unknown>) : props.form.form_data);

watchEffect(() => {
    for (const f of props.fields) {
        if (f.type !== 'computed') {
continue;
}

        const [a, b] = f.args ?? [];
        props.form.form_data[f.key] = evaluateFormula(f.formula, props.form.form_data[a], props.form.form_data[b]);
    }
});

const setFiles = (e: Event) => {
    props.form.attachments = Array.from((e.target as HTMLInputElement).files ?? []);
};
/* eslint-enable vue/no-mutating-props */

const error = (f: TicketField) =>
    props.form.errors[f.key] ?? props.form.errors[`form_data.${f.key}`] ?? props.form.errors[`${f.key}.0`];
</script>

<template>
    <div class="row g-5">
        <div
            v-for="f in fields"
            :key="f.key"
            :class="f.width === 'full' || f.type === 'textarea' || f.type === 'attachments' ? 'col-12' : 'col-md-6'"
        >
            <label
                :for="`tf-${f.key}`"
                :class="['form-label', f.required ? 'required' : '']"
            >{{ f.label }}</label>

            <select
                v-if="f.type === 'select'"
                :id="`tf-${f.key}`"
                v-model="model(f)[f.key]"
                class="form-select"
                :class="{ 'is-invalid': error(f) }"
            >
                <option value="">Select…</option>
                <option
                    v-for="o in f.options ?? []"
                    :key="o.value"
                    :value="o.value"
                >{{ o.label }}</option>
            </select>

            <select
                v-else-if="f.type === 'priority'"
                :id="`tf-${f.key}`"
                v-model="model(f).priority_id"
                class="form-select"
                :class="{ 'is-invalid': error(f) }"
            >
                <option value="">Select priority…</option>
                <option
                    v-for="p in priorities"
                    :key="p.id"
                    :value="p.id"
                >{{ p.name }}</option>
            </select>

            <textarea
                v-else-if="f.type === 'textarea'"
                :id="`tf-${f.key}`"
                v-model="model(f)[f.key] as string"
                class="form-control"
                :class="{ 'is-invalid': error(f) }"
                rows="4"
            />

            <div
                v-else-if="f.type === 'checkbox'"
                class="form-check form-check-custom mt-2"
            >
                <input
                    :id="`tf-${f.key}`"
                    v-model="model(f)[f.key]"
                    class="form-check-input"
                    type="checkbox"
                    :true-value="1"
                    :false-value="0"
                />
                <label
                    class="form-check-label"
                    :for="`tf-${f.key}`"
                >Yes</label>
            </div>

            <div
                v-else-if="f.type === 'computed'"
                class="input-group"
            >
                <span class="input-group-text"><i class="ki-outline ki-calculator fs-4" /></span>
                <input
                    :id="`tf-${f.key}`"
                    :value="form.form_data[f.key] ?? '—'"
                    class="form-control bg-light fw-bold"
                    readonly
                    tabindex="-1"
                />
            </div>

            <template v-else-if="f.type === 'attachments'">
                <input
                    :id="`tf-${f.key}`"
                    type="file"
                    class="form-control"
                    :class="{ 'is-invalid': error(f) }"
                    multiple
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                    @change="setFiles"
                />
                <div class="form-text">Max 10 MB per file.</div>
            </template>

            <input
                v-else
                :id="`tf-${f.key}`"
                v-model="model(f)[f.key]"
                :type="f.type"
                class="form-control"
                :class="{ 'is-invalid': error(f) }"
                autocomplete="off"
            />

            <div
                v-if="error(f)"
                class="invalid-feedback d-block"
            >{{ error(f) }}</div>
        </div>
    </div>
</template>
