<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { store, index } from '@/routes/departments';

defineProps<{ employees: { id: number; english_name: string; email: string | null }[] }>();

const form = useForm({
    manager_employee_id: '' as number | '',
    name: '',
});

const submit = () => {
    form.post(store.url());
};
</script>

<template>
    <div>
        <Head title="New department" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to departments
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">New department</h2>
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
                                for="dept-name"
                            >Name</label>
                            <input
                                id="dept-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
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
                                for="dept-manager"
                            >Manager</label>
                            <select
                                id="dept-manager"
                                v-model="form.manager_employee_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.manager_employee_id }"
                            >
                                <option value="">No manager</option>
                                <option
                                    v-for="e in employees"
                                    :key="e.id"
                                    :value="e.id"
                                >{{ e.english_name }}{{ e.email ? '' : ' (no email)' }}</option>
                            </select>
                            <div
                                v-if="form.errors.manager_employee_id"
                                class="invalid-feedback"
                            >{{ form.errors.manager_employee_id }}</div>
                            <div class="form-text">Approves requests as the direct manager of this department's employees.</div>
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
                        Create department
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
