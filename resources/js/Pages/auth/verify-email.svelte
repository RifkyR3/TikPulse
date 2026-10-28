<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { authStore } from '@/stores/auth.svelte';
    import { localeStore } from '@/stores/locale.svelte';
    import ThemeToggle from '@/components/layout/ThemeToggle.svelte';
    import LanguageSwitcher from '@/components/layout/LanguageSwitcher.svelte';
    import * as Card from '@/components/ui/card';
    import { Button } from '@/components/ui/button';
    import { Mail, CheckCircle2, LogOut, Loader2, ExternalLink, KeyRound } from '@lucide/svelte';
    import { toast } from 'svelte-sonner';
    import apiClient from '@/services/api';

    let { status }: { status?: string } = $props();

    let isSending = $state(false);
    let isVerifying = $state(false);
    let verificationCode = $state('');

    const verificationLinkSent = $derived(status === 'verification-link-sent');

    async function handleResend() {
        isSending = true;
        try {
            const res = await apiClient.post('/email/verification-notification');
            toast.success(
                res.data?.message || localeStore.t('verification.newCodeSentSuccess')
            );
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to resend verification email.');
        } finally {
            isSending = false;
        }
    }

    async function handleVerifyCode(e: Event) {
        e.preventDefault();
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

            router.visit('/');
        } catch (err: any) {
            toast.error(
                err.response?.data?.message || localeStore.t('verification.codeInvalidOrExpired')
            );
        } finally {
            isVerifying = false;
        }
    }

    async function handleLogout() {
        await authStore.logout();
        router.visit('/login');
    }
</script>

<svelte:head>
    <title>{localeStore.t('verification.title')} - TikPulse</title>
</svelte:head>

<div class="min-h-screen bg-background flex flex-col justify-center items-center p-4 relative overflow-hidden">
    <!-- Background glow aesthetics -->
    <div class="absolute -top-40 -right-40 size-96 bg-sky-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -left-40 size-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Top Right Controls -->
    <div class="absolute top-4 right-4 flex items-center space-x-2">
        <LanguageSwitcher />
        <ThemeToggle />
    </div>

    <div class="w-full max-w-md relative z-10">
        <!-- Logo Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gradient-to-tr from-sky-500 via-indigo-500 to-primary text-white shadow-xl shadow-sky-500/25 mb-4">
                <Mail class="size-7 animate-bounce" />
            </div>
            <h1 class="text-2xl font-black tracking-tight text-foreground">
                Tik<span class="text-sky-500">Pulse</span>
            </h1>
            <p class="text-xs text-muted-foreground mt-1">
                {localeStore.t('verification.title')}
            </p>
        </div>

        <Card.Root class="border-border shadow-xl backdrop-blur-sm bg-card/95">
            <Card.Header class="pb-2">
                <Card.Title class="text-base font-bold text-center">
                    {localeStore.locale === 'id' ? 'Cek Kotak Masuk Email Anda' : 'Check Your Email Inbox'}
                </Card.Title>
                <Card.Description class="text-xs text-center mt-2 leading-relaxed">
                    {localeStore.locale === 'id'
                        ? 'Masukkan 6 digit kode verifikasi yang dikirimkan ke email Anda atau klik tautan verifikasi.'
                        : 'Enter the 6-digit verification code sent to your email or click the link in the message.'}
                </Card.Description>
            </Card.Header>

            <Card.Content class="space-y-4 pt-3">
                {#if verificationLinkSent}
                    <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2.5 animate-in fade-in duration-200">
                        <CheckCircle2 class="size-4 shrink-0" />
                        <span>
                            {localeStore.locale === 'id'
                                ? 'Kode verifikasi telah dikirim ke alamat email Anda.'
                                : 'A fresh verification code has been sent to your email address.'}
                        </span>
                    </div>
                {/if}

                <!-- Verification Code Form -->
                <form onsubmit={handleVerifyCode} class="space-y-3 p-3.5 rounded-xl bg-muted/30 border border-border">
                    <label for="verify-code-input" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                        <KeyRound class="size-3.5 text-sky-500" />
                        <span>{localeStore.t('verification.enterCode')}</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <input
                            id="verify-code-input"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="6"
                            bind:value={verificationCode}
                            placeholder="123456"
                            class="flex-1 text-center font-mono font-black tracking-widest text-base py-2 rounded-xl border border-input bg-background text-foreground shadow-xs focus:outline-none focus:ring-2 focus:ring-sky-500"
                        />
                        <Button
                            type="submit"
                            disabled={isVerifying || verificationCode.trim().length !== 6}
                            class="bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs py-2 px-4 shadow-md shadow-sky-600/20 cursor-pointer disabled:opacity-50"
                        >
                            {#if isVerifying}
                                <Loader2 class="size-4 animate-spin" />
                            {:else}
                                <span>{localeStore.t('verification.verify')}</span>
                            {/if}
                        </Button>
                    </div>
                </form>

                <!-- Local Dev Mailpit Helper -->
                <div class="p-3 rounded-xl bg-sky-500/10 border border-sky-500/20 text-xs text-sky-700 dark:text-sky-300 flex items-center justify-between">
                    <div>
                        <p class="font-bold">Mailpit Dev Mailbox</p>
                        <p class="text-[11px] opacity-80">Local email viewer running on port 8025</p>
                    </div>
                    <a
                        href="http://localhost:8025"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-sky-500 text-white font-bold text-[11px] hover:bg-sky-600 transition-colors"
                    >
                        <span>Open</span>
                        <ExternalLink class="size-3" />
                    </a>
                </div>

                <div class="space-y-2 pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        onclick={handleResend}
                        disabled={isSending}
                        class="w-full text-xs font-semibold border-border hover:bg-muted cursor-pointer"
                    >
                        {#if isSending}
                            <Loader2 class="size-4 animate-spin mr-1.5" />
                            <span>{localeStore.locale === 'id' ? 'Mengirim...' : 'Sending...'}</span>
                        {:else}
                            <Mail class="size-4 mr-1.5" />
                            <span>{localeStore.t('verification.resendCode')}</span>
                        {/if}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        onclick={handleLogout}
                        class="w-full text-xs font-semibold text-muted-foreground hover:text-foreground cursor-pointer"
                    >
                        <LogOut class="size-3.5 mr-1.5" />
                        <span>{localeStore.t('customer.logout')}</span>
                    </Button>
                </div>
            </Card.Content>
        </Card.Root>

        <p class="text-center text-[11px] text-muted-foreground mt-8">
            Tik<span class="text-sky-500 font-bold">Pulse</span> Enterprise • Clean Architecture & Safe Mode Guardrails
        </p>
    </div>
</div>
