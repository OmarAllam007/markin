<script setup lang="ts">
import { create as leaveTypesCreate, destroy as leaveTypesDestroy, edit as leaveTypesEdit } from '@/routes/leave-types/index';
import { Head, Link, router } from '@inertiajs/vue3';

type LeaveTypeRow = {
    id: number;
    name: string;
    annual_days: number | null;
    is_paid: boolean;
    is_active: boolean;
    requests_count: number;
};

defineProps<{ leaveTypes: LeaveTypeRow[] }>();

const remove = (t: LeaveTypeRow) => {
    const message = t.requests_count > 0
        ? `"${t.name}" has ${t.requests_count} request(s), so it will be deactivated instead of deleted. Continue?`
        : `Delete leave type "${t.name}"?`;
    if (!window.confirm(message)) {
        return;
    }
    router.delete(leaveTypesDestroy.url({ leave_type: t.id }));
};
</script>

<template>
    <div>
        <Head title="Leave types" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Leave types</h2>
                </div>
                <div class="card-toolbar">
                    <Link
                        :href="leaveTypesCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        Add leave type
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Days per year</th>
                                <th>Paid</th>
                                <th>Status</th>
                                <th>Requests</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="t in leaveTypes"
                                :key="t.id"
                            >
                                <td class="fw-bold text-gray-800">{{ t.name }}</td>
                                <td class="text-gray-700">{{ t.annual_days ?? 'No limit' }}</td>
                                <td>
                                    <span
                                        v-if="t.is_paid"
                                        class="badge badge-light-success"
                                    >Paid</span>
                                    <span
                                        v-else
                                        class="badge badge-light-warning"
                                    >Unpaid</span>
                                </td>
                                <td>
                                    <span
                                        v-if="t.is_active"
                                        class="badge badge-light-primary"
                                    >Active</span>
                                    <span
                                        v-else
                                        class="badge badge-light-secondary"
                                    >Inactive</span>
                                </td>
                                <td class="text-gray-700">{{ t.requests_count }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="leaveTypesEdit.url({ leave_type: t.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="remove(t)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="leaveTypes.length === 0">
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-10"
                                >
                                    No leave types yet. Add one so employees can request leave.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
