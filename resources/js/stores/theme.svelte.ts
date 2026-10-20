export type ThemeMode = 'light' | 'dark' | 'system';

class ThemeStore {
    theme = $state<ThemeMode>(
        (typeof window !== 'undefined' && (localStorage.getItem('tikpulse_theme') as ThemeMode)) || 'system'
    );

    isDark = $derived.by(() => {
        if (typeof window === 'undefined') return false;
        if (this.theme === 'dark') return true;
        if (this.theme === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    constructor() {
        if (typeof window !== 'undefined') {
            this.applyTheme();
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                if (this.theme === 'system') {
                    this.applyTheme();
                }
            });
        }
    }

    applyTheme() {
        if (typeof document === 'undefined') return;
        if (this.isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    setTheme(mode: ThemeMode) {
        this.theme = mode;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_theme', mode);
        }
        this.applyTheme();
    }

    toggleTheme() {
        this.setTheme(this.isDark ? 'light' : 'dark');
    }
}

export const themeStore = new ThemeStore();
