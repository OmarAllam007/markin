<script setup lang="ts">
import { useGoogleMaps } from '@/composables/useGoogleMaps';
import { update, index } from '@/routes/locations';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

type Coordinates = { north: number; south: number; east: number; west: number };

type LocationModel = {
    id: number;
    name: string;
    coordinates: Coordinates;
};

const props = defineProps<{ location: LocationModel }>();

const form = useForm({
    name: props.location.name,
    coordinates: { ...props.location.coordinates } as Coordinates,
});

const mapRef = ref<HTMLElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const coordLat = ref('');
const coordLng = ref('');
const mapError = ref('');

let map: google.maps.Map | null = null;
let drawingManager: google.maps.drawing.DrawingManager | null = null;
let currentRectangle: google.maps.Rectangle | null = null;

const { load } = useGoogleMaps();

const applyRectangle = (rect: google.maps.Rectangle) => {
    if (currentRectangle && currentRectangle !== rect) {
        currentRectangle.setMap(null);
    }
    currentRectangle = rect;
    const bounds = rect.getBounds();
    if (!bounds) {
        return;
    }
    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();
    form.coordinates = {
        north: ne.lat(),
        south: sw.lat(),
        east: ne.lng(),
        west: sw.lng(),
    };
    drawingManager?.setDrawingMode(null);
};

onMounted(async () => {
    try {
        await load();
    } catch {
        mapError.value = 'Failed to load Google Maps.';
        return;
    }

    if (!mapRef.value) {
        return;
    }

    const { north, south, east, west } = props.location.coordinates;
    const centerLat = (north + south) / 2;
    const centerLng = (east + west) / 2;

    map = new google.maps.Map(mapRef.value, {
        center: { lat: centerLat, lng: centerLng },
        zoom: 15,
        mapTypeId: 'roadmap',
    });

    const existingBounds = new google.maps.LatLngBounds(
        { lat: south, lng: west },
        { lat: north, lng: east },
    );

    const existingRect = new google.maps.Rectangle({
        bounds: existingBounds,
        fillColor: '#4F46E5',
        fillOpacity: 0.2,
        strokeColor: '#4F46E5',
        strokeWeight: 2,
        editable: true,
        draggable: true,
        map,
    });
    currentRectangle = existingRect;
    map.fitBounds(existingBounds, 60);

    google.maps.event.addListener(existingRect, 'bounds_changed', () => applyRectangle(existingRect));

    drawingManager = new google.maps.drawing.DrawingManager({
        drawingMode: null,
        drawingControl: true,
        drawingControlOptions: {
            position: google.maps.ControlPosition.TOP_CENTER,
            drawingModes: [google.maps.drawing.OverlayType.RECTANGLE],
        },
        rectangleOptions: {
            fillColor: '#4F46E5',
            fillOpacity: 0.2,
            strokeColor: '#4F46E5',
            strokeWeight: 2,
            editable: true,
            draggable: true,
        },
    });
    drawingManager.setMap(map);

    google.maps.event.addListener(drawingManager, 'rectanglecomplete', (rect: google.maps.Rectangle) => {
        applyRectangle(rect);
        google.maps.event.addListener(rect, 'bounds_changed', () => applyRectangle(rect));
    });

    if (searchInput.value) {
        const autocomplete = new google.maps.places.Autocomplete(searchInput.value);
        autocomplete.bindTo('bounds', map);
        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place.geometry?.viewport) {
                map!.fitBounds(place.geometry.viewport);
            } else if (place.geometry?.location) {
                map!.setCenter(place.geometry.location);
                map!.setZoom(14);
            }
        });
    }
});

const searchByCoords = () => {
    const lat = parseFloat(coordLat.value);
    const lng = parseFloat(coordLng.value);
    if (isNaN(lat) || isNaN(lng)) {
        return;
    }
    map?.setCenter({ lat, lng });
    map?.setZoom(14);
};

const submit = () => {
    form.put(update.url({ location: props.location.id }));
};
</script>

<template>
    <div>
        <Head :title="`Edit ${location.name}`" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to locations
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Edit location</h2>
                </div>
            </div>
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5 mb-5">
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="loc-name"
                            >Name</label>
                            <input
                                id="loc-name"
                                v-model="form.name"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.name }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.name"
                                class="invalid-feedback"
                            >
                                {{ form.errors.name }}
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Map area</label>
                        <p class="text-muted fs-7 mb-3">
                            Drag or resize the existing rectangle, or draw a new one. Search by place name or coordinates to navigate the map.
                        </p>

                        <div class="d-flex flex-wrap gap-3 mb-3">
                            <div class="d-flex align-items-center position-relative flex-grow-1" style="min-width: 220px; max-width: 360px;">
                                <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                                <input
                                    ref="searchInput"
                                    type="text"
                                    class="form-control form-control-solid ps-12"
                                    placeholder="Search by place name…"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <input
                                    v-model="coordLat"
                                    type="number"
                                    step="any"
                                    class="form-control form-control-solid w-130px"
                                    placeholder="Latitude"
                                />
                                <input
                                    v-model="coordLng"
                                    type="number"
                                    step="any"
                                    class="form-control form-control-solid w-130px"
                                    placeholder="Longitude"
                                />
                                <button
                                    type="button"
                                    class="btn btn-light btn-sm"
                                    @click="searchByCoords"
                                >
                                    Go
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="mapError"
                            class="alert alert-danger"
                        >
                            {{ mapError }}
                        </div>
                        <div
                            ref="mapRef"
                            class="rounded border"
                            style="height: 450px; width: 100%;"
                        ></div>

                        <div
                            v-if="form.coordinates"
                            class="mt-3 p-3 bg-light-primary rounded d-flex flex-wrap gap-4"
                        >
                            <span class="fs-7 fw-semibold text-gray-700">
                                <span class="text-muted me-1">North:</span>{{ form.coordinates.north.toFixed(6) }}
                            </span>
                            <span class="fs-7 fw-semibold text-gray-700">
                                <span class="text-muted me-1">South:</span>{{ form.coordinates.south.toFixed(6) }}
                            </span>
                            <span class="fs-7 fw-semibold text-gray-700">
                                <span class="text-muted me-1">East:</span>{{ form.coordinates.east.toFixed(6) }}
                            </span>
                            <span class="fs-7 fw-semibold text-gray-700">
                                <span class="text-muted me-1">West:</span>{{ form.coordinates.west.toFixed(6) }}
                            </span>
                        </div>
                        <div
                            v-if="form.errors.coordinates"
                            class="text-danger fs-7 mt-1"
                        >
                            {{ form.errors.coordinates }}
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link
                        :href="index.url()"
                        class="btn btn-light"
                    >Cancel</Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
