<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import { store as resetPassword } from '@/actions/App/Http/Controllers/Auth/NewPasswordController';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => form.post(resetPassword.url(), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head title="Reset password" />

    <main class="login-page">
        <header class="page-header">
            <div class="brand" aria-label="Markin">
                <span class="brand-symbol" aria-hidden="true"
                    ><i></i><i></i><i></i
                ></span>
                <span>markin</span>
            </div>
        </header>

        <div class="page-content-single">
            <section class="form-section" aria-labelledby="reset-title">
                <div class="form-wrap">
                    <header class="form-heading">
                        <p>Account recovery</p>
                        <h2 id="reset-title">Choose a new password</h2>
                        <span>Set a new password for {{ email }}.</span>
                    </header>

                    <form novalidate @submit.prevent="submit">
                        <div class="field">
                            <label for="email">Work email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                autocomplete="username"
                                :aria-invalid="Boolean(form.errors.email)"
                                required
                            />
                            <p v-if="form.errors.email" class="field-error" role="alert">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="field">
                            <label for="password">New password</label>
                            <input
                                id="password"
                                v-model="form.password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                :aria-invalid="Boolean(form.errors.password)"
                                required
                                autofocus
                            />
                            <p v-if="form.errors.password" class="field-error" role="alert">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="field">
                            <label for="password_confirmation">Confirm new password</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                            />
                        </div>

                        <button class="submit" type="submit" :disabled="form.processing">
                            <span>{{ form.processing ? 'Resetting…' : 'Reset password' }}</span>
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</template>

<style>
@import './auth-shared.css';
</style>
