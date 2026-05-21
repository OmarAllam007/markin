<script setup lang="ts">
import { update as zkMachinesUpdate, index as zkMachinesIndex } from '@/routes/zk-machines/index';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type LocationOption = { id: number; name: string };

type MachineModel = {
    id: number;
    serial_number: string;
    name: string | null;
    location_id: number | null;
    secret_token: string | null;
    firmware_version: string | null;
    platform: string | null;
    last_sync_at: string | null;
    last_attlog_stamp: number;
};

const props = defineProps<{
    machine: MachineModel;
    locations: LocationOption[];
    pending_logs_count: number;
}>();

const form = useForm({
    serial_number: props.machine.serial_number,
    name: props.machine.name ?? '',
    location_id: props.machine.location_id,
    secret_token: props.machine.secret_token ?? '',
});

const pushUrl = 'https://mark.test';
const copied = ref(false);

const copyPushUrl = async () => {
    await navigator.clipboard.writeText(pushUrl);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const formatDate = (val: string | null) =>
    val ? new Date(val).toLocaleString() : 'Never';

const submit = () => {
    form.put(zkMachinesUpdate.url({ zk_machine: props.machine.id }));
};
</script>

<template>
    <div>
        <Head :title="`Edit ${machine.name ?? machine.serial_number}`" />

        <div class="mb-5">
            <Link
                :href="zkMachinesIndex.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to machines
            </Link>
        </div>

        <!-- Machine status panel -->
        <div class="row g-5 mb-6">
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body py-5 px-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Last Sync</div>
                        <div class="fw-bold text-gray-800 fs-6">{{ formatDate(machine.last_sync_at) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body py-5 px-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Unsynced Records</div>
                        <div
                            class="fw-bold fs-6"
                            :class="pending_logs_count > 0 ? 'text-danger' : 'text-success'"
                        >
                            {{ pending_logs_count > 0 ? `${pending_logs_count} pending` : 'All synced' }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body py-5 px-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Firmware</div>
                        <div class="fw-bold text-gray-800 fs-6">{{ machine.firmware_version ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body py-5 px-6">
                        <div class="text-muted fs-8 text-uppercase fw-bold mb-1">ATTLOG Stamp</div>
                        <div class="fw-bold text-gray-800 fs-6">{{ machine.last_attlog_stamp }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Push URL -->
        <div class="alert alert-primary d-flex align-items-start gap-4 mb-6">
            <i class="ki-outline ki-information-4 fs-2 text-primary mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <div class="fw-bold mb-1">Device push address</div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <code class="bg-light px-3 py-1 rounded fs-7 text-gray-800 flex-grow-1">{{ pushUrl }}</code>
                    <button
                        type="button"
                        class="btn btn-sm btn-light-primary"
                        @click="copyPushUrl"
                    >
                        <i class="ki-outline ki-copy fs-6 me-1"></i>
                        {{ copied ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Edit Machine</h2>
                </div>
            </div>

            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body border-top p-9">
                    <div class="row g-5">
                        <!-- Serial Number -->
                        <div class="col-md-6">
                            <label
                                class="form-label required"
                                for="serial-number"
                            >Serial Number</label>
                            <input
                                id="serial-number"
                                v-model="form.serial_number"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.serial_number }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.serial_number"
                                class="invalid-feedback"
                            >
                                {{ form.errors.serial_number }}
                            </div>
                        </div>

                        <!-- Name -->
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="machine-name"
                            >Display Name</label>
                            <input
                                id="machine-name"
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

                        <!-- Location -->
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="location"
                            >Location</label>
                            <select
                                id="location"
                                v-model="form.location_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.location_id }"
                            >
                                <option :value="null">— None —</option>
                                <option
                                    v-for="loc in locations"
                                    :key="loc.id"
                                    :value="loc.id"
                                >
                                    {{ loc.name }}
                                </option>
                            </select>
                            <div
                                v-if="form.errors.location_id"
                                class="invalid-feedback"
                            >
                                {{ form.errors.location_id }}
                            </div>
                        </div>

                        <!-- Secret Token -->
                        <div class="col-md-6">
                            <label
                                class="form-label"
                                for="secret-token"
                            >Secret Token <span class="text-muted">(optional)</span></label>
                            <input
                                id="secret-token"
                                v-model="form.secret_token"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.secret_token }"
                                autocomplete="off"
                            />
                            <div
                                v-if="form.errors.secret_token"
                                class="invalid-feedback"
                            >
                                {{ form.errors.secret_token }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link
                        :href="zkMachinesIndex.url()"
                        class="btn btn-light"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
