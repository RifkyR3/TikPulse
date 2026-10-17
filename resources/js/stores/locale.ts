import { defineStore } from 'pinia';
import { ref } from 'vue';
import en from '@/locales/en';
import id from '@/locales/id';
import apiClient from '@/services/api';

export type SupportedLocale = 'en' | 'id';

const dictionaries: Record<SupportedLocale, Record<string, any>> = {
    en,
    id,
};

export const useLocaleStore = defineStore('locale', () => {
    // Default language is 'en', but restore previously saved language from localStorage if available
    const savedLocale = localStorage.getItem('tikpulse_locale');
    const initialLocale: SupportedLocale = savedLocale === 'id' ? 'id' : 'en';

    const currentLocale = ref<SupportedLocale>(initialLocale);

    // Apply document lang attribute immediately
    if (typeof document !== 'undefined') {
        document.documentElement.lang = initialLocale;
    }

    /**
     * Switch language and optionally sync with backend database.
     */
    const setLocale = async (newLocale: SupportedLocale, syncWithBackend = true): Promise<void> => {
        currentLocale.value = newLocale;
        localStorage.setItem('tikpulse_locale', newLocale);

        if (typeof document !== 'undefined') {
            document.documentElement.lang = newLocale;
        }

        if (syncWithBackend) {
            try {
                // If user is logged in, persist to backend
                await apiClient.post('/auth/locale', { locale: newLocale });
            } catch {
                // Ignore failure if user is not authenticated yet
            }
        }
    };

    /**
     * Called upon login / auth restoration to load the user's previously chosen language.
     */
    const initFromUser = (userPreferredLocale?: string | null): void => {
        if (userPreferredLocale && (userPreferredLocale === 'en' || userPreferredLocale === 'id')) {
            currentLocale.value = userPreferredLocale;
            localStorage.setItem('tikpulse_locale', userPreferredLocale);
            if (typeof document !== 'undefined') {
                document.documentElement.lang = userPreferredLocale;
            }
        } else {
            // Retain whatever was in localStorage, default 'en'
            const local = localStorage.getItem('tikpulse_locale');
            const fallback: SupportedLocale = local === 'id' ? 'id' : 'en';
            currentLocale.value = fallback;
            if (typeof document !== 'undefined') {
                document.documentElement.lang = fallback;
            }
        }
    };

    /**
     * Translate key (e.g. 'nav.dashboard', 'auth.welcomeBack')
     */
    const t = (path: string, params?: Record<string, string | number>): string => {
        const keys = path.split('.');
        const activeDict = dictionaries[currentLocale.value] || dictionaries.en;
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

        // Fallback to English if not found in current dictionary
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

        // Param replacement like {name}
        if (params) {
            return Object.entries(params).reduce((acc, [key, val]) => {
                return acc.replace(new RegExp(`{${key}}`, 'g'), String(val));
            }, result);
        }

        return result;
    };

    return {
        currentLocale,
        setLocale,
        initFromUser,
        t,
    };
});
