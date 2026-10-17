<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import AdminLayout from '@/components/layout/AdminLayout.vue';
import { useRouterContextStore } from '@/stores/routerContext';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import {
    ShieldAlert,
    ShieldCheck,
    RefreshCw,
    Search,
    Filter,
    Clock,
    Server,
    User,
    Globe,
    AlertOctagon,
    CheckCircle2
} from 'lucide-vue-next';

import DataTable from '@/components/common/DataTable.vue';
import type { ColumnDef } from '@tanstack/vue-table';

interface AuditLogItem {
    id: number;
    router_id: number | null;
    user_id: number | null;
    command_type: string;
    target_endpoint: string;
    payload: Record<string, unknown> | null;
    is_blocked: boolean;
    bypass_reason: string | null;
    client_ip: string;
    created_at: string;
    router?: { id: number; name: string; ip_address: string };
    user?: { id: number; name: string; email: string };
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

const columns: ColumnDef<AuditLogItem>[] = [
    {
        id: 'is_blocked',
        accessorKey: 'is_blocked',
        header: 'Guardrail Status',
    },
    {
        id: 'command_type',
        accessorKey: 'command_type',
        header: 'Command / Action',
    },
    {
        id: 'router',
        header: 'Router Node',
    },
    {
        id: 'user',
        header: 'Admin / Operator',
    },
    {
        id: 'client_ip',
        accessorKey: 'client_ip',
        header: 'Client IP',
    },
    {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Timestamp',
    },
    {
        id: 'actions',
        header: 'Details',
    },
];

const routerStore = useRouterContextStore();

const logs = ref<AuditLogItem[]>([]);
const isLoading = ref(false);
const currentPage = ref(1);
const lastPage = ref(1);
const totalLogs = ref(0);
const selectedRouterId = ref<string>('');
const blockedOnly = ref(false);
const selectedPayload = ref<AuditLogItem | null>(null);

const fetchLogs = async (page = 1) => {
    isLoading.value = true;
    try {
        const params: Record<string, unknown> = {
            page,
        };
        if (selectedRouterId.value) {
            params.router_id = selectedRouterId.value;
        }
        if (blockedOnly.value) {
            params.blocked_only = true;
        }

        const res = await apiClient.get<ApiResponse<PaginatedData<AuditLogItem>>>('/safe-mode/audit-logs', { params });
        if (res.data.status === 'success' && res.data.data) {
            logs.value = res.data.data.data;
            currentPage.value = res.data.data.current_page;
            lastPage.value = res.data.data.last_page;
            totalLogs.value = res.data.data.total;
        }
    } catch {
        // Fallback or error
    } finally {
        isLoading.value = false;
    }
};

watch([selectedRouterId, blockedOnly], () => {
    currentPage.value = 1;
    fetchLogs(1);
});

onMounted(() => {
    routerStore.fetchRouters();
    fetchLogs(1);
});
</script>

<template>
    <AdminLayout>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        Audit Trail & Compliance
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {{ totalLogs }} Recorded Actions
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    Safe Mode Audit Logs
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Tamper-evident audit trail for every command executed or intercepted by Safe Mode guardrails.
                </p>
            </div>

            <button
                type="button"
                @click="fetchLogs(currentPage)"
                :disabled="isLoading"
                class="p-2.5 rounded-xl border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50 self-start sm:self-auto"
                title="Refresh Logs"
            >
                <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
            </button>
        </div>

        <!-- Filter Controls -->
        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <!-- Router Filter -->
                <div class="flex items-center space-x-2">
                    <Server class="w-4 h-4 text-muted-foreground shrink-0" />
                    <select
                        v-model="selectedRouterId"
                        class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                    >
                        <option value="">All Routers</option>
                        <option v-for="r in routerStore.routers" :key="r.id" :value="r.id">
                            {{ r.name }} ({{ r.host || r.ip_address }})
                        </option>
                    </select>
                </div>

                <!-- Blocked Only Toggle -->
                <label class="flex items-center space-x-2 text-xs text-foreground cursor-pointer select-none">
                    <input
                        type="checkbox"
                        v-model="blockedOnly"
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <span class="font-medium">Blocked Actions Only</span>
                </label>
            </div>

            <div class="text-xs text-muted-foreground font-mono">
                Showing page {{ currentPage }} of {{ lastPage }}
            </div>
        </div>

        <!-- Audit Logs Table (TanStack Table v8) -->
        <DataTable
            :columns="columns"
            :data="logs"
            :is-loading="isLoading"
            :manual-pagination="true"
            :page-count="lastPage"
            :page-index="currentPage - 1"
            :total-rows="totalLogs"
            empty-message="No audit logs found. Safe Mode operations and destructive attempts will be logged here."
            @page-change="(p) => fetchLogs(p)"
        >
            <!-- Guardrail Status Slot -->
            <template #cell-is_blocked="{ row }">
                <span
                    :class="[
                        'inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider',
                        row.is_blocked
                            ? 'bg-destructive/10 text-destructive border border-destructive/20'
                            : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
                    ]"
                >
                    <AlertOctagon v-if="row.is_blocked" class="w-3 h-3" />
                    <CheckCircle2 v-else class="w-3 h-3" />
                    <span>{{ row.is_blocked ? 'BLOCKED' : 'ALLOWED' }}</span>
                </span>
            </template>

            <!-- Command Type Slot -->
            <template #cell-command_type="{ row }">
                <div class="font-bold text-foreground font-mono">
                    {{ row.command_type }}
                </div>
                <div class="text-[10px] text-muted-foreground truncate max-w-xs font-mono">
                    {{ row.target_endpoint }}
                </div>
            </template>

            <!-- Router Slot -->
            <template #cell-router="{ row }">
                <div class="font-medium text-foreground text-xs truncate max-w-[150px]">
                    {{ row.router?.name || `Router #${row.router_id}` }}
                </div>
                <div class="text-[10px] font-mono text-muted-foreground">
                    {{ row.router?.host || row.router?.ip_address || '-' }}
                </div>
            </template>

            <!-- User Slot -->
            <template #cell-user="{ row }">
                <div class="font-medium text-foreground text-xs truncate max-w-[140px]">
                    {{ row.user?.name || `User #${row.user_id}` }}
                </div>
                <div class="text-[10px] text-muted-foreground truncate max-w-[140px]">
                    {{ row.user?.email || '-' }}
                </div>
            </template>

            <!-- Client IP Slot -->
            <template #cell-client_ip="{ row }">
                <span class="font-mono text-muted-foreground">{{ row.client_ip }}</span>
            </template>

            <!-- Timestamp Slot -->
            <template #cell-created_at="{ row }">
                <span class="font-mono text-muted-foreground text-[11px]">
                    {{ new Date(row.created_at).toLocaleString() }}
                </span>
            </template>

            <!-- Actions Slot -->
            <template #cell-actions="{ row }">
                <div class="text-right">
                    <button
                        type="button"
                        @click="selectedPayload = row"
                        class="px-2.5 py-1 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground text-[11px] font-medium transition-colors cursor-pointer"
                    >
                        Payload
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Payload Modal -->
        <div
            v-if="selectedPayload"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
        >
            <div class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between border-b border-border pb-3">
                    <div class="flex items-center space-x-2">
                        <span
                            :class="[
                                'px-2 py-0.5 rounded text-[10px] font-bold font-mono',
                                selectedPayload.is_blocked
                                    ? 'bg-destructive/10 text-destructive'
                                    : 'bg-emerald-500/10 text-emerald-500'
                            ]"
                        >
                            {{ selectedPayload.is_blocked ? 'BLOCKED' : 'ALLOWED' }}
                        </span>
                        <h4 class="font-bold text-sm text-foreground font-mono">
                            {{ selectedPayload.command_type }}
                        </h4>
                    </div>
                    <button
                        type="button"
                        @click="selectedPayload = null"
                        class="text-muted-foreground hover:text-foreground text-xs font-bold p-1 cursor-pointer"
                    >
                        ✕
                    </button>
                </div>

                <div v-if="selectedPayload.bypass_reason" class="p-3 rounded-lg bg-muted text-xs text-foreground">
                    <span class="font-semibold block text-[11px] text-muted-foreground">Bypass / Unlock Reason:</span>
                    <span>{{ selectedPayload.bypass_reason }}</span>
                </div>

                <div>
                    <span class="font-semibold block text-[11px] text-muted-foreground mb-1">Payload JSON:</span>
                    <pre class="p-3 rounded-xl bg-background border border-border text-[11px] font-mono text-foreground overflow-x-auto max-h-60">{{ JSON.stringify(selectedPayload.payload || {}, null, 2) }}</pre>
                </div>

                <div class="pt-2 flex justify-end">
                    <button
                        type="button"
                        @click="selectedPayload = null"
                        class="px-4 py-1.5 rounded-lg border border-border hover:bg-muted text-xs font-semibold cursor-pointer"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
