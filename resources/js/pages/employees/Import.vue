<script setup lang="ts">
import { importMethod as importFormRoute, index as employeesIndex } from '@/routes/employees';
import { store as importStore, template as importTemplate } from '@/routes/employees/import';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<{ flash: { success?: string | null; error?: string | null; import_errors?: string[] } }>();
const importErrors = computed(() => page.props.flash?.import_errors ?? []);

const form = useForm({ file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);
const fileName = ref('');

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.file = file;
    fileName.value = file?.name ?? '';
};

const submit = () => {
    form.post(importStore.url(), {
        forceFormData: true,
    });
};
</script>

<template>
    <div>
        <Head title="Import Employees" />
        <div class="card">
            <div class="card-header border-0 pt-6 d-flex align-items-center gap-3">
                <Link
                    :href="employeesIndex.url()"
                    class="btn btn-sm btn-icon btn-light btn-active-light-primary"
                >
                    <i class="ki-outline ki-arrow-left fs-3" />
                </Link>
                <div class="card-title">
                    <h2 class="fw-bold">Import Employees</h2>
                </div>
            </div>

            <div class="card-body py-6">
                <div class="mw-700px mx-auto">
                    <!-- Template download notice -->
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-8">
                        <i class="ki-outline ki-information-5 fs-2tx text-primary me-4" />
                        <div class="d-flex flex-stack flex-grow-1 flex-wrap flex-md-nowrap">
                            <div class="mb-3 mb-md-0 fw-semibold">
                                <h4 class="text-gray-900 fw-bold">Use the official template</h4>
                                <div class="fs-6 text-gray-700 pe-7">
                                    Download and fill the template before uploading. Required columns:
                                    <code>english_name</code>, <code>arabic_name</code>, <code>mobile</code>.
                                    Mobile format: <code>+966 501234567</code>
                                </div>
                            </div>
                            <a
                                :href="importTemplate.url()"
                                class="btn btn-primary px-6 align-self-center text-nowrap"
                            >
                                <i class="ki-outline ki-file-down fs-2 me-1" />
                                Download Template
                            </a>
                        </div>
                    </div>

                    <!-- Row-level import errors -->
                    <div
                        v-if="importErrors.length"
                        class="alert alert-danger d-flex flex-column p-6 mb-8"
                    >
                        <div class="d-flex align-items-center mb-3">
                            <i class="ki-outline ki-cross-circle fs-2 text-danger me-3" />
                            <span class="fw-bold fs-5">
                                {{ importErrors.length }} row(s) could not be imported
                            </span>
                        </div>
                        <ul class="mb-0 ps-5">
                            <li
                                v-for="(err, idx) in importErrors"
                                :key="idx"
                                class="fs-7 text-danger"
                            >
                                {{ err }}
                            </li>
                        </ul>
                    </div>

                    <!-- Upload form -->
                    <form @submit.prevent="submit">
                        <div class="mb-7">
                            <label class="form-label required fw-semibold fs-6">Excel File</label>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".xlsx,.xls"
                                class="d-none"
                                @change="onFileChange"
                            />
                            <div
                                class="dropzone d-flex flex-column align-items-center justify-content-center border border-dashed rounded p-10 cursor-pointer"
                                :class="{ 'border-primary bg-light-primary': fileName }"
                                @click="fileInput?.click()"
                            >
                                <i
                                    class="fs-3x mb-3"
                                    :class="fileName ? 'ki-outline ki-file-sheet text-primary' : 'ki-outline ki-file-up text-gray-400'"
                                />
                                <div v-if="fileName" class="text-primary fw-bold fs-5">{{ fileName }}</div>
                                <div v-else class="text-gray-600 fw-semibold fs-6">
                                    Click to choose a file or drag & drop
                                </div>
                                <div class="text-muted fs-7 mt-1">Supported: .xlsx, .xls — Max 10 MB</div>
                            </div>
                            <div v-if="form.errors.file" class="text-danger fs-7 mt-2">
                                {{ form.errors.file }}
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3">
                            <Link
                                :href="employeesIndex.url()"
                                class="btn btn-light"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                class="btn btn-primary"
                                :disabled="!form.file || form.processing"
                            >
                                <span v-if="form.processing" class="indicator-progress d-block">
                                    Importing…
                                    <span class="spinner-border spinner-border-sm align-middle ms-2" />
                                </span>
                                <span v-else>
                                    <i class="ki-outline ki-file-up fs-2 me-1" />
                                    Import
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
