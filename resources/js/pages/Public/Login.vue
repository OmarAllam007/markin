<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import { store as loginStore } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { register } from '@/routes/index';

type Language = 'en' | 'ar';

const currentLanguage = ref<Language>('en');
const showPassword = ref(false);
const form = useForm({ email: '', password: '', remember: false });

const copy = {
    en: {
        pageTitle: 'Log in',
        signIn: 'Sign in',
        intro: 'Good work starts with knowing where the day stands.',
        description:
            'Markin keeps attendance, shifts, and people operations in one place—so your team can spend less time checking and more time moving.',
        attendance: 'Attendance',
        attendanceText: 'A reliable view of every workday.',
        schedules: 'Schedules',
        schedulesText: 'Shifts that stay clear for everyone.',
        people: 'People',
        peopleText: 'The records your business depends on.',
        welcome: 'Welcome back',
        heading: 'Continue to your workspace',
        subheading: 'Use your company account to sign in.',
        email: 'Work email',
        emailPlaceholder: 'name@company.com',
        password: 'Password',
        passwordPlaceholder: 'Enter your password',
        showPassword: 'Show password',
        hidePassword: 'Hide password',
        remember: 'Keep me signed in',
        signingIn: 'Signing in…',
        submit: 'Sign in',
        newToMarkin: 'New to Markin?',
        createWorkspace: 'Create a workspace',
        encrypted: 'Protected with encrypted data transfer',
        language: 'Language',
    },
    ar: {
        pageTitle: 'تسجيل الدخول',
        signIn: 'تسجيل الدخول',
        intro: 'يبدأ العمل الجيد بمعرفة أين يقف يومك.',
        description:
            'يجمع ماركن الحضور والمناوبات وعمليات الموظفين في مكان واحد، لتقضي وقتًا أقل في المتابعة ووقتًا أكثر في الإنجاز.',
        attendance: 'الحضور',
        attendanceText: 'رؤية موثوقة لكل يوم عمل.',
        schedules: 'الجداول',
        schedulesText: 'مناوبات واضحة للجميع.',
        people: 'الموظفون',
        peopleText: 'السجلات التي يعتمد عليها عملك.',
        welcome: 'مرحبًا بعودتك',
        heading: 'تابع إلى مساحة عملك',
        subheading: 'استخدم حساب شركتك لتسجيل الدخول.',
        email: 'البريد الإلكتروني للعمل',
        emailPlaceholder: 'name@company.com',
        password: 'كلمة المرور',
        passwordPlaceholder: 'أدخل كلمة المرور',
        showPassword: 'إظهار كلمة المرور',
        hidePassword: 'إخفاء كلمة المرور',
        remember: 'إبقائي مسجّلًا للدخول',
        signingIn: 'جارٍ تسجيل الدخول…',
        submit: 'تسجيل الدخول',
        newToMarkin: 'جديد في ماركن؟',
        createWorkspace: 'أنشئ مساحة عمل',
        encrypted: 'نقل بيانات مشفّر ومحمي',
        language: 'اللغة',
    },
} as const;

const text = computed(() => copy[currentLanguage.value]);

function applyLanguage(language: Language): void {
    currentLanguage.value = language;
    localStorage.setItem('app-lang', language);
    document.documentElement.setAttribute('lang', language);
    document.documentElement.setAttribute('dir', 'ltr');
}

onMounted(() => {
    const storedLanguage = localStorage.getItem('app-lang');
    applyLanguage(storedLanguage === 'ar' ? 'ar' : 'en');
});

const submit = () => form.post(loginStore.url());
</script>

<template>
    <Head :title="text.pageTitle" />

    <main class="login-page" :dir="currentLanguage === 'ar' ? 'rtl' : 'ltr'">
        <header class="page-header">
            <div class="brand" aria-label="Markin">
                <span class="brand-symbol" aria-hidden="true"
                    ><i></i><i></i><i></i
                ></span>
                <span>markin</span>
            </div>

            <div class="language-switcher" :aria-label="text.language">
                <button
                    type="button"
                    :class="{ active: currentLanguage === 'en' }"
                    :aria-pressed="currentLanguage === 'en'"
                    @click="applyLanguage('en')"
                >
                    EN
                </button>
                <span aria-hidden="true"></span>
                <button
                    type="button"
                    :class="{ active: currentLanguage === 'ar' }"
                    :aria-pressed="currentLanguage === 'ar'"
                    @click="applyLanguage('ar')"
                >
                    ع
                </button>
            </div>
        </header>

        <div class="page-content">
            <section class="product-intro" aria-labelledby="product-title">
                <p class="section-index">Markin / 01</p>
                <h1 id="product-title">{{ text.intro }}</h1>
                <p class="intro-copy">{{ text.description }}</p>

                <dl class="product-details">
                    <div>
                        <dt>{{ text.attendance }}</dt>
                        <dd>{{ text.attendanceText }}</dd>
                    </div>
                    <div>
                        <dt>{{ text.schedules }}</dt>
                        <dd>{{ text.schedulesText }}</dd>
                    </div>
                    <div>
                        <dt>{{ text.people }}</dt>
                        <dd>{{ text.peopleText }}</dd>
                    </div>
                </dl>
            </section>

            <section class="form-section" aria-labelledby="login-title">
                <div class="form-wrap">
                    <header class="form-heading">
                        <p>{{ text.welcome }}</p>
                        <h2 id="login-title">{{ text.heading }}</h2>
                        <span>{{ text.subheading }}</span>
                    </header>

                    <form novalidate @submit.prevent="submit">
                        <div class="field">
                            <label for="email">{{ text.email }}</label>
                            <input
                                id="email"
                                v-model="form.email"
                                name="email"
                                type="email"
                                :placeholder="text.emailPlaceholder"
                                autocomplete="username"
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="
                                    form.errors.email
                                        ? 'email-error'
                                        : undefined
                                "
                                required
                                autofocus
                            />
                            <p
                                v-if="form.errors.email"
                                id="email-error"
                                class="field-error"
                                role="alert"
                            >
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="field">
                            <div class="password-label">
                                <label for="password">{{
                                    text.password
                                }}</label>
                                <button
                                    type="button"
                                    :aria-label="
                                        showPassword
                                            ? text.hidePassword
                                            : text.showPassword
                                    "
                                    :aria-pressed="showPassword"
                                    @click="showPassword = !showPassword"
                                >
                                    {{
                                        showPassword
                                            ? text.hidePassword
                                            : text.showPassword
                                    }}
                                </button>
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                name="password"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="text.passwordPlaceholder"
                                autocomplete="current-password"
                                :aria-invalid="Boolean(form.errors.password)"
                                :aria-describedby="
                                    form.errors.password
                                        ? 'password-error'
                                        : undefined
                                "
                                required
                            />
                            <p
                                v-if="form.errors.password"
                                id="password-error"
                                class="field-error"
                                role="alert"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <label class="remember" for="remember">
                            <input
                                id="remember"
                                v-model="form.remember"
                                name="remember"
                                type="checkbox"
                            />
                            <span aria-hidden="true"
                                ><svg viewBox="0 0 12 10">
                                    <path d="m1 5 3 3 7-7" /></svg
                            ></span>
                            {{ text.remember }}
                        </label>

                        <button
                            class="submit"
                            type="submit"
                            :disabled="form.processing"
                        >
                            <span>{{
                                form.processing ? text.signingIn : text.submit
                            }}</span>
                            <svg
                                v-if="!form.processing"
                                viewBox="0 0 20 20"
                                aria-hidden="true"
                            >
                                <path d="M4 10h12m-5-5 5 5-5 5" />
                            </svg>
                            <i v-else aria-hidden="true"></i>
                        </button>
                    </form>

                    <p class="register-prompt">
                        {{ text.newToMarkin }}
                        <Link :href="register.url()">{{
                            text.createWorkspace
                        }}</Link>
                    </p>

                    <p class="security-note">
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <rect x="4" y="8" width="12" height="9" rx="2" />
                            <path d="M7 8V6a3 3 0 0 1 6 0v2" />
                        </svg>
                        {{ text.encrypted }}
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>

<style scoped>
.login-page {
    --ink: #182522;
    --muted: #67716e;
    --line: #dfe4e1;
    --green: #1f614f;
    --paper: #f4f6f3;
    min-height: 100vh;
    color: var(--ink);
    background: var(--paper);
    font-family: 'Instrument Sans', sans-serif;
}

.page-header {
    height: 6.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-inline: clamp(1.5rem, 4.5vw, 5rem);
    border-bottom: 1px solid var(--line);
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    color: var(--ink);
    font-size: 1.35rem;
    font-weight: 650;
    letter-spacing: -0.045em;
    text-decoration: none;
}

.brand-symbol {
    width: 1.85rem;
    height: 1.85rem;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 3px;
    padding: 0.4rem;
    border-radius: 0.42rem;
    background: var(--green);
}

.brand-symbol i {
    width: 3px;
    border-radius: 2px;
    background: #fff;
}
.brand-symbol i:nth-child(1) {
    height: 42%;
}
.brand-symbol i:nth-child(2) {
    height: 100%;
}
.brand-symbol i:nth-child(3) {
    height: 66%;
}

.language-switcher {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.35rem;
    border: 1px solid #d6dcd8;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.5);
}

.language-switcher button {
    min-width: 2.25rem;
    height: 2rem;
    padding: 0 0.55rem;
    border: 0;
    border-radius: 999px;
    color: #7a8481;
    background: transparent;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 650;
    cursor: pointer;
}

.language-switcher button.active {
    color: #fff;
    background: var(--ink);
}
.language-switcher button:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 2px;
}
.language-switcher > span {
    width: 1px;
    height: 0.9rem;
    background: #d6dcd8;
}

.page-content {
    min-height: calc(100vh - 6.75rem);
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(32rem, 0.76fr);
}

.product-intro {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(4rem, 8vw, 8.5rem) clamp(2rem, 7vw, 8rem);
    border-inline-end: 1px solid var(--line);
}

.section-index {
    margin: 0 0 2rem;
    color: var(--green);
    font-size: 0.67rem;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
}

.product-intro h1 {
    max-width: 48rem;
    margin: 0;
    font-size: clamp(3rem, 5.7vw, 6.4rem);
    font-weight: 520;
    line-height: 0.98;
    letter-spacing: -0.066em;
}

.intro-copy {
    max-width: 38rem;
    margin: 2.1rem 0 0;
    color: var(--muted);
    font-size: clamp(1rem, 1.25vw, 1.15rem);
    line-height: 1.75;
}

.product-details {
    max-width: 48rem;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: clamp(1.5rem, 3vw, 3.5rem);
    margin: clamp(4.5rem, 10vh, 8rem) 0 0;
    padding-top: 1.4rem;
    border-top: 1px solid #cfd6d2;
}

.product-details div {
    min-width: 0;
}
.product-details dt {
    margin-bottom: 0.45rem;
    font-size: 0.77rem;
    font-weight: 700;
}
.product-details dd {
    margin: 0;
    color: #747e7b;
    font-size: 0.76rem;
    line-height: 1.5;
}

.form-section {
    display: grid;
    place-items: center;
    padding: clamp(3rem, 6vw, 7rem);
    background: #fff;
}

.form-wrap {
    width: min(100%, 26.5rem);
}
.form-heading {
    margin-bottom: 2.7rem;
}
.form-heading p {
    margin: 0 0 0.85rem;
    color: var(--green);
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}
.form-heading h2 {
    margin: 0;
    font-size: clamp(2rem, 2.8vw, 2.65rem);
    font-weight: 570;
    line-height: 1.12;
    letter-spacing: -0.05em;
}
.form-heading span {
    display: block;
    margin-top: 0.8rem;
    color: var(--muted);
    font-size: 0.9rem;
}
.field {
    margin-bottom: 1.45rem;
}
.field label {
    display: block;
    margin-bottom: 0.55rem;
    font-size: 0.78rem;
    font-weight: 650;
}
.field input {
    width: 100%;
    height: 3.5rem;
    padding: 0 0.95rem;
    border: 1px solid #cbd3cf;
    border-radius: 0.42rem;
    outline: 0;
    color: var(--ink);
    background: #fff;
    font: inherit;
    font-size: 0.9rem;
    transition:
        border-color 130ms ease,
        box-shadow 130ms ease;
}

.field input::placeholder {
    color: #a0a8a5;
}
.field input:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(31, 97, 79, 0.1);
}
.field input[aria-invalid='true'] {
    border-color: #bd604d;
}
.field-error {
    margin: 0.4rem 0 0;
    color: #a94735;
    font-size: 0.73rem;
}
.password-label {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.password-label button {
    margin-bottom: 0.55rem;
    padding: 0;
    border: 0;
    color: var(--green);
    background: transparent;
    font: inherit;
    font-size: 0.72rem;
    font-weight: 600;
    cursor: pointer;
}
.password-label button:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
}
.password-label button:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 3px;
}

.remember {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.65rem;
    margin: 0.1rem 0 1.7rem;
    color: #55615d;
    font-size: 0.78rem;
    cursor: pointer;
}

.remember input {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
}
.remember > span {
    width: 1.05rem;
    height: 1.05rem;
    display: grid;
    place-items: center;
    border: 1px solid #b8c2be;
    border-radius: 0.2rem;
    background: #fff;
}
.remember svg {
    width: 0.62rem;
    fill: none;
    stroke: #fff;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
    opacity: 0;
}
.remember input:checked + span {
    border-color: var(--green);
    background: var(--green);
}
.remember input:checked + span svg {
    opacity: 1;
}
.remember input:focus-visible + span {
    outline: 3px solid rgba(31, 97, 79, 0.15);
    outline-offset: 2px;
}

.submit {
    width: 100%;
    height: 3.55rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    border: 1px solid var(--ink);
    border-radius: 0.42rem;
    color: #fff;
    background: var(--ink);
    font: inherit;
    font-size: 0.84rem;
    font-weight: 650;
    cursor: pointer;
    transition:
        background 130ms ease,
        transform 130ms ease;
}

.submit:hover:not(:disabled) {
    background: var(--green);
    border-color: var(--green);
}
.submit:active:not(:disabled) {
    transform: translateY(1px);
}
.submit:focus-visible {
    outline: 3px solid rgba(31, 97, 79, 0.2);
    outline-offset: 3px;
}
.submit:disabled {
    opacity: 0.65;
    cursor: wait;
}
.submit svg {
    width: 1rem;
    fill: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.7;
}
.submit i {
    width: 0.9rem;
    height: 0.9rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spin 700ms linear infinite;
}
.register-prompt {
    margin: 1.6rem 0 0;
    color: #707a77;
    text-align: center;
    font-size: 0.8rem;
}
.register-prompt a {
    margin-inline-start: 0.25rem;
    color: var(--green);
    font-weight: 650;
    text-decoration: none;
}
.register-prompt a:hover {
    text-decoration: underline;
    text-underline-offset: 3px;
}
.register-prompt a:focus-visible {
    outline: 2px solid var(--green);
    outline-offset: 3px;
}
.security-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin: 4rem 0 0;
    color: #949c99;
    font-size: 0.68rem;
}
.security-note svg {
    width: 0.85rem;
    fill: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 1.4;
}

.login-page[dir='rtl'] .submit svg {
    transform: scaleX(-1);
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 960px) {
    .page-content {
        grid-template-columns: 1fr 1fr;
    }
    .product-intro {
        padding-inline: clamp(2rem, 5vw, 4rem);
    }
    .product-details {
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-top: 3rem;
    }
    .product-details div {
        display: grid;
        grid-template-columns: 6rem 1fr;
        gap: 1rem;
    }
    .form-section {
        padding-inline: clamp(2rem, 5vw, 4rem);
    }
}

@media (max-width: 720px) {
    .page-header {
        height: 5.5rem;
        padding-inline: 1.25rem;
        background: #fff;
    }
    .page-content {
        min-height: calc(100vh - 5.5rem);
        display: block;
    }
    .product-intro {
        display: none;
    }
    .form-section {
        min-height: calc(100vh - 5.5rem);
        padding: 3.5rem 1.25rem 2rem;
    }
    .security-note {
        margin-top: 3rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .field input,
    .submit {
        transition: none;
    }
    .submit i {
        animation: none;
    }
}
</style>
