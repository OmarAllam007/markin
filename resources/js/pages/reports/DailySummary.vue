<script setup lang="ts">
import ExportButtons from '@/components/ExportButtons.vue';
import { dailySummary as dailySummaryRoute } from '@/routes/reports/index';
import { groupDetail as groupDetailRoute } from '@/routes/reports/daily-summary';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type GroupData = {
    group_id: number;
    name: string;
    total: number;
    on_time: number;
    late: number;
    absent: number;
    on_leave: number;
    off_days: number;
};

type EmployeeRow = {
    employee_number: string | null;
    name: string | null;
    status: string;
    status_label: string;
    shift_name: string | null;
    check_in_time: string | null;
    check_out_time: string | null;
    location: string | null;
    total_late_minutes: number;
    total_early_leave_minutes: number;
    overtime_minutes: number;
};

const props = defineProps<{
    groups: GroupData[];
    filters: {
        date: string;
        group_by: 'departments' | 'locations';
    };
    filterOptions: {
        locations: { id: number; name: string }[];
        departments: { id: number; name: string }[];
    };
}>();

const date = ref(props.filters.date);
const groupBy = ref<'departments' | 'locations'>(props.filters.group_by);

const search = () => {
    router.get(
        dailySummaryRoute.url(),
        { date: date.value, group_by: groupBy.value },
        { preserveState: true, replace: true, only: ['groups', 'filters'] },
    );
};

const exportUrl = computed(() => {
    const q = new URLSearchParams({ date: date.value, group_by: groupBy.value, export: 'xlsx' });
    return dailySummaryRoute.url() + '?' + q.toString();
});

// ── Group detail modal ────────────────────────────────────────────────────────
const modalGroup = ref<GroupData | null>(null);
const modalEmployees = ref<EmployeeRow[]>([]);
const modalLoading = ref(false);

async function openGroupModal(group: GroupData) {
    modalGroup.value = group;
    modalEmployees.value = [];
    modalLoading.value = true;

    const url = groupDetailRoute.url({
        query: { date: date.value, group_by: groupBy.value, group_id: group.group_id },
    });

    const res = await fetch(url, { headers: { Accept: 'application/json' } });
    const data = await res.json();
    modalEmployees.value = data.employees ?? [];
    modalLoading.value = false;
}

function closeModal() {
    modalGroup.value = null;
    modalEmployees.value = [];
}

function statusBadgeClass(status: string): string {
    if (['present', 'remote'].includes(status)) return 'badge-light-success';
    if (['late', 'missing_checkout'].includes(status)) return 'badge-light-warning';
    if (status === 'absent') return 'badge-light-danger';
    if (['leave', 'business_trip', 'half_day'].includes(status)) return 'badge-light-info';
    return 'badge-light-secondary';
}

function fmtMins(m: number): string {
    if (m === 0) return '—';
    const h = Math.floor(m / 60);
    const mm = m % 60;
    return h > 0 ? `${h}h ${mm}m` : `${mm}m`;
}

// ── Chart colors ─────────────────────────────────────────────────────────────
const CHART_COLORS = ['#3E97FF', '#50CD89', '#F1416C', '#FFC700', '#181C32'];
const LEGEND_ITEMS = [
    { label: 'On-Time', color: CHART_COLORS[0] },
    { label: 'Late', color: CHART_COLORS[1] },
    { label: 'Absent', color: CHART_COLORS[2] },
    { label: 'On Leave', color: CHART_COLORS[3] },
    { label: 'Off days (Weekend)', color: CHART_COLORS[4] },
];

// ── Chart refs & instances ────────────────────────────────────────────────────
const chartRefs = ref<(HTMLElement | null)[]>([]);
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const chartInstances: any[] = [];

function seriesFor(g: GroupData): number[] {
    return [g.on_time, g.late, g.absent, g.on_leave, g.off_days];
}

function renderCharts() {
    destroyCharts();

    if (typeof window === 'undefined' || !(window as any).ApexCharts) {
        return;
    }

    props.groups.forEach((group, i) => {
        const el = chartRefs.value[i];
        if (!el) return;

        const series = seriesFor(group);
        const total = series.reduce((a, b) => a + b, 0);

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const chart = new (window as any).ApexCharts(el, {
            series,
            chart: {
                type: 'donut',
                height: 220,
                toolbar: { show: false },
                sparkline: { enabled: false },
            },
            labels: LEGEND_ITEMS.map((l) => l.label),
            colors: CHART_COLORS,
            dataLabels: {
                enabled: true,
                formatter: (val: number) => (val > 0 ? `${val.toFixed(1)}%` : ''),
                style: { fontSize: '11px', fontWeight: 600 },
                dropShadow: { enabled: false },
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                fontSize: '13px',
                                fontWeight: 600,
                                color: '#5E6278',
                                formatter: () => String(total),
                            },
                        },
                    },
                },
            },
            legend: { show: false },
            tooltip: {
                y: { formatter: (val: number) => `${val} employee(s)` },
            },
            stroke: { width: 2 },
        });

        chart.render();
        chartInstances.push(chart);
    });
}

function destroyCharts() {
    while (chartInstances.length) {
        chartInstances.pop()?.destroy();
    }
}

onMounted(() => renderCharts());
onUnmounted(() => destroyCharts());
watch(() => props.groups, () => renderCharts(), { flush: 'post' });

function pct(val: number, total: number): string {
    if (total === 0) return '0%';
    return `${Math.round((val / total) * 100)}%`;
}
</script>

<template>
    <div>
        <Head title="Daily Summary Report" />

        <!--begin::Filter card-->
        <div class="card mb-6 no-print">
            <div class="card-header border-0 pt-6 d-flex justify-content-between align-items-center">
                <div class="card-title">
                    <i class="ki-outline ki-chart-pie-4 fs-1 text-primary me-3"></i>
                    <h2 class="fw-bold">Daily Summary Report</h2>
                </div>
                <div class="no-print">
                    <ExportButtons :export-url="exportUrl" />
                </div>
            </div>
            <div class="card-body pt-2 pb-6">
                <div class="row g-4 align-items-end">
                    <!--begin::Date-->
                    <div class="col-md-4 col-xl-3">
                        <label class="form-label fs-7">Date</label>
                        <input
                            v-model="date"
                            type="date"
                            class="form-control form-control-solid"
                        />
                    </div>
                    <!--end::Date-->

                    <!--begin::Group by-->
                    <div class="col-md-4 col-xl-3">
                        <label class="form-label fs-7">Group by</label>
                        <div class="d-flex gap-6 pt-1">
                            <label class="d-flex align-items-center gap-2 cursor-pointer">
                                <input
                                    v-model="groupBy"
                                    type="radio"
                                    class="form-check-input mt-0"
                                    value="locations"
                                />
                                <span class="fs-6 fw-semibold text-gray-700">Locations</span>
                            </label>
                            <label class="d-flex align-items-center gap-2 cursor-pointer">
                                <input
                                    v-model="groupBy"
                                    type="radio"
                                    class="form-check-input mt-0"
                                    value="departments"
                                />
                                <span class="fs-6 fw-semibold text-gray-700">Departments</span>
                            </label>
                        </div>
                    </div>
                    <!--end::Group by-->

                    <!--begin::Search button-->
                    <div class="col-md-4 col-xl-2">
                        <button
                            type="button"
                            class="btn btn-primary w-100"
                            @click="search"
                        >
                            <i class="ki-outline ki-magnifier fs-4 me-1"></i>
                            Search
                        </button>
                    </div>
                    <!--end::Search button-->
                </div>

                <!--begin::Grouping guide-->
                <div class="mt-6 pt-5 border-top border-gray-200">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <i class="ki-outline ki-information-5 fs-5 text-gray-500"></i>
                        <span class="fs-7 fw-semibold text-gray-600">How chart categories are calculated</span>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6 col-xl">
                            <div class="d-flex align-items-start gap-2">
                                <span class="rounded-circle d-inline-block flex-shrink-0 mt-1" :style="{ width: '8px', height: '8px', backgroundColor: CHART_COLORS[0] }"></span>
                                <div>
                                    <span class="fw-semibold text-gray-700 fs-8">On-Time</span>
                                    <div class="text-muted fs-8">Present · Remote</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl">
                            <div class="d-flex align-items-start gap-2">
                                <span class="rounded-circle d-inline-block flex-shrink-0 mt-1" :style="{ width: '8px', height: '8px', backgroundColor: CHART_COLORS[1] }"></span>
                                <div>
                                    <span class="fw-semibold text-gray-700 fs-8">Late</span>
                                    <div class="text-muted fs-8">Late · Missing Checkout</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl">
                            <div class="d-flex align-items-start gap-2">
                                <span class="rounded-circle d-inline-block flex-shrink-0 mt-1" :style="{ width: '8px', height: '8px', backgroundColor: CHART_COLORS[2] }"></span>
                                <div>
                                    <span class="fw-semibold text-gray-700 fs-8">Absent</span>
                                    <div class="text-muted fs-8">Absent</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl">
                            <div class="d-flex align-items-start gap-2">
                                <span class="rounded-circle d-inline-block flex-shrink-0 mt-1" :style="{ width: '8px', height: '8px', backgroundColor: CHART_COLORS[3] }"></span>
                                <div>
                                    <span class="fw-semibold text-gray-700 fs-8">On Leave</span>
                                    <div class="text-muted fs-8">Leave · Business Trip · Half Day</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl">
                            <div class="d-flex align-items-start gap-2">
                                <span class="rounded-circle d-inline-block flex-shrink-0 mt-1" :style="{ width: '8px', height: '8px', backgroundColor: CHART_COLORS[4] }"></span>
                                <div>
                                    <span class="fw-semibold text-gray-700 fs-8">Off Days</span>
                                    <div class="text-muted fs-8">Weekend · Holiday</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!--end::Grouping guide-->
            </div>
        </div>
        <!--end::Filter card-->

        <!--begin::Empty state-->
        <div
            v-if="groups.length === 0"
            class="card"
        >
            <div class="card-body text-center py-12">
                <i class="ki-outline ki-chart-pie-4 fs-3x text-gray-300 mb-4 d-block"></i>
                <span class="fs-6 text-gray-400">No attendance records found for {{ filters.date }}.</span>
            </div>
        </div>
        <!--end::Empty state-->

        <!--begin::Charts grid-->
        <div
            v-else
            class="row g-5"
        >
            <div
                v-for="(group, idx) in groups"
                :key="group.name"
                class="col-sm-6 col-xl-4"
            >
                <div
                    class="card h-100 cursor-pointer card-hover"
                    @click="openGroupModal(group)"
                >
                    <div class="card-body p-6">
                        <!--begin::Header-->
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <span class="fw-bold text-gray-800 fs-6">{{ group.name }}</span>
                            <span class="badge badge-light-primary fs-8">{{ group.total }} employees</span>
                        </div>
                        <!--end::Header-->

                        <!--begin::Chart-->
                        <div
                            :ref="(el) => { chartRefs[idx] = el as HTMLElement | null }"
                            class="w-100"
                            style="min-height: 220px"
                        ></div>
                        <!--end::Chart-->

                        <!--begin::Legend-->
                        <div class="mt-4">
                            <div
                                v-for="item in LEGEND_ITEMS"
                                :key="item.label"
                                class="d-flex align-items-center justify-content-between py-1"
                            >
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="rounded-circle d-inline-block flex-shrink-0"
                                        :style="{ width: '10px', height: '10px', backgroundColor: item.color }"
                                    ></span>
                                    <span class="text-gray-600 fs-7">{{ item.label }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="fw-bold text-gray-800 fs-7">
                                        {{
                                            item.label === 'On-Time' ? group.on_time
                                            : item.label === 'Late' ? group.late
                                            : item.label === 'Absent' ? group.absent
                                            : item.label === 'On Leave' ? group.on_leave
                                            : group.off_days
                                        }}
                                    </span>
                                    <span class="text-muted fs-8 w-30px text-end">
                                        {{
                                            pct(
                                                item.label === 'On-Time' ? group.on_time
                                                : item.label === 'Late' ? group.late
                                                : item.label === 'Absent' ? group.absent
                                                : item.label === 'On Leave' ? group.on_leave
                                                : group.off_days,
                                                group.total
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!--end::Legend-->
                    </div>
                </div>
            </div>
        </div>
        <!--end::Charts grid-->

        <!--begin::Group detail modal-->
        <Teleport to="body">
            <div
                v-if="modalGroup"
                class="modal-backdrop-ds"
                @click.self="closeModal"
            >
                <div class="modal-dialog-ds">
                    <!--begin::Header-->
                    <div class="d-flex align-items-center justify-content-between px-7 py-5 border-bottom">
                        <div class="d-flex align-items-center gap-3">
                            <div class="w-40px h-40px rounded-circle bg-light-primary d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="ki-outline ki-people fs-4 text-primary"></i>
                            </div>
                            <div>
                                <h5 class="fw-bold text-gray-800 mb-0">{{ modalGroup.name }}</h5>
                                <div class="fs-7 text-muted">{{ filters.date }} · {{ modalGroup.total }} employees</div>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-light"
                            @click="closeModal"
                        >
                            <i class="ki-outline ki-cross fs-4"></i>
                        </button>
                    </div>
                    <!--end::Header-->

                    <!--begin::Body-->
                    <div class="modal-body-ds">
                        <!--begin::Loading-->
                        <div
                            v-if="modalLoading"
                            class="d-flex align-items-center justify-content-center py-12"
                        >
                            <span class="spinner-border spinner-border-sm text-primary me-3"></span>
                            <span class="text-muted fs-7">Loading employees…</span>
                        </div>
                        <!--end::Loading-->

                        <!--begin::Empty-->
                        <div
                            v-else-if="modalEmployees.length === 0"
                            class="text-center py-12"
                        >
                            <i class="ki-outline ki-people fs-3x text-gray-300 mb-4 d-block"></i>
                            <span class="fs-6 text-gray-400">No records found.</span>
                        </div>
                        <!--end::Empty-->

                        <!--begin::Table-->
                        <div
                            v-else
                            class="table-responsive"
                        >
                            <table class="table table-row-bordered table-row-gray-200 align-middle gs-0 gy-3 gx-5">
                                <thead>
                                    <tr class="fw-bold text-gray-600 fs-7 border-bottom">
                                        <th class="min-w-80px">Emp #</th>
                                        <th class="min-w-160px">Name</th>
                                        <th class="min-w-100px">Status</th>
                                        <th class="min-w-100px">Shift</th>
                                        <th class="min-w-100px">Check-in</th>
                                        <th class="min-w-100px">Check-out</th>
                                        <th class="min-w-80px text-center">Late</th>
                                        <th class="min-w-80px text-center">Early Leave</th>
                                        <th class="min-w-80px text-center">Overtime</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="emp in modalEmployees"
                                        :key="(emp.employee_number ?? '') + emp.name"
                                    >
                                        <td class="text-gray-600 fs-7">{{ emp.employee_number ?? '—' }}</td>
                                        <td class="fw-semibold text-gray-800 fs-7">{{ emp.name ?? '—' }}</td>
                                        <td>
                                            <span
                                                class="badge fs-8"
                                                :class="statusBadgeClass(emp.status)"
                                            >{{ emp.status_label }}</span>
                                        </td>
                                        <td class="text-gray-600 fs-7">{{ emp.shift_name ?? '—' }}</td>
                                        <td>
                                            <span
                                                v-if="emp.check_in_time"
                                                class="fw-semibold text-gray-800 fs-7"
                                            >{{ emp.check_in_time }}</span>
                                            <span
                                                v-else
                                                class="text-muted fs-7"
                                            >—</span>
                                            <span
                                                v-if="emp.location"
                                                class="badge badge-light ms-2 fs-8"
                                            >{{ emp.location }}</span>
                                        </td>
                                        <td>
                                            <span
                                                v-if="emp.check_out_time"
                                                class="fw-semibold text-gray-800 fs-7"
                                            >{{ emp.check_out_time }}</span>
                                            <span
                                                v-else
                                                class="text-muted fs-7"
                                            >—</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                v-if="emp.total_late_minutes > 0"
                                                class="badge badge-light-danger fs-8"
                                            >{{ fmtMins(emp.total_late_minutes) }}</span>
                                            <span
                                                v-else
                                                class="text-muted fs-8"
                                            >—</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                v-if="emp.total_early_leave_minutes > 0"
                                                class="badge badge-light-secondary fs-8"
                                            >{{ fmtMins(emp.total_early_leave_minutes) }}</span>
                                            <span
                                                v-else
                                                class="text-muted fs-8"
                                            >—</span>
                                        </td>
                                        <td class="text-center">
                                            <span
                                                v-if="emp.overtime_minutes > 0"
                                                class="badge badge-light-success fs-8"
                                            >{{ fmtMins(emp.overtime_minutes) }}</span>
                                            <span
                                                v-else
                                                class="text-muted fs-8"
                                            >—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!--end::Table-->
                    </div>
                    <!--end::Body-->
                </div>
            </div>
        </Teleport>
        <!--end::Group detail modal-->
    </div>
</template>

<style scoped>
.modal-backdrop-ds {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1055;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.modal-dialog-ds {
    background: #fff;
    border-radius: 0.75rem;
    width: 100%;
    max-width: 1100px;
    max-height: calc(100vh - 3rem);
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.modal-body-ds {
    overflow-y: auto;
    flex: 1;
    padding: 1.5rem;
}

.card-hover:hover {
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}
</style>
