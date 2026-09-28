<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { store as resendVerification } from '@/actions/App/Http/Controllers/Auth/EmailVerificationNotificationController';
import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';

defineProps<{ email: string }>();

const flash = computed(() => (usePage().props.flash as { success?: string }) ?? {});
const form = useForm({});

const resend = () => form.post(resendVerification.url());
</script>

<template>
    <Head title="Verify email" />

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
            <section class="form-section" aria-labelledby="verify-title">
                <div class="form-wrap">
                    <header class="form-heading">
                        <p>One more step</p>
                        <h2 id="verify-title">Verify your email</h2>
                        <span>We sent a verification link to <strong>{{ email }}</strong>. Click it to activate your account.</span>
                    </header>

                    <p v-if="flash.success" class="status-note">{{ flash.success }}</p>

                    <button class="submit" type="button" :disabled="form.processing" @click="resend">
                        <span>{{ form.processing ? 'Sending…' : 'Resend verification email' }}</span>
                    </button>

                    <button class="secondary-action" type="button" @click="form.post(logout.url())">
                        Log out
                    </button>
                </div>
            </section>
        </div>
    </main>
</template>

<style>
@import './auth-shared.css';
</style>
