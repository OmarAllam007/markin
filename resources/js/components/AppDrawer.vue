<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

/** Side panel for focused forms. Slides in from the reading end (right in LTR, left in RTL). */
const open = defineModel<boolean>('open', { required: true });

defineProps<{ title: string; subtitle?: string; width?: string }>();

const panel = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

const close = () => (open.value = false);
const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();

watch(open, async isOpen => {
    document.body.style.overflow = isOpen ? 'hidden' : '';

    if (isOpen) {
        returnFocus = document.activeElement as HTMLElement | null;
        window.addEventListener('keydown', onKey);
        await nextTick();
        panel.value?.querySelector<HTMLElement>('input:not([type=hidden]), select, textarea, button:not(.drawer-close)')?.focus();
    } else {
        window.removeEventListener('keydown', onKey);
        returnFocus?.focus();
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer">
            <div
                v-if="open"
                class="app-drawer-root"
            >
                <div
                    class="app-drawer-scrim"
                    @click="close"
                />
                <aside
                    ref="panel"
                    class="app-drawer"
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    :style="{ '--drawer-width': width ?? '520px' }"
                >
                    <header class="app-drawer-head">
                        <div class="min-w-0">
                            <h2>{{ title }}</h2>
                            <p v-if="subtitle">{{ subtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="drawer-close"
                            aria-label="Close"
                            @click="close"
                        ><i class="ki-outline ki-cross" /></button>
                    </header>
                    <div class="app-drawer-body">
                        <slot />
                    </div>
                    <footer
                        v-if="$slots.footer"
                        class="app-drawer-foot"
                    >
                        <slot name="footer" />
                    </footer>
                </aside>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.app-drawer-root { position: fixed; inset: 0; z-index: 1060; }
.app-drawer-scrim { position: absolute; inset: 0; background: rgba(14, 37, 54, .42); backdrop-filter: blur(2px); }
.app-drawer { position: absolute; inset-block: 0; inset-inline-end: 0; display: flex; flex-direction: column; width: min(var(--drawer-width), 100vw); background: #fff; box-shadow: -18px 0 50px rgba(14, 37, 54, .22); }
.app-drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 24px 28px 20px; background: linear-gradient(115deg, #173c61, #176b81); color: #fff; }
.app-drawer-head h2 { margin: 0; color: #fff; font-size: 19px; font-weight: 700; }
.app-drawer-head p { margin: 4px 0 0; color: #b9d9e1; font-size: 13px; }
.drawer-close { display: grid; flex: 0 0 34px; place-items: center; height: 34px; border: 0; border-radius: 9px; background: rgba(255, 255, 255, .12); color: #fff; font-size: 18px; cursor: pointer; }
.drawer-close:hover { background: rgba(255, 255, 255, .22); }
.drawer-close:focus-visible { outline: 2px solid #7fe0d9; outline-offset: 2px; }
.app-drawer-body { flex: 1; overflow-y: auto; padding: 24px 28px; }
.app-drawer-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 28px; border-top: 1px solid #edf1f3; background: #fafcfd; }

.drawer-enter-active, .drawer-leave-active { transition: opacity .22s ease; }
.drawer-enter-active .app-drawer, .drawer-leave-active .app-drawer { transition: transform .26s cubic-bezier(.2, .8, .2, 1); }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .app-drawer, .drawer-leave-to .app-drawer { transform: translateX(100%); }
:global([dir='rtl']) .drawer-enter-from .app-drawer, :global([dir='rtl']) .drawer-leave-to .app-drawer { transform: translateX(-100%); }
@media (prefers-reduced-motion: reduce) {
    .drawer-enter-active, .drawer-leave-active, .drawer-enter-active .app-drawer, .drawer-leave-active .app-drawer { transition: none; }
}
</style>
