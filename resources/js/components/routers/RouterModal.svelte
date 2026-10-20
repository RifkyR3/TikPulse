<script lang="ts">
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { RouterItem, RouterProtocol } from '@/types/router';
    import { X, Server, Check, Loader2, ShieldCheck, Terminal, Copy, Sparkles } from '@lucide/svelte';
    import { PROTOCOL_GUIDES, type ProtocolGuide } from '@/constants/protocolGuides';

    let {
        isOpen = false,
        routerToEdit = null,
        onClose,
        onCreated,
        onUpdated,
    }: {
        isOpen?: boolean;
        routerToEdit?: RouterItem | null;
        onClose?: () => void;
        onCreated?: () => void;
        onUpdated?: () => void;
    } = $props();

    let isSetupCliVisible = $state(false);
    let copiedCli = $state(false);

    let name = $state('');
    let host = $state('192.168.88.1');
    let api_port = $state(8728);
    let protocol = $state<RouterProtocol>('socket');
    let username = $state('admin');
    let password = $state('');
    let is_safe_mode_enabled = $state(true);
    let timeout_seconds = $state(5);
    let description = $state('');

    let isSubmitting = $state(false);
    let error = $state<string | null>(null);

    const currentProtocolGuide = $derived<ProtocolGuide>(
        PROTOCOL_GUIDES[protocol || 'socket']
    );

    $effect(() => {
        if (routerToEdit) {
            name = routerToEdit.name;
            host = routerToEdit.ip_address;
            api_port = routerToEdit.port;
            protocol = routerToEdit.protocol;
            username = routerToEdit.username;
            password = '';
            is_safe_mode_enabled = routerToEdit.is_safe_mode_enabled;
            timeout_seconds = 5;
            description = routerToEdit.description || '';
        } else {
            resetForm();
        }
    });

    function resetForm() {
        name = '';
        host = '192.168.88.1';
        api_port = 8728;
        protocol = 'socket';
        username = 'admin';
        password = '';
        is_safe_mode_enabled = true;
        timeout_seconds = 5;
        description = '';
        error = null;
    }

    function onProtocolChange(newProtocol: RouterProtocol) {
        protocol = newProtocol;
        if (newProtocol === 'socket') {
            api_port = 8728;
        } else if (newProtocol === 'socket_ssl') {
            api_port = 8729;
        } else if (newProtocol === 'rest_https') {
            api_port = 443;
        }
    }

    async function copyModalCliScript() {
        try {
            await navigator.clipboard.writeText(currentProtocolGuide.mikrotikCommands);
            copiedCli = true;
            setTimeout(() => {
                copiedCli = false;
            }, 2500);
        } catch {
            // Fallback
        }
    }

    function handleClose() {
        resetForm();
        onClose?.();
    }

    async function handleSubmit() {
        error = null;
        isSubmitting = true;

        try {
            const payload: Record<string, unknown> = {
                name,
                host,
                api_port,
                protocol,
                username,
                is_safe_mode_enabled,
                timeout_seconds,
                description,
            };

            if (password) {
                payload.password = password;
            }

            if (routerToEdit) {
                await apiClient.put(`/routers/${routerToEdit.id}`, payload);
                await routerContextStore.fetchRouters();
                onUpdated?.();
            } else {
                if (!password) {
                    error = 'Password is required for new router profiles.';
                    isSubmitting = false;
                    return;
                }
                payload.password = password;
                await apiClient.post('/routers', payload);
                await routerContextStore.fetchRouters();
                onCreated?.();
            }

            handleClose();
        } catch (err: unknown) {
            const axiosErr = err as { response?: { data?: { message?: string } } };
            error = axiosErr.response?.data?.message || 'Failed to save router profile.';
        } finally {
            isSubmitting = false;
        }
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150">
        <div
            class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all max-h-[90vh] overflow-y-auto"
            role="dialog"
            aria-modal="true"
        >
            <button
                type="button"
                onclick={handleClose}
                class="absolute top-4 right-4 text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-5">
                <div class="w-11 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-500 flex items-center justify-center shrink-0">
                    <Server class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">
                        {routerToEdit ? 'Edit Router Profile' : 'Register MikroTik Router'}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {routerToEdit ? 'Update connection & guardrail settings' : 'Add new router profile for hotspot & monitoring'}
                    </p>
                </div>
            </div>

            <!-- Error Banner -->
            {#if error}
                <div class="mb-4 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                    {error}
                </div>
            {/if}

            <form onsubmit={(e) => { e.preventDefault(); handleSubmit(); }} class="space-y-4">
                <!-- Name -->
                <div>
                    <label for="router_name" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Router Name
                    </label>
                    <input
                        id="router_name"
                        bind:value={name}
                        type="text"
                        required
                        placeholder="e.g. Core-RB4011-Hotspot"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Host & Port Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label for="router_host" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Host / IP Address
                        </label>
                        <input
                            id="router_host"
                            bind:value={host}
                            type="text"
                            required
                            placeholder="192.168.88.1"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label for="router_api_port" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Port
                        </label>
                        <input
                            id="router_api_port"
                            bind:value={api_port}
                            type="number"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Protocol with Recommendation and Setup Guide -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label for="router_protocol" class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Connection Driver
                        </label>
                        <button
                            type="button"
                            onclick={() => (isSetupCliVisible = !isSetupCliVisible)}
                            class="text-[11px] font-semibold text-sky-500 hover:text-sky-400 flex items-center space-x-1 cursor-pointer"
                        >
                            <Terminal class="w-3 h-3" />
                            <span>{isSetupCliVisible ? 'Hide Setup CLI' : 'Show MikroTik Setup CLI'}</span>
                        </button>
                    </div>

                    <select
                        id="router_protocol"
                        value={protocol}
                        onchange={(e) => onProtocolChange((e.target as HTMLSelectElement).value as RouterProtocol)}
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer font-medium"
                    >
                        <option value="socket">Socket API (Port 8728) — ★ Recommended (Local LAN)</option>
                        <option value="socket_ssl">Socket SSL (Port 8729) — Recommended (Secure Remote / WAN)</option>
                        <option value="rest_https">RouterOS REST (Port 443) — Recommended (ROS v7+ Cloud)</option>
                    </select>

                    <!-- Active Protocol Recommendation & Info Pill -->
                    <div class="p-3 rounded-xl border border-sky-500/20 bg-sky-500/5 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center space-x-1 font-bold text-sky-600 dark:text-sky-400 text-[11px]">
                                {#if currentProtocolGuide.isPrimaryRecommended}
                                    <Sparkles class="w-3.5 h-3.5 text-amber-500" />
                                {/if}
                                <span>{currentProtocolGuide.badge}</span>
                            </span>
                            <span class="text-[10px] text-muted-foreground font-mono">Default Port: {currentProtocolGuide.port}</span>
                        </div>
                        <p class="text-muted-foreground text-[11px] leading-relaxed">
                            {currentProtocolGuide.description}
                        </p>
                    </div>

                    <!-- Collapsible MikroTik Terminal Script -->
                    {#if isSetupCliVisible}
                        <div class="rounded-xl bg-slate-950 border border-slate-800 text-slate-100 p-3 space-y-2 font-mono text-xs shadow-inner animate-in fade-in duration-150">
                            <div class="flex items-center justify-between border-b border-slate-800 pb-1.5">
                                <div class="flex items-center space-x-1.5 text-[10px] text-slate-400">
                                    <Terminal class="w-3 h-3 text-sky-400" />
                                    <span>MikroTik Setup CLI Commands</span>
                                </div>
                                <button
                                    type="button"
                                    onclick={copyModalCliScript}
                                    class="px-2 py-0.5 rounded text-[10px] font-sans font-bold transition-all flex items-center space-x-1 cursor-pointer {copiedCli ? 'bg-emerald-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'}"
                                >
                                    {#if copiedCli}
                                        <Check class="w-3 h-3" />
                                    {:else}
                                        <Copy class="w-3 h-3" />
                                    {/if}
                                    <span>{copiedCli ? 'Copied!' : 'Copy Script'}</span>
                                </button>
                            </div>
                            <pre class="overflow-x-auto text-[10px] leading-relaxed text-sky-300 whitespace-pre-wrap select-all">{currentProtocolGuide.mikrotikCommands}</pre>
                            <p class="text-[9px] text-slate-400 font-sans">
                                💡 {currentProtocolGuide.setupNote}
                            </p>
                        </div>
                    {/if}
                </div>

                <!-- Username & Password Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="router_username" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Username
                        </label>
                        <input
                            id="router_username"
                            bind:value={username}
                            type="text"
                            required
                            placeholder="admin"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label for="router_password" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Password
                        </label>
                        <input
                            id="router_password"
                            bind:value={password}
                            type="password"
                            required={!routerToEdit}
                            placeholder={routerToEdit ? 'Leave blank to keep' : '••••••••'}
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Safe Mode Default Flag -->
                <div class="flex items-center space-x-3 pt-2">
                    <input
                        id="router_safe_mode_checkbox"
                        bind:checked={is_safe_mode_enabled}
                        type="checkbox"
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <label for="router_safe_mode_checkbox" class="text-xs text-foreground font-medium cursor-pointer flex items-center space-x-1.5">
                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
                        <span>Enable Safe Mode Guardrail (Blocks accidental reboots & mass deletions)</span>
                    </label>
                </div>

                <!-- Description / Location -->
                <div>
                    <label for="router_description" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Location / Description
                    </label>
                    <input
                        id="router_description"
                        bind:value={description}
                        type="text"
                        placeholder="e.g. Branch Office Floor 2"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Submit Buttons -->
                <div class="mt-6 flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <button
                        type="button"
                        onclick={handleClose}
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-500 text-white shadow-md shadow-sky-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        {#if isSubmitting}
                            <Loader2 class="w-3.5 h-3.5 animate-spin" />
                        {:else}
                            <Check class="w-3.5 h-3.5" />
                        {/if}
                        <span>{routerToEdit ? 'Save Changes' : 'Save Router Profile'}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
