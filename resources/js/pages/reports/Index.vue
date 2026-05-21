<script setup lang="ts">
import { absenceReport as absenceReportRoute, dailySummary as dailySummaryRoute, departmentAttendance as departmentAttendanceRoute, detailedReport as detailedReportRoute, lateArrivals as lateArrivalsRoute, missingPunches as missingPunchesRoute, monthlySummary as monthlySummaryRoute, overtime as overtimeRoute } from '@/routes/reports/index';
import { Head, Link } from '@inertiajs/vue3';

const reports = [
    {
        key: 'daily-summary',
        title: 'Daily Summary',
        description: 'Summary of branches and departments',
        icon: 'ki-chart-pie-4',
        color: 'bg-primary',
        textColor: 'text-white',
        href: dailySummaryRoute.url(),
        available: true,
    },
    {
        key: 'overtime',
        title: 'Overtime Report',
        description: 'Employee overtime hours by period',
        icon: 'ki-time',
        color: 'bg-success',
        textColor: 'text-white',
        href: overtimeRoute.url(),
        available: true,
    },
    {
        key: 'full-detail',
        title: 'Full Detailed Report',
        description: 'Complete attendance record per employee',
        icon: 'ki-document',
        color: 'bg-dark',
        textColor: 'text-white',
        href: detailedReportRoute.url(),
        available: true,
    },
    {
        key: 'monthly-summary',
        title: 'Monthly Summary',
        description: 'Simplified monthly attendance overview',
        icon: 'ki-calendar',
        color: 'bg-info',
        textColor: 'text-white',
        href: monthlySummaryRoute.url(),
        available: true,
    },
    {
        key: 'late-arrivals',
        title: 'Late Arrivals',
        description: 'Employees arriving after scheduled check-in',
        icon: 'ki-clock',
        color: 'bg-warning',
        textColor: 'text-dark',
        href: lateArrivalsRoute.url(),
        available: true,
    },
    {
        key: 'absence',
        title: 'Absence Report',
        description: 'Unplanned absences by employee or department',
        icon: 'ki-user-cross',
        color: 'bg-danger',
        textColor: 'text-white',
        href: absenceReportRoute.url(),
        available: true,
    },
    {
        key: 'missing-punches',
        title: 'Missing Punches',
        description: 'Records with no check-out registered',
        icon: 'ki-abstract-26',
        color: 'bg-secondary',
        textColor: 'text-dark',
        href: missingPunchesRoute.url(),
        available: true,
    },
    {
        key: 'department-attendance',
        title: 'Department Attendance',
        description: 'Attendance trends grouped by department',
        icon: 'ki-office-bag',
        color: 'bg-primary',
        textColor: 'text-white',
        href: departmentAttendanceRoute.url(),
        available: true,
    },
];
</script>

<template>
    <div>
        <Head title="Reports" />

        <div class="card mb-6">
            <div class="card-body py-6">
                <h2 class="fw-bold text-gray-800 mb-1">Reports</h2>
                <span class="text-muted fs-6">Select a report to view attendance insights</span>
            </div>
        </div>

        <div class="row g-5">
            <div
                v-for="report in reports"
                :key="report.key"
                class="col-sm-6 col-xl-3"
            >
                <component
                    :is="report.available ? Link : 'div'"
                    v-bind="report.available ? { href: report.href } : {}"
                    class="card h-100 border-0 overflow-hidden"
                    :class="[report.available ? 'cursor-pointer card-hover' : 'opacity-65']"
                    style="text-decoration: none"
                >
                    <!--begin::Colored header-->
                    <div
                        class="d-flex align-items-center justify-content-between px-6 py-8"
                        :class="report.color"
                    >
                        <i
                            class="ki-outline fs-3x opacity-75"
                            :class="[report.icon, report.textColor]"
                        ></i>
                        <span
                            v-if="!report.available"
                            class="badge bg-white bg-opacity-25 text-white fs-8 fw-semibold px-3 py-2"
                        >
                            Coming soon
                        </span>
                        <i
                            v-else
                            class="ki-outline ki-arrow-right fs-2 text-white opacity-50"
                        ></i>
                    </div>
                    <!--end::Colored header-->

                    <!--begin::Body-->
                    <div class="card-body border border-top-0 rounded-bottom px-6 py-5">
                        <div class="fw-bold text-gray-800 fs-5 mb-1">{{ report.title }}</div>
                        <div class="text-muted fs-7">{{ report.description }}</div>
                    </div>
                    <!--end::Body-->
                </component>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card-hover {
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.card-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
}
</style>
