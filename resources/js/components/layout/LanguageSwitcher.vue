<script setup lang="ts">
import { ref } from 'vue';
import { useLocaleStore, type SupportedLocale } from '@/stores/locale';
import { Globe, Check, ChevronDown } from 'lucide-vue-next';

const localeStore = useLocaleStore();
const isOpen = ref(false);

const languages: { code: SupportedLocale; label: string; flag: string }[] = [
    { code: 'en', label: 'English', flag: '🇬🇧' },
    { code: 'id', label: 'Bahasa Indonesia', flag: '🇮🇩' },
];

const handleSelect = async (code: SupportedLocale) => {
    isOpen.value = false;
    await localeStore.setLocale(code);
};
</script>

<template>
    <div class="relative">
        <!-- Backdrop for closing dropdown on click outside -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

        <button
            type="button"
            @click="isOpen = !isOpen"
            class="flex items-center space-x-1.5 px-2.5 py-1.5 rounded-xl border border-border bg-card/80 hover:bg-muted text-xs font-semibold text-foreground transition-all shadow-2xs cursor-pointer select-none"
            :title="localeStore.currentLocale === 'en' ? 'Language: English (Click to change)' : 'Bahasa: Indonesia (Klik untuk ubah)'"
        >
            <Globe class="w-3.5 h-3.5 text-muted-foreground" />
            <span class="uppercase font-mono text-[11px] font-bold">
                {{ localeStore.currentLocale }}
            </span>
            <ChevronDown class="w-3 h-3 text-muted-foreground transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute top-full right-0 mt-2 w-44 rounded-2xl border border-border bg-card p-1.5 shadow-xl z-50 animate-in fade-in slide-in-from-top-2 duration-150"
        >
            <div class="px-2.5 py-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">
                {{ localeStore.t('common.language') }}
            </div>

            <div class="space-y-0.5">
                <button
                    v-for="lang in languages"
                    :key="lang.code"
                    type="button"
                    @click="handleSelect(lang.code)"
                    class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors cursor-pointer text-left"
                    :class="[
                        localeStore.currentLocale === lang.code
                            ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold'
                            : 'hover:bg-muted text-foreground'
                    ]"
                >
                    <span class="flex items-center space-x-2">
                        <span class="text-sm leading-none">{{ lang.flag }}</span>
                        <span>{{ lang.label }}</span>
                    </span>
                    <Check v-if="localeStore.currentLocale === lang.code" class="w-3.5 h-3.5 text-sky-500" />
                </button>
            </div>
        </div>
    </div>
</template>
