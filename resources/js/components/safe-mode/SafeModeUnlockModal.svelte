<script lang="ts">
    import { safeModeStore } from '@/stores/safeMode.svelte';
    import { routerContextStore } from '@/stores/routerContext.svelte';
    import { ShieldAlert, AlertTriangle, X, Check, Loader2, KeyRound } from '@lucide/svelte';

    let {
        isOpen = false,
        onClose,
        onUnlocked,
    }: {
        isOpen?: boolean;
        onClose?: () => void;
        onUnlocked?: () => void;
    } = $props();

    let password = $state('');
    let verificationText = $state('');
    let reason = $state('');
    let error = $state<string | null>(null);

    function handleClose() {
        password = '';
        verificationText = '';
        reason = '';
        error = null;
        onClose?.();
    }

    async function handleUnlock() {
        error = null;

        if (!password) {
            error = 'Please enter your administrator account password.';
            return;
        }

        if (verificationText.trim().toUpperCase() !== 'UNLOCK') {
            error = 'Please type UNLOCK in the verification field to confirm.';
            return;
        }

        if (!routerContextStore.activeRouter) {
            error = 'No active router selected.';
            return;
        }

        try {
            await safeModeStore.unlock(routerContextStore.activeRouter.id, password, reason || 'Manual administrative unlock');
            handleClose();
            onUnlocked?.();
        } catch (err: any) {
            error = err.message || 'Unlock sequence failed.';
        }
    }
</script>

{#if isOpen}
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150">
        <div
            class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
            role="dialog"
            aria-modal="true"
        >
            <!-- Close Button -->
            <button
                type="button"
                onclick={handleClose}
                class="absolute top-4 right-4 text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-4">
                <div class="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-500 flex items-center justify-center shrink-0">
                    <ShieldAlert class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">
                        Two-Step Safe Mode Unlock
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Target Router: <span class="font-semibold text-foreground">{routerContextStore.activeRouter?.name}</span>
                    </p>
                </div>
            </div>

            <!-- Caution Warning Box -->
            <div class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-600 dark:text-amber-400 flex items-start space-x-3">
                <AlertTriangle class="w-4 h-4 shrink-0 mt-0.5" />
                <div class="leading-relaxed">
                    <strong>Guardrail Override:</strong> Disabling Safe Mode permits destructive commands such as router reboots, configuration resets, and bulk voucher deletions. Safe Mode will automatically re-lock after <strong>15 minutes</strong>.
                </div>
            </div>

            <!-- Error Banner -->
            {#if error}
                <div class="mb-4 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                    {error}
                </div>
            {/if}

            <!-- Form -->
            <form onsubmit={(e) => { e.preventDefault(); handleUnlock(); }} class="space-y-4">
                <!-- Step 1: Admin Password -->
                <div>
                    <label for="admin_password" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Step 1: Admin Password
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <KeyRound class="h-4 w-4" />
                        </div>
                        <input
                            id="admin_password"
                            bind:value={password}
                            type="password"
                            required
                            placeholder="Enter your login password"
                            class="block w-full rounded-lg border border-border bg-background py-2 pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                        />
                    </div>
                </div>

                <!-- Step 2: Verification Word -->
                <div>
                    <label for="unlock_verification" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Step 2: Type <span class="text-amber-500 font-mono">UNLOCK</span> to confirm
                    </label>
                    <input
                        id="unlock_verification"
                        bind:value={verificationText}
                        type="text"
                        required
                        placeholder="Type UNLOCK here"
                        class="block w-full font-mono rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 uppercase"
                    />
                </div>

                <!-- Reason (Optional) -->
                <div>
                    <label for="audit_reason" class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Audit Reason (Optional)
                    </label>
                    <input
                        id="audit_reason"
                        bind:value={reason}
                        type="text"
                        placeholder="e.g. Firmware maintenance / voucher bulk cleanup"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                    />
                </div>

                <!-- Action Buttons -->
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
                        disabled={safeModeStore.isLoading}
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        {#if safeModeStore.isLoading}
                            <Loader2 class="w-3.5 h-3.5 animate-spin" />
                        {:else}
                            <Check class="w-3.5 h-3.5" />
                        {/if}
                        <span>Unlock for 15 Minutes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
{/if}
