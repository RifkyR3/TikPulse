<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useSafeModeStore } from '@/stores/safeMode';
import { useLocaleStore } from '@/stores/locale';
import { Server, ChevronDown, Check, Plus, RefreshCw, Radio } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'add-router'): void;
}>();

const routerStore = useRouterContextStore();
const safeModeStore = useSafeModeStore();
const localeStore = useLocaleStore();
const isOpen = ref(false);

const handleSelect = async (routerId: number) => {
    routerStore.setActiveRouter(routerId);
    isOpen.value = false;
    await safeModeStore.fetchStatus(routerId);
};

onMounted(async () => {
    if (routerStore.routers.length === 0) {
        await routerStore.fetchRouters();
    }
});
</script>

<template>
    <div class="relative">
        <!-- Backdrop for closing dropdown on click outside -->
        <div v-if="isOpen" class="fixed inset-0 z-40" @click="isOpen = false"></div>

        <button
            type="button"
            @click="isOpen = !isOpen"
            class="flex items-center space-x-2 px-3 py-1.5 rounded-xl border border-border bg-card/80 hover:bg-muted text-foreground text-xs font-medium transition-all shadow-sm cursor-pointer relative z-40"
        >
            <div class="w-6 h-6 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                <Server class="w-3.5 h-3.5" />
            </div>
            <div class="flex flex-col text-left leading-none max-w-[120px] sm:max-w-[180px] truncate">
                <span class="font-bold truncate text-foreground">
                    {{ routerStore.activeRouter?.name || localeStore.t('nav.selectRouter') }}
                </span>
                <span class="text-[10px] text-muted-foreground truncate font-mono mt-0.5">
                    {{ routerStore.activeRouter?.host || localeStore.t('nav.noRouter') }}
                </span>
            </div>
            <ChevronDown class="w-3.5 h-3.5 text-muted-foreground ml-1 transition-transform" :class="{ 'rotate-180': isOpen }" />
        </button>

        <!-- Dropdown Menu -->
        <div
            v-if="isOpen"
            class="absolute top-full left-0 mt-2 w-72 rounded-2xl border border-border bg-card p-2 shadow-2xl z-50 animate-in fade-in slide-in-from-top-2 duration-150"
        >
            <div class="px-3 py-2 border-b border-border mb-1 flex items-center justify-between">
                <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wider">MikroTik Routers</span>
                <button
                    type="button"
                    @click="routerStore.fetchRouters"
                    class="p-1 hover:bg-muted rounded text-muted-foreground hover:text-foreground transition-colors"
                    title="Refresh router list"
                >
                    <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': routerStore.isLoading }" />
                </button>
            </div>

            <!-- List of routers -->
            <div class="max-h-60 overflow-y-auto space-y-1">
                <button
                    v-for="r in routerStore.routers"
                    :key="r.id"
                    type="button"
                    @click="handleSelect(r.id)"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-left text-xs transition-colors cursor-pointer"
                    :class="[
                        r.id === routerStore.activeRouterId
                            ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 font-semibold'
                            : 'hover:bg-muted text-foreground'
                    ]"
                >
                    <div class="flex items-center space-x-2.5">
                        <span
                            class="w-2 h-2 rounded-full"
                            :class="[
                                r.status === 'online' ? 'bg-emerald-500 shadow-sm shadow-emerald-500/50' :
                                r.status === 'error' ? 'bg-rose-500' : 'bg-slate-400'
                            ]"
                        ></span>
                        <div class="truncate">
                            <div class="truncate font-medium">{{ r.name }}</div>
                            <div class="text-[10px] text-muted-foreground font-mono">{{ r.host }}:{{ r.api_port }} ({{ r.protocol }})</div>
                        </div>
                    </div>
                    <Check v-if="r.id === routerStore.activeRouterId" class="w-3.5 h-3.5 shrink-0 text-sky-500" />
                </button>

                <div v-if="routerStore.routers.length === 0" class="p-4 text-center text-xs text-muted-foreground">
                    No routers registered yet.
                </div>
            </div>

            <!-- Add new router button -->
            <div class="pt-2 mt-1 border-t border-border">
                <button
                    type="button"
                    @click="emit('add-router'); isOpen = false;"
                    class="w-full flex items-center justify-center space-x-1.5 py-2 px-3 rounded-xl border border-dashed border-border hover:border-sky-500/50 hover:bg-sky-500/5 text-xs font-semibold text-muted-foreground hover:text-sky-500 transition-all cursor-pointer"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>Register New Router</span>
                </button>
            </div>
        </div>
    </div>
</template>
