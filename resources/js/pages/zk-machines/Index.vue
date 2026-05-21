<script setup lang="ts">
import {
    index as zkMachinesIndex,
    create as zkMachinesCreate,
    edit as zkMachinesEdit,
    destroy as zkMachinesDestroy,
} from '@/routes/zk-machines/index';
import { Head, Link, router } from '@inertiajs/vue3';

type LocationRef = { id: number; name: string };

type MachineRow = {
    id: number;
    serial_number: string;
    name: string | null;
    firmware_version: string | null;
    last_sync_at: string | null;
    location: LocationRef | null;
    raw_logs_count: number;
    pending_logs_count: number;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    machines: {
        data: MachineRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    };
}>();

const removeMachine = (m: MachineRow) => {
    if (!window.confirm(`Remove machine "${m.name ?? m.serial_number}"? All raw logs will also be deleted.`)) {
        return;
    }
    router.delete(zkMachinesDestroy.url({ zk_machine: m.id }));
};

const formatDate = (val: string | null) =>
    val ? new Date(val).toLocaleString() : '—';
</script>

<template>
    <div>
        <Head title="ZK Machines" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">ZK Machines</h2>
                </div>
                <div class="card-toolbar">
                    <Link
                        :href="zkMachinesCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        Add Machine
                    </Link>
                </div>
            </div>

            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Machine</th>
                                <th>Location</th>
                                <th>Last Sync</th>
                                <th>Unsynced</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in machines.data"
                                :key="m.id"
                            >
                                <td>
                                    <span class="fw-bold text-gray-800 d-block">
                                        {{ m.name ?? '—' }}
                                    </span>
                                    <span class="text-muted fs-7">{{ m.serial_number }}</span>
                                    <span
                                        v-if="m.firmware_version"
                                        class="text-muted fs-8 ms-2"
                                    >· {{ m.firmware_version }}</span>
                                </td>
                                <td class="text-gray-700">
                                    {{ m.location?.name ?? '—' }}
                                </td>
                                <td class="text-gray-700 fs-7">
                                    {{ formatDate(m.last_sync_at) }}
                                </td>
                                <td>
                                    <span
                                        v-if="m.pending_logs_count > 0"
                                        class="badge badge-light-danger"
                                    >
                                        <i class="ki-outline ki-warning-2 fs-8 me-1"></i>
                                        {{ m.pending_logs_count }} unsynced
                                    </span>
                                    <span
                                        v-else
                                        class="badge badge-light-success"
                                    >
                                        <i class="ki-outline ki-check fs-8 me-1"></i>
                                        All synced
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="zkMachinesEdit.url({ zk_machine: m.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeMachine(m)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Remove
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="machines.data.length === 0">
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-10"
                                >
                                    No machines registered yet.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="machines.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ machines.data.length ? (machines.current_page - 1) * machines.per_page + 1 : 0 }}
                        –{{ Math.min(machines.current_page * machines.per_page, machines.total) }} of
                        {{ machines.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in machines.links"
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
