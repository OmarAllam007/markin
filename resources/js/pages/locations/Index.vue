<script setup lang="ts">
import { index as locationsIndex, create as locationsCreate, edit as locationsEdit, destroy as locationsDestroy } from '@/routes/locations';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { watch } from 'vue';

type Coordinates = { north: number; south: number; east: number; west: number };

type LocationRow = {
    id: number;
    name: string;
    coordinates: Coordinates;
};

type PaginatorLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    locations: {
        data: LocationRow[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: PaginatorLink[];
    } | LocationRow[];
    filters: { search: string | null };
}>();

const isPaginated = (v: typeof props.locations): v is Exclude<typeof props.locations, LocationRow[]> =>
    !Array.isArray(v);

const filterForm = useForm({
    search: props.filters?.search ?? '',
});

const submitFilters = () => {
    const q: Record<string, string> = {};
    if (filterForm.search) {
        q.search = filterForm.search;
    }
    filterForm.get(locationsIndex.url({ query: q }), {
        preserveState: true,
        replace: true,
        only: ['locations', 'filters'],
    });
};

const debouncedSubmit = useDebounceFn(submitFilters, 400);
watch(() => filterForm.search, () => debouncedSubmit());

const removeLocation = (l: LocationRow) => {
    if (!window.confirm(`Delete location "${l.name}"? This cannot be undone.`)) {
        return;
    }
    router.delete(locationsDestroy.url({ location: l.id }));
};

const formatCoords = (c: Coordinates) =>
    `N ${c.north.toFixed(5)}, S ${c.south.toFixed(5)}, E ${c.east.toFixed(5)}, W ${c.west.toFixed(5)}`;
</script>

<template>
    <div>
        <Head title="Locations" />

        <div class="card">
            <div class="card-header border-0 pt-6 d-flex flex-wrap flex-stack gap-3">
                <div class="card-title">
                    <h2 class="fw-bold">Locations</h2>
                </div>
                <div class="card-toolbar d-flex flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center position-relative my-1">
                        <i class="ki-outline ki-magnifier fs-3 position-absolute ms-5" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="form-control form-control-solid w-250px ps-12"
                            placeholder="Search locations…"
                            autocomplete="off"
                        />
                    </div>
                    <Link
                        :href="locationsCreate.url()"
                        class="btn btn-sm btn-primary"
                    >
                        <i class="ki-outline ki-plus fs-2"></i>
                        New location
                    </Link>
                </div>
            </div>
            <div class="card-body py-0">
                <div class="table-responsive">
                    <table class="table table-row-bordered table-row-dashed align-middle gy-4 gs-5">
                        <thead>
                            <tr class="text-start text-gray-500 text-uppercase fs-7 fw-bold">
                                <th>Name</th>
                                <th>Bounds (N/S/E/W)</th>
                                <th class="text-end w-200px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="l in isPaginated(locations) ? locations.data : locations"
                                :key="l.id"
                            >
                                <td class="fw-bold text-gray-800">{{ l.name }}</td>
                                <td class="text-gray-700 fs-7 text-nowrap">{{ formatCoords(l.coordinates) }}</td>
                                <td class="text-end text-nowrap">
                                    <Link
                                        :href="locationsEdit.url({ location: l.id })"
                                        class="btn btn-sm btn-light btn-active-light-primary me-1"
                                    >
                                        <i class="ki-duotone ki-notepad-edit">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>   Edit
                                    </Link>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light btn-active-light-danger"
                                        @click="removeLocation(l)"
                                    >
                                        <i class="ki-duotone ki-trash">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="(isPaginated(locations) ? locations.data : locations).length === 0">
                                <td
                                    colspan="3"
                                    class="text-center text-muted py-10"
                                >
                                    No locations found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="isPaginated(locations) && locations.last_page > 1"
                class="card-footer d-flex flex-wrap py-3"
            >
                <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-end">
                    <span class="text-muted fs-7 me-auto">
                        {{ locations.data.length ? (locations.current_page - 1) * locations.per_page + 1 : 0 }}
                        – {{ Math.min(locations.current_page * locations.per_page, locations.total) }} of
                        {{ locations.total }}
                    </span>
                    <div class="d-flex flex-wrap gap-1">
                        <template
                            v-for="l in locations.links"
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
