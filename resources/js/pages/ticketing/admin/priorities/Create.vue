<script setup lang="ts">
import { store, index } from '@/routes/ticketing/admin/priorities';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    color: '#6c757d',
    icon: '',
    sla_hours: '',
    is_default: false,
    sort_order: 0,
});

const submit = () => form.post(store.url());
</script>

<template>
    <div>
        <Head title="New Priority" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to priorities
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">New Priority</h2></div>
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
                                for="pri-name"
                            >Name</label>
                            <input
                                id="pri-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                placeholder="e.g. High"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.name"
                                class="invalid-feedback"
                            >{{ form.errors.name }}</div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="pri-color"
                            >Color</label>
                            <div class="d-flex align-items-center gap-3">
                                <input
                                    id="pri-color"
                                    v-model="form.color"
                                    type="color"
                                    class="form-control form-control-color"
                                    style="width: 48px; height: 38px;"
                                />
                                <input
                                    v-model="form.color"
                                    type="text"
                                    class="form-control"
                                />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label"
                                for="pri-sla"
                            >SLA (hours)</label>
                            <input
                                id="pri-sla"
                                v-model="form.sla_hours"
                                type="number"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.sla_hours }"
                                min="1"
                                placeholder="e.g. 24"
                            />
                            <div
                                v-if="form.errors.sla_hours"
                                class="invalid-feedback"
                            >{{ form.errors.sla_hours }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="pri-order"
                            >Sort Order</label>
                            <input
                                id="pri-order"
                                v-model="form.sort_order"
                                type="number"
                                class="form-control"
                                min="0"
                            />
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch ms-2">
                                <input
                                    id="pri-default"
                                    v-model="form.is_default"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="pri-default"
                                >Set as default priority</label>
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
                        Create priority
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
