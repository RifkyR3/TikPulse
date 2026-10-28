<script lang="ts">
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import apiClient from '@/services/api';
    import { toast } from 'svelte-sonner';
    import { Mail, Send, ExternalLink, X, Loader2, CheckCircle2, KeyRound } from '@lucide/svelte';

    let isDismissed = $state(false);
    let isSending = $state(false);
    let isVerifying = $state(false);
    let showCodeInput = $state(false);
    let verificationCode = $state('');

    const isVisible = $derived(
        !isDismissed &&
        authStore.user !== null &&
        authStore.user.is_email_verified === false &&
        !authStore.user.email?.endsWith('@customer.tikpulse.local')
    );

    async function handleSendVerification() {
        isSending = true;
        try {
            const res = await apiClient.post('/email/verification-notification');
            toast.success(
                res.data?.message || localeStore.t('verification.codeSentSuccess')
            );
            showCodeInput = true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to send verification email.');
        } finally {
            isSending = false;
        }
    }

    async function handleVerifyCode(e?: Event) {
        if (e) e.preventDefault();
        const code = verificationCode.trim();
        if (code.length !== 6) {
            toast.error(localeStore.t('verification.enterCodePrompt'));
            return;
        }

        isVerifying = true;
        try {
            const res = await apiClient.post('/email/verify-code', { code });
            toast.success(
                res.data?.message || localeStore.t('verification.verifiedSuccess')
            );

            if (authStore.user) {
                authStore.user = {
                    ...authStore.user,
                    is_email_verified: true,
                    email_verified_at: res.data?.data?.user?.email_verified_at || new Date().toISOString(),
                };
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(authStore.user));
                }
            }
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('email-verified'));
            }
            showCodeInput = false;
            verificationCode = '';
        } catch (err: any) {
            toast.error(
                err.response?.data?.message || localeStore.t('verification.codeInvalidOrExpired')
            );
        } finally {
            isVerifying = false;
        }
    }
</script>

{#if isVisible}
    <div class="bg-gradient-to-r from-amber-500/15 via-sky-500/15 to-indigo-500/15 border-b border-amber-500/30 px-4 py-2.5 text-xs text-foreground flex flex-col gap-2.5 shadow-xs transition-all duration-200">
        <!-- Main Banner Row -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="size-7 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <Mail class="size-3.5" />
                </div>
                <div>
                    <span class="font-bold">
                        {localeStore.t('verification.unverifiedNotice')}
                    </span>
                    <span class="text-muted-foreground hidden sm:inline ml-1">
                        {localeStore.t('verification.unverifiedDetail')}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Toggle Send Email Button -->
                <button
                    type="button"
                    onclick={handleSendVerification}
                    disabled={isSending}
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-primary text-primary-foreground font-bold text-xs hover:opacity-90 transition-opacity cursor-pointer disabled:opacity-50 shadow-xs"
                >
                    {#if isSending}
                        <Loader2 class="size-3 animate-spin" />
                        <span>{localeStore.locale === 'id' ? 'Mengirim...' : 'Sending...'}</span>
                    {:else}
                        <Send class="size-3" />
                        <span>{showCodeInput ? localeStore.t('verification.resendCode') : localeStore.t('verification.sendCode')}</span>
                    {/if}
                </button>

                <!-- Toggle Code Input Drawer/Bar -->
                <button
                    type="button"
                    onclick={() => (showCodeInput = !showCodeInput)}
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-card/70 hover:bg-muted text-xs font-semibold text-foreground transition-colors cursor-pointer"
                >
                    <KeyRound class="size-3 text-sky-500" />
                    <span>{showCodeInput ? localeStore.t('verification.hideCodeInput') : localeStore.t('verification.toggleCodeInput')}</span>
                </button>

                <!-- Dev helper to open Mailpit -->
                <a
                    href="http://localhost:8025"
                    target="_blank"
                    rel="noreferrer"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-border bg-card/60 hover:bg-muted text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors"
                    title="Open Mailpit Web UI"
                >
                    <span>Mailpit (8025)</span>
                    <ExternalLink class="size-3" />
                </a>

                <button
                    type="button"
                    onclick={() => (isDismissed = true)}
                    class="p-1 rounded-md text-muted-foreground hover:text-foreground hover:bg-muted/50 cursor-pointer transition-colors"
                    aria-label="Dismiss banner"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Verification Code Input Row (Toggled) -->
        {#if showCodeInput}
            <form
                onsubmit={handleVerifyCode}
                class="pt-2 border-t border-amber-500/20 flex flex-wrap items-center justify-between gap-2.5 animate-in fade-in slide-in-from-top-1 duration-200"
            >
                <div class="flex items-center gap-2 text-xs">
                    <span class="font-semibold text-muted-foreground">
                        {localeStore.t('verification.enterCode')}:
                    </span>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        bind:value={verificationCode}
                        placeholder={localeStore.t('verification.placeholder')}
                        class="w-28 text-center font-mono font-bold tracking-widest text-xs px-2.5 py-1 rounded-lg border border-input bg-background text-foreground shadow-xs focus:outline-none focus:ring-2 focus:ring-sky-500"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="submit"
                        disabled={isVerifying || verificationCode.trim().length !== 6}
                        class="inline-flex items-center gap-1.5 px-3.5 py-1 rounded-lg bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs transition-opacity cursor-pointer disabled:opacity-50 shadow-xs"
                    >
                        {#if isVerifying}
                            <Loader2 class="size-3 animate-spin" />
                            <span>{localeStore.t('verification.verifying')}</span>
                        {:else}
                            <CheckCircle2 class="size-3" />
                            <span>{localeStore.t('verification.verify')}</span>
                        {/if}
                    </button>
                </div>
            </form>
        {/if}
    </div>
{/if}
