<script setup lang="ts">
import { update, index } from '@/routes/ticketing/admin/subcategories';
import { Head, Link, useForm } from '@inertiajs/vue3';

type CategoryOption = { id: number; name: string };

type SubcategoryModel = {
    id: number;
    category_id: number;
    name: string;
    name_ar: string | null;
    description: string | null;
    is_active: boolean;
};

const props = defineProps<{ subcategory: SubcategoryModel; categories: CategoryOption[] }>();

const form = useForm({
    category_id: props.subcategory.category_id,
    name: props.subcategory.name,
    name_ar: props.subcategory.name_ar ?? '',
    description: props.subcategory.description ?? '',
    is_active: props.subcategory.is_active,
});

const submit = () => form.put(update.url({ subcategory: props.subcategory.id }));
</script>

<template>
    <div>
        <Head :title="`Edit ${subcategory.name}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to subcategories
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">Edit Subcategory</h2></div>
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
                                for="sub-cat"
                            >Category</label>
                            <select
                                id="sub-cat"
                                v-model="form.category_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.category_id }"
                            >
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
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="sub-name"
                            >Name (English)</label>
                            <input
                                id="sub-name"
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
                                for="sub-name-ar"
                            >Name (Arabic)</label>
                            <input
                                id="sub-name-ar"
                                v-model="form.name_ar"
                                type="text"
                                class="form-control"
                                dir="rtl"
                                autocomplete="off"
                            />
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch ms-2">
                                <input
                                    id="sub-active"
                                    v-model="form.is_active"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="sub-active"
                                >Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label
                                class="form-label"
                                for="sub-desc"
                            >Description</label>
                            <textarea
                                id="sub-desc"
                                v-model="form.description"
                                class="form-control"
                                rows="3"
                            />
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
