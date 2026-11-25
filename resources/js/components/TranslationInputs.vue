<script setup lang="ts">
/**
 * One input per extra content language (CONTENT_LOCALES minus the default),
 * bound to a `{ locale: text }` translations object.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isRtl, languageName } from '@/lib/ticketFields';

const props = withDefaults(defineProps<{ label: string; idPrefix: string; inline?: boolean; wrapperClass?: string }>(), {
    inline: false,
    wrapperClass: 'col-md-6',
});

const model = defineModel<Record<string, string> | null>({ required: true });

const locales = computed(() => ((usePage().props.contentLocales as string[] | undefined) ?? ['en']).slice(1));

const update = (loc: string, value: string) => {
    model.value = { ...(model.value ?? {}), [loc]: value };
};
</script>

<template>
    <div
        v-for="loc in locales"
        :key="loc"
        :class="props.inline ? '' : props.wrapperClass"
    >
        <label
            v-if="!props.inline"
            class="form-label"
            :for="`${props.idPrefix}-${loc}`"
        >{{ props.label }} ({{ languageName(loc) }})</label>
        <input
            :id="`${props.idPrefix}-${loc}`"
            :value="model?.[loc] ?? ''"
            type="text"
            class="form-control"
            :dir="isRtl(loc) ? 'rtl' : 'ltr'"
            :placeholder="props.inline ? `${props.label} (${languageName(loc)})` : ''"
            :aria-label="`${props.label} (${languageName(loc)})`"
            autocomplete="off"
            @input="update(loc, ($event.target as HTMLInputElement).value)"
        />
    </div>
</template>
