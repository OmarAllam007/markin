<script setup lang="ts">
import { useToast } from '@/composables/useToast';
import Footer from '@/pages/_shared/footer.vue';
import Header from '@/pages/_shared/header.vue';
import Sidebar from '@/pages/_shared/sidebar.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string | null; error?: string | null } }>();
const toast = useToast();
const sidebarOpen = ref(false);

watch(() => page.props.flash, (flash) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
}, { immediate: true });

watch(() => page.url, () => { sidebarOpen.value = false; });
</script>

<template>
    <div class="workspace-shell">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <div v-if="sidebarOpen" class="sidebar-scrim" @click="sidebarOpen = false" />
        <Sidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <div class="workspace-body">
            <Header @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <main id="main-content" class="workspace-content" tabindex="-1"><slot /></main>
            <Footer />
        </div>
    </div>
</template>

<style>
.workspace-shell { --brand: #344a78; --ink: #19243E; --muted: #68748b; min-height: 100vh; display: flex; color: var(--ink); background: radial-gradient(circle at 80% -10%, #e9eef9 0, transparent 34%), #f6f8fc; }
.workspace-body { min-width: 0; min-height: 100vh; flex: 1; display: flex; flex-direction: column; }
.workspace-content { width: 100%; max-width: 1660px; margin: 0 auto; padding: 32px clamp(18px, 3vw, 48px); flex: 1; min-width: 0; }
.workspace-content .card { border: 1px solid #e5eaf2; border-radius: 18px; box-shadow: 0 8px 32px rgba(35, 45, 89, .045); }
.workspace-content .btn-primary { background: var(--brand); border-color: var(--brand); }
.workspace-content .text-primary { color: var(--brand) !important; }
.skip-link { position: absolute; z-index: 100; top: -60px; left: 16px; background: white; padding: 10px; }
.skip-link:focus { top: 12px; }
.sidebar-scrim { display: none; }
@media (max-width: 1023px) { .workspace-content { padding: 20px 16px; } .sidebar-scrim { display: block; position: fixed; inset: 0; z-index: 40; background: rgba(16, 39, 45, .45); } }
@media print { .workspace-sidebar, .workspace-header, .workspace-footer { display: none !important; } .workspace-content { padding: 0; max-width: none; } }
</style>
