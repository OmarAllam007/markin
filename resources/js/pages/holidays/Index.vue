<script setup lang="ts">
import { index as holidaysIndex, create as holidaysCreate, edit as holidaysEdit, destroy as holidaysDestroy } from '@/routes/holidays/index';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

type Creator = { id: number; name: string };

type HolidayRow = {
    id: number;
    name: string;
    date: string;
    is_recurring: boolean;
    creator: Creator | null;
};

const props = defineProps<{
    holidays: HolidayRow[];
    filters: { year: number };
}>();

const filterForm = useForm({
    year: props.filters.year,
});

const submitFilters = () => {
    filterForm.get(holidaysIndex.url({ query: { year: String(filterForm.year) } }), {
        preserveState: true,
        replace: true,
        only: ['holidays', 'filters'],
    });
};

const removeHoliday = (h: HolidayRow) => {
    if (!window.confirm(`Delete holiday "${h.name}"? This cannot be undone. Existing attendance records for this date will be reverted to Absent.`)) {
        return;
    }
    router.delete(holidaysDestroy.url({ holiday: h.id }));
};

const formatDate = (d: string) =>
    new Date(d + 'T00:00:00').toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });

const years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - 1 + i);
</script>

<template>
    <div>
        <Head title="Holidays" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Holidays</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <select
                        v-model="filterForm.year"
                        class="form-select form-select-solid w-120px"
                        @change="submitFilters"
                    >
                        <option
                            v-for="y in years"
                            :key="y"
                            :value="y"
                        >
                            {{ y }}
                        </option>
                    </select>
                    <Link
                        :href="holidaysCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        Add holiday
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Date</th>
                                <th>Recurring</th>
                                <th>Added by</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="h in holidays"
                                :key="h.id"
                            >
                                <td class="fw-bold text-gray-800">{{ h.name }}</td>
                                <td class="text-gray-700">{{ formatDate(h.date) }}</td>
                                <td>
                                    <span
                                        v-if="h.is_recurring"
                                        class="badge badge-light-success"
                                    >Yearly</span>
                                    <span
                                        v-else
                                        class="badge badge-light-secondary"
                                    >One-time</span>
                                </td>
                                <td class="text-gray-700">{{ h.creator?.name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="holidaysEdit.url({ holiday: h.id })"
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
                                        @click="removeHoliday(h)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="holidays.length === 0">
                                <td
                                    colspan="5"
                                    class="text-center text-muted py-10"
                                >
                                    No holidays found for {{ filters.year }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
