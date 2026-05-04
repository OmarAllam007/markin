<script setup lang="ts">
import { index, update } from '@/routes/users';
import { Head, Link, useForm } from '@inertiajs/vue3';

type StatusOption = { value: string; label: string; color: string };
type UserModel = {
    id: number;
    name: string;
    email: string;
    country_code: string;
    mobile: string;
    is_admin: boolean;
    is_supervisor: boolean;
    status: string;
};

const props = defineProps<{
    user: UserModel;
    isSelf: boolean;
    statuses: StatusOption[];
}>();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    country_code: props.user.country_code,
    mobile: props.user.mobile,
    password: '',
    password_confirmation: '',
    is_admin: props.user.is_admin,
    is_supervisor: props.user.is_supervisor,
    status: props.user.status,
});

const submit = () => {
    form.put(update.url({ user: props.user.id }));
};
</script>

<template>
    <div>
        <Head :title="`Edit ${user.name}`" />
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
                    <h2 class="fw-bold">Edit user</h2>
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
                                :disabled="isSelf"
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
                            />
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
                            />
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
                                :class="{
                                    'is-invalid': form.errors.password,
                                }"
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
                    <div
                        v-if="!isSelf"
                        class="row g-5 mb-5"
                    >
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
                    <div
                        v-if="!isSelf"
                        class="row g-5 mb-5"
                    >
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
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
