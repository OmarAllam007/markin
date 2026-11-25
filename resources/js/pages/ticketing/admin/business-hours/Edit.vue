<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as holidaysIndex } from '@/routes/holidays';
import { update } from '@/routes/ticketing/admin/business-hours';

type Window = [string, string] | null;

const props = defineProps<{ hours: Record<string, Window>; timezone: string; holidaysCount: number }>();

const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const form = useForm({ hours: Object.fromEntries(DAYS.map((_, d) => [String(d), props.hours[String(d)] ? [...props.hours[String(d)]!] : null])) as Record<string, Window> });

const toggle = (d: string) => (form.hours[d] = form.hours[d] ? null : ['08:00', '17:00']);
const minutes = (w: Window) => (w ? Math.max(0, (+w[1].slice(0, 2) * 60 + +w[1].slice(3)) - (+w[0].slice(0, 2) * 60 + +w[0].slice(3))) : 0);
const weekly = computed(() => Object.values(form.hours).reduce((sum, w) => sum + minutes(w), 0));
const hm = (m: number) => `${Math.floor(m / 60)}h${m % 60 ? ` ${m % 60}m` : ''}`;

/** Copies one day's hours to every open day. */
const copyToOpen = (d: string) => {
    const w = form.hours[d];

    if (w) {
        Object.keys(form.hours).forEach(k => form.hours[k] && (form.hours[k] = [...w]));
    }
};

const dayError = (d: string) => Object.entries(form.errors).find(([k]) => k === `hours.${d}` || k.startsWith(`hours.${d}.`))?.[1];
</script>

<template>
    <div>
        <Head title="Working hours" />
        <div class="card mw-800px">
            <div class="card-header border-0 pt-6">
                <div class="card-title flex-column align-items-start">
                    <h2 class="fw-bold">Working hours</h2>
                    <span class="text-muted fs-7 mt-1">SLAs set to <b>business hours only</b> count time inside these hours. Outside them, and on holidays, the clock waits. Other SLAs count around the clock.</span>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="meta">
                    <span><i class="ki-outline ki-time" /> {{ hm(weekly) }} a week</span>
                    <span><i class="ki-outline ki-geolocation" /> Times in {{ timezone }}</span>
                    <span><i class="ki-outline ki-calendar" /> {{ holidaysCount }} {{ holidaysCount === 1 ? 'holiday' : 'holidays' }} closed — <Link :href="holidaysIndex.url()">manage holidays</Link></span>
                </div>

                <ul class="week">
                    <li
                        v-for="(name, d) in DAYS"
                        :key="d"
                        :class="{ closed: !form.hours[String(d)] }"
                    >
                        <label class="form-check form-switch mb-0 day">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :checked="!!form.hours[String(d)]"
                                :aria-label="`${name} open`"
                                @change="toggle(String(d))"
                            >
                            <span class="form-check-label fw-semibold">{{ name }}</span>
                        </label>
                        <template v-if="form.hours[String(d)]">
                            <div class="times">
                                <input
                                    v-model="form.hours[String(d)]![0]"
                                    type="time"
                                    class="form-control form-control-sm"
                                    :aria-label="`${name} opens`"
                                >
                                <span class="text-muted">to</span>
                                <input
                                    v-model="form.hours[String(d)]![1]"
                                    type="time"
                                    class="form-control form-control-sm"
                                    :aria-label="`${name} closes`"
                                >
                            </div>
                            <span class="len">{{ hm(minutes(form.hours[String(d)])) }}</span>
                            <button
                                type="button"
                                class="btn btn-sm btn-link p-0 copy"
                                @click="copyToOpen(String(d))"
                            >Copy to open days</button>
                        </template>
                        <span
                            v-else
                            class="text-muted fs-7"
                        >Closed</span>
                        <div
                            v-if="dayError(String(d))"
                            class="text-danger fs-8 err"
                        >{{ dayError(String(d)) }}</div>
                    </li>
                </ul>
                <div
                    v-if="form.errors.hours"
                    class="text-danger fs-7 mt-3"
                >{{ form.errors.hours }}</div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="form.processing"
                    @click="form.put(update.url(), { preserveScroll: true })"
                >
                    <span
                        v-if="form.processing"
                        class="spinner-border spinner-border-sm me-2"
                    />
                    Save working hours
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.meta { display: flex; flex-wrap: wrap; gap: 8px 22px; margin-bottom: 18px; color: #5e7383; font-size: 13px; }
.meta i { color: #2aa2a9; }
.week { margin: 0; padding: 0; list-style: none; border: 1px solid #e5edf1; border-radius: 12px; }
.week li { display: grid; grid-template-columns: 170px auto 70px 1fr; align-items: center; gap: 14px; padding: 12px 18px; }
.week li + li { border-top: 1px solid #edf1f3; }
.week li.closed { background: #fafbfc; }
.times { display: flex; align-items: center; gap: 8px; }
.times input { width: 120px; }
.len { color: #24465b; font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
.copy { justify-self: end; font-size: 12px; }
.err { grid-column: 1 / -1; }
@media (max-width: 640px) { .week li { grid-template-columns: 1fr; } .copy { justify-self: start; } }
</style>
