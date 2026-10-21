<script lang="ts">
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { Group, ModuleItem, ModulePermission } from '@/types/group';
    import type { Router } from '@/types/router';
    import type { ApiResponse } from '@/types/api';
    import { X, Shield, Save, Server, Layers, Check, CheckSquare, Square, RefreshCw, Sparkles } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';

    let {
        isOpen = false,
        groupToEdit = null,
        modules = [],
        routers = [],
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        groupToEdit: Group | null;
        modules: ModuleItem[];
        routers: Router[];
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let slug = $state('');
    let description = $state('');
    let allRouters = $state(true);
    let selectedRouterIds = $state<number[]>([]);
    let permissionsMap = $state<Record<string, { can_read: boolean; can_write: boolean; can_update: boolean; can_delete: boolean }>>({});
    let isSaving = $state(false);
    let errors = $state<Record<string, string[]>>({});

    $effect(() => {
        if (isOpen) {
            errors = {};
            if (groupToEdit) {
                name = groupToEdit.name;
                slug = groupToEdit.slug;
                description = groupToEdit.description || '';
                allRouters = groupToEdit.all_routers;
                selectedRouterIds = groupToEdit.router_ids ? [...groupToEdit.router_ids] : [];

                const map: Record<string, { can_read: boolean; can_write: boolean; can_update: boolean; can_delete: boolean }> = {};
                modules.forEach((mod) => {
                    const existing = groupToEdit?.permissions?.find((p) => p.module_key === mod.key);
                    map[mod.key] = {
                        can_read: existing ? existing.can_read : false,
                        can_write: existing ? existing.can_write : false,
                        can_update: existing ? existing.can_update : false,
                        can_delete: existing ? existing.can_delete : false,
                    };
                });
                permissionsMap = map;
            } else {
                name = '';
                slug = '';
                description = '';
                allRouters = false;
                selectedRouterIds = routers.map((r) => r.id);

                const map: Record<string, { can_read: boolean; can_write: boolean; can_update: boolean; can_delete: boolean }> = {};
                modules.forEach((mod) => {
                    map[mod.key] = {
                        can_read: ['dashboard', 'routers', 'hotspot_vouchers'].includes(mod.key),
                        can_write: mod.key === 'hotspot_vouchers',
                        can_update: mod.key === 'hotspot_vouchers',
                        can_delete: false,
                    };
                });
                permissionsMap = map;
            }
        }
    });

    function toggleRouter(id: number) {
        if (selectedRouterIds.includes(id)) {
            selectedRouterIds = selectedRouterIds.filter((rId) => rId !== id);
        } else {
            selectedRouterIds = [...selectedRouterIds, id];
        }
    }

    function toggleAllPermissions(grant: boolean) {
        const newMap: Record<string, { can_read: boolean; can_write: boolean; can_update: boolean; can_delete: boolean }> = {};
        modules.forEach((mod) => {
            newMap[mod.key] = {
                can_read: grant,
                can_write: grant && mod.available_actions.includes('write'),
                can_update: grant && mod.available_actions.includes('update'),
                can_delete: grant && mod.available_actions.includes('delete'),
            };
        });
        permissionsMap = newMap;
    }

    function toggleModuleRow(key: string) {
        const current = permissionsMap[key];
        const allEnabled = current?.can_read && current?.can_write && current?.can_update && current?.can_delete;
        const mod = modules.find((m) => m.key === key);

        permissionsMap[key] = {
            can_read: !allEnabled,
            can_write: !allEnabled && (mod?.available_actions.includes('write') ?? true),
            can_update: !allEnabled && (mod?.available_actions.includes('update') ?? true),
            can_delete: !allEnabled && (mod?.available_actions.includes('delete') ?? true),
        };
    }

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        isSaving = true;
        errors = {};

        const payload: Record<string, any> = {
            name,
            slug: slug || undefined,
            description: description || undefined,
            all_routers: allRouters,
            router_ids: allRouters ? [] : selectedRouterIds,
            permissions: permissionsMap,
        };

        try {
            if (groupToEdit) {
                const res = await apiClient.put<ApiResponse<Group>>(`/groups/${groupToEdit.id}`, payload);
                toast.success(res.data.message || localeStore.t('users.groupUpdated'));
            } else {
                const res = await apiClient.post<ApiResponse<Group>>('/groups', payload);
                toast.success(res.data.message || localeStore.t('users.groupCreated'));
            }
            onSaved();
            onClose();
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                errors = err.response.data.errors;
            } else {
                toast.error(err.response?.data?.message || 'Failed to save access group.');
            }
        } finally {
            isSaving = false;
        }
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <button
            type="button"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity cursor-default"
            onclick={onClose}
            aria-label="Close modal"
        ></button>

        <!-- Modal Dialog -->
        <div class="relative w-full max-w-2xl rounded-2xl border border-border bg-card p-6 shadow-2xl z-10 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-border shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-sky-500/15 text-sky-500 flex items-center justify-center">
                        <Shield class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            {groupToEdit ? localeStore.t('users.editGroup') : localeStore.t('users.newGroup')}
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            Configure group permissions, module capabilities, and MikroTik router scoping
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="size-7 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
                >
                    <X class="size-4" />
                </button>
            </div>

            <!-- Form Content -->
            <form onsubmit={handleSubmit} class="space-y-4 overflow-y-auto pr-1 flex-1">
                {#if groupToEdit?.is_superadmin}
                    <div class="p-3 rounded-xl bg-sky-500/10 border border-sky-500/30 flex items-start gap-2.5 text-xs text-sky-500">
                        <Sparkles class="size-4 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Super Administrator Group</span>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{localeStore.t('users.superadminNotice')}</p>
                        </div>
                    </div>
                {/if}

                <!-- Group Name & Slug -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label for="group-name" class="text-xs font-bold text-foreground">
                            <span>{localeStore.t('users.name')} <span class="text-destructive">*</span></span>
                        </label>
                        <input
                            id="group-name"
                            type="text"
                            bind:value={name}
                            placeholder="e.g. Hotspot Cashier"
                            required
                            class="w-full px-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                        />
                        {#if errors.name}
                            <p class="text-[11px] text-destructive font-medium">{errors.name[0]}</p>
                        {/if}
                    </div>

                    <div class="space-y-1.5">
                        <label for="group-slug" class="text-xs font-bold text-foreground">
                            <span>Slug / Key</span>
                        </label>
                        <input
                            id="group-slug"
                            type="text"
                            bind:value={slug}
                            placeholder="auto-generated from name"
                            disabled={groupToEdit?.is_superadmin}
                            class="w-full px-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40 disabled:opacity-50 font-mono"
                        />
                        {#if errors.slug}
                            <p class="text-[11px] text-destructive font-medium">{errors.slug[0]}</p>
                        {/if}
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-1.5">
                    <label for="group-desc" class="text-xs font-bold text-foreground">
                        <span>Description</span>
                    </label>
                    <input
                        id="group-desc"
                        type="text"
                        bind:value={description}
                        placeholder="Purpose of this group role..."
                        class="w-full px-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                    />
                </div>

                <!-- MikroTik Router Scoping -->
                <div class="p-3.5 rounded-xl border border-border bg-card/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Server class="size-3.5 text-sky-500" />
                                <span>{localeStore.t('users.routerScope')}</span>
                            </span>
                            <p class="text-[11px] text-muted-foreground">{localeStore.t('users.selectRoutersDesc')}</p>
                        </div>

                        {#if !groupToEdit?.is_superadmin}
                            <div class="flex items-center gap-1 p-0.5 rounded-lg border border-border bg-muted/60 text-xs">
                                <button
                                    type="button"
                                    onclick={() => (allRouters = true)}
                                    class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all cursor-pointer {allRouters ? 'bg-background text-foreground shadow-2xs font-bold' : 'text-muted-foreground'}"
                                >
                                    All Routers
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (allRouters = false)}
                                    class="px-2.5 py-1 rounded-md text-[11px] font-semibold transition-all cursor-pointer {!allRouters ? 'bg-background text-foreground shadow-2xs font-bold' : 'text-muted-foreground'}"
                                >
                                    Specific Routers
                                </button>
                            </div>
                        {:else}
                            <span class="text-[10px] font-mono font-bold text-sky-500 uppercase">Unrestricted</span>
                        {/if}
                    </div>

                    {#if !allRouters && !groupToEdit?.is_superadmin}
                        <div class="pt-2 border-t border-border grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto">
                            {#each routers as router (router.id)}
                                {@const isChecked = selectedRouterIds.includes(router.id)}
                                <button
                                    type="button"
                                    onclick={() => toggleRouter(router.id)}
                                    class="flex items-center justify-between p-2 rounded-lg border text-xs transition-colors cursor-pointer text-left {isChecked ? 'border-sky-500/50 bg-sky-500/10 text-foreground font-semibold' : 'border-border bg-card/40 text-muted-foreground hover:bg-muted'}"
                                >
                                    <div class="truncate pr-2">
                                        <div class="font-bold text-xs truncate">{router.name}</div>
                                        <div class="text-[10px] text-muted-foreground font-mono">{router.host}:{router.api_port}</div>
                                    </div>
                                    <div class="size-4 rounded border flex items-center justify-center shrink-0 {isChecked ? 'border-sky-500 bg-sky-500 text-white' : 'border-border'}">
                                        {#if isChecked}
                                            <Check class="size-3" />
                                        {/if}
                                    </div>
                                </button>
                            {/each}
                        </div>
                    {/if}
                </div>

                <!-- Module Permissions Matrix -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Layers class="size-3.5 text-indigo-500" />
                                <span>{localeStore.t('users.modulePermissions')}</span>
                            </span>
                            <p class="text-[11px] text-muted-foreground">Grant granular read, write, update, and delete access</p>
                        </div>

                        {#if !groupToEdit?.is_superadmin}
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick={() => toggleAllPermissions(true)}
                                    class="text-[11px] font-bold text-sky-500 hover:text-sky-400 cursor-pointer"
                                >
                                    {localeStore.t('users.grantAll')}
                                </button>
                                <span class="text-muted-foreground">•</span>
                                <button
                                    type="button"
                                    onclick={() => toggleAllPermissions(false)}
                                    class="text-[11px] font-bold text-muted-foreground hover:text-foreground cursor-pointer"
                                >
                                    {localeStore.t('users.revokeAll')}
                                </button>
                            </div>
                        {/if}
                    </div>

                    <!-- Permissions Table -->
                    <div class="rounded-xl border border-border bg-card overflow-hidden">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-muted/40 border-b border-border font-bold text-muted-foreground text-[10px] uppercase tracking-wider">
                                <tr>
                                    <th class="py-2 px-3">{localeStore.t('users.module')}</th>
                                    <th class="py-2 px-2 text-center w-16">{localeStore.t('users.read')}</th>
                                    <th class="py-2 px-2 text-center w-16">{localeStore.t('users.write')}</th>
                                    <th class="py-2 px-2 text-center w-16">{localeStore.t('users.update')}</th>
                                    <th class="py-2 px-2 text-center w-16">{localeStore.t('users.delete')}</th>
                                    <th class="py-2 px-2 text-center w-16">All</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                {#each modules as mod (mod.key)}
                                    {@const perm = permissionsMap[mod.key] || { can_read: false, can_write: false, can_update: false, can_delete: false }}
                                    {@const hasWrite = mod.available_actions.includes('write')}
                                    {@const hasUpdate = mod.available_actions.includes('update')}
                                    {@const hasDelete = mod.available_actions.includes('delete')}
                                    {@const isSuper = groupToEdit?.is_superadmin}

                                    <tr class="hover:bg-muted/30 transition-colors">
                                        <td class="py-2 px-3">
                                            <div class="font-bold text-foreground text-xs">{mod.name}</div>
                                            {#if mod.description}
                                                <div class="text-[10px] text-muted-foreground line-clamp-1">{mod.description}</div>
                                            {/if}
                                        </td>
                                        <!-- Read -->
                                        <td class="py-2 px-2 text-center">
                                            <input
                                                type="checkbox"
                                                checked={isSuper || perm.can_read}
                                                disabled={isSuper}
                                                onchange={(e) => {
                                                    if (!permissionsMap[mod.key]) permissionsMap[mod.key] = { can_read: false, can_write: false, can_update: false, can_delete: false };
                                                    permissionsMap[mod.key].can_read = (e.target as HTMLInputElement).checked;
                                                }}
                                                class="rounded border-border text-sky-600 focus:ring-sky-500 size-4 cursor-pointer disabled:opacity-50"
                                            />
                                        </td>
                                        <!-- Write -->
                                        <td class="py-2 px-2 text-center">
                                            {#if hasWrite}
                                                <input
                                                    type="checkbox"
                                                    checked={isSuper || perm.can_write}
                                                    disabled={isSuper}
                                                    onchange={(e) => {
                                                        if (!permissionsMap[mod.key]) permissionsMap[mod.key] = { can_read: false, can_write: false, can_update: false, can_delete: false };
                                                        permissionsMap[mod.key].can_write = (e.target as HTMLInputElement).checked;
                                                    }}
                                                    class="rounded border-border text-sky-600 focus:ring-sky-500 size-4 cursor-pointer disabled:opacity-50"
                                                />
                                            {:else}
                                                <span class="text-muted-foreground/40 text-[10px]">-</span>
                                            {/if}
                                        </td>
                                        <!-- Update -->
                                        <td class="py-2 px-2 text-center">
                                            {#if hasUpdate}
                                                <input
                                                    type="checkbox"
                                                    checked={isSuper || perm.can_update}
                                                    disabled={isSuper}
                                                    onchange={(e) => {
                                                        if (!permissionsMap[mod.key]) permissionsMap[mod.key] = { can_read: false, can_write: false, can_update: false, can_delete: false };
                                                        permissionsMap[mod.key].can_update = (e.target as HTMLInputElement).checked;
                                                    }}
                                                    class="rounded border-border text-sky-600 focus:ring-sky-500 size-4 cursor-pointer disabled:opacity-50"
                                                />
                                            {:else}
                                                <span class="text-muted-foreground/40 text-[10px]">-</span>
                                            {/if}
                                        </td>
                                        <!-- Delete -->
                                        <td class="py-2 px-2 text-center">
                                            {#if hasDelete}
                                                <input
                                                    type="checkbox"
                                                    checked={isSuper || perm.can_delete}
                                                    disabled={isSuper}
                                                    onchange={(e) => {
                                                        if (!permissionsMap[mod.key]) permissionsMap[mod.key] = { can_read: false, can_write: false, can_update: false, can_delete: false };
                                                        permissionsMap[mod.key].can_delete = (e.target as HTMLInputElement).checked;
                                                    }}
                                                    class="rounded border-border text-sky-600 focus:ring-sky-500 size-4 cursor-pointer disabled:opacity-50"
                                                />
                                            {:else}
                                                <span class="text-muted-foreground/40 text-[10px]">-</span>
                                            {/if}
                                        </td>
                                        <!-- Row Toggle -->
                                        <td class="py-2 px-2 text-center">
                                            {#if !isSuper}
                                                <button
                                                    type="button"
                                                    onclick={() => toggleModuleRow(mod.key)}
                                                    class="text-[10px] text-muted-foreground hover:text-sky-500 font-bold cursor-pointer"
                                                    title="Toggle full access for this module"
                                                >
                                                    Toggle
                                                </button>
                                            {:else}
                                                <Check class="size-3.5 text-sky-500 mx-auto" />
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Buttons -->
                <div class="pt-3 border-t border-border flex items-center justify-end gap-2.5">
                    <button
                        type="button"
                        onclick={onClose}
                        class="px-4 py-2 rounded-xl border border-border bg-card hover:bg-muted text-xs font-semibold text-foreground transition-colors cursor-pointer"
                    >
                        {localeStore.t('common.cancel')}
                    </button>
                    <button
                        type="submit"
                        disabled={isSaving}
                        class="px-5 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center gap-1.5 transition-all cursor-pointer disabled:opacity-50"
                    >
                        {#if isSaving}
                            <RefreshCw class="size-3.5 animate-spin" />
                            <span>{localeStore.t('common.loading')}</span>
                        {:else}
                            <Save class="size-3.5" />
                            <span>{localeStore.t('common.save')}</span>
                        {/if}
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
