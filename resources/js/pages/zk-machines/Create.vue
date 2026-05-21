<script setup lang="ts">
import { store as zkMachinesStore, index as zkMachinesIndex } from '@/routes/zk-machines/index';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type LocationOption = { id: number; name: string };

defineProps<{
    locations: LocationOption[];
}>();

const form = useForm({
    serial_number: '',
    name: '',
    location_id: null as number | null,
    secret_token: '',
});

const pushUrl = 'https://mark.test';
const copied = ref(false);

const copyPushUrl = async () => {
    await navigator.clipboard.writeText(pushUrl);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
};

const submit = () => {
    form.post(zkMachinesStore.url());
};
</script>

<template>
    <div>
        <Head title="Add ZK Machine" />

        <div class="mb-5">
            <Link
                :href="zkMachinesIndex.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to machines
            </Link>
        </div>

        <!-- Push URL instruction banner -->
        <div class="alert alert-primary d-flex align-items-start gap-4 mb-6">
            <i class="ki-outline ki-information-4 fs-2 text-primary mt-1 flex-shrink-0"></i>
            <div class="flex-grow-1">
                <div class="fw-bold mb-1">Configure your ZK device</div>
                <div class="fs-7 text-gray-700">
                    In the machine's network settings, set the <strong>Server Address</strong> to:
                </div>
                <div class="d-flex align-items-center gap-2 mt-2">
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
                <div class="fs-8 text-muted mt-1">
                    The machine will automatically use <code>/api/iclock/cdata</code> — do not enter the path manually.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 d-flex align-items-center">
                <div class="card-title">
                    <h2 class="fw-bold">Add ZK Machine</h2>
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
                                placeholder="e.g. 6316154800026"
                                autocomplete="off"
                            />
                            <div class="form-text text-muted">
                                Found in the machine's Info / About screen.
                            </div>
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
                                placeholder="e.g. Main Entrance"
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
                                placeholder="Leave blank if machine has no token"
                                autocomplete="off"
                            />
                            <div class="form-text text-muted">
                                Set in the machine's ADMS / Cloud settings if required.
                            </div>
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
                        Register Machine
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
