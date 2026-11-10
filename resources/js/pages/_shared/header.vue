<script setup lang="ts">
import { destroy as logout } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { edit as profileEdit } from '@/actions/App/Http/Controllers/ProfileController';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const emit = defineEmits<{ 'toggle-sidebar': [] }>();
const page = usePage<{ auth: { user: { name: string; email: string } | null } }>();
const user = computed(() => page.props.auth?.user);
const menuOpen = ref(false);
const pageName = computed(() => {
    const segment = page.url.split('?')[0].split('/').filter(Boolean).pop();
    return segment ? segment.replace(/-/g, ' ') : 'Overview';
});
</script>

<template>
    <header class="workspace-header">
        <div class="header-leading">
            <button class="mobile-menu" type="button" aria-label="Open navigation" @click="emit('toggle-sidebar')">☰</button>
            <div><span class="header-eyebrow">Workspace / {{ pageName }}</span><h1>{{ pageName }}</h1></div>
        </div>
        <div class="header-actions">
            <span class="header-user-name">{{ user?.name }}</span>
            <div class="user-menu-wrap">
                <button type="button" class="avatar-button" :aria-expanded="menuOpen" aria-label="Account menu" @click="menuOpen = !menuOpen">{{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}</button>
                <div v-if="menuOpen" class="account-menu">
                    <strong>{{ user?.name }}</strong><small>{{ user?.email }}</small>
                    <Link :href="profileEdit.url()" @click="menuOpen = false">My profile</Link>
                    <button type="button" @click="router.post(logout.url())">Sign out</button>
                </div>
            </div>
        </div>
    </header>
</template>

<style scoped>
.workspace-header { height: 88px; padding: 0 clamp(18px, 3vw, 48px); display: flex; align-items: center; justify-content: space-between; gap: 20px; background: rgba(255,255,255,.86); border-bottom: 1px solid #e5eaf2; }
.header-leading, .header-actions { display: flex; align-items: center; gap: 16px; }
.header-eyebrow { display: block; color: #738099; font-size: 12px; text-transform: capitalize; }
h1 { margin: 2px 0 0; font-size: 21px; font-weight: 700; text-transform: capitalize; color: #19243E; }
.header-user-name { font-weight: 600; color: #394762; }
.avatar-button { width: 42px; height: 42px; border-radius: 14px; border: 0; background: #e6ecfa; color: #263c6b; font-weight: 800; }
.user-menu-wrap { position: relative; }
.account-menu { position: absolute; z-index: 50; inset-inline-end: 0; top: 52px; width: 220px; padding: 12px; background: white; border: 1px solid #e5eaf2; border-radius: 14px; box-shadow: 0 18px 40px #182e3320; display: grid; gap: 5px; }
.account-menu small { color: #738099; overflow: hidden; text-overflow: ellipsis; }
.account-menu a, .account-menu button { display: block; text-align: start; padding: 10px; border: 0; border-radius: 8px; background: transparent; color: #394762; text-decoration: none; }
.account-menu a:hover, .account-menu button:hover { background: #f0f4fc; }
.mobile-menu { display: none; background: none; border: 0; font-size: 24px; color: #19243E; }
@media (max-width: 1023px) { .mobile-menu { display: block; } .header-user-name { display: none; } .workspace-header { height: 72px; } }
</style>
