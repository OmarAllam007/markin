<script setup lang="ts">
import {
    index as announcementsIndex,
    create as announcementsCreate,
    show as announcementsShow,
    destroy as announcementsDestroy,
} from '@/routes/announcements';
import { Head, Link, router } from '@inertiajs/vue3';

type PaginatorLink = { url: string | null; label: string; active: boolean };

type AnnouncementRow = {
    id: number;
    type: string;
    title: string;
    description: string;
    target_type: string;
    recipients_count: number;
    sent_at: string | null;
    created_at: string;
    creator: { id: number; name: string } | null;
    target_location: { id: number; name: string } | null;
    target_department: { id: number; name: string } | null;
};

const props = defineProps<{
    announcements:
        | {
              data: AnnouncementRow[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              links: PaginatorLink[];
          }
        | AnnouncementRow[];
}>();

const isPaginated = (
    v: typeof props.announcements,
): v is Exclude<typeof props.announcements, AnnouncementRow[]> => !Array.isArray(v);

const TYPE_COLOR: Record<string, string> = {
    notification: 'badge-light-primary',
    warning: 'badge-light-warning',
};

const TYPE_LABEL: Record<string, string> = {
    notification: 'Notification',
    warning: 'Warning',
};

const TARGET_LABEL: Record<string, string> = {
    locations_departments: 'Location / Department',
    employees: 'Employees',
};

function targetSummary(row: AnnouncementRow): string {
    if (row.target_type === 'employees') {
        return 'Selected employees';
    }
    const parts: string[] = [];
    parts.push(row.target_location ? row.target_location.name : 'All Locations');
    parts.push(row.target_department ? row.target_department.name : 'All Departments');
    return parts.join(' + ');
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function removeAnnouncement(a: AnnouncementRow) {
    if (!window.confirm(`Delete "${a.title}"? This cannot be undone.`)) return;
    router.delete(announcementsDestroy.url({ announcement: a.id }));
}
</script>

<template>
    <div>
        <Head title="Notifications & Warnings" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Notifications &amp; Warnings</h2>
                </div>
                <div class="card-toolbar">
                    <Link
                        :href="announcementsCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        Create
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Type</th>
                                <th>Title</th>
                                <th>Target</th>
                                <th>Recipients</th>
                                <th>Sent By</th>
                                <th>Sent At</th>
                                <th class="text-end w-160px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="a in isPaginated(announcements) ? announcements.data : announcements"
                                :key="a.id"
                            >
                                <td>
                                    <span :class="['badge', TYPE_COLOR[a.type] ?? 'badge-light']">
                                        {{ TYPE_LABEL[a.type] ?? a.type }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-gray-800">{{ a.title }}</div>
                                    <div class="text-gray-500 fs-7 text-truncate" style="max-width: 280px;">{{ a.description }}</div>
                                </td>
                                <td class="text-gray-700">
                                    <div class="fs-8 text-muted mb-1">{{ TARGET_LABEL[a.target_type] ?? a.target_type }}</div>
                                    <div class="fw-semibold fs-7">{{ targetSummary(a) }}</div>
                                </td>
                                <td class="text-gray-700 fw-bold">{{ a.recipients_count }}</td>
                                <td class="text-gray-600 fs-7">{{ a.creator?.name ?? '—' }}</td>
                                <td class="text-gray-600 fs-7 text-nowrap">{{ formatDate(a.sent_at) }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="announcementsShow.url({ announcement: a.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-eye">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                        </i>
                                        View
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeAnnouncement(a)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                            <span class="path3"></span>
                                            <span class="path4"></span>
                                            <span class="path5"></span>
                                        </i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="(isPaginated(announcements) ? announcements.data : announcements).length === 0"
                            >
                                <td colspan="7" class="text-center text-muted py-10">
                                    No notifications sent yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(announcements) && announcements.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ announcements.data.length ? (announcements.current_page - 1) * announcements.per_page + 1 : 0 }}
                        –
                        {{ Math.min(announcements.current_page * announcements.per_page, announcements.total) }}
                        of {{ announcements.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in announcements.links"
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
