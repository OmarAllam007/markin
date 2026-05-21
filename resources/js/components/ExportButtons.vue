<script setup lang="ts">
defineProps<{ exportUrl: string }>();

const print = () => window.print();
</script>

<template>
    <div class="d-flex gap-2">
        <a
            :href="exportUrl"
            class="btn btn-sm btn-light-success"
        >
            <i class="ki-outline ki-file-down fs-4 me-1"></i>Export Excel
        </a>
        <button
            type="button"
            class="btn btn-sm btn-light-primary"
            @click="print"
        >
            <i class="ki-outline ki-printer fs-4 me-1"></i>Print / PDF
        </button>
    </div>
</template>
