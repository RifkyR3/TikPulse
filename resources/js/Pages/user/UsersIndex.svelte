<script lang="ts">
    import { onMount } from 'svelte';
    import AdminLayout from '@/components/layout/AdminLayout.svelte';
    import DataTable, { type Column } from '@/components/common/DataTable.svelte';
    import UserModal from '@/components/user/UserModal.svelte';
    import GroupModal from '@/components/user/GroupModal.svelte';
    import ResetPasswordModal from '@/components/user/ResetPasswordModal.svelte';
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { User } from '@/types/auth';
    import type { Group, ModuleItem } from '@/types/group';
    import type { ApiResponse } from '@/types/api';
    import { toast } from 'svelte-sonner';
    import {
        Users,
        Shield,
        UserPlus,
        Plus,
        Search,
        Edit3,
        Trash2,
        Sparkles,
        Server,
        CheckCircle2,
        XCircle,
        RefreshCw,
        ArrowRight,
        Layers,
        KeyRound,
    } from '@lucide/svelte';

    // State
    let activeTab = $state<'users' | 'groups'>('users');
    let users = $state<User[]>([]);
    let groups = $state<Group[]>([]);
    let modules = $state<ModuleItem[]>([]);

    let isLoadingUsers = $state(false);
    let isLoadingGroups = $state(false);
    let isDeletingUser = $state<number | null>(null);
    let isDeletingGroup = $state<number | null>(null);

    // Filters
    let userSearch = $state('');
    let selectedGroupFilter = $state('');
    let selectedStatusFilter = $state('');

    // Modals
    let isUserModalOpen = $state(false);
    let userToEdit = $state<User | null>(null);

    let isResetPasswordModalOpen = $state(false);
    let userToResetPassword = $state<User | null>(null);

    let isGroupModalOpen = $state(false);
    let groupToEdit = $state<Group | null>(null);

    // Table Columns for Users
    const userColumns: Column<User>[] = [
        { id: 'user', header: 'User Account' },
        { id: 'group', header: 'Group & Role' },
        { id: 'router_access', header: 'MikroTik Access' },
        { id: 'status', header: 'Status' },
        { id: 'created_at', header: 'Joined' },
        { id: 'actions', header: 'Actions', align: 'right' },
    ];

    // Filtered Users
    const filteredUsers = $derived.by(() => {
        let result = users;
        if (userSearch.trim()) {
            const q = userSearch.toLowerCase().trim();
            result = result.filter(
                (u) =>
                    u.name.toLowerCase().includes(q) ||
                    u.email.toLowerCase().includes(q) ||
                    u.role.toLowerCase().includes(q)
            );
        }
        if (selectedGroupFilter) {
            result = result.filter(
                (u) => String(u.group_id || u.group?.id) === selectedGroupFilter
            );
        }
        if (selectedStatusFilter !== '') {
            const isActive = selectedStatusFilter === 'active';
            result = result.filter((u) => u.is_active === isActive);
        }
        return result;
    });

    onMount(() => {
        fetchUsers();
        fetchGroups();
        fetchModules();
        routerContextStore.fetchRouters();
    });

    async function fetchUsers() {
        isLoadingUsers = true;
        try {
            const res = await apiClient.get<ApiResponse<User[]>>('/users?per_page=100');
            if (res.data.status === 'success') {
                users = res.data.data;
            }
        } catch {
            toast.error('Failed to load users.');
        } finally {
            isLoadingUsers = false;
        }
    }

    async function fetchGroups() {
        isLoadingGroups = true;
        try {
            const res = await apiClient.get<ApiResponse<Group[]>>('/groups');
            if (res.data.status === 'success') {
                groups = res.data.data;
            }
        } catch {
            toast.error('Failed to load groups.');
        } finally {
            isLoadingGroups = false;
        }
    }

    async function fetchModules() {
        try {
            const res = await apiClient.get<ApiResponse<ModuleItem[]>>('/modules');
            if (res.data.status === 'success') {
                modules = res.data.data;
            }
        } catch {
            // Ignore
        }
    }

    async function handleDeleteUser(user: User) {
        if (!confirm(localeStore.t('users.confirmDeleteUser', { name: user.name }))) {
            return;
        }

        isDeletingUser = user.id;
        try {
            const res = await apiClient.delete<ApiResponse<null>>(`/users/${user.id}`);
            toast.success(res.data.message || localeStore.t('users.userDeleted'));
            await fetchUsers();
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete user.');
        } finally {
            isDeletingUser = null;
        }
    }

    async function handleDeleteGroup(group: Group) {
        if (!confirm(localeStore.t('users.confirmDeleteGroup', { name: group.name }))) {
            return;
        }

        isDeletingGroup = group.id;
        try {
            const res = await apiClient.delete<ApiResponse<null>>(`/groups/${group.id}`);
            toast.success(res.data.message || localeStore.t('users.groupDeleted'));
            await fetchGroups();
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to delete group.');
        } finally {
            isDeletingGroup = null;
        }
    }

    async function handleSimulateGroup(group: Group) {
        try {
            const success = await authStore.switchRole(group.is_superadmin ? null : group.id);
            if (success) {
                toast.success(
                    group.is_superadmin
                        ? 'Reverted to Super Administrator.'
                        : `Now simulating role: ${group.name}`
                );
            }
        } catch {
            toast.error('Failed to switch role.');
        }
    }

    function getInitials(name?: string) {
        if (!name) return 'U';
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }
</script>

<!-- Custom Cell Snippets for Users Table -->
{#snippet userCell(row: User)}
    <div class="flex items-center gap-3">
        <div class="size-8 rounded-lg bg-gradient-to-tr from-sky-600 to-indigo-600 text-white font-bold text-xs flex items-center justify-center shrink-0 shadow-xs">
            {getInitials(row.name)}
        </div>
        <div class="min-w-0">
            <div class="font-bold text-foreground text-xs truncate flex items-center gap-1.5">
                <span>{row.name}</span>
                {#if row.id === authStore.user?.id}
                    <span class="text-[9px] px-1 py-0.2 rounded bg-sky-500/10 text-sky-500 font-mono font-bold">You</span>
                {/if}
            </div>
            <div class="text-[11px] text-muted-foreground font-mono truncate">{row.email}</div>
        </div>
    </div>
{/snippet}

{#snippet groupCell(row: User)}
    <div class="flex items-center gap-2">
        {#if row.group?.is_superadmin || row.role === 'superadmin'}
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-sky-500/15 text-sky-500 border border-sky-500/30">
                Superadmin
            </span>
        {:else if row.group}
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-foreground border border-border">
                {row.group.name}
            </span>
        {:else}
            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono text-muted-foreground uppercase">
                {row.role}
            </span>
        {/if}
    </div>
{/snippet}

{#snippet routerAccessCell(row: User)}
    {#if row.group?.is_superadmin || row.role === 'superadmin' || row.group?.all_routers}
        <span class="inline-flex items-center gap-1 text-xs text-foreground font-medium">
            <Server class="size-3 text-emerald-500" />
            <span>All Routers</span>
        </span>
    {:else}
        {@const count = row.allowed_router_ids?.length ?? 0}
        <span class="inline-flex items-center gap-1 text-xs text-muted-foreground font-medium">
            <Server class="size-3 text-sky-500" />
            <span>{count} Router(s)</span>
        </span>
    {/if}
{/snippet}

{#snippet statusCell(row: User)}
    {#if row.is_active}
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            <CheckCircle2 class="size-3" />
            <span>{localeStore.t('users.active')}</span>
        </span>
    {:else}
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-muted text-muted-foreground">
            <XCircle class="size-3" />
            <span>{localeStore.t('users.inactive')}</span>
        </span>
    {/if}
{/snippet}

{#snippet createdAtCell(row: User)}
    <span class="text-xs text-muted-foreground font-mono">
        {row.created_at ? new Date(row.created_at).toLocaleDateString() : '-'}
    </span>
{/snippet}

{#snippet actionsCell(row: User)}
    <div class="flex items-center justify-end gap-1.5">
        {#if authStore.can('users', 'update')}
            <button
                type="button"
                onclick={() => {
                    userToResetPassword = row;
                    isResetPasswordModalOpen = true;
                }}
                class="p-1.5 rounded-lg border border-border hover:bg-sky-500/10 text-muted-foreground hover:text-sky-500 transition-colors cursor-pointer"
                title={localeStore.locale === 'id' ? 'Reset Kata Sandi' : 'Reset Password'}
            >
                <KeyRound class="size-3.5" />
            </button>
            <button
                type="button"
                onclick={() => {
                    userToEdit = row;
                    isUserModalOpen = true;
                }}
                class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                title={localeStore.t('common.edit')}
            >
                <Edit3 class="size-3.5" />
            </button>
        {/if}

        {#if authStore.can('users', 'delete') && row.id !== authStore.user?.id}
            <button
                type="button"
                onclick={() => handleDeleteUser(row)}
                disabled={isDeletingUser === row.id}
                class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                title={localeStore.t('common.delete')}
            >
                <Trash2 class="size-3.5 {isDeletingUser === row.id ? 'animate-pulse' : ''}" />
            </button>
        {/if}
    </div>
{/snippet}

<AdminLayout title="User & Access Management">
    <div class="space-y-6">
        <!-- Top Title & Global Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center space-x-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20">
                        Enterprise RBAC
                    </span>
                    <span class="text-xs text-muted-foreground font-mono">
                        Multi-Router Access Control
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-foreground mt-1">
                    {localeStore.t('users.title')}
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {localeStore.t('users.subtitle')}
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                {#if activeTab === 'users' && authStore.can('users', 'write')}
                    <button
                        type="button"
                        onclick={() => {
                            userToEdit = null;
                            isUserModalOpen = true;
                        }}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-all cursor-pointer"
                    >
                        <UserPlus class="size-3.5 mr-1" />
                        <span>{localeStore.t('users.newUser')}</span>
                    </button>
                {:else if activeTab === 'groups' && authStore.can('groups', 'write')}
                    <button
                        type="button"
                        onclick={() => {
                            groupToEdit = null;
                            isGroupModalOpen = true;
                        }}
                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-all cursor-pointer"
                    >
                        <Plus class="size-3.5 mr-1" />
                        <span>{localeStore.t('users.newGroup')}</span>
                    </button>
                {/if}
            </div>
        </div>

        <!-- Tabs Navigation -->
        <div class="border-b border-border flex items-center space-x-1">
            <button
                type="button"
                onclick={() => (activeTab = 'users')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'users'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Users class="size-4 mr-1.5" />
                <span>{localeStore.t('users.usersTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {users.length}
                </span>
            </button>

            <button
                type="button"
                onclick={() => (activeTab = 'groups')}
                class="px-4 py-2.5 text-xs font-bold border-b-2 transition-all cursor-pointer flex items-center space-x-2 {activeTab === 'groups'
                    ? 'border-sky-500 text-sky-600 dark:text-sky-400'
                    : 'border-transparent text-muted-foreground hover:text-foreground'}"
            >
                <Shield class="size-4 mr-1.5" />
                <span>{localeStore.t('users.groupsTab')}</span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono ml-1">
                    {groups.length}
                </span>
            </button>
        </div>

        <!-- TAB 1: USERS ACCOUNTS -->
        {#if activeTab === 'users'}
            <div class="space-y-4">
                <!-- Filters Toolbar -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 p-3.5 rounded-xl border border-border bg-card/60 shadow-2xs">
                    <div class="relative flex-1 max-w-sm">
                        <Search class="size-3.5 text-muted-foreground absolute left-3 top-2.5" />
                        <input
                            type="text"
                            bind:value={userSearch}
                            placeholder="Search by name, email, or role..."
                            class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Group Filter -->
                        <select
                            bind:value={selectedGroupFilter}
                            class="px-2.5 py-1.5 rounded-lg border border-border bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40 cursor-pointer"
                        >
                            <option value="">All Groups</option>
                            {#each groups as g (g.id)}
                                <option value={String(g.id)}>{g.name}</option>
                            {/each}
                        </select>

                        <!-- Status Filter -->
                        <select
                            bind:value={selectedStatusFilter}
                            class="px-2.5 py-1.5 rounded-lg border border-border bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40 cursor-pointer"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>

                        <button
                            type="button"
                            onclick={fetchUsers}
                            class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                            title="Refresh users"
                        >
                            <RefreshCw class="size-3.5 {isLoadingUsers ? 'animate-spin' : ''}" />
                        </button>
                    </div>
                </div>

                <!-- Users DataTable -->
                <DataTable
                    columns={userColumns}
                    data={filteredUsers}
                    isLoading={isLoadingUsers}
                    emptyMessage="No users found."
                    pageSize={15}
                    customCells={{
                        user: userCell,
                        group: groupCell,
                        router_access: routerAccessCell,
                        status: statusCell,
                        created_at: createdAtCell,
                        actions: actionsCell,
                    }}
                />
            </div>
        {/if}

        <!-- TAB 2: GROUPS & PERMISSIONS -->
        {#if activeTab === 'groups'}
            <div class="space-y-4">
                {#if isLoadingGroups}
                    <div class="py-12 text-center text-xs text-muted-foreground flex items-center justify-center gap-2">
                        <RefreshCw class="size-4 animate-spin" />
                        <span>Loading access groups...</span>
                    </div>
                {:else}
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {#each groups as group (group.id)}
                            {@const isSimulatingThis = authStore.user?.simulated_group_id === group.id}
                            <div class="p-5 rounded-2xl border transition-all space-y-4 flex flex-col justify-between {group.is_superadmin ? 'border-sky-500/40 bg-sky-500/5 shadow-md shadow-sky-500/5' : isSimulatingThis ? 'border-amber-500/50 bg-amber-500/5' : 'border-border bg-card/70 hover:shadow-md hover:border-border/80'}">
                                <div class="space-y-3">
                                    <!-- Group Header -->
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-bold text-sm text-foreground">{group.name}</h3>
                                                {#if group.is_superadmin}
                                                    <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded-md bg-sky-500/15 text-sky-500 font-bold border border-sky-500/30">
                                                        Superadmin
                                                    </span>
                                                {:else}
                                                    <span class="text-[9px] uppercase font-mono px-1.5 py-0.5 rounded-md bg-muted text-muted-foreground font-semibold">
                                                        {group.slug}
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if group.description}
                                                <p class="text-xs text-muted-foreground mt-1 line-clamp-2">{group.description}</p>
                                            {/if}
                                        </div>

                                        <div class="size-8 rounded-xl bg-card border border-border flex items-center justify-center text-muted-foreground shrink-0 shadow-2xs">
                                            <Shield class="size-4 {group.is_superadmin ? 'text-sky-500' : 'text-indigo-500'}" />
                                        </div>
                                    </div>

                                    <!-- Scoping Info -->
                                    <div class="pt-2 border-t border-border/60 space-y-2 text-xs">
                                        <!-- Router Scope -->
                                        <div class="flex items-center justify-between text-muted-foreground">
                                            <span class="flex items-center gap-1.5">
                                                <Server class="size-3.5 text-sky-500" />
                                                <span>MikroTik Scope:</span>
                                            </span>
                                            <span class="font-semibold text-foreground font-mono">
                                                {group.all_routers || group.is_superadmin ? 'All Routers' : `${group.router_ids?.length || 0} Specific`}
                                            </span>
                                        </div>

                                        <!-- Module Permissions Count -->
                                        <div class="flex items-center justify-between text-muted-foreground">
                                            <span class="flex items-center gap-1.5">
                                                <Layers class="size-3.5 text-indigo-500" />
                                                <span>Modules Granted:</span>
                                            </span>
                                            <span class="font-semibold text-foreground font-mono">
                                                {group.is_superadmin ? 'All Modules (Full)' : `${group.permissions?.filter(p => p.can_read).length || 0} of ${modules.length}`}
                                            </span>
                                        </div>

                                        <!-- Members Count -->
                                        <div class="flex items-center justify-between text-muted-foreground">
                                            <span class="flex items-center gap-1.5">
                                                <Users class="size-3.5 text-emerald-500" />
                                                <span>Assigned Members:</span>
                                            </span>
                                            <span class="font-semibold text-foreground font-mono">
                                                {group.users_count ?? 0} user(s)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Card Actions -->
                                <div class="pt-3 border-t border-border flex items-center justify-between gap-2">
                                    <!-- Superadmin Role Switch Button -->
                                    {#if authStore.isSuperAdmin && !group.is_superadmin}
                                        <button
                                            type="button"
                                            onclick={() => handleSimulateGroup(group)}
                                            class="px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer {isSimulatingThis ? 'bg-amber-500 text-amber-950 font-bold' : 'hover:bg-muted text-muted-foreground hover:text-foreground'}"
                                            title="Simulate role"
                                        >
                                            <Sparkles class="size-3.5" />
                                            <span>{isSimulatingThis ? 'Simulating' : 'Simulate'}</span>
                                        </button>
                                    {:else}
                                        <div></div>
                                    {/if}

                                    <div class="flex items-center gap-1.5">
                                        {#if authStore.can('groups', 'update')}
                                            <button
                                                type="button"
                                                onclick={() => {
                                                    groupToEdit = group;
                                                    isGroupModalOpen = true;
                                                }}
                                                class="p-1.5 rounded-lg border border-border hover:bg-muted text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                                title="Edit Group & Permissions"
                                            >
                                                <Edit3 class="size-3.5" />
                                            </button>
                                        {/if}

                                        {#if authStore.can('groups', 'delete') && !group.is_superadmin}
                                            <button
                                                type="button"
                                                onclick={() => handleDeleteGroup(group)}
                                                disabled={isDeletingGroup === group.id}
                                                class="p-1.5 rounded-lg border border-border hover:bg-destructive/10 text-muted-foreground hover:text-destructive transition-colors cursor-pointer disabled:opacity-50"
                                                title="Delete Group"
                                            >
                                                <Trash2 class="size-3.5 {isDeletingGroup === group.id ? 'animate-pulse' : ''}" />
                                            </button>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        {/if}
    </div>

    <!-- Modals -->
    <UserModal
        isOpen={isUserModalOpen}
        {userToEdit}
        {groups}
        onClose={() => (isUserModalOpen = false)}
        onSaved={fetchUsers}
    />

    <GroupModal
        isOpen={isGroupModalOpen}
        {groupToEdit}
        {modules}
        routers={routerContextStore.routers}
        onClose={() => (isGroupModalOpen = false)}
        onSaved={fetchGroups}
    />

    <ResetPasswordModal
        isOpen={isResetPasswordModalOpen}
        user={userToResetPassword}
        onClose={() => (isResetPasswordModalOpen = false)}
        onSuccess={fetchUsers}
    />
</AdminLayout>
