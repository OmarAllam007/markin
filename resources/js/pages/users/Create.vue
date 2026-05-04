<script setup lang="ts">
import { store, index } from '@/routes/users';
import { Head, Link, useForm } from '@inertiajs/vue3';

type StatusOption = { value: string; label: string; color: string };

const props = defineProps<{
    statuses: StatusOption[];
}>();

const form = useForm({
    name: '',
    email: '',
    country_code: '+1',
    mobile: '',
    password: '',
    password_confirmation: '',
    is_admin: false,
    is_supervisor: false,
    status: 'active',
});

const submit = () => {
    form.post(store.url());
};
</script>

<template>
    <div>
        <Head title="New user" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to users
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">New admin user</h2>
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
                                for="name"
                                >Name</label
                            >
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                autocomplete="name"
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
                                class="form-label required"
                                for="email"
                                >Email</label
                            >
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.email,
                                }"
                                autocomplete="email"
                            />
                            <div
                                v-if="form.errors.email"
                                class="invalid-feedback"
                            >
                                {{ form.errors.email }}
                            </div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-2">
                            <label
                                class="form-label required"
                                for="cc"
                                >Country code</label
                            >
                            <input
                                id="cc"
                                v-model="form.country_code"
                                type="text"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.country_code,
                                }"
                                placeholder="+1"
                            />
                            <div
                                v-if="form.errors.country_code"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.country_code }}
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label required"
                                for="mobile"
                                >Mobile</label
                            >
                            <input
                                id="mobile"
                                v-model="form.mobile"
                                type="text"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.mobile,
                                }"
                            />
                            <div
                                v-if="form.errors.mobile"
                                class="invalid-feedback"
                            >
                                {{ form.errors.mobile }}
                            </div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="pw"
                                >Password</label
                            >
                            <input
                                id="pw"
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.password,
                                }"
                                autocomplete="new-password"
                            />
                            <div
                                v-if="form.errors.password"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.password }}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="pwc"
                                >Confirm password</label
                            >
                            <input
                                id="pwc"
                                v-model="form.password_confirmation"
                                type="password"
                                class="form-control"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-6 d-flex flex-column gap-2">
                            <div class="form-check form-check-custom form-check-solid">
                                <input
                                    id="ia"
                                    v-model="form.is_admin"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="ia"
                                >
                                    Is admin
                                </label>
                            </div>
                            <div
                                v-if="form.errors.is_admin"
                                class="text-danger fs-7"
                            >
                                {{ form.errors.is_admin }}
                            </div>
                        </div>
                        <div class="col-md-6 d-flex flex-column gap-2">
                            <div class="form-check form-check-custom form-check-solid">
                                <input
                                    id="is"
                                    v-model="form.is_supervisor"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="is"
                                >
                                    Is supervisor
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="row g-5 mb-5">
                        <div class="col-md-4">
                            <label
                                class="form-label"
                                for="st"
                                >Status</label
                            >
                            <select
                                id="st"
                                v-model="form.status"
                                class="form-select"
                            >
                                <option
                                    v-for="s in statuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
                <div
                    class="card-footer d-flex justify-content-end gap-2"
                >
                    <Link
                        :href="index.url()"
                        class="btn btn-light"
                        >Cancel</Link
                    >
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Create user
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
