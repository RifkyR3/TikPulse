<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import RouterModal from '@/components/routers/RouterModal.vue';
import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useSafeModeStore } from '@/stores/safeMode';
import { useLocaleStore } from '@/stores/locale';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import type { RouterItem } from '@/types/router';
import {
    Server,
    Plus,
    Search,
    RefreshCw,
    ShieldCheck,
    ShieldAlert,
    Power,
    Edit3,
    Trash2,
    CheckCircle2,
    AlertCircle,
    Activity,
    Wifi,
    Cpu,
    HardDrive,
    Clock,
    Lock,
    Terminal,
    Copy,
    Check,
    Sparkles,
    BookOpen,
    Info
} from 'lucide-vue-next';
import { PROTOCOL_GUIDES, type ProtocolGuide } from '@/constants/protocolGuides';
import type { RouterProtocol } from '@/types/router';
import { toast } from 'vue-sonner';
import { debounce } from '@/utils/debounce';

const routerStore = useRouterContextStore();
const safeModeStore = useSafeModeStore();
const localeStore = useLocaleStore();

const searchQuery = ref('');
const debouncedSearch = ref('');
const protocolFilter = ref('all');
const isAddModalOpen = ref(false);
const editingRouter = ref<RouterItem | null>(null);
const testingRouterId = ref<number | null>(null);
const rebootingRouterId = ref<number | null>(null);
const deletingRouterId = ref<number | null>(null);
const testResult = ref<{ routerId: number; success: boolean; message: string; pingMs?: number } | null>(null);
const actionError = ref<string | null>(null);
const actionSuccess = ref<string | null>(null);
const isUnlockModalOpen = ref(false);

import DataTable from '@/components/common/DataTable.vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { LayoutGrid, List } from 'lucide-vue-next';

const viewMode = ref<'table' | 'grid'>('table');

const routerColumns: ColumnDef<RouterItem>[] = [
    {
        id: 'name',
        accessorKey: 'name',
        header: 'Router & Host',
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
    },
    {
        id: 'protocol',
        accessorKey: 'protocol',
        header: 'Driver Protocol',
    },
    {
        id: 'telemetry',
        header: 'CPU / RAM',
    },
    {
        id: 'ros_version',
        accessorKey: 'ros_version',
        header: 'RouterOS',
    },
    {
        id: 'safe_mode',
        accessorKey: 'is_safe_mode_enabled',
        header: 'Safe Mode Guard',
    },
    {
        id: 'actions',
        header: 'Actions',
    },
];

const copiedProtocol = ref<string | null>(null);

const setDebouncedSearch = debounce((val: string) => {
    debouncedSearch.value = val;
}, 250);

watch(searchQuery, (newVal) => {
    setDebouncedSearch(newVal);
});

const activeGuide = computed<ProtocolGuide | null>(() => {
    if (protocolFilter.value !== 'all' && protocolFilter.value in PROTOCOL_GUIDES) {
        return PROTOCOL_GUIDES[protocolFilter.value as RouterProtocol];
    }
    return null;
});

const copyCliScript = async (protocol: string, script: string) => {
    try {
        await navigator.clipboard.writeText(script);
        copiedProtocol.value = protocol;
        setTimeout(() => {
            if (copiedProtocol.value === protocol) {
                copiedProtocol.value = null;
            }
        }, 2500);
    } catch {
        // Fallback
    }
};

const filteredRouters = computed(() => {
    const q = debouncedSearch.value.toLowerCase().trim();
    return routerStore.routers.filter((r) => {
        const matchesSearch =
            !q ||
            r.name.toLowerCase().includes(q) ||
            (r.host || r.ip_address || '').toLowerCase().includes(q);
        const matchesProtocol =
            protocolFilter.value === 'all' || r.protocol === protocolFilter.value;
        return matchesSearch && matchesProtocol;
    });
});

const openAddModal = () => {
    editingRouter.value = null;
    isAddModalOpen.value = true;
};

const openEditModal = (router: RouterItem) => {
    editingRouter.value = router;
    isAddModalOpen.value = true;
};

const handleTestConnection = async (router: RouterItem) => {
    testingRouterId.value = router.id;
    testResult.value = null;
    actionError.value = null;

    try {
        const res = await apiClient.post<ApiResponse<{ success: boolean; ping_ms: number; message: string }>>(
            `/routers/${router.id}/test-connection`
        );
        testResult.value = {
            routerId: router.id,
            success: true,
            message: res.data.message || 'Connected successfully',
            pingMs: res.data.data?.ping_ms,
        };
        toast.success(`Connected to "${router.name}" (${res.data.data?.ping_ms ?? 0}ms)`);
        await routerStore.fetchRouters();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        const msg = axiosErr.response?.data?.message || 'Connection failed.';
        testResult.value = {
            routerId: router.id,
            success: false,
            message: msg,
        };
        toast.error(`Connection failed for "${router.name}": ${msg}`);
    } finally {
        testingRouterId.value = null;
    }
};

const handleReboot = async (router: RouterItem) => {
    if (router.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
        actionError.value = `Router "${router.name}" has Safe Mode ENABLED. Destructive operations (Reboot) are blocked until unlocked.`;
        toast.error(`Reboot blocked by Safe Mode on "${router.name}"`);
        isUnlockModalOpen.value = true;
        return;
    }

    if (!confirm(`CAUTION: Are you sure you want to REBOOT "${router.name}" (${router.host || router.ip_address})?`)) {
        return;
    }

    rebootingRouterId.value = router.id;
    actionError.value = null;
    actionSuccess.value = null;

    try {
        const res = await apiClient.post<ApiResponse<null>>(`/routers/${router.id}/reboot`);
        const msg = res.data.message || `Reboot command sent to ${router.name}.`;
        actionSuccess.value = msg;
        toast.success(msg);
        setTimeout(() => { actionSuccess.value = null; }, 5000);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            actionError.value = 'Reboot blocked by Safe Mode guardrail. Please unlock Safe Mode first.';
            toast.error('Reboot blocked by Safe Mode guardrail.');
            isUnlockModalOpen.value = true;
        } else {
            const msg = axiosErr.response?.data?.message || 'Failed to reboot router.';
            actionError.value = msg;
            toast.error(msg);
        }
    } finally {
        rebootingRouterId.value = null;
    }
};

const handleDelete = async (router: RouterItem) => {
    if (router.is_safe_mode_enabled && !safeModeStore.isUnlocked) {
        actionError.value = `Deleting router "${router.name}" requires Safe Mode to be unlocked.`;
        toast.error(`Deletion blocked by Safe Mode on "${router.name}"`);
        isUnlockModalOpen.value = true;
        return;
    }

    if (!confirm(`Are you sure you want to remove router "${router.name}" from TikPulse?`)) {
        return;
    }

    deletingRouterId.value = router.id;
    actionError.value = null;
    actionSuccess.value = null;

    try {
        await apiClient.delete(`/routers/${router.id}`);
        const msg = `Router "${router.name}" deleted successfully.`;
        actionSuccess.value = msg;
        toast.success(msg);
        await routerStore.fetchRouters();
        setTimeout(() => { actionSuccess.value = null; }, 4000);
    } catch (err: unknown) {
        const axiosErr = err as { response?: { status?: number; data?: { message?: string } } };
        if (axiosErr.response?.status === 403) {
            actionError.value = 'Deletion blocked by Safe Mode guardrail. Please unlock Safe Mode first.';
            toast.error('Deletion blocked by Safe Mode guardrail.');
            isUnlockModalOpen.value = true;
        } else {
            const msg = axiosErr.response?.data?.message || 'Failed to delete router.';
            actionError.value = msg;
            toast.error(msg);
        }
    } finally {
        deletingRouterId.value = null;
    }
};

onMounted(() => {
    routerStore.fetchRouters();
});
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Fleet Inventory
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {{ routerStore.routers.length }} {{ localeStore.t('routers.nodesRegistered') }}
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {{ localeStore.t('routers.title') }}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ localeStore.t('routers.subtitle') }}
                </p>
            </div>

            <div class="flex items-center space-x-2.5">
                <button
                    type="button"
                    @click="routerStore.fetchRouters()"
                    :disabled="routerStore.isLoading"
                    class="p-2.5 rounded-xl border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50"
                    :title="localeStore.t('common.refresh')"
                >
                    <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': routerStore.isLoading }" />
                </button>

                <button
                    type="button"
                    @click="openAddModal"
                    class="px-4 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-2 transition-colors cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>{{ localeStore.t('routers.addNewRouter') }}</span>
                </button>
            </div>
        </div>

        <!-- Global Action Alerts -->
        <div v-if="actionError" class="p-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs flex items-center justify-between gap-3">
            <div class="flex items-center space-x-2">
                <AlertCircle class="w-4 h-4 shrink-0" />
                <span>{{ actionError }}</span>
            </div>
            <button
                v-if="actionError.includes('Safe Mode')"
                type="button"
                @click="isUnlockModalOpen = true"
                class="px-2.5 py-1 rounded-lg bg-destructive text-destructive-foreground text-[11px] font-bold cursor-pointer shrink-0"
            >
                Unlock Safe Mode
            </button>
        </div>

        <!-- Filter & Search Bar -->
        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="relative w-full sm:w-80">
                <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="localeStore.t('routers.searchPlaceholder')"
                    class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-border bg-background focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 text-foreground"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                <span class="text-xs text-muted-foreground font-medium shrink-0">{{ localeStore.t('routers.driver') }}:</span>
                <select
                    v-model="protocolFilter"
                    class="w-full sm:w-auto px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer font-medium"
                >
                    <option value="all">{{ localeStore.t('routers.allProtocols') }}</option>
                    <option value="socket">Socket API (8728) — ★ Recommended (Local LAN)</option>
                    <option value="socket_ssl">Socket SSL (8729) — Recommended (Secure Remote)</option>
                    <option value="rest_https">RouterOS REST (443) — Recommended (ROS v7+ Cloud)</option>
                </select>

                <button
                    type="button"
                    v-if="protocolFilter === 'all'"
                    @click="protocolFilter = 'socket'"
                    class="px-2.5 py-1.5 rounded-xl border border-sky-500/30 bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 text-xs font-semibold flex items-center space-x-1.5 transition-all cursor-pointer shrink-0"
                    title="View MikroTik terminal setup guide for recommended driver"
                >
                    <BookOpen class="w-3.5 h-3.5" />
                    <span>MikroTik Setup Guide</span>
                </button>
                <button
                    type="button"
                    v-else
                    @click="protocolFilter = 'all'"
                    class="px-2.5 py-1.5 rounded-xl border border-border hover:bg-muted text-muted-foreground text-xs font-medium transition-all cursor-pointer shrink-0"
                    title="Reset driver filter to show all routers"
                >
                    <span>Clear Filter</span>
                </button>

                <!-- View Mode Toggle (Table / Grid) -->
                <div class="flex items-center border border-border rounded-xl p-0.5 bg-background shrink-0">
                    <button
                        type="button"
                        @click="viewMode = 'table'"
                        :class="['p-1.5 rounded-lg transition-colors cursor-pointer', viewMode === 'table' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground']"
                        title="Table View (TanStack Table)"
                    >
                        <List class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        @click="viewMode = 'grid'"
                        :class="['p-1.5 rounded-lg transition-colors cursor-pointer', viewMode === 'grid' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:text-foreground']"
                        title="Card Grid View"
                    >
                        <LayoutGrid class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- MikroTik Driver Setup & Recommendation Guide Banner -->
        <div
            v-if="activeGuide"
            class="rounded-2xl border border-sky-500/30 bg-gradient-to-br from-card via-card to-sky-500/5 p-5 shadow-sm transition-all animate-in fade-in slide-in-from-top-2 duration-200 space-y-4"
        >
            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
                <!-- Left: Recommendations & Details -->
                <div class="space-y-3 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            :class="[
                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border',
                                activeGuide.isPrimaryRecommended
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                                    : 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border-sky-500/30'
                            ]"
                        >
                            {{ activeGuide.badge }}
                        </span>
                        <span class="text-xs font-mono font-bold text-foreground">
                            {{ activeGuide.name }} (Port {{ activeGuide.port }})
                        </span>
                        <span
                            v-if="activeGuide.isPrimaryRecommended"
                            class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20"
                        >
                            <Sparkles class="w-3 h-3 text-amber-500" />
                            <span>Fastest Response Time</span>
                        </span>
                    </div>

                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-foreground">
                            {{ activeGuide.recommendationSummary }}
                        </h4>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            {{ activeGuide.description }}
                        </p>
                    </div>

                    <div class="p-3 rounded-xl bg-muted/40 border border-border text-xs space-y-2">
                        <span class="text-[11px] font-bold text-foreground uppercase tracking-wider block">
                            Key Advantages:
                        </span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-muted-foreground">
                            <div
                                v-for="(pro, idx) in activeGuide.pros"
                                :key="idx"
                                class="flex items-start space-x-1.5"
                            >
                                <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" />
                                <span>{{ pro }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: MikroTik Terminal Setup CLI Box -->
                <div class="w-full lg:w-[480px] shrink-0 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 p-4 space-y-2.5 font-mono text-xs shadow-lg">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                        <div class="flex items-center space-x-2 text-[11px] text-slate-300">
                            <Terminal class="w-3.5 h-3.5 text-sky-400" />
                            <span class="font-bold">MikroTik RouterOS Setup CLI</span>
                        </div>
                        <button
                            type="button"
                            @click="copyCliScript(activeGuide.id, activeGuide.mikrotikCommands)"
                            class="px-2.5 py-1 rounded-lg text-[11px] font-sans font-bold transition-all flex items-center space-x-1.5 cursor-pointer shadow-xs"
                            :class="copiedProtocol === activeGuide.id ? 'bg-emerald-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'"
                            title="Copy setup commands to clipboard"
                        >
                            <Check v-if="copiedProtocol === activeGuide.id" class="w-3.5 h-3.5" />
                            <Copy v-else class="w-3.5 h-3.5" />
                            <span>{{ copiedProtocol === activeGuide.id ? 'Copied to Clipboard!' : 'Copy CLI Script' }}</span>
                        </button>
                    </div>

                    <pre class="overflow-x-auto text-[11px] leading-relaxed text-sky-300 whitespace-pre-wrap select-all bg-slate-900/80 p-2.5 rounded-lg border border-slate-800/80">{{ activeGuide.mikrotikCommands }}</pre>

                    <p class="text-[10px] text-slate-400 font-sans leading-normal">
                        💡 {{ activeGuide.setupNote }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Routers Table View (Powered by TanStack Table v8) -->
        <div v-if="viewMode === 'table'" class="space-y-4">
            <DataTable
                :columns="routerColumns"
                :data="filteredRouters"
                :page-size="10"
                empty-message="No MikroTik routers found."
            >
                <!-- Router & Host -->
                <template #cell-name="{ row }">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                            <Server class="w-4 h-4" />
                        </div>
                        <div>
                            <div class="font-bold text-foreground">{{ row.name }}</div>
                            <div class="text-[11px] font-mono text-muted-foreground">{{ row.host || row.ip_address }}:{{ row.api_port || row.port }}</div>
                        </div>
                    </div>
                </template>

                <!-- Status -->
                <template #cell-status="{ row }">
                    <span
                        :class="[
                            'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                            row.status === 'online'
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : 'bg-muted text-muted-foreground border border-border'
                        ]"
                    >
                        <span class="w-1.5 h-1.5 rounded-full mr-1" :class="row.status === 'online' ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : 'bg-slate-400'"></span>
                        {{ row.status }}
                    </span>
                </template>

                <!-- Protocol -->
                <template #cell-protocol="{ row }">
                    <span class="font-mono text-xs text-foreground uppercase px-2 py-0.5 rounded-md bg-muted/60 border border-border">
                        {{ row.protocol }}
                    </span>
                </template>

                <!-- Telemetry -->
                <template #cell-telemetry="{ row }">
                    <div class="space-y-0.5 font-mono text-[11px]">
                        <div>CPU: <strong class="text-foreground">{{ row.cpu_load !== null && row.cpu_load !== undefined ? row.cpu_load + '%' : '-' }}</strong></div>
                        <div>RAM: <strong class="text-foreground">{{ row.memory_usage_percent !== null && row.memory_usage_percent !== undefined ? row.memory_usage_percent + '%' : '-' }}</strong></div>
                    </div>
                </template>

                <!-- ROS Version -->
                <template #cell-ros_version="{ row }">
                    <div class="font-mono text-xs text-foreground">{{ row.ros_version || 'v7.x' }}</div>
                    <div class="text-[10px] text-muted-foreground truncate max-w-[120px]">{{ row.board_model || '-' }}</div>
                </template>

                <!-- Safe Mode -->
                <template #cell-safe_mode="{ row }">
                    <span
                        :class="[
                            'inline-flex items-center space-x-1 px-2 py-0.5 rounded-md text-[10px] font-bold',
                            row.is_safe_mode_enabled
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                        ]"
                    >
                        <ShieldCheck v-if="row.is_safe_mode_enabled" class="w-3 h-3" />
                        <ShieldAlert v-else class="w-3 h-3" />
                        <span>{{ row.is_safe_mode_enabled ? 'PROTECTED' : 'DISABLED' }}</span>
                    </span>
                </template>

                <!-- Actions -->
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end space-x-1.5">
                        <button
                            type="button"
                            @click="routerStore.setActiveRouter(row)"
                            :disabled="routerStore.activeRouter?.id === row.id"
                            :class="[
                                'py-1 px-2.5 rounded-lg text-xs font-bold transition-all cursor-pointer',
                                routerStore.activeRouter?.id === row.id
                                    ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 cursor-default'
                                    : 'bg-muted hover:bg-muted/80 text-foreground border border-border'
                            ]"
                        >
                            {{ routerStore.activeRouter?.id === row.id ? 'Active Node' : 'Select' }}
                        </button>
                        <button
                            type="button"
                            @click="handleTestConnection(row)"
                            :disabled="testingRouterId === row.id"
                            class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50"
                            title="Test Connection"
                        >
                            <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': testingRouterId === row.id }" />
                        </button>
                        <button
                            type="button"
                            @click="handleReboot(row)"
                            :disabled="rebootingRouterId === row.id"
                            class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                            title="Reboot Router"
                        >
                            <Power class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="openEditModal(row)"
                            class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            title="Edit Router"
                        >
                            <Edit3 class="w-3.5 h-3.5" />
                        </button>
                        <button
                            type="button"
                            @click="handleDelete(row)"
                            :disabled="deletingRouterId === row.id"
                            class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                            title="Delete Router"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Routers Card Grid View -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <div
                v-for="router in filteredRouters"
                :key="router.id"
                :class="[
                    'relative rounded-2xl border bg-card p-5 shadow-sm transition-all duration-200 flex flex-col justify-between space-y-4',
                    routerStore.activeRouter?.id === router.id
                        ? 'border-sky-500 ring-2 ring-sky-500/15 shadow-md'
                        : 'border-border hover:border-border/80'
                ]"
            >
                <!-- Card Header -->
                <div class="space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center space-x-3 overflow-hidden">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-sky-500/20 to-indigo-500/20 border border-sky-500/20 text-sky-500 flex items-center justify-center shrink-0">
                                <Server class="w-5 h-5" />
                            </div>
                            <div class="overflow-hidden">
                                <h3 class="font-bold text-sm text-foreground truncate" :title="router.name">
                                    {{ router.name }}
                                </h3>
                                <p class="text-xs text-muted-foreground font-mono truncate">
                                    {{ router.host || router.ip_address }}:{{ router.api_port || router.port }}
                                </p>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <span
                            :class="[
                                'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider shrink-0',
                                router.status === 'online'
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                    : 'bg-muted text-muted-foreground border border-border'
                            ]"
                        >
                            <span class="w-1.5 h-1.5 rounded-full mr-1" :class="router.status === 'online' ? 'bg-emerald-500 shadow-xs shadow-emerald-500/50' : 'bg-slate-400'"></span>
                            {{ router.status }}
                        </span>
                    </div>

                    <!-- Description / Location -->
                    <p v-if="router.description" class="text-xs text-muted-foreground italic">
                        {{ router.description }}
                    </p>
                </div>

                <!-- Telemetry Pills Grid -->
                <div class="grid grid-cols-2 gap-2 text-xs bg-muted/40 p-3 rounded-xl border border-border/60 font-mono">
                    <div class="flex items-center space-x-1.5">
                        <Cpu class="w-3.5 h-3.5 text-sky-500 shrink-0" />
                        <span class="text-muted-foreground">CPU:</span>
                        <strong class="text-foreground">{{ router.cpu_load !== null && router.cpu_load !== undefined ? router.cpu_load + '%' : '-' }}</strong>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <HardDrive class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                        <span class="text-muted-foreground">RAM:</span>
                        <strong class="text-foreground truncate">{{ router.memory_usage_percent !== null && router.memory_usage_percent !== undefined ? router.memory_usage_percent + '%' : (router.free_memory_bytes ? Math.round(router.free_memory_bytes / (1024 * 1024)) + 'MB free' : '-') }}</strong>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <Wifi class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                        <span class="text-muted-foreground">ROS:</span>
                        <strong class="text-foreground truncate">{{ router.ros_version || 'v7.x' }}</strong>
                    </div>
                    <div class="flex items-center space-x-1.5">
                        <Clock class="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                        <span class="text-muted-foreground">Uptime:</span>
                        <strong class="text-foreground truncate">{{ router.uptime || '-' }}</strong>
                    </div>
                </div>

                <!-- Safe Mode Status Pill -->
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground text-[11px] font-medium">Safe Mode Guard:</span>
                    <span
                        :class="[
                            'inline-flex items-center space-x-1 px-2 py-0.5 rounded-md text-[10px] font-bold',
                            router.is_safe_mode_enabled
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                                : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'
                        ]"
                    >
                        <ShieldCheck v-if="router.is_safe_mode_enabled" class="w-3 h-3" />
                        <ShieldAlert v-else class="w-3 h-3" />
                        <span>{{ router.is_safe_mode_enabled ? 'PROTECTED' : 'DISABLED' }}</span>
                    </span>
                </div>

                <!-- Test Result inline notification if triggered -->
                <div
                    v-if="testResult && testResult.routerId === router.id"
                    :class="[
                        'p-2.5 rounded-lg text-xs font-mono flex items-center justify-between',
                        testResult.success
                            ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                            : 'bg-destructive/10 text-destructive border border-destructive/20'
                    ]"
                >
                    <span class="truncate">{{ testResult.message }}</span>
                    <span v-if="testResult.pingMs" class="font-bold shrink-0 ml-2">{{ testResult.pingMs }}ms</span>
                </div>

                <!-- Action Buttons -->
                <div class="pt-2 border-t border-border flex items-center justify-between gap-1.5">
                    <button
                        type="button"
                        @click="routerStore.setActiveRouter(router)"
                        :disabled="routerStore.activeRouter?.id === router.id"
                        :class="[
                            'flex-1 py-1.5 px-2.5 rounded-lg text-xs font-bold transition-all cursor-pointer text-center',
                            routerStore.activeRouter?.id === router.id
                                ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 cursor-default'
                                : 'bg-muted hover:bg-muted/80 text-foreground border border-border'
                        ]"
                    >
                        {{ routerStore.activeRouter?.id === router.id ? 'Active Node' : 'Select Router' }}
                    </button>

                    <button
                        type="button"
                        @click="handleTestConnection(router)"
                        :disabled="testingRouterId === router.id"
                        class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50"
                        title="Test Connection (Ping socket/REST)"
                    >
                        <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': testingRouterId === router.id }" />
                    </button>

                    <button
                        type="button"
                        @click="handleReboot(router)"
                        :disabled="rebootingRouterId === router.id"
                        class="p-2 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                        title="Reboot Router (Safe Mode Protected)"
                    >
                        <Power class="w-3.5 h-3.5" />
                    </button>

                    <button
                        type="button"
                        @click="openEditModal(router)"
                        class="p-2 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                        title="Edit Router Configuration"
                    >
                        <Edit3 class="w-3.5 h-3.5" />
                    </button>

                    <button
                        type="button"
                        @click="handleDelete(router)"
                        :disabled="deletingRouterId === router.id"
                        class="p-2 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                        title="Delete Router"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="filteredRouters.length === 0"
                class="col-span-full py-16 text-center border-2 border-dashed border-border rounded-2xl p-8"
            >
                <div class="flex flex-col items-center justify-center space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-muted text-muted-foreground flex items-center justify-center">
                        <Server class="w-6 h-6" />
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-foreground">No MikroTik Routers Found</h4>
                        <p class="text-xs text-muted-foreground max-w-sm">
                            {{ searchQuery ? 'No routers matched your search query.' : 'Get started by registering your first MikroTik router profile.' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="openAddModal"
                        class="mt-2 px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add First Router</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Global Modals -->
        <RouterModal
            :is-open="isAddModalOpen"
            :router-to-edit="editingRouter"
            @close="isAddModalOpen = false"
            @created="isAddModalOpen = false"
            @updated="isAddModalOpen = false"
        />

        <SafeModeUnlockModal
            :is-open="isUnlockModalOpen"
            @close="isUnlockModalOpen = false"
            @unlocked="isUnlockModalOpen = false"
        />
    </AdminLayout>
</template>
