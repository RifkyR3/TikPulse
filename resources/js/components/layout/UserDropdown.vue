<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useLocaleStore } from '@/stores/locale';
import { LogOut, User as UserIcon, Shield, ChevronDown } from 'lucide-vue-next';

const router = useRouter();
const authStore = useAuthStore();
const localeStore = useLocaleStore();
const isOpen = ref(false);

const handleLogout = async () => {
    isOpen.value = false;
    await authStore.logout();
    router.push('/login');
};
</script>

<template>
    <div class="relative">
        <!-- Backdrop for closing dropdown on click outside -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

        <button
            type="button"
            @click="isOpen = !isOpen"
            class="flex items-center space-x-2 p-1.5 rounded-xl border border-border bg-card/80 hover:bg-muted text-foreground transition-all cursor-pointer shadow-sm relative z-40"
        >
            <div class="w-7 h-7 rounded-lg bg-gradient-to-tr from-sky-500 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                {{ authStore.user?.name ? authStore.user.name.charAt(0).toUpperCase() : 'A' }}
            </div>
            <div class="hidden md:flex flex-col text-left leading-none max-w-[100px] truncate">
                <span class="text-xs font-semibold truncate text-foreground">{{ authStore.user?.name || 'Admin' }}</span>
                <span class="text-[10px] text-muted-foreground uppercase font-mono mt-0.5">{{ authStore.user?.role || 'operator' }}</span>
            </div>
            <ChevronDown class="w-3.5 h-3.5 text-muted-foreground transition-transform hidden sm:inline" :class="{ 'rotate-180': isOpen }" />
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute top-full right-0 mt-2 w-56 rounded-2xl border border-border bg-card p-2 shadow-2xl z-50 animate-in fade-in slide-in-from-top-2 duration-150"
        >
            <div class="px-3 py-2 border-b border-border mb-1">
                <div class="font-bold text-xs text-foreground truncate">{{ authStore.user?.name }}</div>
                <div class="text-[11px] text-muted-foreground truncate font-mono">{{ authStore.user?.email }}</div>
                <div class="mt-1.5 inline-flex items-center space-x-1 px-2 py-0.5 rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 text-[10px] font-semibold uppercase">
                    <Shield class="w-2.5 h-2.5" />
                    <span>{{ authStore.user?.role }}</span>
                </div>
            </div>

            <button
                type="button"
                @click="handleLogout"
                class="w-full flex items-center space-x-2 px-3 py-2 rounded-xl text-xs font-medium text-destructive hover:bg-destructive/10 transition-colors cursor-pointer"
            >
                <LogOut class="w-3.5 h-3.5" />
                <span>{{ localeStore.t('nav.signOut') }}</span>
            </button>
        </div>
    </div>
</template>
