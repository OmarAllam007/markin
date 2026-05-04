<script setup lang="ts">
import {
    index as workShiftsIndex,
    create as workShiftsCreate,
    edit as workShiftsEdit,
    destroy as workShiftsDestroy,
} from '@/routes/work-shifts';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type Creator = { id: number; name: string };

type WorkShiftRow = {
    id: number;
    name: string;
    type: 'fixed' | 'flexible';
    created_at: string;
    creator: Creator | null;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    workShifts:
        | {
              data: WorkShiftRow[];
              current_page: number;
              last_page: number;
              per_page: number;
              total: number;
              links: PaginatorLink[];
          }
        | WorkShiftRow[];
    filters: { search: string | null };
}>();

const isPaginated = (
    v: typeof props.workShifts,
): v is Exclude<typeof props.workShifts, WorkShiftRow[]> => !Array.isArray(v);

const filterForm = useForm({
    search: props.filters?.search ?? '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) {
        q.search = filterForm.search;
    }
    filterForm.get(workShiftsIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['workShifts', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(
    () => filterForm.search,
    () => debouncedSubmit(),
);

const removeWorkShift = (w: WorkShiftRow) => {
    if (!window.confirm(`Delete work shift "${w.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(workShiftsDestroy.url({ work_shift: w.id }));
};
</script>

<template>
    <div>
        <Head title="Work Shifts" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Work Shifts</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search work shifts…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="workShiftsCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New work shift
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Type</th>
                                <th>Created by</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="w in isPaginated(workShifts) ? workShifts.data : workShifts"
                                :key="w.id"
                            >
                                <td class="fw-bold text-gray-800">{{ w.name }}</td>
                                <td>
                                    <span
                                        class="badge"
                                        :class="w.type === 'fixed' ? 'badge-light-primary' : 'badge-light-success'"
                                    >
                                        {{ w.type === 'fixed' ? 'Fixed' : 'Flexible' }}
                                    </span>
                                </td>
                                <td class="text-gray-700">{{ w.creator?.name ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="workShiftsEdit.url({ work_shift: w.id })"
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
                                        @click="removeWorkShift(w)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr
                                v-if="
                                    (isPaginated(workShifts) ? workShifts.data : workShifts).length === 0
                                "
                            >
                                <td
                                    colspan="4"
                                    class="text-center text-muted py-10"
                                >
                                    No work shifts found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(workShifts) && workShifts.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ workShifts.data.length ? (workShifts.current_page - 1) * workShifts.per_page + 1 : 0 }}
                        –
                        {{ Math.min(workShifts.current_page * workShifts.per_page, workShifts.total) }}
                        of {{ workShifts.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in workShifts.links"
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
