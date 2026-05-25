<script setup lang="ts">
import { update, index } from '@/routes/holidays/index';
import { Head, Link, useForm } from '@inertiajs/vue3';

type HolidayData = {
    id: number;
    name: string;
    date: string;
    is_recurring: boolean;
};

const props = defineProps<{ holiday: HolidayData }>();

const form = useForm({
    name: props.holiday.name,
    date: props.holiday.date,
    is_recurring: props.holiday.is_recurring,
});

const submit = () => {
    form.put(update.url({ holiday: props.holiday.id }));
};
</script>

<template>
    <div>
        <Head title="Edit holiday" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to holidays
            </Link>
        </div>
        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Edit holiday</h2>
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
                                for="holiday-name"
                            >Name</label>
                            <input
                                id="holiday-name"
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
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="holiday-date"
                            >Date</label>
                            <input
                                id="holiday-date"
                                v-model="form.date"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.date }"
                            />
                            <div
                                v-if="form.errors.date"
                                class="invalid-feedback"
                            >
                                {{ form.errors.date }}
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input
                                    id="holiday-recurring"
                                    v-model="form.is_recurring"
                                    class="form-check-input"
                                    type="checkbox"
                                />
                                <label
                                    class="form-check-label"
                                    for="holiday-recurring"
                                >
                                    Repeat every year (yearly recurring holiday)
                                </label>
                            </div>
                            <div class="text-muted fs-7 mt-1">
                                Changing the date or recurring setting will automatically update existing attendance records.
                            </div>
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
