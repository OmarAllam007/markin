<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { switchMethod as switchTenantRoute } from '@/actions/App/Http/Controllers/TenantController';
import SettingsModal from '@/pages/_shared/SettingsModal.vue';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as attendancesIndex } from '@/routes/attendances';
import { index as departmentsIndex } from '@/routes/departments';
import { index as employeesIndex } from '@/routes/employees';
import { index as holidaysIndex } from '@/routes/holidays/index';
import { index as leaveTypesIndex } from '@/routes/leave-types/index';
import { index as leavesIndex } from '@/routes/leaves/index';
import { index as locationsIndex } from '@/routes/locations';
import { index as reportsIndex } from '@/routes/reports/index';
import { index as ticketApprovalRolesIndex } from '@/routes/ticketing/admin/approval-roles/index';
import { index as ticketCategoriesIndex } from '@/routes/ticketing/admin/categories/index';
import { index as ticketGroupsIndex } from '@/routes/ticketing/admin/groups/index';
import { index as ticketPrioritiesIndex } from '@/routes/ticketing/admin/priorities/index';
import { index as ticketRequestStatusesIndex } from '@/routes/ticketing/admin/request-statuses/index';
import { index as ticketServiceCatalogIndex } from '@/routes/ticketing/admin/service-catalog/index';
import { index as ticketSlasIndex } from '@/routes/ticketing/admin/slas/index';
import { index as ticketSubcategoriesIndex } from '@/routes/ticketing/admin/subcategories/index';
import { index as ticketsIndex } from '@/routes/ticketing/tickets/index';
import { index as usersIndex } from '@/routes/users';
import { index as workShiftsIndex } from '@/routes/work-shifts';
import { index as zkMachinesIndex } from '@/routes/zk-machines/index';

defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
type Tenant = { id: number; name: string; parent_id: number | null };
const page = usePage<{ auth: { currentTenant: Tenant | null; switchableTenants: Tenant[] }; enabledModules: string[]; canManageTicketing?: boolean }>();
const settingsOpen = ref(false);
const tenantOpen = ref(false);
const currentPath = computed(() => page.url.split('?')[0]);
const hasTicketing = computed(() => page.props.enabledModules?.includes('ticketing') ?? false);
const tenants = computed(() => page.props.auth?.switchableTenants ?? []);
const currentTenant = computed(() => page.props.auth?.currentTenant);
const iconPaths: Record<string, string> = {
    overview: 'M3 3h8v8H3z M13 3h8v5h-8z M13 10h8v11h-8z M3 13h8v8H3z',
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    clock: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20 M12 6v6l4 2',
    chart: 'M3 3v18h18 M7 17v-4 M12 17V8 M17 17v-7',
    building: 'M3 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16 M17 9h2a2 2 0 0 1 2 2v10 M2 21h20 M7 7h2 M12 7h2 M7 11h2 M12 11h2 M8 21v-5h4v5',
    pin: 'M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0 M12 10a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5',
    shift: 'M4 7h12 M13 4l3 3-3 3 M20 17H8 M11 14l-3 3 3 3 M4 11V7 M20 13v4',
    calendar: 'M3 5h18v16H3z M7 3v4 M17 3v4 M3 10h18 M8 14h3 M8 18h3 M15 14h2',
    device: 'M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2 M8 6h8 M8 10h8 M10 18h4',
    megaphone: 'M3 11v3a2 2 0 0 0 2 2h2l2 5h3l-2-5h2l8 4V5l-8 4H5a2 2 0 0 0-2 2 M20 10a2 2 0 0 1 0 4',
    ticket: 'M3 5h18v5a2 2 0 0 0 0 4v5H3v-5a2 2 0 0 0 0-4z M13 5v14 M7 9h2 M7 15h2',
    folder: 'M3 6a2 2 0 0 1 2-2h5l2 3h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
    layers: 'M12 2 2 8l10 6 10-6z M2 12l10 6 10-6 M2 16l10 6 10-6',
    flag: 'M5 22V3 M5 4h14l-2 5 2 5H5',
    timer: 'M9 2h6 M12 14l3-3 M12 22a8 8 0 1 0 0-16 8 8 0 0 0 0 16 M18 5l2-2',
    form: 'M7 3h10a2 2 0 0 1 2 2v16H5V5a2 2 0 0 1 2-2 M9 8h6 M9 12h6 M9 16h4',
    settings: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M10 2h4l.6 2.2 2 .8 2-.9 2.8 2.8-.9 2 .8 2L23 11v4l-2.2.6-.8 2 .9 2-2.8 2.8-2-.9-2 .8L14 23h-4l-.6-2.2-2-.8-2 .9-2.8-2.8.9-2-.8-2L1 15v-4l2.2-.6.8-2-.9-2L5.9 3.6l2 .9 2-.8z',
    home: 'M3 10 12 3l9 7v11h-7v-6h-4v6H3z',
};
const sections = computed(() => [
    { title: 'Main', links: [
        { label: 'Overview', href: '/', icon: 'overview' },
        { label: 'Employees', href: employeesIndex.url(), icon: 'users' },
        { label: 'Attendance', href: attendancesIndex.url(), icon: 'clock' },
        { label: 'Reports', href: reportsIndex.url(), icon: 'chart' },
        { label: 'Leave requests', href: leavesIndex.url(), icon: 'form' },
    ] },
    { title: 'Organization', links: [
        { label: 'Departments', href: departmentsIndex.url(), icon: 'building' },
        { label: 'Locations', href: locationsIndex.url(), icon: 'pin' },
        { label: 'Work shifts', href: workShiftsIndex.url(), icon: 'shift' },
        { label: 'Holidays', href: holidaysIndex.url(), icon: 'calendar' },
        { label: 'Leave types', href: leaveTypesIndex.url(), icon: 'folder' },
        { label: 'Users', href: usersIndex.url(), icon: 'users' },
        { label: 'Devices', href: zkMachinesIndex.url(), icon: 'device' },
    ] },
    { title: 'Communication', links: [
        { label: 'Announcements', href: announcementsIndex.url(), icon: 'megaphone' },
        ...(hasTicketing.value ? [{ label: 'Tickets', href: ticketsIndex.url(), icon: 'ticket' }] : []),
    ] },
    ...(hasTicketing.value && page.props.canManageTicketing ? [{ title: 'Ticket setup', links: [
        { label: 'Categories', href: ticketCategoriesIndex.url(), icon: 'folder' },
        { label: 'Subcategories', href: ticketSubcategoriesIndex.url(), icon: 'layers' },
        { label: 'Priorities', href: ticketPrioritiesIndex.url(), icon: 'flag' },
        { label: 'SLAs', href: ticketSlasIndex.url(), icon: 'timer' },
        { label: 'Groups', href: ticketGroupsIndex.url(), icon: 'users' },
        { label: 'Request statuses', href: ticketRequestStatusesIndex.url(), icon: 'status' },
        { label: 'Approval roles', href: ticketApprovalRolesIndex.url(), icon: 'security-user' },
        { label: 'Service catalog', href: ticketServiceCatalogIndex.url(), icon: 'technology-4' },
    ] }] : []),
]);
function switchTenant(id: number) {
 tenantOpen.value = false; router.post(switchTenantRoute.url(), { tenant_id: id }); 
}
function active(href: string) {
 return href === '/' ? currentPath.value === '/' : currentPath.value === href || currentPath.value.startsWith(`${href}/`); 
}
</script>

<template>
    <aside class="workspace-sidebar" :class="{ 'is-open': open }" aria-label="Main navigation">
        <div class="sidebar-brand"><div class="brand-mark">A</div><div><strong>Alatheer</strong><span>WORKSPACE</span></div><button class="sidebar-close" type="button" aria-label="Close navigation" @click="emit('close')">×</button></div>
        <div class="sidebar-scroll">
            <div v-if="tenants.length" class="tenant-area"><span class="section-label">Active company</span><button class="tenant-button" type="button" :aria-expanded="tenantOpen" @click="tenantOpen = !tenantOpen"><svg class="tenant-symbol nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths.home" /></svg><span>{{ currentTenant?.name ?? 'Select company' }}</span><span>⌄</span></button><div v-if="tenantOpen" class="tenant-list"><button v-for="tenant in tenants" :key="tenant.id" type="button" @click="switchTenant(tenant.id)">{{ tenant.name }}</button></div></div>
            <nav><section v-for="section in sections" :key="section.title" class="nav-section"><h2 class="section-label">{{ section.title }}</h2><Link v-for="item in section.links" :key="item.href" :href="item.href" class="nav-link" :class="{ active: active(item.href) }" :aria-current="active(item.href) ? 'page' : undefined" @click="emit('close')"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths[item.icon]" /></svg><span>{{ item.label }}</span></Link></section></nav>
        </div>
        <div class="sidebar-bottom"><button type="button" class="nav-link settings-button" @click="settingsOpen = true"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path :d="iconPaths.settings" /></svg><span>Settings</span></button><span class="sidebar-version">Alatheer workspace</span></div>
        <Teleport to="body"><SettingsModal v-if="settingsOpen" @close="settingsOpen = false" /></Teleport>
    </aside>
</template>

<style scoped>
.workspace-sidebar { width: 264px; flex: 0 0 264px; height: 100vh; position: sticky; top: 0; z-index: 45; display: flex; flex-direction: column; background: #19243E; color: #f8faff; }
.sidebar-brand { height: 88px; padding: 0 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #ffffff1a; }
.brand-mark { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; background: #bdcbed; color: #19243E; font-size: 23px; font-weight: 800; }
.sidebar-brand strong { display: block; font-size: 18px; letter-spacing: .02em; }.sidebar-brand span { display: block; color: #b9c7df; font-size: 9px; letter-spacing: .19em; }
.sidebar-close { display: none; margin-inline-start: auto; color: white; background: none; border: 0; font-size: 28px; }
.sidebar-scroll { padding: 22px 14px; overflow-y: auto; flex: 1; scrollbar-width: thin; }
.section-label { display: block; margin: 0 12px 10px; color: #9cabc7; font-size: 10px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
.tenant-area { margin-bottom: 28px; }.tenant-button { width: 100%; display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid #ffffff27; border-radius: 12px; color: white; background: #ffffff0d; text-align: start; }.tenant-button span:nth-child(2) { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }.tenant-symbol { background: #ffffff18; padding: 5px 9px; border-radius: 8px; }.tenant-list { margin-top: 6px; padding: 5px; background: #273654; border-radius: 10px; }.tenant-list button { width: 100%; padding: 8px 10px; border: 0; background: transparent; color: white; text-align: start; border-radius: 7px; }.tenant-list button:hover { background: #ffffff18; }
.nav-section { margin-bottom: 24px; }.nav-link { display: flex; align-items: center; gap: 12px; min-height: 40px; margin: 3px 0; padding: 8px 12px; border-radius: 10px; color: #cbd5e7; font-size: 13px; font-weight: 550; text-decoration: none; transition: background .15s, color .15s; }.nav-link:hover { color: white; background: #ffffff14; }.nav-link.active { color: #19243E; background: #dce5fa; font-weight: 750; }.nav-icon { width: 19px; height: 19px; flex: 0 0 19px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.sidebar-bottom { padding: 10px 14px 20px; border-top: 1px solid #ffffff1a; }.settings-button { width: 100%; border: 0; background: transparent; text-align: start; }.sidebar-version { display: block; padding: 6px 12px 0; color: #9aa9c3; font-size: 11px; }
@media (max-width: 1023px) { .workspace-sidebar { position: fixed; inset-block: 0; inset-inline-start: 0; transform: translateX(-100%); transition: transform .2s ease; box-shadow: 15px 0 40px #0002; }.workspace-sidebar.is-open { transform: translateX(0); }.sidebar-close { display: block; } }
:global([dir='rtl']) .workspace-sidebar { direction: rtl; }
@media (max-width: 1023px) { :global([dir='rtl']) .workspace-sidebar { transform: translateX(100%); } :global([dir='rtl']) .workspace-sidebar.is-open { transform: translateX(0); } }
</style>
