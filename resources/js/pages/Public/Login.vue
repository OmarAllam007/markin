<script setup lang="ts">
import { store as loginStore } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { register } from '@/routes/index';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(loginStore.url());
};
</script>

<template>
    <div class="d-flex flex-column flex-lg-row flex-column-fluid min-h-100 min-h-lg-100">
        <Head title="Log in" />
        <!--begin::Form-->
        <div
            class="d-flex flex-center flex-lg-row-fluid w-lg-50 p-8 p-lg-15 order-2 order-lg-1"
        >
            <div class="w-100" style="max-width: 440px">
                <div class="text-center mb-10">
                    <h1 class="text-gray-900 fs-2x fw-bolder">Sign in</h1>
                    <div class="text-gray-600 fs-5 fw-semibold mt-1">
                        Your company workspace
                    </div>
                </div>
                <form
                    class="form w-100"
                    novalidate
                    @submit.prevent="submit"
                >
                    <div
                        v-if="form.errors.email"
                        class="mb-5 alert alert-danger"
                    >
                        {{ form.errors.email }}
                    </div>
                    <div class="mb-4">
                        <label
                            for="email"
                            class="form-label fw-semibold text-gray-900"
                            >Email</label
                        >
                        <input
                            id="email"
                            v-model="form.email"
                            name="email"
                            type="email"
                            class="form-control"
                            :class="{
                                'is-invalid': form.errors.email,
                            }"
                            autocomplete="username"
                            required
                        />
                    </div>
                    <div class="mb-4">
                        <div
                            class="d-flex flex-stack fs-base fw-semibold text-gray-900 mb-1"
                        >
                            <label for="password">Password</label>
                        </div>
                        <input
                            id="password"
                            v-model="form.password"
                            name="password"
                            type="password"
                            class="form-control"
                            :class="{
                                'is-invalid': form.errors.password,
                            }"
                            autocomplete="current-password"
                            required
                        />
                    </div>
                    <div class="mb-8 form-check form-check-custom form-check-solid">
                        <input
                            id="remember"
                            v-model="form.remember"
                            name="remember"
                            class="form-check-input"
                            type="checkbox"
                        />
                        <label
                            class="form-check-label"
                            for="remember"
                        >
                            Remember me
                        </label>
                    </div>
                    <div class="d-flex flex-center flex-column">
                        <button
                            type="submit"
                            class="btn btn-primary w-100 mb-4"
                            :disabled="form.processing"
                        >
                            <span
                                v-if="form.processing"
                                class="spinner-border spinner-border-sm me-2"
                            />
                            Sign in
                        </button>
                    </div>
                </form>
                <div class="text-center text-gray-600 fs-5 mt-8">
                    <span>Not a member yet?</span>
                    <Link
                        :href="register.url()"
                        class="link-primary fs-5 fw-bold ms-1"
                    >Sign up</Link
                    >
                </div>
            </div>
        </div>
        <!--end::Form-->
        <!--begin::Hero-->
        <div
            class="d-flex flex-lg-row-fluid w-lg-50 order-1 order-lg-2 min-h-250px min-h-lg-500px p-8 p-lg-15 align-items-center bgi-size-cover bgi-position-center"
            style="
                background: linear-gradient(135deg, #3f6ad8 0%, #2c5aa0 100%);
            "
        >
            <div class="text-center mx-auto" style="max-width: 420px">
                <h2 class="text-white fs-1 fw-bold mb-4">Fast, efficient, productive</h2>
                <p class="text-white text-opacity-75 fs-4 fw-normal mb-0">
                    Manage your team, attendance, and company settings in one
                    place.
                </p>
            </div>
        </div>
        <!--end::Hero-->
    </div>
</template>
