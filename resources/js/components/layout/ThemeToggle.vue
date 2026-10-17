<script setup lang="ts">
import { useThemeStore } from '@/stores/theme';
import { Sun, Moon } from 'lucide-vue-next';

const themeStore = useThemeStore();
</script>

<template>
    <button
        type="button"
        @click="themeStore.toggleTheme"
        class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg border border-border bg-card text-foreground transition-colors hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        title="Toggle Theme (Dark / Light)"
        aria-label="Toggle Theme"
    >
        <Sun v-if="themeStore.isDark" class="h-4 w-4 transition-transform duration-200 rotate-0 scale-100 text-amber-400" />
        <Moon v-else class="h-4 w-4 transition-transform duration-200 rotate-0 scale-100 text-slate-700" />
    </button>
</template>
