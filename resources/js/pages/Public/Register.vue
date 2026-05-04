<script setup lang="ts">
import { store as registerStore } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { login } from '@/routes/index';
import { Head, Link, useForm } from '@inertiajs/vue3';

const countryCodes = [
    { code: '+1', label: '+1 USA / Canada' },
    { code: '+7', label: '+7 Russia' },
    { code: '+20', label: '+20 Egypt' },
    { code: '+27', label: '+27 South Africa' },
    { code: '+30', label: '+30 Greece' },
    { code: '+31', label: '+31 Netherlands' },
    { code: '+32', label: '+32 Belgium' },
    { code: '+33', label: '+33 France' },
    { code: '+34', label: '+34 Spain' },
    { code: '+36', label: '+36 Hungary' },
    { code: '+39', label: '+39 Italy' },
    { code: '+40', label: '+40 Romania' },
    { code: '+41', label: '+41 Switzerland' },
    { code: '+43', label: '+43 Austria' },
    { code: '+44', label: '+44 United Kingdom' },
    { code: '+45', label: '+45 Denmark' },
    { code: '+46', label: '+46 Sweden' },
    { code: '+47', label: '+47 Norway' },
    { code: '+48', label: '+48 Poland' },
    { code: '+49', label: '+49 Germany' },
    { code: '+51', label: '+51 Peru' },
    { code: '+52', label: '+52 Mexico' },
    { code: '+54', label: '+54 Argentina' },
    { code: '+55', label: '+55 Brazil' },
    { code: '+56', label: '+56 Chile' },
    { code: '+57', label: '+57 Colombia' },
    { code: '+58', label: '+58 Venezuela' },
    { code: '+60', label: '+60 Malaysia' },
    { code: '+61', label: '+61 Australia' },
    { code: '+62', label: '+62 Indonesia' },
    { code: '+63', label: '+63 Philippines' },
    { code: '+64', label: '+64 New Zealand' },
    { code: '+65', label: '+65 Singapore' },
    { code: '+66', label: '+66 Thailand' },
    { code: '+81', label: '+81 Japan' },
    { code: '+82', label: '+82 South Korea' },
    { code: '+84', label: '+84 Vietnam' },
    { code: '+86', label: '+86 China' },
    { code: '+90', label: '+90 Turkey' },
    { code: '+91', label: '+91 India' },
    { code: '+92', label: '+92 Pakistan' },
    { code: '+94', label: '+94 Sri Lanka' },
    { code: '+98', label: '+98 Iran' },
    { code: '+212', label: '+212 Morocco' },
    { code: '+213', label: '+213 Algeria' },
    { code: '+216', label: '+216 Tunisia' },
    { code: '+218', label: '+218 Libya' },
    { code: '+220', label: '+220 Gambia' },
    { code: '+221', label: '+221 Senegal' },
    { code: '+234', label: '+234 Nigeria' },
    { code: '+254', label: '+254 Kenya' },
    { code: '+255', label: '+255 Tanzania' },
    { code: '+256', label: '+256 Uganda' },
    { code: '+260', label: '+260 Zambia' },
    { code: '+263', label: '+263 Zimbabwe' },
    { code: '+351', label: '+351 Portugal' },
    { code: '+353', label: '+353 Ireland' },
    { code: '+358', label: '+358 Finland' },
    { code: '+380', label: '+380 Ukraine' },
    { code: '+420', label: '+420 Czech Republic' },
    { code: '+966', label: '+966 Saudi Arabia' },
    { code: '+961', label: '+961 Lebanon' },
    { code: '+962', label: '+962 Jordan' },
    { code: '+964', label: '+964 Iraq' },
    { code: '+965', label: '+965 Kuwait' },
    { code: '+968', label: '+968 Oman' },
    { code: '+971', label: '+971 UAE' },
    { code: '+972', label: '+972 Israel' },
    { code: '+974', label: '+974 Qatar' },
    { code: '+880', label: '+880 Bangladesh' },
];

const form = useForm({
    company_name: '',
    number_of_employees: 10 as number | null,
    name: '',
    country_code: '+1',
    mobile: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(registerStore.url());
};
</script>

<template>
    <div class="d-flex flex-column flex-lg-row flex-column-fluid min-h-100 min-h-lg-100">
        <Head title="Create account" />
        <!--begin::Form-->
        <div
            class="d-flex flex-center flex-lg-row-fluid w-lg-50 p-8 p-lg-15 order-2 order-lg-1"
        >
            <div class="w-100" style="max-width: 520px">
                <div class="text-center mb-8">
                    <h1 class="text-gray-900 fs-2x fw-bolder">Create account</h1>
                    <div class="text-gray-600 fs-5 fw-semibold mt-1">
                        Set up your company and admin profile
                    </div>
                </div>
                <form
                    class="form w-100"
                    novalidate
                    @submit.prevent="submit"
                >
                    <h3 class="fs-5 fw-bold text-gray-800 mb-4">Company</h3>
                    <div class="row g-3 mb-5">
                        <div class="col-md-7">
                            <label
                                for="company_name"
                                class="form-label fw-semibold"
                                >Company name</label
                            >
                            <input
                                id="company_name"
                                v-model="form.company_name"
                                type="text"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.company_name,
                                }"
                            />
                            <div
                                v-if="form.errors.company_name"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.company_name }}
                            </div>
                        </div>
                        <div class="col-md-5">
                            <label
                                for="number_of_employees"
                                class="form-label fw-semibold"
                                >Employees</label
                            >
                            <input
                                id="number_of_employees"
                                v-model.number="form.number_of_employees"
                                type="number"
                                min="1"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.number_of_employees,
                                }"
                            />
                            <div
                                v-if="form.errors.number_of_employees"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.number_of_employees }}
                            </div>
                        </div>
                    </div>
                    <p class="text-muted fs-7 mb-6">
                        If this company name already exists, you will be added
                        to that company. Headcount is saved when the company is
                        first created.
                    </p>
                    <h3 class="fs-5 fw-bold text-gray-800 mb-4">Your profile</h3>
                    <div class="mb-4">
                        <label
                            for="name"
                            class="form-label fw-semibold"
                            >Full name</label
                        >
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="form-control"
                            :class="{ 'is-invalid': form.errors.name }"
                            autocomplete="name"
                        />
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-4">
                            <label
                                for="country_code"
                                class="form-label fw-semibold"
                                >Code</label
                            >
                            <select
                                id="country_code"
                                v-model="form.country_code"
                                class="form-select"
                                :class="{
                                    'is-invalid': form.errors.country_code,
                                }"
                            >
                                <option
                                    v-for="c in countryCodes"
                                    :key="c.code"
                                    :value="c.code"
                                >
                                    {{ c.label }}
                                </option>
                            </select>
                            <div
                                v-if="form.errors.country_code"
                                class="invalid-feedback d-block"
                            >
                                {{ form.errors.country_code }}
                            </div>
                        </div>
                        <div class="col-sm-8">
                            <label
                                for="mobile"
                                class="form-label fw-semibold"
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
                        </div>
                    </div>
                    <div class="mb-4">
                        <label
                            for="email"
                            class="form-label fw-semibold"
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
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label
                                for="password"
                                class="form-label fw-semibold"
                                >Password</label
                            >
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="form-control"
                                :class="{
                                    'is-invalid': form.errors.password,
                                }"
                                autocomplete="new-password"
                            />
                        </div>
                        <div class="col-sm-6">
                            <label
                                for="password_confirmation"
                                class="form-label fw-semibold"
                                >Confirm</label
                            >
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                class="form-control"
                                autocomplete="new-password"
                            />
                        </div>
                    </div>
                    <p class="text-muted fs-7 mb-4">
                        Use 8 or more characters with a mix of letters, numbers
                        &amp; symbols.
                    </p>
                    <button
                        type="submit"
                        class="btn btn-primary w-100"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Sign up
                    </button>
                </form>
                <div class="text-center text-gray-600 fs-5 mt-8">
                    <span>Already have an account?</span>
                    <Link
                        :href="login.url()"
                        class="link-primary fs-5 fw-bold ms-1"
                    >Sign in</Link
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
                    Register your organization and start managing your team
                    in minutes.
                </p>
            </div>
        </div>
        <!--end::Hero-->
    </div>
</template>
