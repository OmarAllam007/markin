<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { absenceReport as absenceReportRoute, dailySummary as dailySummaryRoute, departmentAttendance as departmentAttendanceRoute, detailedReport as detailedReportRoute, lateArrivals as lateArrivalsRoute, missingPunches as missingPunchesRoute, monthlySummary as monthlySummaryRoute, overtime as overtimeRoute } from '@/routes/reports/index';

const search = ref('');
const iconPaths: Record<string, string> = {
    'ki-chart-pie-4': 'M12 3v9h9 M10 3.2A9 9 0 1 0 20.8 14H10z',
    'ki-calendar': 'M4 5h16v16H4z M8 3v4 M16 3v4 M4 10h16 M8 14h3 M8 18h3 M15 14h2',
    'ki-office-bag': 'M3 8h18v12H3z M8 8V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v3 M3 13c5 3 13 3 18 0 M10 13h4v3h-4z',
    'ki-document': 'M6 2h8l4 4v16H6z M14 2v5h4 M9 11h6 M9 15h6 M9 19h4',
    'ki-time': 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18 M12 7v5l3 2 M9 1h6',
    'ki-clock': 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18 M12 7v5l4 1 M8 1h8',
    'ki-user-cross': 'M9 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M2 21v-2a7 7 0 0 1 7-7 M16 15l5 5 M21 15l-5 5',
    'ki-abstract-26': 'M4 4h16v16H4z M8 8h8 M8 12h5 M8 16h3 M16 15l2 2 3-4',
};
const sections = [
    {
        title: 'Attendance overview',
        description: 'See the bigger picture across days, months and departments.',
        reports: [
            { title: 'Daily summary', description: 'A quick view of attendance across branches and departments.', icon: 'ki-chart-pie-4', href: dailySummaryRoute.url(), tone: 'blue' },
            { title: 'Monthly summary', description: 'A simple month-by-month view of attendance and working days.', icon: 'ki-calendar', href: monthlySummaryRoute.url(), tone: 'teal' },
            { title: 'Department attendance', description: 'Compare attendance patterns between departments.', icon: 'ki-office-bag', href: departmentAttendanceRoute.url(), tone: 'violet' },
        ],
    },
    {
        title: 'Time and hours',
        description: 'Review detailed records and time worked.',
        reports: [
            { title: 'Full detailed report', description: 'Complete attendance records for each employee.', icon: 'ki-document', href: detailedReportRoute.url(), tone: 'blue' },
            { title: 'Overtime report', description: 'Overtime hours for employees in a selected period.', icon: 'ki-time', href: overtimeRoute.url(), tone: 'teal' },
        ],
    },
    {
        title: 'Needs attention',
        description: 'Find exceptions that may need a closer look.',
        reports: [
            { title: 'Late arrivals', description: 'Employees who checked in after their scheduled time.', icon: 'ki-clock', href: lateArrivalsRoute.url(), tone: 'amber' },
            { title: 'Absence report', description: 'Unplanned absences by employee or department.', icon: 'ki-user-cross', href: absenceReportRoute.url(), tone: 'rose' },
            { title: 'Missing punches', description: 'Attendance records without a registered check-out.', icon: 'ki-abstract-26', href: missingPunchesRoute.url(), tone: 'amber' },
        ],
    },
];

const filteredSections = computed(() => sections.map(section => ({
    ...section,
    reports: section.reports.filter(report => `${report.title} ${report.description}`.toLowerCase().includes(search.value.trim().toLowerCase())),
})).filter(section => section.reports.length));
</script>

<template>
    <div class="reports-page">
        <Head title="Reports" />

        <header class="reports-hero">
            <div>
                <span class="hero-eyebrow">ATTENDANCE / REPORTS</span>
                <h1>Reports</h1>
                <p>Choose the view you need to understand attendance, working time and exceptions.</p>
            </div>
            <div class="hero-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path :d="iconPaths['ki-chart-pie-4']" /></svg></div>
        </header>

        <div class="reports-toolbar">
            <div>
                <strong>Explore reports</strong>
                <span>8 reports available</span>
            </div>
            <label class="report-search">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></svg>
                <input v-model="search" type="search" placeholder="Search reports" aria-label="Search reports">
            </label>
        </div>

        <div v-if="!filteredSections.length" class="reports-empty">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path d="m16 16 5 5" /></svg>
            <h2>No reports found</h2>
            <p>Try a different search term.</p>
        </div>

        <section v-for="section in filteredSections" :key="section.title" class="report-section">
            <div class="section-heading">
                <div>
                    <h2>{{ section.title }}</h2>
                    <p>{{ section.description }}</p>
                </div>
                <span>{{ section.reports.length }}</span>
            </div>
            <div class="report-grid">
                <Link v-for="report in section.reports" :key="report.href" :href="report.href" class="report-card" :class="`tone-${report.tone}`">
                    <div class="report-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths[report.icon]" /></svg></div>
                    <div class="report-copy">
                        <h3>{{ report.title }}</h3>
                        <p>{{ report.description }}</p>
                    </div>
                    <span class="report-arrow" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 12h15 M13 6l6 6-6 6" /></svg></span>
                </Link>
            </div>
        </section>
    </div>
</template>

<style scoped>
.reports-page { padding-bottom: 30px; }
.reports-hero { display: flex; align-items: center; justify-content: space-between; gap: 24px; min-height: 184px; padding: 32px 38px; border-radius: 18px; background: linear-gradient(110deg, #173c61, #176b81); color: #fff; overflow: hidden; }
.hero-eyebrow { color: #a9dfe5; font-size: 11px; font-weight: 800; letter-spacing: .13em; }
.reports-hero h1 { margin: 8px 0; color: #fff; font-size: clamp(28px, 3vw, 38px); font-weight: 750; }
.reports-hero p { max-width: 530px; margin: 0; color: #d9ebef; font-size: 14px; line-height: 1.5; }
.hero-mark { display: grid; flex: 0 0 112px; place-items: center; width: 112px; height: 112px; border: 1px solid #ffffff35; border-radius: 28px; background: #ffffff12; transform: rotate(-8deg); }
.hero-mark svg { width: 54px; height: 54px; color: #e0f7f8; transform: rotate(8deg); }
.reports-toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin: 28px 0 30px; }
.reports-toolbar strong { display: block; color: #24465b; font-size: 17px; }
.reports-toolbar span { display: block; margin-top: 2px; color: #7b909d; font-size: 12px; }
.report-search { display: flex; align-items: center; gap: 9px; width: min(100%, 300px); padding: 10px 13px; border: 1px solid #dae5ea; border-radius: 10px; background: #fff; color: #8094a0; }
.report-search svg { width: 17px; height: 17px; flex: 0 0 17px; }
.report-search:focus-within { border-color: #2aa2a9; box-shadow: 0 0 0 3px #2aa2a91f; }
.report-search input { width: 100%; border: 0; outline: 0; background: transparent; color: #24465b; font-size: 13px; }
.report-section { margin-bottom: 32px; }
.section-heading { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 14px; }
.section-heading h2 { margin: 0 0 3px; color: #24465b; font-size: 18px; font-weight: 750; }
.section-heading p { margin: 0; color: #8496a1; font-size: 12px; }
.section-heading > span { display: grid; place-items: center; min-width: 26px; height: 26px; border-radius: 8px; background: #e8f2f4; color: #38737d; font-size: 12px; font-weight: 700; }
.report-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
.report-card { --tone: #276c9d; --tone-bg: #e8f2f8; display: flex; align-items: flex-start; gap: 15px; min-height: 136px; padding: 22px; border: 1px solid #e3ebef; border-radius: 14px; background: #fff; box-shadow: 0 5px 18px #1c415908; text-decoration: none; transition: border-color .15s, box-shadow .15s, transform .15s; }
.report-card:hover { border-color: #bad6df; box-shadow: 0 10px 24px #1c415914; transform: translateY(-2px); }
.report-card:focus-visible { outline: 3px solid #2aa2a9; outline-offset: 2px; }
.tone-teal { --tone: #16838b; --tone-bg: #e4f4f3; }
.tone-violet { --tone: #6876a8; --tone-bg: #eef0f8; }
.tone-amber { --tone: #a87824; --tone-bg: #fbf3e3; }
.tone-rose { --tone: #b55f68; --tone-bg: #faedef; }
.report-icon { display: grid; flex: 0 0 42px; place-items: center; width: 42px; height: 42px; border-radius: 12px; background: var(--tone-bg); color: var(--tone); font-size: 23px; }
.report-icon svg { width: 23px; height: 23px; }
.report-copy { min-width: 0; flex: 1; }
.report-copy h3 { margin: 1px 0 7px; color: #263f51; font-size: 15px; font-weight: 750; }
.report-copy p { margin: 0; color: #748894; font-size: 12px; line-height: 1.5; }
.report-arrow { align-self: flex-end; color: #91a8b2; font-size: 17px; }
.report-arrow svg { width: 17px; height: 17px; }
.reports-page svg { fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.reports-empty svg { width: 30px; height: 30px; }
.reports-empty { padding: 58px 20px; border: 1px dashed #cfdee4; border-radius: 14px; text-align: center; color: #8195a1; }
.reports-empty i { font-size: 30px; }
.reports-empty h2 { margin: 10px 0 5px; color: #355468; font-size: 17px; }
.reports-empty p { margin: 0; font-size: 13px; }
@media (max-width: 1199px) { .report-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 767px) { .reports-hero { padding: 26px; } .hero-mark { display: none; } .report-grid { grid-template-columns: minmax(0, 1fr); } .report-card { min-height: 116px; } .report-search { width: 100%; } }
@media (prefers-reduced-motion: reduce) { .report-card { transition: none; } }
</style>
