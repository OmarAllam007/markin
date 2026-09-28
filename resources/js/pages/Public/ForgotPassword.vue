<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { store as sendResetLink } from '@/actions/App/Http/Controllers/Auth/PasswordResetLinkController';
import { login } from '@/routes/index';

const flash = computed(() => (usePage().props.flash as { success?: string }) ?? {});
const form = useForm({ email: '' });

const submit = () => form.post(sendResetLink.url());
</script>

<template>
    <Head title="Forgot password" />

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
            <section class="form-section" aria-labelledby="forgot-title">
                <div class="form-wrap">
                    <header class="form-heading">
                        <p>Account recovery</p>
                        <h2 id="forgot-title">Forgot your password?</h2>
                        <span>Enter your work email and we'll send you a link to reset it.</span>
                    </header>

                    <p v-if="flash.success" class="status-note">{{ flash.success }}</p>

                    <form novalidate @submit.prevent="submit">
                        <div class="field">
                            <label for="email">Work email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                placeholder="name@company.com"
                                autocomplete="username"
                                :aria-invalid="Boolean(form.errors.email)"
                                required
                                autofocus
                            />
                            <p v-if="form.errors.email" class="field-error" role="alert">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <button class="submit" type="submit" :disabled="form.processing">
                            <span>{{ form.processing ? 'Sending…' : 'Send reset link' }}</span>
                        </button>
                    </form>

                    <p class="register-prompt">
                        Remembered your password?
                        <Link :href="login.url()">Sign in</Link>
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>

<style>
@import './auth-shared.css';
</style>
