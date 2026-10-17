<script setup lang="ts">
import { useSafeModeStore } from '@/stores/safeMode';
import { useRouterContextStore } from '@/stores/routerContext';
import { useLocaleStore } from '@/stores/locale';
import { ShieldCheck, ShieldAlert, Lock, Unlock } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'open-unlock-modal'): void;
}>();

const safeModeStore = useSafeModeStore();
const routerStore = useRouterContextStore();
const localeStore = useLocaleStore();

const handleClick = () => {
    if (safeModeStore.isUnlocked) {
        // Re-lock immediately
        if (routerStore.activeRouter) {
            safeModeStore.lock(routerStore.activeRouter.id);
        }
    } else {
        // Open two-step unlock modal
        emit('open-unlock-modal');
    }
};
</script>

<template>
    <button
        type="button"
        @click="handleClick"
        class="group relative inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-all shadow-sm cursor-pointer select-none"
        :class="[
            safeModeStore.isUnlocked
                ? 'bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20'
                : 'bg-emerald-500/10 border-emerald-500/40 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
        ]"
        :title="safeModeStore.isUnlocked ? localeStore.t('safeMode.clickToLock') : localeStore.t('safeMode.clickToUnlock')"
    >
        <span class="relative flex h-2 w-2">
            <span
                class="relative inline-flex rounded-full h-2 w-2"
                :class="safeModeStore.isUnlocked ? 'bg-amber-500 shadow-xs shadow-amber-500/50' : 'bg-emerald-500 shadow-xs shadow-emerald-500/50'"
            ></span>
        </span>

        <component
            :is="safeModeStore.isUnlocked ? ShieldAlert : ShieldCheck"
            class="w-3.5 h-3.5"
        />

        <span>
            {{ safeModeStore.isUnlocked ? `SAFE MODE (${safeModeStore.formattedTimeRemaining})` : localeStore.t('safeMode.safeModeOn') }}
        </span>

        <span class="text-[10px] opacity-75 group-hover:opacity-100 uppercase ml-0.5 font-mono">
            {{ safeModeStore.isUnlocked ? '• LOCK' : '• UNLOCK' }}
        </span>
    </button>
</template>
