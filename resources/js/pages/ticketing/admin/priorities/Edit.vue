<script setup lang="ts">
import { update, index } from '@/routes/ticketing/admin/priorities';
import { Head, Link, useForm } from '@inertiajs/vue3';

type PriorityModel = { id: number; name: string; color: string; icon: string | null; sla_hours: number | null; is_default: boolean; sort_order: number };

const props = defineProps<{ priority: PriorityModel }>();

const form = useForm({
    name: props.priority.name,
    color: props.priority.color,
    icon: props.priority.icon ?? '',
    sla_hours: props.priority.sla_hours ?? '',
    is_default: props.priority.is_default,
    sort_order: props.priority.sort_order,
});

const submit = () => form.put(update.url({ priority: props.priority.id }));
</script>

<template>
    <div>
        <Head :title="`Edit ${priority.name}`" />
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
                <div class="card-title"><h2 class="fw-bold">Edit Priority</h2></div>
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
                                min="1"
                            />
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
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
