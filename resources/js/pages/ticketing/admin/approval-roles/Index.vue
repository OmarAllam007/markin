<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { destroy, store, update } from '@/routes/ticketing/admin/approval-roles';

type Employee = { id: number; english_name: string; email: string | null };
type Role = { id: number; name: string; employee_id: number | null; employee: Employee | null };

defineProps<{ roles: Role[]; employees: Employee[] }>();

const editingId = ref<number | 'new' | null>(null);
const form = useForm({ name: '', employee_id: '' as number | '' });

const start = (role?: Role) => {
    form.clearErrors();
    form.name = role?.name ?? '';
    form.employee_id = role?.employee_id ?? '';
    editingId.value = role?.id ?? 'new';
};

const save = () => {
    const options = { preserveScroll: true, onSuccess: () => (editingId.value = null) };

    if (editingId.value === 'new') {
        form.post(store.url(), options);
    } else if (editingId.value) {
        form.put(update.url({ approval_role: editingId.value }), options);
    }
};

const remove = (role: Role) => {
    if (window.confirm(`Delete the role "${role.name}"? Services that use it will skip this approval step.`)) {
        router.delete(destroy.url({ approval_role: role.id }), { preserveScroll: true });
    }
};
</script>

<template>
    <div>
        <Head title="Approval roles" />
        <div class="card">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold">Approval roles</h2>
                    <span class="text-muted fs-7 mt-1">Roles that approve requests and who holds each one. Add them to a service in the service catalog. Direct manager comes from each employee, department manager from each department.</span>
                </div>
                <div class="card-toolbar">
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        :disabled="editingId === 'new'"
                        @click="start()"
                    ><i class="ki-outline ki-plus fs-2" />New role</button>
                </div>
            </div>
            <div class="card-body pt-0">
                <div class="table-responsive">
                <table class="table table-row-dashed align-middle fs-6 gy-4">
                    <colgroup>
                        <col class="role-column">
                        <col class="holder-column">
                        <col class="actions-column">
                    </colgroup>
                    <thead>
                        <tr class="text-muted fw-bold fs-7">
                            <th>Role</th>
                            <th>Held by</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="row in [...roles, ...(editingId === 'new' ? [null] : [])]"
                            :key="row?.id ?? 'new'"
                        >
                            <tr v-if="row && editingId !== row.id">
                                <td class="fw-bold text-gray-800">{{ row.name }}</td>
                                <td>
                                    <template v-if="row.employee">
                                        <div class="fw-semibold text-gray-800">{{ row.employee.english_name }}</div>
                                        <div class="text-muted fs-7 text-break">{{ row.employee.email }}</div>
                                    </template>
                                    <span
                                        v-else
                                        class="badge badge-light-warning"
                                    >Nobody — this step will be skipped</span>
                                </td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-icon btn-light me-1"
                                        title="Edit"
                                        @click="start(row)"
                                    ><i class="ki-outline ki-pencil" /></button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-icon btn-light btn-active-light-danger"
                                        title="Delete"
                                        @click="remove(row)"
                                    ><i class="ki-outline ki-trash" /></button>
                                </td>
                            </tr>
                            <tr v-else>
                                <td>
                                    <input
                                        v-model="form.name"
                                        class="form-control form-control-sm"
                                        :class="{ 'is-invalid': form.errors.name }"
                                        placeholder="e.g. HR manager"
                                        @keydown.enter.prevent="save"
                                    >
                                    <div class="invalid-feedback">{{ form.errors.name }}</div>
                                </td>
                                <td>
                                    <select
                                        v-model="form.employee_id"
                                        class="form-select form-select-sm"
                                        :class="{ 'is-invalid': form.errors.employee_id }"
                                    >
                                        <option value="">Nobody yet</option>
                                        <option
                                            v-for="e in employees"
                                            :key="e.id"
                                            :value="e.id"
                                            :disabled="!e.email"
                                        >{{ e.english_name }}{{ e.email ? ` — ${e.email}` : ' (no email)' }}</option>
                                    </select>
                                    <div class="invalid-feedback">{{ form.errors.employee_id }}</div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light me-1"
                                        @click="editingId = null"
                                    >Cancel</button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-primary"
                                        :disabled="form.processing || !form.name"
                                        @click="save"
                                    >Save</button>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!roles.length && editingId !== 'new'">
                            <td
                                colspan="3"
                                class="text-center text-muted py-10"
                            >No roles yet. Add the roles that approve requests, such as HR manager or finance manager.</td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.table { min-width: 680px; table-layout: fixed; }
.role-column { width: 30%; }
.holder-column { width: 46%; }
.actions-column { width: 24%; }
.table th, .table td { padding-inline: 14px; overflow-wrap: anywhere; }
.table th:first-child, .table td:first-child { padding-inline-start: 0; }
.table th:last-child, .table td:last-child { padding-inline-end: 0; }
</style>
