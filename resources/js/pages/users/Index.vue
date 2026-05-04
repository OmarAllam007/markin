<script setup lang="ts">
import { index as usersIndex, create as usersCreate, edit as usersEdit, destroy as usersDestroy } from '@/routes/users';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type Tenant = {
    id: number;
    name: string;
};

type UserRow = {
    id: number;
    name: string;
    email: string;
    country_code: string;
    mobile: string;
    is_admin: boolean;
    is_supervisor: boolean;
    preferred_theme: string;
    preferred_language: string;
    status: string;
    tenant?: Tenant | null;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    users: {
        data: UserRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
    filters: { search: string | null; tenant_id: string | number | null };
    tenants: Tenant[];
}>();

type AuthUser = { id: number };

const page = usePage<{
    auth: { user: AuthUser };
}>();

const filterForm = useForm({
    search: props.filters?.search ?? '',
    tenant_id: props.filters?.tenant_id ? String(props.filters.tenant_id) : '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) {
        q.search = filterForm.search;
    }
    if (filterForm.tenant_id) {
        q.tenant_id = filterForm.tenant_id;
    }
    filterForm.get(usersIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['users', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(
    () => filterForm.search,
    () => debouncedSubmit(),
);

const onTenantChange = () => {
    submitFilters();
};

const statusClass = (status: string) => {
    if (status === 'active') {
        return 'badge-light-success';
    }
    if (status === 'inactive') {
        return 'badge-light-warning';
    }
    return 'badge-light-danger';
};

const removeUser = (u: UserRow) => {
    if (!window.confirm(`Delete user "${u.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(usersDestroy.url({ user: u.id }));
};

const formatBool = (v: boolean) => (v ? 'Yes' : 'No');
</script>

<template>
    <div>
        <Head title="Users" />

        <div class="card">
            <div
                class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3"
            >
                <div class="card-title">
                    <h2 class="fw-bold">Admin users</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i
                            class="ki-outline ki-magnifier fs-3 position-absolute ms-5"
                        />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search name, email, mobile…"
                            autocomplete="off"
                        />
                    </div>
                    <div class="min-w-175px">
                        <select
                            v-model="filterForm.tenant_id"
                            class="form-select form-select-solid"
                            @change="onTenantChange"
                        >
                            <option value="">All tenants</option>
                            <option
                                v-for="t in tenants"
                                :key="t.id"
                                :value="String(t.id)"
                            >
                                {{ t.name }}
                            </option>
                        </select>
                    </div>
                    <Link
                        :href="usersCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New user
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table
                        class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5"
                    >
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Tenant</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Admin</th>
                                <th>Supervisor</th>
                                <th>Status</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="u in users.data"
                                :key="u.id"
                            >
                                <td>
                                    <span
                                        v-if="u.tenant"
                                        class="text-gray-800"
                                        >{{ u.tenant.name }}</span
                                    >
                                    <span
                                        v-else
                                        class="text-muted"
                                        >—</span
                                    >
                                </td>
                                <td class="fw-bold text-gray-800">
                                    {{ u.name }}
                                </td>
                                <td class="text-gray-700">
                                    {{ u.email }}
                                </td>
                                <td class="text-nowrap text-gray-700">
                                    <span class="me-1">{{ u.country_code }}</span>
                                    {{ u.mobile }}
                                </td>
                                <td>
                                    <span
                                        :class="[
                                            'badge',
                                            u.is_admin
                                                ? 'badge-light-primary'
                                                : 'badge-light',
                                        ]"
                                        >{{ formatBool(u.is_admin) }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        :class="[
                                            'badge',
                                            u.is_supervisor
                                                ? 'badge-light-info'
                                                : 'badge-light',
                                        ]"
                                        >{{ formatBool(u.is_supervisor) }}</span
                                    >
                                </td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="statusClass(u.status)"
                                    >
                                        {{ u.status }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="usersEdit.url({ user: u.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>  Edit
                                    </Link>
                                    <button
                                        v-if="u.id !== page.props.auth.user.id"
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeUser(u)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="users.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ users.data.length ? (users.current_page - 1) * users.per_page + 1 : 0 }}
                        – {{ Math.min(users.current_page * users.per_page, users.total) }} of
                        {{ users.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in users.links"
                            :key="l.label + String(l.url)"
                        >
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                :class="[
                                    'btn btn-sm border',
                                    l.active
                                        ? 'btn-primary'
                                        : 'btn-light',
                                ]"
                                preserve-state
                            >
                                <span v-html="l.label" />
                            </Link>
                            <span
                                v-else
                                class="btn btn-sm border btn-light pe-none opacity-50"
                            >
                                <span v-html="l.label" />
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
