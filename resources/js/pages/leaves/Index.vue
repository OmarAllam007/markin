<script setup lang="ts">
import { approve as leavesApprove, index as leavesIndex, reject as leavesReject } from '@/routes/leaves/index';
import { Head, Link, router } from '@inertiajs/vue3';

type PaginatorLink = { url: string | null; label: string; active: boolean };

type LeaveRow = {
    id: number;
    start_date: string;
    end_date: string;
    days: number;
    reason: string | null;
    status: string;
    review_note: string | null;
    reviewed_at: string | null;
    employee: { id: number; arabic_name: string; english_name: string; employee_number: string | null } | null;
    leave_type: { id: number; name: string; is_paid: boolean } | null;
    reviewer: { id: number; name: string } | null;
};

const props = defineProps<{
    requests: {
        data: LeaveRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
    filters: { status: string };
    counts: { pending: number };
}>();

const tabs = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const switchStatus = (status: string) => {
    router.get(leavesIndex.url({ query: { status } }), {}, { preserveState: true, replace: true });
};

const approve = (r: LeaveRow) => {
    const name = r.employee ? r.employee.english_name : 'this employee';
    if (!window.confirm(`Approve ${r.days} day(s) of ${r.leave_type?.name ?? 'leave'} for ${name}? Those days will be marked as leave in attendance.`)) {
        return;
    }
    router.post(leavesApprove.url({ leaveRequest: r.id }), {}, { preserveScroll: true });
};

const reject = (r: LeaveRow) => {
    const note = window.prompt('Reason for rejection (optional):') ?? null;
    router.post(leavesReject.url({ leaveRequest: r.id }), { review_note: note }, { preserveScroll: true });
};

const formatDate = (d: string) =>
    new Date(d.slice(0, 10) + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });

const statusBadge = (status: string) =>
    ({ approved: 'badge-light-success', rejected: 'badge-light-danger' })[status] ?? 'badge-light-warning';
</script>

<template>
    <div>
        <Head title="Leave requests" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Leave requests</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-2">
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        :class="['btn btn-sm', props.filters.status === t.value ? 'btn-primary' : 'btn-light']"
                        @click="switchStatus(t.value)"
                    >
                        {{ t.label }}
                        <span
                            v-if="t.value === 'pending' && props.counts.pending > 0"
                            class="badge badge-circle badge-light ms-2"
                        >{{ props.counts.pending }}</span>
                    </button>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th v-if="props.filters.status !== 'pending'">Reviewed by</th>
                                <th
                                    v-else
                                    class="text-end w-200px"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in requests.data"
                                :key="r.id"
                            >
                                <td class="fw-bold text-gray-800">
                                    {{ r.employee?.english_name ?? '—' }}
                                    <div
                                        v-if="r.employee?.employee_number || r.employee?.arabic_name"
                                        class="text-muted fs-7 fw-normal"
                                    >
                                        {{ [r.employee.employee_number, r.employee.arabic_name].filter(Boolean).join(' · ') }}
                                    </div>
                                </td>
                                <td>
                                    {{ r.leave_type?.name ?? '—' }}
                                    <span
                                        v-if="r.leave_type && !r.leave_type.is_paid"
                                        class="badge badge-light-warning ms-1"
                                    >Unpaid</span>
                                </td>
                                <td class="text-gray-700 text-nowrap">
                                    {{ formatDate(r.start_date) }} → {{ formatDate(r.end_date) }}
                                </td>
                                <td class="text-gray-700">{{ r.days }}</td>
                                <td class="text-gray-700">{{ r.reason || '—' }}</td>
                                <td
                                    v-if="props.filters.status !== 'pending'"
                                    class="text-gray-700"
                                >
                                    <span :class="['badge', statusBadge(r.status)]">{{ r.status }}</span>
                                    <div class="text-muted fs-7 mt-1">
                                        {{ r.reviewer?.name ?? '—' }}
                                        <template v-if="r.review_note"> — {{ r.review_note }}</template>
                                    </div>
                                </td>
                                <td
                                    v-else
                                    class="text-end text-nowrap"
                                >
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-success me-1"
                                        @click="approve(r)"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="reject(r)"
                                    >
                                        Reject
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="requests.data.length === 0">
                                <td
                                    colspan="6"
                                    class="text-center text-muted py-10"
                                >
                                    No {{ props.filters.status }} leave requests.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="requests.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ requests.data.length ? (requests.current_page - 1) * requests.per_page + 1 : 0 }}
                        –
                        {{ Math.min(requests.current_page * requests.per_page, requests.total) }}
                        of {{ requests.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in requests.links"
                            :key="l.label + String(l.url)"
                        >
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                :class="['btn btn-sm border', l.active ? 'btn-primary' : 'btn-light']"
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
