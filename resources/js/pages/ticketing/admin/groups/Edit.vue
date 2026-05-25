<script setup lang="ts">
import { update, index } from '@/routes/ticketing/admin/groups';
import { Head, Link, useForm } from '@inertiajs/vue3';

type GroupModel = { id: number; name: string; description: string | null; is_active: boolean };

const props = defineProps<{ group: GroupModel }>();

const form = useForm({
    name: props.group.name,
    description: props.group.description ?? '',
    is_active: props.group.is_active,
});

const submit = () => form.put(update.url({ group: props.group.id }));
</script>

<template>
    <div>
        <Head :title="`Edit ${group.name}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to groups
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title"><h2 class="fw-bold">Edit Group</h2></div>
            </div>
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5 mb-5">
                        <div class="col-md-8">
                            <label
                                class="form-label required"
                                for="grp-name"
                            >Name</label>
                            <input
                                id="grp-name"
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
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check form-switch ms-2">
                                <input
                                    id="grp-active"
                                    v-model="form.is_active"
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                />
                                <label
                                    class="form-check-label"
                                    for="grp-active"
                                >Active</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label
                                class="form-label"
                                for="grp-desc"
                            >Description</label>
                            <textarea
                                id="grp-desc"
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
