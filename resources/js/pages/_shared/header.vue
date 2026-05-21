<script setup lang="ts">
import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { edit as profileEdit } from '@/actions/App/Http/Controllers/ProfileController';
import { usePage, router, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

type UserRef = { name: string; email: string };

const page = usePage<{
    auth: { user: UserRef | null };
}>();
const currentUser = computed(() => page.props.auth?.user ?? null);

const signOut = () => {
    router.post(logout.url());
};

// ── Theme mode ───────────────────────────────────────────────────────────────
type ThemeMode = 'light' | 'dark' | 'system';

const THEME_STORAGE_KEY = 'data-bs-theme';
const themeMode = ref<ThemeMode>('light');

function resolveSystemTheme(): 'light' | 'dark' {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(mode: ThemeMode) {
    themeMode.value = mode;
    localStorage.setItem(THEME_STORAGE_KEY, mode);
    const resolved = mode === 'system' ? resolveSystemTheme() : mode;
    document.documentElement.setAttribute('data-bs-theme', resolved);
}

onMounted(() => {
    const stored = (localStorage.getItem(THEME_STORAGE_KEY) ?? 'light') as ThemeMode;
    applyTheme(stored);
});

// ── Language ─────────────────────────────────────────────────────────────────
type Lang = 'en' | 'ar';

const currentLang = ref<Lang>('en');

const languages: { code: Lang; label: string; flag: string; dir: 'ltr' | 'rtl' }[] = [
    { code: 'en', label: 'English', flag: 'assets/media/flags/united-states.svg', dir: 'ltr' },
    { code: 'ar', label: 'Arabic', flag: 'assets/media/flags/saudi-arabia.svg', dir: 'rtl' },
];

const currentLangEntry = computed(() => languages.find((l) => l.code === currentLang.value)!);

function applyLang(lang: Lang) {
    currentLang.value = lang;
    localStorage.setItem('app-lang', lang);
    const entry = languages.find((l) => l.code === lang)!;
    document.documentElement.setAttribute('dir', entry.dir);
    document.documentElement.setAttribute('lang', lang);
}

onMounted(() => {
    const stored = (localStorage.getItem('app-lang') ?? 'en') as Lang;
    applyLang(stored);
});
</script>

<template>
    <!--begin::Header-->
    <div id="kt_app_header" class="app-header" data-kt-sticky="true" data-kt-sticky-activate-="true" data-kt-sticky-name="app-header-sticky" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
        <!--begin::Header container-->
        <div class="app-container container-fluid d-flex align-items-stretch justify-content-between" id="kt_app_header_container">
            <!--begin::Header wrapper-->
            <div class="app-header-wrapper d-flex flex-grow-1 align-items-stretch justify-content-between" id="kt_app_header_wrapper">
                <!--begin::Logo wrapper-->
                <div class="app-header-logo d-flex flex-shrink-0 align-items-center justify-content-between justify-content-lg-center">
                    <!--begin::Logo wrapper-->
                    <button class="btn btn-icon btn-color-gray-600 btn-active-color-primary ms-n3 me-2 d-flex d-lg-none" id="kt_app_sidebar_toggle">
                        <i class="ki-outline ki-abstract-14 fs-2"></i>
                    </button>
                    <!--end::Logo wrapper-->
                    <!--begin::Logo image-->
                    <a href="index.html">
                        <img alt="Logo" src="assets/media/logos/default-small.svg" class="h-30px h-lg-40px theme-light-show" />
                        <img alt="Logo" src="assets/media/logos/default-small-dark.svg" class="h-30px h-lg-40px theme-dark-show" />
                    </a>
                    <!--end::Logo image-->
                </div>
                <!--end::Logo wrapper-->
                <!--begin::Menu wrapper-->
                <div id="kt_app_header_menu_wrapper" class="d-flex align-items-center w-100">
                    <!--begin::Header menu-->
                    <div class="app-header-menu app-header-mobile-drawer align-items-start align-items-lg-center w-100" data-kt-drawer="true" data-kt-drawer-name="app-header-menu" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="250px" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_app_header_menu_toggle" data-kt-swapper="true" data-kt-swapper-mode="{default: 'append', lg: 'prepend'}" data-kt-swapper-parent="{default: '#kt_app_body', lg: '#kt_app_header_menu_wrapper'}">
                        <!--begin::Menu-->
                        <div class="menu menu-rounded menu-column menu-lg-row menu-active-bg menu-state-primary menu-title-gray-700 menu-arrow-gray-500 menu-bullet-gray-500 my-5 my-lg-0 align-items-stretch fw-semibold px-2 px-lg-0" id="#kt_header_menu" data-kt-menu="true">

                        </div>
                        <!--end::Menu-->
                    </div>
                    <!--end::Header menu-->
                </div>
                <!--end::Menu wrapper-->
                <!--begin::Navbar-->
                <div class="app-navbar flex-shrink-0">
                    <!--begin::Notifications-->
                    <div class="app-navbar-item ms-1 ms-lg-5">
                        <!--begin::Menu- wrapper-->
                        <div class="btn btn-icon btn-custom btn-active-color-primary btn-color-gray-700 w-35px h-35px w-md-40px h-md-40px" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom">
                            <i class="ki-outline ki-calendar fs-1"></i>
                        </div>
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::Notifications-->
                    <!--begin::Quick links-->
                    <div class="app-navbar-item ms-1 ms-lg-5">
                        <!--begin::Menu- wrapper-->
                        <div class="btn btn-icon btn-custom btn-active-color-primary btn-color-gray-700 w-35px h-35px w-md-40px h-md-40px" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom">
                            <i class="ki-outline ki-abstract-26 fs-1"></i>
                        </div>
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::Quick links-->
                    <!--begin::Chat-->
                    <div class="app-navbar-item ms-1 ms-lg-5">
                        <!--begin::Menu wrapper-->
                        <div class="btn btn-icon btn-custom btn-active-color-primary btn-color-gray-700 w-35px h-35px w-md-40px h-md-40px position-relative" id="kt_drawer_chat_toggle">
                            <i class="ki-outline ki-notification-on fs-1"></i>
                        </div>
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::Chat-->
                    <!--begin::User menu-->
                    <div class="app-navbar-item ms-3 ms-lg-5" id="kt_header_user_menu_toggle">
                        <!--begin::Menu wrapper-->
                        <div class="cursor-pointer symbol symbol-35px symbol-md-40px" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-attach="parent" data-kt-menu-placement="bottom-end">
                            <img class="symbol symbol-circle symbol-35px symbol-md-40px" src="assets/media/avatars/300-13.jpg" alt="user" />
                        </div>
                        <!--begin::User account menu-->
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-color fw-semibold py-4 fs-6 w-275px" data-kt-menu="true">
                            <!--begin::Menu item-->
                            <div class="menu-item px-3">
                                <div class="menu-content d-flex align-items-center px-3">
                                    <!--begin::Avatar-->
                                    <div class="symbol symbol-50px me-5">
                                        <img alt="Logo" src="assets/media/avatars/300-13.jpg" />
                                    </div>
                                    <!--end::Avatar-->
                                    <!--begin::Username-->
                                    <div class="d-flex flex-column">
                                        <div class="fw-bold d-flex align-items-center fs-5">
                                            {{ currentUser?.name ?? 'User' }}
                                        </div>
                                        <span class="fw-semibold text-muted fs-7 text-start">{{
                                            currentUser?.email ?? '—'
                                        }}</span>
                                    </div>
                                    <!--end::Username-->
                                </div>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <Link :href="profileEdit.url()" class="menu-link px-5">My Profile</Link>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="apps/projects/list.html" class="menu-link px-5">
                                    <span class="menu-text">My Projects</span>
                                    <span class="menu-badge">
                                        <span class="badge badge-light-danger badge-circle fw-bold fs-7">3</span>
                                    </span>
                                </a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                <a href="#" class="menu-link px-5">
                                    <span class="menu-title">My Subscription</span>
                                    <span class="menu-arrow"></span>
                                </a>
                                <!--begin::Menu sub-->
                                <div class="menu-sub menu-sub-dropdown w-175px py-4">
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="account/referrals.html" class="menu-link px-5">Referrals</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="account/billing.html" class="menu-link px-5">Billing</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="account/statements.html" class="menu-link px-5">Payments</a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="account/statements.html" class="menu-link d-flex flex-stack px-5">Statements
                                            <span class="ms-2 lh-0" data-bs-toggle="tooltip" title="View your statements">
                                                <i class="ki-outline ki-information-5 fs-5"></i>
                                            </span>
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                    <!--begin::Menu separator-->
                                    <div class="separator my-2"></div>
                                    <!--end::Menu separator-->
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <div class="menu-content px-3">
                                            <label class="form-check form-switch form-check-custom form-check-solid">
                                                <input class="form-check-input w-30px h-20px" type="checkbox" value="1" checked="checked" name="notifications" />
                                                <span class="form-check-label text-muted fs-7">Notifications</span>
                                            </label>
                                        </div>
                                    </div>
                                    <!--end::Menu item-->
                                </div>
                                <!--end::Menu sub-->
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <a href="account/statements.html" class="menu-link px-5">My Statements</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu separator-->
                            <div class="separator my-2"></div>
                            <!--end::Menu separator-->
                            <!--begin::Menu item — Mode-->
                            <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                <a href="#" class="menu-link px-5">
                                    <span class="menu-title position-relative">Mode
                                        <span class="ms-5 position-absolute translate-middle-y top-50 end-0">
                                            <i class="ki-outline ki-night-day fs-2" :class="themeMode !== 'dark' ? '' : 'd-none'"></i>
                                            <i class="ki-outline ki-moon fs-2" :class="themeMode === 'dark' ? '' : 'd-none'"></i>
                                        </span>
                                    </span>
                                </a>
                                <!--begin::Mode submenu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-title-gray-700 menu-icon-gray-500 menu-active-bg menu-state-color fw-semibold py-4 fs-base w-150px" data-kt-menu="true">
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" :class="{ active: themeMode === 'light' }" @click.prevent="applyTheme('light')">
                                            <span class="menu-icon"><i class="ki-outline ki-night-day fs-2"></i></span>
                                            <span class="menu-title">Light</span>
                                        </a>
                                    </div>
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" :class="{ active: themeMode === 'dark' }" @click.prevent="applyTheme('dark')">
                                            <span class="menu-icon"><i class="ki-outline ki-moon fs-2"></i></span>
                                            <span class="menu-title">Dark</span>
                                        </a>
                                    </div>
                                    <div class="menu-item px-3 my-0">
                                        <a href="#" class="menu-link px-3 py-2" :class="{ active: themeMode === 'system' }" @click.prevent="applyTheme('system')">
                                            <span class="menu-icon"><i class="ki-outline ki-screen fs-2"></i></span>
                                            <span class="menu-title">System</span>
                                        </a>
                                    </div>
                                </div>
                                <!--end::Mode submenu-->
                            </div>
                            <!--end::Menu item — Mode-->
                            <!--begin::Menu item — Language-->
                            <div class="menu-item px-5" data-kt-menu-trigger="{default: 'click', lg: 'hover'}" data-kt-menu-placement="left-start" data-kt-menu-offset="-15px, 0">
                                <a href="#" class="menu-link px-5">
                                    <span class="menu-title position-relative">Language
                                        <span class="fs-8 rounded bg-light px-3 py-2 position-absolute translate-middle-y top-50 end-0">
                                            {{ currentLangEntry.label }}
                                            <img class="w-15px h-15px rounded-1 ms-2" :src="currentLangEntry.flag" alt="" />
                                        </span>
                                    </span>
                                </a>
                                <!--begin::Language submenu-->
                                <div class="menu-sub menu-sub-dropdown w-175px py-4">
                                    <div v-for="lang in languages" :key="lang.code" class="menu-item px-3">
                                        <a href="#" class="menu-link d-flex px-5" :class="{ active: currentLang === lang.code }" @click.prevent="applyLang(lang.code)">
                                            <span class="symbol symbol-20px me-4">
                                                <img class="rounded-1" :src="lang.flag" :alt="lang.label" />
                                            </span>
                                            {{ lang.label }}
                                        </a>
                                    </div>
                                </div>
                                <!--end::Language submenu-->
                            </div>
                            <!--end::Menu item — Language-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5 my-1">
                                <a href="account/settings.html" class="menu-link px-5">Account Settings</a>
                            </div>
                            <!--end::Menu item-->
                            <!--begin::Menu item-->
                            <div class="menu-item px-5">
                                <button
                                    type="button"
                                    class="menu-link px-5 w-100 text-start border-0 bg-transparent"
                                    @click="signOut"
                                >
                                    Sign out
                                </button>
                            </div>
                            <!--end::Menu item-->
                        </div>
                        <!--end::User account menu-->
                        <!--end::Menu wrapper-->
                    </div>
                    <!--end::User menu-->
                    <!--begin::Header menu toggle-->
                    <div class="app-navbar-item d-lg-none ms-2 me-n3" title="Show header menu">
                        <div class="btn btn-icon btn-custom btn-active-color-primary btn-color-gray-700 w-35px h-35px w-md-40px h-md-40px" id="kt_app_header_menu_toggle">
                            <i class="ki-outline ki-text-align-left fs-1"></i>
                        </div>
                    </div>
                    <!--end::Header menu toggle-->
                </div>
                <!--end::Navbar-->
            </div>
            <!--end::Header wrapper-->
        </div>
        <!--end::Header container-->
    </div>
    <!--end::Header-->
</template>

<style scoped>

</style>
