<script setup lang="ts">
import { onMounted } from 'vue';
import { useThemeStore } from '@/stores/theme';
import { Toaster } from 'vue-sonner';

const themeStore = useThemeStore();

onMounted(() => {
    themeStore.applyTheme();
});
</script>

<template>
    <div class="min-h-screen bg-background text-foreground antialiased selection:bg-sky-500 selection:text-white">
        <Toaster position="bottom-right" richColors closeButton :theme="themeStore.theme === 'dark' ? 'dark' : 'light'" />
        <router-view />
    </div>
</template>
