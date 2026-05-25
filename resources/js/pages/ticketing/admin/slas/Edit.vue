<script setup lang="ts">
import { update, index } from '@/routes/ticketing/admin/slas';
import { Head, Link, useForm } from '@inertiajs/vue3';

type SlaModel = { id: number; name: string; first_response_hours: number; resolve_hours: number; business_hours_only: boolean; is_active: boolean };

const props = defineProps<{ sla: SlaModel }>();

const form = useForm({
    name: props.sla.name,
    first_response_hours: props.sla.first_response_hours,
    resolve_hours: props.sla.resolve_hours,
    business_hours_only: props.sla.business_hours_only,
    is_active: props.sla.is_active,
});

const submit = () => form.put(update.url({ sla: props.sla.id }));
</script>

<template>
    <div>
        <Head :title="`Edit ${sla.name}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to SLA policies
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">Edit SLA Policy</h2></div>
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
                                for="sla-name"
                            >Policy Name</label>
                            <input
                                id="sla-name"
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
                                class="form-label required"
                                for="sla-resp"
                            >First Response (hours)</label>
                            <input
                                id="sla-resp"
                                v-model="form.first_response_hours"
                                type="number"
                                class="form-control"
                                min="1"
                            />
                        </div>
                        <div class="col-md-3">
                            <label
                                class="form-label required"
                                for="sla-resolve"
                            >Resolve By (hours)</label>
                            <input
                                id="sla-resolve"
                                v-model="form.resolve_hours"
                                type="number"
                                class="form-control"
                                min="1"
                            />
                        </div>
                        <div class="col-md-6 d-flex gap-5">
                            <div class="form-check form-switch">
                                <input
                                    id="sla-biz"
                                    v-model="form.business_hours_only"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="sla-biz"
                                >Business hours only</label>
                            </div>
                            <div class="form-check form-switch">
                                <input
                                    id="sla-active"
                                    v-model="form.is_active"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="sla-active"
                                >Active</label>
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
