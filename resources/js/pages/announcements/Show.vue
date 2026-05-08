<script setup lang="ts">
import {
    index as announcementsIndex,
    destroy as announcementsDestroy,
} from '@/routes/announcements';
import { Head, Link, router } from '@inertiajs/vue3';

type Employee = {
    id: number;
    employee_number: string | null;
    english_name: string;
    arabic_name: string;
    email: string | null;
    job_title_en: string | null;
    department: { id: number; name: string } | null;
    location: { id: number; name: string } | null;
};

type Announcement = {
    id: number;
    type: string;
    title: string;
    description: string;
    target_type: string;
    target_location_id: number | null;
    target_department_id: number | null;
    target_employee_ids: number[] | null;
    attachment_path: string | null;
    recipients_count: number;
    sent_at: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
    target_location: { id: number; name: string } | null;
    target_department: { id: number; name: string } | null;
};

const props = defineProps<{
    announcement: Announcement;
    targetEmployees: Employee[];
}>();

const TYPE_COLOR: Record<string, string> = {
    notification: 'badge-light-primary',
    warning: 'badge-light-warning',
};

const TYPE_LABEL: Record<string, string> = {
    notification: 'Notification',
    warning: 'Warning',
};

const TYPE_ICON: Record<string, string> = {
    notification: 'ki-notification-bing text-primary',
    warning: 'ki-warning-2 text-warning',
};

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function targetSummary(): string {
    if (props.announcement.target_type === 'employees') {
        return `${props.targetEmployees.length} selected employee(s)`;
    }
    const loc = props.announcement.target_location?.name ?? 'All Locations';
    const dep = props.announcement.target_department?.name ?? 'All Departments';
    return `${loc} + ${dep}`;
}

function confirmDelete() {
    if (!window.confirm(`Delete "${props.announcement.title}"? This cannot be undone.`)) return;
    router.delete(announcementsDestroy.url({ announcement: props.announcement.id }));
}

function print() {
    window.print();
}
</script>

<template>
    <div>
        <Head :title="announcement.title" />

        <!-- ── Screen toolbar (hidden on print) ── -->
        <div class="d-flex align-items-center justify-content-between mb-6 no-print">
            <Link
                :href="announcementsIndex.url()"
                class="btn btn-sm btn-light"
            >
                <i class="ki-outline ki-arrow-left fs-4 me-1"></i>
                Back
            </Link>
            <div class="d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-sm btn-light-primary"
                    @click="print"
                >
                    <i class="ki-outline ki-printer fs-5 me-1"></i>
                    Print
                </button>
                <button
                    type="button"
                    class="btn btn-sm btn-light-danger"
                    @click="confirmDelete"
                >
                    <i class="ki-duotone ki-trash fs-5 me-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                        <span class="path4"></span>
                        <span class="path5"></span>
                    </i>
                    Delete
                </button>
            </div>
        </div>

        <!-- ── Main card ── -->
        <div class="card print-card">

            <!-- Header -->
            <div class="card-header border-0 pt-8 pb-0">
                <div class="d-flex align-items-start gap-4 w-100">
                    <!-- Icon -->
                    <div
                        class="w-55px h-55px rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                        :class="announcement.type === 'warning' ? 'bg-light-warning' : 'bg-light-primary'"
                    >
                        <i
                            class="ki-outline fs-2"
                            :class="TYPE_ICON[announcement.type] ?? 'ki-notification-bing text-primary'"
                        ></i>
                    </div>

                    <div class="flex-grow-1">
                        <!-- Type badge + title -->
                        <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
                            <span :class="['badge fs-7 py-2 px-3', TYPE_COLOR[announcement.type] ?? 'badge-light']">
                                {{ TYPE_LABEL[announcement.type] ?? announcement.type }}
                            </span>
                            <h2 class="fw-bold text-gray-900 mb-0">{{ announcement.title }}</h2>
                        </div>

                        <!-- Meta row -->
                        <div class="d-flex flex-wrap gap-5 fs-7 text-gray-500">
                            <span>
                                <i class="ki-outline ki-user fs-7 me-1"></i>
                                Sent by <strong class="text-gray-700">{{ announcement.creator?.name ?? '—' }}</strong>
                            </span>
                            <span>
                                <i class="ki-outline ki-time fs-7 me-1"></i>
                                {{ formatDate(announcement.sent_at) }}
                            </span>
                            <span>
                                <i class="ki-outline ki-people fs-7 me-1"></i>
                                <strong class="text-gray-700">{{ announcement.recipients_count }}</strong> recipient(s)
                            </span>
                            <span>
                                <i class="ki-outline ki-geolocation fs-7 me-1"></i>
                                {{ targetSummary() }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body pt-8">
                <div class="separator mb-7"></div>

                <!-- Message body -->
                <div class="mb-8">
                    <div class="fs-7 fw-bold text-uppercase text-gray-400 mb-3">Message</div>
                    <div class="p-6 rounded bg-light fs-6 text-gray-800 ck-content" v-html="announcement.description"></div>
                </div>

                <!-- Attachment -->
                <div v-if="announcement.attachment_path" class="mb-8 no-print">
                    <div class="fs-7 fw-bold text-uppercase text-gray-400 mb-3">Attachment</div>
                    <div class="d-flex align-items-center gap-3 p-4 rounded border border-dashed bg-light-primary">
                        <i class="ki-outline ki-file fs-2 text-primary"></i>
                        <span class="fw-semibold text-gray-700">
                            {{ announcement.attachment_path.split('/').pop() }}
                        </span>
                    </div>
                </div>

                <!-- Recipients table -->
                <div v-if="targetEmployees.length > 0">
                    <div class="fs-7 fw-bold text-uppercase text-gray-400 mb-3">
                        Recipients ({{ targetEmployees.length }})
                    </div>
                    <div class="table-responsive">
                        <table class="table table-row-bordered table-row-gray-100 align-middle gy-3 gs-4">
                            <thead>
                                <tr class="text-gray-500 text-uppercase fs-8 fw-bold">
                                    <th>ID</th>
                                    <th>English Name</th>
                                    <th>Arabic Name</th>
                                    <th>Department</th>
                                    <th>Location</th>
                                    <th>Job Title</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in targetEmployees" :key="e.id">
                                    <td class="text-gray-500 fs-7">{{ e.employee_number ?? `#${e.id}` }}</td>
                                    <td class="fw-semibold text-gray-800">{{ e.english_name }}</td>
                                    <td class="text-gray-700">{{ e.arabic_name }}</td>
                                    <td class="text-gray-600 fs-7">{{ e.department?.name ?? '—' }}</td>
                                    <td class="text-gray-600 fs-7">{{ e.location?.name ?? '—' }}</td>
                                    <td class="text-gray-600 fs-7">{{ e.job_title_en ?? '—' }}</td>
                                    <td class="text-gray-600 fs-7">
                                        <span v-if="e.email">{{ e.email }}</span>
                                        <span v-else class="text-muted">No email</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else class="text-center text-muted py-6 fs-7">
                    No recipients for this announcement.
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }

    .print-card {
        box-shadow: none !important;
        border: none !important;
    }
}

/* Render CKEditor HTML output correctly outside the editor */
.ck-content :deep(h2) { font-size: 1.25rem; font-weight: 700; margin: 0.75rem 0 0.4rem; }
.ck-content :deep(h3) { font-size: 1.1rem; font-weight: 600; margin: 0.6rem 0 0.3rem; }
.ck-content :deep(ul),
.ck-content :deep(ol) { padding-left: 1.5rem; margin: 0.4rem 0; }
.ck-content :deep(li) { margin-bottom: 0.2rem; }
.ck-content :deep(blockquote) {
    border-left: 4px solid #d1d5db;
    padding-left: 1rem;
    color: #6b7280;
    margin: 0.5rem 0;
}
.ck-content :deep(a) { color: var(--kt-primary); text-decoration: underline; }
.ck-content :deep(strong) { font-weight: 700; }
.ck-content :deep(em) { font-style: italic; }
.ck-content :deep(u) { text-decoration: underline; }
</style>
