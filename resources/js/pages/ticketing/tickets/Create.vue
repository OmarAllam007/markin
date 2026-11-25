<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import TicketFormRenderer from '@/components/ticketing/TicketFormRenderer.vue';
import type { TicketField } from '@/lib/ticketFields';
import { store, index } from '@/routes/ticketing/tickets';

type Item = { id: number; name: string };
type Subcategory = { id: number; name: string; items: Item[] };
type Category = { id: number; name: string; ticket_type: string | null; subcategories: Subcategory[] };

const props = defineProps<{
    categories: Category[];
    priorities: { id: number; name: string; is_default: boolean }[];
    formFields: TicketField[];
}>();

const query = new URLSearchParams(window.location.search);

const form = useForm({
    category_id: query.get('category_id') ?? '',
    subcategory_id: query.get('subcategory_id') ?? '',
    item_id: query.get('item_id') ?? '',
    subject: '',
    description: '',
    priority_id: (props.priorities.find(p => p.is_default)?.id ?? '') as string | number,
    attachments: [] as File[],
    form_data: {} as Record<string, string | number | null>,
});

const subcategories = computed(() => props.categories.find(c => c.id === Number(form.category_id))?.subcategories ?? []);
const items = computed(() => subcategories.value.find(s => s.id === Number(form.subcategory_id))?.items ?? []);
const selectedCategory = computed(() => props.categories.find(c => c.id === Number(form.category_id)));
const selectedSubcategory = computed(() => subcategories.value.find(s => s.id === Number(form.subcategory_id)));
const selectedItem = computed(() => items.value.find(i => i.id === Number(form.item_id)));
const ready = computed(() => Boolean(form.subcategory_id));

// The form is designed per service level, so re-resolve it whenever the selection changes.
const reloadForm = () =>
    router.reload({
        only: ['formFields'],
        data: { category_id: form.category_id, subcategory_id: form.subcategory_id, item_id: form.item_id },
    });

const pickCategory = () => {
    form.subcategory_id = '';
    form.item_id = '';
    reloadForm();
};
const pickSubcategory = () => {
    form.item_id = '';
    reloadForm();
};

const submit = () => form.post(store.url(), { forceFormData: true });
</script>

<template>
    <div>
        <Head title="New Ticket" />
        <div class="mb-5">
            <Link
                :href="index.url()"
                class="text-gray-600 text-hover-primary"
            >
                <i class="ki-outline ki-left fs-3"></i> Back to tickets
            </Link>
        </div>
        <div class="request-hero mb-7">
            <div class="request-hero-content">
                <span class="request-eyebrow">SERVICE DESK / NEW REQUEST</span>
                <h1>How can we help?</h1>
                <p>Choose the service you need, then tell us the details. Your request will go to the right team.</p>
            </div>
            <div class="request-hero-icon" aria-hidden="true"><i class="ki-outline ki-message-text-2" /></div>
        </div>
        <div class="card request-card">
            <form
                class="form"
                @submit.prevent="submit"
            >
                <div class="card-body p-6 p-lg-9">
                    <div class="request-section-heading mb-6">
                        <span class="request-step">01</span>
                        <div><h2>Choose a service</h2><p>We’ll show the right questions for your selection.</p></div>
                    </div>
                    <div class="row g-5 mb-8 request-selection">
                        <div class="col-md-4">
                            <label
                                class="form-label required"
                                for="tk-cat"
                            >Category</label>
                            <select
                                id="tk-cat"
                                v-model="form.category_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.category_id }"
                                @change="pickCategory"
                            >
                                <option value="">Select category…</option>
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="String(c.id)"
                                >{{ c.name }}</option>
                            </select>
                            <div
                                v-if="form.errors.category_id"
                                class="invalid-feedback"
                            >{{ form.errors.category_id }}</div>
                        </div>
                        <div class="col-md-4">
                            <label
                                class="form-label required"
                                for="tk-subcat"
                            >Subcategory</label>
                            <select
                                id="tk-subcat"
                                v-model="form.subcategory_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.subcategory_id }"
                                :disabled="!subcategories.length"
                                @change="pickSubcategory"
                            >
                                <option value="">Select subcategory…</option>
                                <option
                                    v-for="s in subcategories"
                                    :key="s.id"
                                    :value="String(s.id)"
                                >{{ s.name }}</option>
                            </select>
                            <div
                                v-if="form.errors.subcategory_id"
                                class="invalid-feedback"
                            >{{ form.errors.subcategory_id }}</div>
                        </div>
                        <div
                            v-if="items.length"
                            class="col-md-4"
                        >
                            <label
                                class="form-label"
                                for="tk-item"
                            >Item</label>
                            <select
                                id="tk-item"
                                v-model="form.item_id"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors.item_id }"
                                @change="reloadForm"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="i in items"
                                    :key="i.id"
                                    :value="String(i.id)"
                                >{{ i.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-7">
                        <div class="col-xl-8">
                            <div class="request-section-heading mb-6">
                                <span class="request-step" :class="{ 'request-step-muted': !ready }">02</span>
                                <div><h2>Request details</h2><p>Give the team enough information to get started.</p></div>
                            </div>
                            <div v-if="ready && formFields.length" class="request-fields">
                                <TicketFormRenderer :fields="formFields" :form="form" :priorities="priorities" />
                            </div>
                            <div v-else class="request-empty">
                                <i class="ki-outline ki-notepad-edit" />
                                <h3>Your form will appear here</h3>
                                <p>Choose a category and subcategory above to continue.</p>
                            </div>
                        </div>
                        <div class="col-xl-4">
                            <aside class="request-summary">
                                <div class="request-summary-icon"><i class="ki-outline ki-check-circle" /></div>
                                <h3>Request overview</h3>
                                <p class="text-muted mb-6">Check your service selection before submitting.</p>
                                <div class="request-summary-row"><span>Category</span><strong>{{ selectedCategory?.name ?? 'Not selected' }}</strong></div>
                                <div class="request-summary-row"><span>Service</span><strong>{{ selectedSubcategory?.name ?? 'Not selected' }}</strong></div>
                                <div v-if="items.length" class="request-summary-row"><span>Item</span><strong>{{ selectedItem?.name ?? 'None' }}</strong></div>
                                <div class="request-summary-note"><i class="ki-outline ki-information-5" /> The right team will be assigned after you submit.</div>
                            </aside>
                        </div>
                    </div>
                </div>
                <div class="card-footer request-footer d-flex justify-content-end gap-3">
                    <Link
                        :href="index.url()"
                        class="btn btn-light"
                    >Cancel</Link>
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="form.processing || !ready"
                    >
                        <span
                            v-if="form.processing"
                            class="spinner-border spinner-border-sm me-2"
                        />
                        Submit request <i class="ki-outline ki-arrow-right ms-2" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.request-hero { position: relative; display: flex; justify-content: space-between; overflow: hidden; padding: 36px 42px; border-radius: 18px; color: #fff; background: linear-gradient(115deg, #163f67, #176b84 70%, #168b97); }
.request-hero-content { position: relative; z-index: 1; max-width: 610px; }
.request-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .15em; color: #a9e9ee; }
.request-hero h1 { margin: 10px 0 8px; font-size: clamp(27px, 3vw, 38px); font-weight: 750; }
.request-hero p { margin: 0; font-size: 15px; color: #d8edf3; }
.request-hero-icon { position: absolute; right: 45px; top: 50%; transform: translateY(-50%) rotate(-12deg); font-size: 130px; line-height: 1; opacity: .12; }
.request-card { border: 1px solid #e6edf2; border-radius: 16px; box-shadow: 0 12px 34px rgba(29, 64, 91, .06); }
.request-section-heading { display: flex; align-items: flex-start; gap: 15px; }
.request-section-heading h2 { margin: 0 0 3px; font-size: 19px; font-weight: 700; color: #203e54; }
.request-section-heading p { margin: 0; color: #71869a; font-size: 13px; }
.request-step { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 10px; background: #d9f3f1; color: #087d83; font-size: 12px; font-weight: 800; }
.request-step-muted { background: #eef2f5; color: #8da0ac; }
.request-selection { padding-bottom: 32px; border-bottom: 1px solid #e9eff3; }
.request-selection :deep(.form-select), .request-fields :deep(.form-control), .request-fields :deep(.form-select) { min-height: 46px; border-color: #dce6eb; border-radius: 9px; background-color: #fff; }
.request-selection :deep(.form-select:focus), .request-fields :deep(.form-control:focus), .request-fields :deep(.form-select:focus) { border-color: #168b97; box-shadow: 0 0 0 3px rgba(22, 139, 151, .12); }
.request-fields { padding: 26px; border: 1px solid #e8eef2; border-radius: 13px; background: #fbfcfd; }
.request-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 240px; border: 1px dashed #cbdce4; border-radius: 13px; background: #f9fbfc; text-align: center; }
.request-empty i { margin-bottom: 12px; color: #5c9da8; font-size: 36px; }
.request-empty h3 { margin: 0 0 5px; color: #34536a; font-size: 16px; font-weight: 700; }
.request-empty p { margin: 0; color: #8a9eac; font-size: 13px; }
.request-summary { position: sticky; top: 25px; padding: 26px; border-radius: 13px; background: #f1f7f8; }
.request-summary-icon { display: grid; place-items: center; width: 42px; height: 42px; margin-bottom: 15px; border-radius: 12px; background: #d8eeee; color: #11828b; font-size: 24px; }
.request-summary h3 { margin-bottom: 4px; color: #23485b; font-size: 17px; font-weight: 700; }
.request-summary-row { display: flex; justify-content: space-between; gap: 15px; padding: 13px 0; border-top: 1px solid #dce8eb; font-size: 13px; }
.request-summary-row span { color: #728995; }
.request-summary-row strong { color: #2c5061; text-align: right; overflow-wrap: anywhere; }
.request-summary-note { display: flex; gap: 9px; margin-top: 18px; color: #678490; font-size: 12px; line-height: 1.5; }
.request-summary-note i { color: #11828b; font-size: 18px; }
.request-footer { padding: 22px 36px; background: #fbfcfd; border-radius: 0 0 16px 16px; }
@media (max-width: 767px) { .request-hero { padding: 28px; } .request-hero-icon { right: -20px; } .request-fields, .request-summary { padding: 18px; } }
</style>
