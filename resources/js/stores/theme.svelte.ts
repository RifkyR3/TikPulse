import apiClient from '@/services/api';

export type ThemeMode = 'light' | 'dark' | 'system';
export type AccentColor = 'sky' | 'indigo' | 'emerald' | 'violet' | 'amber' | 'rose';

export const ACCENT_COLORS: { id: AccentColor; name: string; hex: string; bgClass: string; gradientClass: string }[] = [
    { id: 'sky', name: 'Sky Blue', hex: '#0284c7', bgClass: 'bg-sky-500', gradientClass: 'from-sky-600 to-indigo-600' },
    { id: 'indigo', name: 'Deep Indigo', hex: '#4f46e5', bgClass: 'bg-indigo-500', gradientClass: 'from-indigo-600 to-purple-600' },
    { id: 'emerald', name: 'Emerald Green', hex: '#059669', bgClass: 'bg-emerald-500', gradientClass: 'from-emerald-600 to-teal-600' },
    { id: 'violet', name: 'Vibrant Violet', hex: '#7c3aed', bgClass: 'bg-violet-500', gradientClass: 'from-violet-600 to-pink-600' },
    { id: 'amber', name: 'Amber Glow', hex: '#d97706', bgClass: 'bg-amber-500', gradientClass: 'from-amber-600 to-orange-600' },
    { id: 'rose', name: 'Rose Red', hex: '#e11d48', bgClass: 'bg-rose-500', gradientClass: 'from-rose-600 to-red-600' },
];

class ThemeStore {
    theme = $state<ThemeMode>(
        (typeof window !== 'undefined' && (localStorage.getItem('tikpulse_theme') as ThemeMode)) || 'system'
    );

    accentColor = $state<AccentColor>(
        (typeof window !== 'undefined' && (localStorage.getItem('tikpulse_accent_color') as AccentColor)) || 'sky'
    );

    isDark = $derived.by(() => {
        if (typeof window === 'undefined') return false;
        if (this.theme === 'dark') return true;
        if (this.theme === 'light') return false;
        return window.matchMedia('(prefers-color-scheme: dark)').matches;
    });

    activeAccent = $derived.by(() => {
        return ACCENT_COLORS.find(c => c.id === this.accentColor) || ACCENT_COLORS[0];
    });

    constructor() {
        if (typeof window !== 'undefined') {
            this.applyTheme();
            this.applyAccent();
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

    applyAccent() {
        if (typeof document === 'undefined') return;
        document.documentElement.setAttribute('data-accent', this.accentColor);
    }

    setTheme(mode: ThemeMode, syncWithBackend = true) {
        this.theme = mode;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_theme', mode);
        }
        this.applyTheme();

        if (syncWithBackend) {
            this.syncSettings({ theme: mode });
        }
    }

    setAccentColor(color: AccentColor, syncWithBackend = true) {
        this.accentColor = color;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_accent_color', color);
        }
        this.applyAccent();

        if (syncWithBackend) {
            this.syncSettings({ accent_color: color });
        }
    }

    toggleTheme() {
        this.setTheme(this.isDark ? 'light' : 'dark');
    }

    initFromUserSettings(settings?: { theme?: ThemeMode; accent_color?: AccentColor }) {
        if (!settings) return;
        if (settings.theme && settings.theme !== this.theme) {
            this.setTheme(settings.theme, false);
        }
        if (settings.accent_color && settings.accent_color !== this.accentColor) {
            this.setAccentColor(settings.accent_color, false);
        }
    }

    private async syncSettings(payload: Record<string, any>) {
        try {
            await apiClient.post('/auth/settings', payload);
        } catch {
            // Ignore if unauthenticated
        }
    }
}

export const themeStore = new ThemeStore();
