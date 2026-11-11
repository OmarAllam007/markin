<script setup lang="ts">
import { index, update } from '@/routes/leave-types/index';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    leaveType: {
        id: number;
        name: string;
        annual_days: number | null;
        is_paid: boolean;
        is_active: boolean;
    };
}>();

const form = useForm({
    name: props.leaveType.name,
    annual_days: props.leaveType.annual_days,
    is_paid: props.leaveType.is_paid,
    is_active: props.leaveType.is_active,
});

const submit = () => {
    form.put(update.url({ leave_type: props.leaveType.id }));
};
</script>

<template>
    <div>
        <Head title="Edit leave type" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to leave types
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Edit leave type</h2>
                </div>
            </div>
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="leave-type-name"
                            >Name</label>
                            <input
                                id="leave-type-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                placeholder="e.g. Annual leave"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.name"
                                class="invalid-feedback"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="leave-type-days"
                            >Days per year</label>
                            <input
                                id="leave-type-days"
                                v-model.number="form.annual_days"
                                type="number"
                                min="0"
                                max="365"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.annual_days }"
                                placeholder="Leave empty for no limit"
                            />
                            <div
                                v-if="form.errors.annual_days"
                                class="invalid-feedback"
                            >
                                {{ form.errors.annual_days }}
                            </div>
                            <div class="text-muted fs-7 mt-1">
                                Empty means no yearly cap. The balance an employee sees is this number minus their approved days this year.
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch mb-3">
                                <input
                                    id="leave-type-paid"
                                    v-model="form.is_paid"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="leave-type-paid"
                                >Paid leave</label>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    id="leave-type-active"
                                    v-model="form.is_active"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="leave-type-active"
                                >Active — employees can pick this type</label>
                            </div>
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
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
