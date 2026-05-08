<script setup lang="ts">
import { switchMethod as switchTenantRoute } from '@/actions/App/Http/Controllers/TenantController';
import { index as departmentsIndex } from '@/routes/departments';
import { index as locationsIndex } from '@/routes/locations';
import { index as usersIndex } from '@/routes/users';
import { index as announcementsIndex } from '@/routes/announcements';
import { index as employeesIndex } from '@/routes/employees';
import { index as workShiftsIndex } from '@/routes/work-shifts';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SettingsModal from '@/pages/_shared/SettingsModal.vue';

const settingsOpen = ref(false);

type TenantOption = { id: number; name: string; parent_id: number | null; [key: string]: unknown };

const page = usePage<{
    auth: {
        currentTenant: TenantOption | null;
        switchableTenants: TenantOption[];
    };
}>();

const currentTenant = computed(() => page.props.auth?.currentTenant ?? null);
const switchableTenants = computed(
    () => page.props.auth?.switchableTenants ?? [],
);

function buildTree(
    tenants: TenantOption[],
    parentId: number | null = null,
    depth = 0,
): Array<TenantOption & { depth: number }> {
    return tenants
        .filter((t) => t.parent_id === parentId)
        .flatMap((t) => [
            { ...t, depth },
            ...buildTree(tenants, t.id, depth + 1),
        ]);
}

const orderedTenants = computed(() => buildTree(switchableTenants.value));

const dropdownOpen = ref(false);

const switchTenant = (tenantId: number) => {
    dropdownOpen.value = false;
    router.post(switchTenantRoute.url(), { tenant_id: tenantId });
};
</script>

<template>
    <!--begin::Sidebar-->
    <div
        id="kt_app_sidebar"
        class="app-sidebar flex-column"
        data-kt-drawer="true"
        data-kt-drawer-name="app-sidebar"
        data-kt-drawer-activate="{default: true, lg: false}"
        data-kt-drawer-overlay="true"
        data-kt-drawer-width="300px"
        data-kt-drawer-direction="start"
        data-kt-drawer-toggle="#kt_app_sidebar_toggle"
    >
        <!--begin::Sidebar nav-->
        <div
            class="app-sidebar-wrapper py-lg-8 py-6"
            id="kt_app_sidebar_wrapper"
        >
            <!--begin::Nav wrapper-->
            <div
                id="kt_app_sidebar_nav_wrapper"
                class="d-flex flex-column px-lg-7 hover-scroll-y px-5"
                data-kt-scroll="true"
                data-kt-scroll-activate="true"
                data-kt-scroll-max-height="auto"
                data-kt-scroll-dependencies="{default: false, lg: '#kt_app_header'}"
                data-kt-scroll-wrappers="#kt_app_sidebar, #kt_app_sidebar_wrapper"
                data-kt-scroll-offset="{default: '10px', lg: '40px'}"
            >
                <!--begin::Tenant switcher-->
                <div
                    v-if="switchableTenants.length > 0"
                    class="position-relative mb-8"
                >
                    <div
                        class="fw-semibold fs-8 text-uppercase mb-2 text-gray-500"
                    >
                        Active Company
                    </div>
                    <button
                        type="button"
                        class="btn btn-light btn-flex justify-content-between w-100 px-4 py-3 text-start"
                        @click="dropdownOpen = !dropdownOpen"
                    >
                        <span
                            class="d-flex align-items-center text-truncate gap-2"
                        >
                            <i
                                class="ki-outline ki-home-2 fs-4 text-primary flex-shrink-0"
                            ></i>
                            <span class="fw-bold text-truncate text-gray-800">{{
                                currentTenant?.name ?? '—'
                            }}</span>
                        </span>
                        <i
                            class="ki-outline fs-5 ms-2 flex-shrink-0"
                            :class="dropdownOpen ? 'ki-up' : 'ki-down'"
                        ></i>
                    </button>
                    <div
                        v-if="dropdownOpen"
                        class="position-absolute z-index-1 mt-1 w-100 rounded border bg-white shadow-sm"
                        style="top: 100%; left: 0"
                    >
                        <button
                            v-for="t in orderedTenants"
                            :key="t.id"
                            type="button"
                            class="d-flex align-items-center text-hover-primary w-100 border-0 bg-transparent px-4 py-3 text-start"
                            :class="{
                                'bg-light-primary': t.id === currentTenant?.id,
                            }"
                            :style="{ paddingLeft: `${t.depth * 16 + 16}px` }"
                            @click="switchTenant(t.id)"
                        >
                            <i
                                class="ki-outline fs-6 me-2"
                                :class="
                                    t.parent_id === null
                                        ? 'ki-home-2 text-primary'
                                        : 'ki-arrow-right text-gray-500'
                                "
                            ></i>
                            <span
                                class="fw-semibold fs-7"
                                :class="
                                    t.id === currentTenant?.id
                                        ? 'text-primary'
                                        : 'text-gray-700'
                                "
                                >{{ t.name }}</span
                            >
                            <i
                                v-if="t.id === currentTenant?.id"
                                class="ki-outline ki-check text-primary fs-6 ms-auto"
                            ></i>
                        </button>
                    </div>
                </div>
                <!--end::Tenant switcher-->

                <!--begin::Links-->
                <div class="mb-0">
                    <!--begin::Title-->
                    <h3 class="fw-bold mb-8 text-gray-800">Employees</h3>
                    <!--end::Title-->
                    <!--begin::Row-->
                    <div
                        class="row g-5"
                        data-kt-buttons="true"
                        data-kt-buttons-target="[data-kt-button]"
                    >
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <Link
                                :href="employeesIndex.url()"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                            >
                                <span class="mb-2">
                                    <i class="ki-outline ki-people fs-1"></i>
                                </span>
                                <span class="fs-7 fw-bold">Employees</span>
                            </Link>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <Link
                                :href="locationsIndex.url()"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                            >
                                <span class="mb-2">
                                    <i class="ki-duotone ki-geolocation fs-1">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                </span>
                                <span class="fs-7 fw-bold">Locations</span>
                            </Link>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <Link
                                :href="departmentsIndex.url()"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                            >
                                <span class="mb-2">
                                    <i class="ki-outline ki-wifi-home fs-1"></i>
                                </span>
                                <span class="fs-7 fw-bold">Departments</span>
                            </Link>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <Link
                                :href="workShiftsIndex.url()"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                            >
                                <span class="mb-2">
                                    <i class="ki-outline ki-time fs-1"></i>
                                </span>
                                <span class="fs-7 fw-bold">Shifts</span>
                            </Link>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <Link
                                :href="announcementsIndex.url()"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                            >
                                <span class="mb-2">
                                    <i class="ki-outline ki-notification-bing fs-1"></i>
                                </span>
                                <span class="fs-7 fw-bold">Notifications</span>
                            </Link>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                        <!--begin::Col-->
                        <div class="col-4">
                            <!--begin::Link-->
                            <button
                                type="button"
                                class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                                data-kt-button="true"
                                @click="settingsOpen = true"
                            >
                                <!--begin::Icon-->
                                <span class="mb-2">
                                    <i class="ki-outline ki-setting-2 fs-1"></i>
                                </span>
                                <!--end::Icon-->
                                <!--begin::Label-->
                                <span class="fs-7 fw-bold">Settings</span>
                                <!--end::Label-->
                            </button>
                            <!--end::Link-->
                        </div>
                        <!--end::Col-->
                    </div>
                    <!--end::Row-->
                </div>
                <!--end::Links-->

                <!--begin::Title-->
                <h3 class="fw-bold mt-8 mb-8 text-gray-800">Admin</h3>
                <!--end::Title-->
                <!--begin::Row-->
                <div
                    class="row g-5"
                    data-kt-buttons="true"
                    data-kt-buttons-target="[data-kt-button]"
                >
                    <div class="col-4">
                        <Link
                            :href="usersIndex.url()"
                            class="btn btn-icon btn-outline btn-bg-light btn-active-light-primary btn-flex flex-column flex-center h-90px w-100 border-gray-200"
                            data-kt-button="true"
                        >
                            <span class="mb-2">
                                <i class="ki-outline ki-users fs-1"></i>
                            </span>
                            <span class="fs-7 fw-bold">Admin Users</span>
                        </Link>
                    </div>
                </div>
            </div>
            <!--end::Nav wrapper-->
        </div>
        <!--end::Sidebar nav-->
    </div>
    <!--end::Sidebar-->

    <Teleport to="body">
        <SettingsModal
            v-if="settingsOpen"
            @close="settingsOpen = false"
        />
    </Teleport>
</template>

<style scoped>
/* Desktop: sidebar occupies a fixed width in the flex row */
@media (min-width: 992px) {
    .app-sidebar {
        width: 360px;
        min-width: 360px;
        flex-shrink: 0;
    }
}
</style>
