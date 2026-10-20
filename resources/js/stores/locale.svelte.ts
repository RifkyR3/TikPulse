import en from '@/locales/en';
import id from '@/locales/id';
import apiClient from '@/services/api';

export type SupportedLocale = 'en' | 'id';

const dictionaries: Record<SupportedLocale, Record<string, any>> = {
    en,
    id,
};

class LocaleStore {
    currentLocale = $state<SupportedLocale>(
        (typeof window !== 'undefined' && localStorage.getItem('tikpulse_locale') === 'id') ? 'id' : 'en'
    );

    get locale(): SupportedLocale {
        return this.currentLocale;
    }

    constructor() {
        if (typeof document !== 'undefined') {
            document.documentElement.lang = this.currentLocale;
        }
    }

    async setLocale(newLocale: SupportedLocale, syncWithBackend = true): Promise<void> {
        this.currentLocale = newLocale;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem('tikpulse_locale', newLocale);
        }
        if (typeof document !== 'undefined') {
            document.documentElement.lang = newLocale;
        }

        if (syncWithBackend) {
            try {
                await apiClient.post('/auth/locale', { locale: newLocale });
            } catch {
                // Ignore if not logged in
            }
        }
    }

    initFromUser(userPreferredLocale?: string | null): void {
        if (userPreferredLocale && (userPreferredLocale === 'en' || userPreferredLocale === 'id')) {
            this.currentLocale = userPreferredLocale;
            if (typeof localStorage !== 'undefined') {
                localStorage.setItem('tikpulse_locale', userPreferredLocale);
            }
            if (typeof document !== 'undefined') {
                document.documentElement.lang = userPreferredLocale;
            }
        } else if (typeof localStorage !== 'undefined') {
            const local = localStorage.getItem('tikpulse_locale');
            const fallback: SupportedLocale = local === 'id' ? 'id' : 'en';
            this.currentLocale = fallback;
            if (typeof document !== 'undefined') {
                document.documentElement.lang = fallback;
            }
        }
    }

    t(path: string, params?: Record<string, string | number>): string {
        const keys = path.split('.');
        const activeDict = dictionaries[this.currentLocale] || dictionaries.en;
        const fallbackDict = dictionaries.en;

        let result: any = activeDict;
        for (const k of keys) {
            if (result && typeof result === 'object' && k in result) {
                result = result[k];
            } else {
                result = undefined;
                break;
            }
        }

        if (result === undefined) {
            result = fallbackDict;
            for (const k of keys) {
                if (result && typeof result === 'object' && k in result) {
                    result = result[k];
                } else {
                    result = undefined;
                    break;
                }
            }
        }

        if (typeof result !== 'string') {
            return path;
        }

        if (params) {
            return Object.entries(params).reduce((acc, [key, val]) => {
                return acc.replace(new RegExp(`{${key}}`, 'g'), String(val));
            }, result);
        }

        return result;
    }
}

export const localeStore = new LocaleStore();
