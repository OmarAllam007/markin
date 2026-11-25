<template>
    <footer class="workspace-footer">© {{ new Date().getFullYear() }} Alatheer</footer>
</template>
<style scoped>
.workspace-footer { display: flex; justify-content: space-between; gap: 12px; padding: 20px clamp(18px, 3vw, 48px); color: #738099; font-size: 13px; }
@media (max-width: 600px) { .workspace-footer span { display: none; } }
</style>
