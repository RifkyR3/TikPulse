import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export type ThemeMode = 'light' | 'dark' | 'system';

export const useThemeStore = defineStore('theme', () => {
    const theme = ref<ThemeMode>(
        (localStorage.getItem('tikpulse_theme') as ThemeMode) || 'system'
    );

    const isDark = computed(() => {
        if (theme.value === 'dark') return true;
        if (theme.value === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    const applyTheme = () => {
        if (isDark.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    const setTheme = (mode: ThemeMode) => {
        theme.value = mode;
        localStorage.setItem('tikpulse_theme', mode);
        applyTheme();
    };

    const toggleTheme = () => {
        setTheme(isDark.value ? 'light' : 'dark');
    };

    // Watch for OS preference changes when in 'system' mode
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (theme.value === 'system') {
            applyTheme();
        }
    });

    return {
        theme,
        isDark,
        setTheme,
        toggleTheme,
        applyTheme,
    };
});
