<script lang="ts">
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { User } from '@/types/auth';
    import type { Group } from '@/types/group';
    import type { ApiResponse } from '@/types/api';
    import { X, UserPlus, Save, Lock, Mail, UserCheck, Shield, RefreshCw } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';

    let {
        isOpen = false,
        userToEdit = null,
        groups = [],
        onClose,
        onSaved,
    }: {
        isOpen: boolean;
        userToEdit: User | null;
        groups: Group[];
        onClose: () => void;
        onSaved: () => void;
    } = $props();

    let name = $state('');
    let email = $state('');
    let password = $state('');
    let groupId = $state<number | null>(null);
    let isActive = $state(true);
    let isSaving = $state(false);
    let errors = $state<Record<string, string[]>>({});

    $effect(() => {
        if (isOpen) {
            errors = {};
            if (userToEdit) {
                name = userToEdit.name;
                email = userToEdit.email;
                password = '';
                groupId = userToEdit.group_id ?? userToEdit.group?.id ?? (groups[0]?.id || null);
                isActive = userToEdit.is_active;
            } else {
                name = '';
                email = '';
                password = '';
                groupId = groups[0]?.id || null;
                isActive = true;
            }
        }
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();
        isSaving = true;
        errors = {};

        const payload: Record<string, any> = {
            name,
            email,
            group_id: groupId,
            is_active: isActive,
        };

        if (password) {
            payload.password = password;
        }

        try {
            if (userToEdit) {
                const res = await apiClient.put<ApiResponse<User>>(`/users/${userToEdit.id}`, payload);
                toast.success(res.data.message || localeStore.t('users.userUpdated'));
            } else {
                if (!password) {
                    errors = { password: ['The password field is required.'] };
                    isSaving = false;
                    return;
                }
                const res = await apiClient.post<ApiResponse<User>>('/users', payload);
                toast.success(res.data.message || localeStore.t('users.userCreated'));
            }
            onSaved();
            onClose();
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                errors = err.response.data.errors;
            } else {
                toast.error(err.response?.data?.message || 'Failed to save user account.');
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
        <div class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl z-10 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="size-8 rounded-lg bg-sky-500/15 text-sky-500 flex items-center justify-center">
                        <UserPlus class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            {userToEdit ? localeStore.t('users.editUser') : localeStore.t('users.newUser')}
                        </h3>
                        <p class="text-[11px] text-muted-foreground">
                            {userToEdit ? 'Modify user credentials and group assignment' : 'Create a new operator or administrator account'}
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

            <!-- Form -->
            <form onsubmit={handleSubmit} class="space-y-4">
                <!-- Name -->
                <div class="space-y-1.5">
                    <label for="user-name" class="text-xs font-bold text-foreground flex items-center justify-between">
                        <span>{localeStore.t('users.name')} <span class="text-destructive">*</span></span>
                    </label>
                    <input
                        id="user-name"
                        type="text"
                        bind:value={name}
                        placeholder="e.g. John Doe"
                        required
                        class="w-full px-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                    />
                    {#if errors.name}
                        <p class="text-[11px] text-destructive font-medium">{errors.name[0]}</p>
                    {/if}
                </div>

                <!-- Email -->
                <div class="space-y-1.5">
                    <label for="user-email" class="text-xs font-bold text-foreground flex items-center justify-between">
                        <span>{localeStore.t('users.email')} <span class="text-destructive">*</span></span>
                    </label>
                    <div class="relative">
                        <Mail class="size-3.5 text-muted-foreground absolute left-3 top-2.5" />
                        <input
                            id="user-email"
                            type="email"
                            bind:value={email}
                            placeholder="operator@company.com"
                            required
                            class="w-full pl-9 pr-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                        />
                    </div>
                    {#if errors.email}
                        <p class="text-[11px] text-destructive font-medium">{errors.email[0]}</p>
                    {/if}
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label for="user-password" class="text-xs font-bold text-foreground flex items-center justify-between">
                        <span>
                            {localeStore.t('users.password')}
                            {#if !userToEdit}
                                <span class="text-destructive">*</span>
                            {/if}
                        </span>
                        {#if userToEdit}
                            <span class="text-[10px] text-muted-foreground font-normal">{localeStore.t('users.passwordHint')}</span>
                        {/if}
                    </label>
                    <div class="relative">
                        <Lock class="size-3.5 text-muted-foreground absolute left-3 top-2.5" />
                        <input
                            id="user-password"
                            type="password"
                            bind:value={password}
                            placeholder={userToEdit ? '••••••••' : 'Minimum 8 characters'}
                            required={!userToEdit}
                            minlength="8"
                            class="w-full pl-9 pr-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40"
                        />
                    </div>
                    {#if errors.password}
                        <p class="text-[11px] text-destructive font-medium">{errors.password[0]}</p>
                    {/if}
                </div>

                <!-- Group Selection -->
                <div class="space-y-1.5">
                    <label for="user-group" class="text-xs font-bold text-foreground flex items-center justify-between">
                        <span>{localeStore.t('users.group')} <span class="text-destructive">*</span></span>
                    </label>
                    <div class="relative">
                        <Shield class="size-3.5 text-muted-foreground absolute left-3 top-2.5" />
                        <select
                            id="user-group"
                            bind:value={groupId}
                            required
                            class="w-full pl-9 pr-3 py-2 rounded-xl border border-border bg-background text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500/40 cursor-pointer"
                        >
                            {#each groups as group (group.id)}
                                <option value={group.id}>
                                    {group.name} ({group.all_routers ? 'All Routers' : `${group.router_ids?.length || 0} Router(s)`})
                                </option>
                            {/each}
                        </select>
                    </div>
                    {#if errors.group_id}
                        <p class="text-[11px] text-destructive font-medium">{errors.group_id[0]}</p>
                    {/if}
                </div>

                <!-- Active Status Toggle -->
                <div class="pt-2 flex items-center justify-between p-3 rounded-xl border border-border bg-card/60">
                    <div>
                        <span class="text-xs font-bold text-foreground">{localeStore.t('users.status')}</span>
                        <p class="text-[11px] text-muted-foreground">Inactive users cannot log into TikPulse</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" bind:checked={isActive} class="sr-only peer" />
                        <div class="w-9 h-5 bg-muted peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>

                <!-- Buttons -->
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
