<script lang="ts">
    import { onMount } from 'svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import { routerStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import {
        RefreshCw,
        Server,
        AlertOctagon,
        CheckCircle2,
    } from '@lucide/svelte';
    import DataTable, { type Column } from '@/components/common/DataTable.svelte';

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
        router?: { id: number; name: string; ip_address: string; host?: string };
        user?: { id: number; name: string; email: string };
    }

    interface PaginatedData<T> {
        data: T[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    }

    const columns: Column<AuditLogItem>[] = [
        { id: 'is_blocked', header: 'Guardrail Status' },
        { id: 'command_type', header: 'Command / Action' },
        { id: 'router', header: 'Router Node' },
        { id: 'user', header: 'Admin / Operator' },
        { id: 'client_ip', header: 'Client IP' },
        { id: 'created_at', header: 'Timestamp' },
        { id: 'actions', header: 'Details', align: 'right' },
    ];

    let logs = $state<AuditLogItem[]>([]);
    let isLoading = $state(false);
    let currentPage = $state(1);
    let lastPage = $state(1);
    let totalLogs = $state(0);
    let selectedRouterId = $state('');
    let blockedOnly = $state(false);
    let selectedPayload = $state<AuditLogItem | null>(null);

    async function fetchLogs(page = 1) {
        isLoading = true;
        try {
            const params: Record<string, unknown> = { page };
            if (selectedRouterId) {
                params.router_id = selectedRouterId;
            }
            if (blockedOnly) {
                params.blocked_only = true;
            }

            const res = await apiClient.get<ApiResponse<PaginatedData<AuditLogItem>>>('/safe-mode/audit-logs', { params });
            if (res.data.status === 'success' && res.data.data) {
                logs = res.data.data.data;
                currentPage = res.data.data.current_page;
                lastPage = res.data.data.last_page;
                totalLogs = res.data.data.total;
            }
        } catch {
            // Error handled
        } finally {
            isLoading = false;
        }
    }

    function onFilterChange() {
        currentPage = 1;
        fetchLogs(1);
    }

    onMount(() => {
        routerStore.fetchRouters();
        fetchLogs(1);
    });
</script>

{#snippet blockedCell(row: AuditLogItem)}
    <span
        class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider {row.is_blocked
            ? 'bg-destructive/10 text-destructive border border-destructive/20'
            : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'}"
    >
        {#if row.is_blocked}
            <AlertOctagon class="w-3 h-3 mr-1" />
            <span>BLOCKED</span>
        {:else}
            <CheckCircle2 class="w-3 h-3 mr-1" />
            <span>ALLOWED</span>
        {/if}
    </span>
{/snippet}

{#snippet commandCell(row: AuditLogItem)}
    <div class="font-bold text-foreground font-mono">
        {{ ...row }.command_type}
    </div>
    <div class="text-[10px] text-muted-foreground truncate max-w-xs font-mono">
        {{ ...row }.target_endpoint}
    </div>
{/snippet}

{#snippet routerCell(row: AuditLogItem)}
    <div class="font-medium text-foreground text-xs truncate max-w-[150px]">
        {row.router?.name || `Router #${row.router_id}`}
    </div>
    <div class="text-[10px] font-mono text-muted-foreground">
        {row.router?.host || row.router?.ip_address || '-'}
    </div>
{/snippet}

{#snippet userCell(row: AuditLogItem)}
    <div class="font-medium text-foreground text-xs truncate max-w-[140px]">
        {row.user?.name || `User #${row.user_id}`}
    </div>
    <div class="text-[10px] text-muted-foreground truncate max-w-[140px]">
        {row.user?.email || '-'}
    </div>
{/snippet}

{#snippet ipCell(row: AuditLogItem)}
    <span class="font-mono text-muted-foreground text-xs">{row.client_ip}</span>
{/snippet}

{#snippet dateCell(row: AuditLogItem)}
    <span class="font-mono text-muted-foreground text-[11px]">
        {new Date(row.created_at).toLocaleString()}
    </span>
{/snippet}

{#snippet actionsCell(row: AuditLogItem)}
    <div class="text-right">
        <button
            type="button"
            onclick={() => (selectedPayload = row)}
            class="px-2.5 py-1 rounded-md border border-border hover:bg-muted text-muted-foreground hover:text-foreground text-[11px] font-medium transition-colors cursor-pointer"
        >
            Payload
        </button>
    </div>
{/snippet}

<AdminLayout title="Safe Mode Audit Logs">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                        Audit Trail & Compliance
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        {totalLogs} Recorded Actions
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
                onclick={() => fetchLogs(currentPage)}
                disabled={isLoading}
                class="p-2.5 rounded-xl border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer disabled:opacity-50 self-start sm:self-auto"
                title="Refresh Logs"
            >
                <RefreshCw class="w-4 h-4 {isLoading ? 'animate-spin' : ''}" />
            </button>
        </div>

        <!-- Filter Controls -->
        <div class="rounded-2xl border border-border bg-card p-4 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                <!-- Router Filter -->
                <div class="flex items-center space-x-2">
                    <Server class="w-4 h-4 text-muted-foreground shrink-0" />
                    <select
                        bind:value={selectedRouterId}
                        onchange={onFilterChange}
                        class="px-3 py-1.5 text-xs rounded-xl border border-border bg-background text-foreground focus:outline-none focus:border-sky-500 cursor-pointer"
                    >
                        <option value="">All Routers</option>
                        {#each routerStore.routers as r (r.id)}
                            <option value={r.id}>
                                {r.name} ({r.host || r.ip_address})
                            </option>
                        {/each}
                    </select>
                </div>

                <!-- Blocked Only Toggle -->
                <label class="flex items-center space-x-2 text-xs text-foreground cursor-pointer select-none">
                    <input
                        type="checkbox"
                        bind:checked={blockedOnly}
                        onchange={onFilterChange}
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <span class="font-medium">Blocked Actions Only</span>
                </label>
            </div>

            <div class="text-xs text-muted-foreground font-mono">
                Showing page {currentPage} of {lastPage}
            </div>
        </div>

        <!-- Audit Logs Table -->
        <DataTable
            {columns}
            data={logs}
            {isLoading}
            manualPagination={true}
            pageCount={lastPage}
            pageIndex={currentPage - 1}
            totalRows={totalLogs}
            emptyMessage="No audit logs found. Safe Mode operations and destructive attempts will be logged here."
            onPageChange={(p) => fetchLogs(p)}
            customCells={{
                is_blocked: blockedCell,
                command_type: commandCell,
                router: routerCell,
                user: userCell,
                client_ip: ipCell,
                created_at: dateCell,
                actions: actionsCell,
            }}
        />

        <!-- Payload Modal -->
        {#if selectedPayload}
            <div
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
            >
                <div class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-border pb-3">
                        <div class="flex items-center space-x-2">
                            <span
                                class="px-2 py-0.5 rounded text-[10px] font-bold font-mono {selectedPayload.is_blocked
                                    ? 'bg-destructive/10 text-destructive'
                                    : 'bg-emerald-500/10 text-emerald-500'}"
                            >
                                {selectedPayload.is_blocked ? 'BLOCKED' : 'ALLOWED'}
                            </span>
                            <h4 class="font-bold text-sm text-foreground font-mono">
                                {selectedPayload.command_type}
                            </h4>
                        </div>
                        <button
                            type="button"
                            onclick={() => (selectedPayload = null)}
                            class="text-muted-foreground hover:text-foreground text-xs font-bold p-1 cursor-pointer"
                        >
                            ✕
                        </button>
                    </div>

                    {#if selectedPayload.bypass_reason}
                        <div class="p-3 rounded-lg bg-muted text-xs text-foreground">
                            <span class="font-semibold block text-[11px] text-muted-foreground">Bypass / Unlock Reason:</span>
                            <span>{selectedPayload.bypass_reason}</span>
                        </div>
                    {/if}

                    <div>
                        <span class="font-semibold block text-[11px] text-muted-foreground mb-1">Payload JSON:</span>
                        <pre class="p-3 rounded-xl bg-background border border-border text-[11px] font-mono text-foreground overflow-x-auto max-h-60">{JSON.stringify(selectedPayload.payload || {}, null, 2)}</pre>
                    </div>

                    <div class="pt-2 flex justify-end">
                        <button
                            type="button"
                            onclick={() => (selectedPayload = null)}
                            class="px-4 py-1.5 rounded-lg border border-border hover:bg-muted text-xs font-semibold cursor-pointer"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</AdminLayout>
