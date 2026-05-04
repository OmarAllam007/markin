import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/pages/layout/AppLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (name.startsWith('Public/')) {
            return null
        }

        return AppLayout
    },
    progress: {
        color: '#4B5563',
    },
});
