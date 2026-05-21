<script setup lang="ts">
import { edit, update } from '@/actions/App/Http/Controllers/ProfileController';
import { Head, Link, useForm } from '@inertiajs/vue3';

type UserProfile = {
    name: string;
    email: string;
    country_code: string;
    mobile: string;
};

const props = defineProps<{ user: UserProfile }>();

const form = useForm({
    email: props.user.email,
    country_code: props.user.country_code,
    mobile: props.user.mobile,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(update.url());
};
</script>

<template>
    <div>
        <Head title="My Profile" />
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">My Profile</h2>
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
                                class="form-label"
                                for="name"
                                >Name</label
                            >
                            <input
                                id="name"
                                type="text"
                                class="form-control"
                                :value="user.name"
                                disabled
                            />
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
                                :class="{ 'is-invalid': form.errors.email }"
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
                                :class="{ 'is-invalid': form.errors.country_code }"
                            />
                            <div
                                v-if="form.errors.country_code"
                                class="invalid-feedback"
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
                                :class="{ 'is-invalid': form.errors.mobile }"
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
                                class="form-label"
                                for="pw"
                                >New password</label
                            >
                            <input
                                id="pw"
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.password }"
                                placeholder="Leave blank to keep current"
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
                                class="form-label"
                                for="pwc"
                                >Confirm new password</label
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
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
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
