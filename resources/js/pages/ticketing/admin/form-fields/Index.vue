<script setup lang="ts">
import { index, show } from '@/routes/ticketing/admin/form-fields';
import { Head, Link } from '@inertiajs/vue3';

type TypeSummary = {
    value: string;
    label: string;
    base_field_count: number;
    custom_field_count: number;
};

defineProps<{ types: TypeSummary[] }>();

const typeIcon: Record<string, string> = {
    leave: 'ki-calendar-add',
    leave_with_permission: 'ki-calendar-tick',
    business_trip: 'ki-airplane',
    overtime: 'ki-time',
    change_device: 'ki-phone',
    missing_attendance: 'ki-fingerprint-scanning',
    general: 'ki-ticket',
};

const typeColor: Record<string, string> = {
    leave: 'success',
    leave_with_permission: 'info',
    business_trip: 'primary',
    overtime: 'warning',
    change_device: 'dark',
    missing_attendance: 'danger',
    general: 'secondary',
};
</script>

<template>
    <div>
        <Head title="Form Fields" />
        <div class="d-flex align-items-center mb-5">
            <div>
                <h1 class="fw-bold mb-1">Form Fields</h1>
                <p class="text-muted mb-0">Manage the form fields each ticket type collects. System fields are locked; you can add custom fields.</p>
            </div>
        </div>
        <div class="row g-5">
            <div
                v-for="type in types"
                :key="type.value"
                class="col-md-6 col-xl-4"
            >
                <div class="card card-flush h-100 border hover-elevate-up">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span :class="`badge badge-light-${typeColor[type.value] ?? 'secondary'} p-3 fs-3`">
                                <i :class="`ki-outline ${typeIcon[type.value] ?? 'ki-ticket'} fs-2`" />
                            </span>
                            <h4 class="fw-bold mb-0">{{ type.label }}</h4>
                        </div>
                        <div class="d-flex gap-4 fs-7 text-muted">
                            <span>
                                <i class="ki-outline ki-lock fs-6 me-1" />
                                {{ type.base_field_count }} system field{{ type.base_field_count !== 1 ? 's' : '' }}
                            </span>
                            <span>
                                <i class="ki-outline ki-plus-circle fs-6 me-1" />
                                {{ type.custom_field_count }} custom field{{ type.custom_field_count !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div class="mt-auto">
                            <Link
                                :href="show.url({ type: type.value })"
                                class="btn btn-sm btn-light btn-active-light-primary w-100"
                            >
                                Manage fields
                                <i class="ki-outline ki-arrow-right fs-5 ms-1" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
