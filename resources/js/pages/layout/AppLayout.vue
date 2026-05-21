<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import Footer from '@/pages/_shared/footer.vue';
import Header from '@/pages/_shared/header.vue';
import Sidebar from '@/pages/_shared/sidebar.vue';
import Toolbar from '@/pages/_shared/toolbar.vue';
import { router, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, watch } from 'vue';

declare global {
    interface Window {
        KTComponents?: { init(): void };
    }
}

const page = usePage<{ flash: { success?: string | null; error?: string | null } }>();
const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    },
    { immediate: true },
);

let cleanupNavigate: (() => void) | undefined;

onMounted(() => {
    cleanupNavigate = router.on('navigate', () => {
        nextTick(() => {
            window.KTComponents?.init();
        });
    });
});

onUnmounted(() => {
    cleanupNavigate?.();
});
</script>

<template>
    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            <Header />
            <!--begin::Wrapper-->
            <div
                class="app-wrapper flex-column flex-row-fluid"
                id="kt_app_wrapper"
            >
                <!--begin::Wrapper container-->
                <div class="app-container container-fluid d-flex flex-row-fluid px-0">
                    <Sidebar  />
                    <!--begin::Main-->
                    <div
                        class="app-main flex-column flex-row-fluid min-w-0"
                        id="kt_app_main"
                    >
                        <!--begin::Content wrapper-->
                        <div class="d-flex flex-column flex-column-fluid content-inner">
                            <!--begin::Content-->
                            <div
                                id="kt_app_content"
                                class="app-content flex-column-fluid "
                            >
                                <slot />
                            </div>
                            <!--end::Content-->
                        </div>
                        <!--end::Content wrapper-->
                        <Footer />
                    </div>
                    <!--end:::Main-->
                </div>
                <!--end::Wrapper container-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::App-->
</template>

<style scoped>
/* On mobile the sidebar becomes a KTM drawer overlay — remove it from the flex flow */
@media (max-width: 991.98px) {
    :deep(#kt_app_sidebar) {
        display: none;
    }
}

/* Main content fills remaining space and never overflows horizontally */
#kt_app_main {
    overflow-x: hidden;
}

/* KTM drawer plugin takes sidebar out of flex flow on desktop — compensate with margin */
@media (min-width: 992px) {
    #kt_app_main {
        margin-inline-start: 360px;
    }

    /* padding-inline-start flips automatically in RTL (replaces Bootstrap's ps-lg-8) */
    .content-inner {
        padding-inline-start: 2rem;
    }
}
</style>
