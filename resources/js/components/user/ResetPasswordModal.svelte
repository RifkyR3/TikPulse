<script lang="ts">
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import type { User } from '@/types/auth';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        X,
        KeyRound,
        Lock,
        Copy,
        Check,
        RefreshCw,
        ShieldAlert,
        Loader2,
        Eye,
        EyeOff,
    } from '@lucide/svelte';

    let {
        isOpen = false,
        user = null,
        onClose,
        onSuccess,
    }: {
        isOpen: boolean;
        user: User | null;
        onClose: () => void;
        onSuccess?: () => void;
    } = $props();

    let mode = $state<'auto' | 'manual'>('auto');
    let customPassword = $state('');
    let customPasswordConfirmation = $state('');
    let showCustomPassword = $state(false);
    let isSubmitting = $state(false);
    let generatedPasswordResult = $state<string | null>(null);
    let isCopied = $state(false);
    let errors = $state<Record<string, string[]>>({});

    $effect(() => {
        if (isOpen) {
            mode = 'auto';
            customPassword = '';
            customPasswordConfirmation = '';
            showCustomPassword = false;
            generatedPasswordResult = null;
            isCopied = false;
            errors = {};
        }
    });

    async function handleResetPassword(e: Event) {
        e.preventDefault();
        if (!user) return;

        isSubmitting = true;
        errors = {};

        try {
            const payload: Record<string, any> = {};
            if (mode === 'manual') {
                payload.password = customPassword;
                payload.password_confirmation = customPasswordConfirmation;
            }

            const res = await apiClient.post<
                ApiResponse<{ user_id: number; user_name: string; temporary_password: string }>
            >(`/users/${user.id}/reset-password`, payload);

            if (res.data.status === 'success') {
                const tempPass = res.data.data?.temporary_password;
                if (tempPass) {
                    generatedPasswordResult = tempPass;
                }
                toast.success(res.data.message || 'Password successfully reset.');
                onSuccess?.();
                if (mode === 'manual') {
                    onClose();
                }
            }
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                errors = err.response.data.errors;
            } else {
                toast.error(err.response?.data?.message || 'Failed to reset user password.');
            }
        } finally {
            isSubmitting = false;
        }
    }

    async function copyToClipboard(text: string) {
        try {
            await navigator.clipboard.writeText(text);
            isCopied = true;
            toast.success(localeStore.locale === 'id' ? 'Kata sandi berhasil disalin!' : 'Password copied to clipboard!');
            setTimeout(() => {
                isCopied = false;
            }, 2500);
        } catch {
            toast.error('Failed to copy to clipboard.');
        }
    }
</script>

{#if isOpen && user}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <button
            type="button"
            class="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity cursor-default"
            onclick={onClose}
            aria-label="Close modal"
        ></button>

        <!-- Modal Dialog -->
        <div class="relative w-full max-w-md rounded-2xl border border-border bg-card p-6 shadow-2xl z-10 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-border">
                <div class="flex items-center gap-2.5">
                    <div class="size-9 rounded-xl bg-sky-500/15 text-sky-500 flex items-center justify-center shadow-xs">
                        <KeyRound class="size-4.5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            {localeStore.locale === 'id' ? 'Reset Kata Sandi Pengguna' : 'Reset User Password'}
                        </h3>
                        <p class="text-[11px] text-muted-foreground font-mono">
                            {user.name} ({user.email})
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="size-7 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
                    aria-label="Close"
                >
                    <X class="size-4" />
                </button>
            </div>

            {#if generatedPasswordResult}
                <!-- Success Result Screen for Auto-generated password -->
                <div class="space-y-4 py-2">
                    <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs space-y-2">
                        <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold">
                            <Check class="size-4" />
                            <span>{localeStore.locale === 'id' ? 'Kata Sandi Berhasil Direset!' : 'Password Successfully Reset!'}</span>
                        </div>
                        <p class="text-muted-foreground text-[11px]">
                            {localeStore.locale === 'id'
                                ? 'Berikan kata sandi baru ini kepada pengguna. Semua sesi login aktif sebelumnya telah dicabut.'
                                : 'Please provide this temporary password to the user. All previous active sessions have been invalidated.'}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <span class="block text-xs font-bold text-foreground">
                            {localeStore.locale === 'id' ? 'Kata Sandi Baru' : 'New Temporary Password'}
                        </span>
                        <div class="flex items-center gap-2">
                            <div class="flex-1 px-3 py-2.5 rounded-xl border border-border bg-muted/40 font-mono text-sm font-bold tracking-wider select-all text-sky-600 dark:text-sky-400">
                                {generatedPasswordResult}
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onclick={() => copyToClipboard(generatedPasswordResult!)}
                                class="h-10 px-3 text-xs gap-1.5 font-bold cursor-pointer"
                            >
                                {#if isCopied}
                                    <Check class="size-3.5 text-emerald-500" />
                                    <span>{localeStore.locale === 'id' ? 'Tersalin' : 'Copied'}</span>
                                {:else}
                                    <Copy class="size-3.5" />
                                    <span>{localeStore.locale === 'id' ? 'Salin' : 'Copy'}</span>
                                {/if}
                            </Button>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-border flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            onclick={onClose}
                            class="text-xs font-bold bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white shadow-md shadow-sky-500/20 cursor-pointer"
                        >
                            {localeStore.locale === 'id' ? 'Selesai & Tutup' : 'Done & Close'}
                        </Button>
                    </div>
                </div>
            {:else}
                <!-- Reset Form -->
                <form onsubmit={handleResetPassword} class="space-y-4">
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-700 dark:text-amber-400 flex items-start gap-2.5">
                        <ShieldAlert class="size-4 shrink-0 mt-0.5" />
                        <p class="text-[11px] leading-relaxed">
                            {localeStore.locale === 'id'
                                ? 'Mereset kata sandi akan langsung mencabut seluruh sesi token aktif pengguna ini.'
                                : 'Resetting this password will immediately invalidate and revoke all active sessions for this user.'}
                        </p>
                    </div>

                    <!-- Mode Toggle -->
                    <div class="grid grid-cols-2 gap-2 p-1 rounded-xl bg-muted/40 border border-border text-xs font-bold">
                        <button
                            type="button"
                            onclick={() => (mode = 'auto')}
                            class="py-1.5 rounded-lg transition-all cursor-pointer {mode === 'auto' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                        >
                            {localeStore.locale === 'id' ? 'Otomatis (Acak)' : 'Auto-Generate'}
                        </button>
                        <button
                            type="button"
                            onclick={() => (mode = 'manual')}
                            class="py-1.5 rounded-lg transition-all cursor-pointer {mode === 'manual' ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                        >
                            {localeStore.locale === 'id' ? 'Manual' : 'Set Manually'}
                        </button>
                    </div>

                    {#if mode === 'auto'}
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            {localeStore.locale === 'id'
                                ? 'Sistem akan membuat kata sandi acak yang aman (12 karakter) dan menampilkannya kepada Anda setelah reset.'
                                : 'The system will generate a secure random 12-character password and display it to you immediately upon confirmation.'}
                        </p>
                    {:else}
                        <div class="space-y-3 pt-1">
                            <div class="space-y-1.5">
                                <label for="reset-pass" class="text-xs font-bold text-foreground flex items-center justify-between">
                                    <span>{localeStore.locale === 'id' ? 'Kata Sandi Baru' : 'New Password'}</span>
                                    <button
                                        type="button"
                                        onclick={() => (showCustomPassword = !showCustomPassword)}
                                        class="text-[11px] text-sky-500 hover:underline flex items-center gap-1 cursor-pointer"
                                    >
                                        {#if showCustomPassword}
                                            <EyeOff class="size-3" />
                                            <span>Hide</span>
                                        {:else}
                                            <Eye class="size-3" />
                                            <span>Show</span>
                                        {/if}
                                    </button>
                                </label>
                                <input
                                    id="reset-pass"
                                    type={showCustomPassword ? 'text' : 'password'}
                                    bind:value={customPassword}
                                    placeholder="••••••••"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono"
                                />
                                {#if errors.password}
                                    <p class="text-[11px] text-destructive">{errors.password[0]}</p>
                                {/if}
                            </div>

                            <div class="space-y-1.5">
                                <label for="reset-pass-confirm" class="text-xs font-bold text-foreground">
                                    {localeStore.locale === 'id' ? 'Konfirmasi Kata Sandi Baru' : 'Confirm New Password'}
                                </label>
                                <input
                                    id="reset-pass-confirm"
                                    type={showCustomPassword ? 'text' : 'password'}
                                    bind:value={customPasswordConfirmation}
                                    placeholder="••••••••"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono"
                                />
                            </div>
                        </div>
                    {/if}

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onclick={onClose}
                            disabled={isSubmitting}
                            class="text-xs font-semibold cursor-pointer"
                        >
                            {localeStore.t('common.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            size="sm"
                            disabled={isSubmitting}
                            class="text-xs font-bold gap-1.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white shadow-md shadow-sky-500/20 cursor-pointer"
                        >
                            {#if isSubmitting}
                                <Loader2 class="size-3.5 animate-spin" />
                                <span>{localeStore.locale === 'id' ? 'Mereset...' : 'Resetting...'}</span>
                            {:else}
                                <RefreshCw class="size-3.5" />
                                <span>{localeStore.locale === 'id' ? 'Konfirmasi Reset' : 'Confirm Reset'}</span>
                            {/if}
                        </Button>
                    </div>
                </form>
            {/if}
        </div>
    </div>
{/if}
