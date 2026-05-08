<script setup lang="ts">
import Editor from '@tinymce/tinymce-vue';
import type { RawEditorOptions } from 'tinymce/tinymce';
import { computed } from 'vue';

/** Self-hosted bundle shipped with the Metronic-style assets in `public/`. */
const tinymceScriptSrc = '/assets/plugins/custom/tinymce/tinymce.bundle.js';

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        disabled?: boolean;
        hasError?: boolean;
        minHeight?: string;
    }>(),
    {
        placeholder: 'Write your message here…',
        disabled: false,
        hasError: false,
        minHeight: '180px',
    },
);

const model = defineModel<string>({ default: '' });

function parseMinHeightPx(value: string): number {
    const match = /^(\d+)/.exec(value);

    if (match) {
        return Number.parseInt(match[1], 10);
    }

    return 180;
}

const init = computed((): RawEditorOptions => ({
    menubar: false,
    branding: false,
    promotion: false,
    plugins: 'lists link autolink table wordcount code autoresize',
    toolbar:
        'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link blockquote | code removeformat',
    placeholder: props.placeholder,
    autoresize_bottom_margin: 24,
    autoresize_min_height: parseMinHeightPx(props.minHeight),
    resize: true,
    content_style: `body { font-family: inherit; font-size: 14px; line-height: 1.6; min-height: ${props.minHeight}; }`,
}));
</script>

<template>
    <div
        class="tinymce-shared"
        :class="{ 'tinymce-shared--invalid': hasError }"
    >
        <Editor
            v-model="model"
            license-key="gpl"
            :disabled="disabled"
            :tinymce-script-src="tinymceScriptSrc"
            :init="init"
        />
    </div>
</template>

<style scoped>
.tinymce-shared :deep(.tox-tinymce) {
    border-radius: 0.475rem;
}

.tinymce-shared--invalid :deep(.tox-tinymce) {
    border-color: var(--bs-danger) !important;
}
</style>
