<script setup lang="ts">
import { update, index } from '@/routes/ticketing/admin/categories';
import { Head, Link, useForm } from '@inertiajs/vue3';

type TicketTypeOption = { value: string; label: string };

type CategoryModel = {
    id: number;
    name: string;
    name_ar: string | null;
    description: string | null;
    icon: string | null;
    color: string;
    is_active: boolean;
    ticket_type: string | null;
};

const props = defineProps<{ category: CategoryModel; types: TicketTypeOption[] }>();

const form = useForm({
    name: props.category.name,
    name_ar: props.category.name_ar ?? '',
    description: props.category.description ?? '',
    icon: props.category.icon ?? '',
    color: props.category.color,
    is_active: props.category.is_active,
    ticket_type: props.category.ticket_type,
});

const submit = () => form.put(update.url({ category: props.category.id }));
</script>

<template>
    <div>
        <Head :title="`Edit ${category.name}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to categories
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Edit Category</h2>
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
                                for="cat-name"
                            >Name (English)</label>
                            <input
                                id="cat-name"
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
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="cat-name-ar"
                            >Name (Arabic)</label>
                            <input
                                id="cat-name-ar"
                                v-model="form.name_ar"
                                type="text"
                                class="form-control"
                                dir="rtl"
                                autocomplete="off"
                            />
                        </div>
                        <div class="col-md-12">
                            <label
                                class="form-label"
                                for="cat-desc"
                            >Description</label>
                            <textarea
                                id="cat-desc"
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                            />
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="cat-type"
                            >Ticket Type</label>
                            <select
                                id="cat-type"
                                v-model="form.ticket_type"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.ticket_type }"
                            >
                                <option :value="null">— General (no special handling) —</option>
                                <option
                                    v-for="type in props.types"
                                    :key="type.value"
                                    :value="type.value"
                                >{{ type.label }}</option>
                            </select>
                            <div
                                v-if="form.errors.ticket_type"
                                class="invalid-feedback"
                            >{{ form.errors.ticket_type }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="cat-icon"
                            >Icon (KI class)</label>
                            <input
                                id="cat-icon"
                                v-model="form.icon"
                                type="text"
                                class="form-control"
                                placeholder="ki-ticket"
                                autocomplete="off"
                            />
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="cat-color"
                            >Color</label>
                            <div class="d-flex align-items-center gap-3">
                                <input
                                    id="cat-color"
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
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch ms-2">
                                <input
                                    id="cat-active"
                                    v-model="form.is_active"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="cat-active"
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
