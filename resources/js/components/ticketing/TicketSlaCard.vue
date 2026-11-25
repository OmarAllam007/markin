<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { formatDateTime } from '@/lib/utils';

export type SlaClock = {
    state: 'running' | 'approval' | 'paused' | 'stopped';
    ticking: boolean;
    business_hours: boolean;
    elapsed_minutes: number;
    running_since: string | null;
    paused_at: string | null;
    budget_minutes: number | null;
    response_budget_minutes: number | null;
    server_now: string;
};

const props = defineProps<{
    clock: SlaClock;
    dueDate: string | null;
    overdue: boolean;
    slaName: string | null;
    createdAt: string;
    firstResponseAt: string | null;
}>();

// Tick from the server's snapshot, so a wrong device clock can't skew the numbers.
const loadedAt = Date.now();
const offset = new Date(props.clock.server_now).getTime() - loadedAt;
const now = ref(Date.now() + offset);
let timer: number | undefined;
onMounted(() => {
    if (props.clock.ticking) {
        timer = window.setInterval(() => (now.value = Date.now() + offset), 30_000);
    }
});
onBeforeUnmount(() => window.clearInterval(timer));

const elapsed = computed(() => props.clock.elapsed_minutes + (props.clock.ticking ? Math.max(0, Math.floor((now.value - (loadedAt + offset)) / 60_000)) : 0));

const fmt = (minutes: number) => {
    const m = Math.abs(Math.round(minutes));
    const d = Math.floor(m / 1440);
    const h = Math.floor((m % 1440) / 60);
    const mm = m % 60;

    return d ? `${d}d ${h}h` : h ? `${h}h ${mm}m` : `${mm}m`;
};

/** Minutes left until due (negative = overdue). Frozen while paused or stopped. */
const remaining = computed(() => {
    if (!props.dueDate) {
        return null;
    }

    const due = new Date(props.dueDate).getTime();
    const at = props.clock.ticking ? now.value : props.clock.state === 'running' ? new Date(props.clock.server_now).getTime() : props.clock.paused_at ? new Date(props.clock.paused_at).getTime() : now.value;

    return Math.round((due - at) / 60_000);
});

const used = computed(() => (props.clock.budget_minutes ? Math.min(elapsed.value / props.clock.budget_minutes, 1.5) : null));
const tone = computed(() => (props.overdue || (remaining.value !== null && remaining.value < 0) ? 'late' : used.value !== null && used.value >= 0.8 ? 'near' : 'ok'));

const responseMinutes = computed(() => (props.firstResponseAt ? Math.round((new Date(props.firstResponseAt).getTime() - new Date(props.createdAt).getTime()) / 60_000) : null));
const responseLate = computed(() => responseMinutes.value !== null && props.clock.response_budget_minutes !== null && responseMinutes.value > props.clock.response_budget_minutes);

const STATE = {
    running: { label: 'Clock running', icon: 'ki-time' },
    approval: { label: 'Paused — awaiting approval', icon: 'ki-verify' },
    paused: { label: 'Paused — on hold', icon: 'ki-pause' },
    stopped: { label: 'Stopped', icon: 'ki-check-circle' },
};
</script>

<template>
    <div :class="['card ticket-panel sla', tone]">
        <div class="sla-head">
            <span :class="['state', clock.state]"><i :class="`ki-outline ${STATE[clock.state].icon}`" />{{ STATE[clock.state].label }}</span>
            <span
                v-if="slaName"
                class="sla-name"
            >{{ slaName }}</span>
        </div>

        <div class="figure">
            <span class="big">{{ fmt(elapsed) }}</span>
            <span
                v-if="clock.budget_minutes"
                class="of"
            >of {{ fmt(clock.budget_minutes) }}</span>
        </div>
        <div class="caption">
            {{ clock.business_hours ? 'Working hours the request has been open' : 'Time the request has been open' }}, excluding time on hold or awaiting approval<template v-if="clock.state === 'running' && !clock.ticking"> · outside working hours now</template>
        </div>

        <div
            v-if="used !== null"
            class="bar"
            role="progressbar"
            :aria-valuenow="Math.round(used * 100)"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`${Math.round(used * 100)}% of the SLA used`"
        >
            <span :style="{ width: `${Math.min(used, 1) * 100}%` }" />
        </div>

        <dl>
            <template v-if="dueDate">
                <dt>Due</dt>
                <dd>{{ formatDateTime(dueDate) }}</dd>
                <dt>{{ remaining !== null && remaining < 0 ? 'Overdue by' : clock.state === 'stopped' ? 'Finished' : 'Time left' }}</dt>
                <dd :class="{ late: remaining !== null && remaining < 0 }">
                    <template v-if="clock.state === 'stopped' && remaining !== null && remaining >= 0">in time</template>
                    <template v-else-if="remaining !== null">{{ fmt(remaining) }}{{ clock.state === 'paused' || clock.state === 'approval' ? ' (paused)' : '' }}</template>
                </dd>
            </template>
            <template v-else>
                <dt>Due</dt>
                <dd class="text-muted">No SLA on this request</dd>
            </template>
            <dt>First response</dt>
            <dd :class="{ late: responseLate }">{{ responseMinutes === null ? 'Waiting' : `after ${fmt(responseMinutes)}` }}</dd>
        </dl>
    </div>
</template>

<style scoped>
.sla { --tone: #16838b; --tone-soft: #e2f2f2; margin-bottom: 24px; padding: 22px 24px; }
.sla.near { --tone: #c98500; --tone-soft: #fff4d9; }
.sla.late { --tone: #c63d3a; --tone-soft: #fde8e7; }
.sla-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 16px; }
.state { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: var(--tone-soft); color: var(--tone); font-size: 12px; font-weight: 700; }
.state.paused, .state.approval { background: #fff4d9; color: #a86f00; }
.state.stopped { background: #eef1f4; color: #5e7383; }
.sla-name { color: #8095a3; font-size: 12px; font-weight: 600; }
.figure { display: flex; align-items: baseline; gap: 8px; }
.big { color: var(--tone); font-size: 34px; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.02em; line-height: 1; }
.of { color: #8095a3; font-size: 14px; font-weight: 600; }
.caption { margin-top: 6px; color: #8095a3; font-size: 12px; }
.bar { height: 6px; margin: 14px 0 4px; overflow: hidden; border-radius: 6px; background: #edf1f3; }
.bar span { display: block; height: 100%; border-radius: 6px; background: var(--tone); transition: width .4s ease; }
dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; margin: 16px 0 0; font-size: 13px; }
dt { color: #7a8d99; font-weight: 500; }
dd { margin: 0; color: #24465b; font-weight: 600; text-align: end; font-variant-numeric: tabular-nums; }
dd.late { color: #c63d3a; }
@media (prefers-reduced-motion: reduce) { .bar span { transition: none; } }
</style>
