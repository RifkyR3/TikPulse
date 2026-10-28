<script lang="ts">
    import { localeStore, type SupportedLocale } from '@/stores/locale.svelte';
    import { themeStore, ACCENT_COLORS, type AccentColor, type ThemeMode } from '@/stores/theme.svelte';
    import { authStore } from '@/stores/auth.svelte';
    import apiClient from '@/services/api';
    import type { ApiResponse } from '@/types/api';
    import { toast } from 'svelte-sonner';
    import { Button } from '@/components/ui/button';
    import {
        X,
        UserCog,
        KeyRound,
        Phone,
        MapPin,
        User,
        Palette,
        Sun,
        Moon,
        Monitor,
        Globe,
        Check,
        Loader2,
        ShieldAlert,
        Lock,
        Eye,
        EyeOff,
        Mail,
        Send,
        CheckCircle2,
    } from '@lucide/svelte';

    interface CustomerStatusData {
        account_type: 'hotspot_customer' | 'ppp_customer' | 'admin';
        username: string;
        name?: string;
        email?: string | null;
        is_email_verified?: boolean;
        email_verified_at?: string | null;
        customer_name?: string;
        customer_phone?: string | null;
        customer_address?: string | null;
        default_password?: string | null;
        is_online: boolean;
        router?: {
            id: number;
            name: string;
            status: string;
        } | null;
    }

    let {
        isOpen = false,
        customerData,
        onClose,
        onSuccess,
    }: {
        isOpen: boolean;
        customerData: CustomerStatusData | null;
        onClose: () => void;
        onSuccess: () => void;
    } = $props();

    let customerEmail = $state('');
    let customerName = $state('');
    let customerPhone = $state('');
    let customerAddress = $state('');
    let currentPassword = $state('');
    let newPassword = $state('');
    let newPasswordConfirmation = $state('');
    let showDefaultPassword = $state(false);
    let selectedLocale = $state<SupportedLocale>(localeStore.locale);
    let selectedTheme = $state<ThemeMode>(themeStore.theme);
    let selectedAccent = $state<AccentColor>(themeStore.accentColor);

    let activeTab = $state<'contact' | 'security' | 'appearance'>('contact');
    let isSaving = $state(false);
    let errors = $state<Record<string, string[]>>({});

    let isSendingVerification = $state(false);
    let isVerifyingCode = $state(false);
    let showVerifyCodeInput = $state(false);
    let verificationCode = $state('');

    let isCurrentEmailVerified = $derived(
        Boolean(customerData?.is_email_verified) &&
        Boolean(customerData?.email) &&
        customerEmail.trim().toLowerCase() === customerData.email.trim().toLowerCase()
    );

    async function handleSendVerificationCode() {
        isSendingVerification = true;
        try {
            const trimmedEmail = customerEmail.trim();
            const res = await apiClient.post('/email/verification-notification', {
                email: trimmedEmail,
            });
            if (customerData) {
                customerData.email = trimmedEmail;
                customerData.is_email_verified = false;
            }
            if (authStore.user) {
                authStore.user.email = trimmedEmail;
                authStore.user.is_email_verified = false;
            }
            toast.success(
                res.data?.message || localeStore.t('verification.codeSentSuccess')
            );
            showVerifyCodeInput = true;
        } catch (err: any) {
            toast.error(err.response?.data?.message || 'Failed to send verification email.');
        } finally {
            isSendingVerification = false;
        }
    }

    async function handleVerifyEmailCode() {
        const code = verificationCode.trim();
        if (code.length !== 6) {
            toast.error(localeStore.t('verification.enterCodePrompt'));
            return;
        }

        isVerifyingCode = true;
        try {
            const res = await apiClient.post('/email/verify-code', { code });
            toast.success(
                res.data?.message || localeStore.t('verification.verifiedSuccess')
            );
            const verifiedUser = res.data?.data?.user;
            const verifiedEmail = verifiedUser?.email || customerEmail.trim();
            const verifiedAt = verifiedUser?.email_verified_at || new Date().toISOString();

            customerEmail = verifiedEmail;
            if (customerData) {
                customerData.email = verifiedEmail;
                customerData.is_email_verified = true;
                customerData.email_verified_at = verifiedAt;
            }
            if (authStore.user) {
                authStore.user = {
                    ...authStore.user,
                    email: verifiedEmail,
                    is_email_verified: true,
                    email_verified_at: verifiedAt,
                };
                if (typeof localStorage !== 'undefined') {
                    localStorage.setItem('tikpulse_user', JSON.stringify(authStore.user));
                }
            }
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('email-verified'));
            }
            showVerifyCodeInput = false;
            verificationCode = '';
        } catch (err: any) {
            toast.error(
                err.response?.data?.message || (
                    localeStore.locale === 'id'
                        ? 'Kode verifikasi salah atau kedaluwarsa.'
                        : 'Invalid or expired verification code.'
                )
            );
        } finally {
            isVerifyingCode = false;
        }
    }

    let prevIsOpen = $state(false);

    $effect(() => {
        const currentlyOpen = isOpen;
        if (currentlyOpen && !prevIsOpen) {
            customerEmail = customerData?.email || '';
            customerName = customerData?.customer_name || '';
            customerPhone = customerData?.customer_phone || '';
            customerAddress = customerData?.customer_address || '';
            currentPassword = '';
            newPassword = '';
            newPasswordConfirmation = '';
            showDefaultPassword = false;
            selectedLocale = localeStore.locale;
            selectedTheme = themeStore.theme;
            selectedAccent = themeStore.accentColor;
            errors = {};
            activeTab = 'contact';
            showVerifyCodeInput = false;
            verificationCode = '';
        }
        prevIsOpen = currentlyOpen;
    });

    async function handleSubmit(e: Event) {
        e.preventDefault();
        isSaving = true;
        errors = {};

        try {
            const payload: Record<string, any> = {
                preferred_locale: selectedLocale,
                theme: selectedTheme,
                accent_color: selectedAccent,
            };

            if (customerEmail.trim()) {
                payload.email = customerEmail.trim();
            }

            if (customerData?.account_type === 'ppp_customer') {
                payload.customer_name = customerName;
                payload.customer_phone = customerPhone;
                payload.customer_address = customerAddress;
            }

            if (newPassword.trim()) {
                payload.current_password = currentPassword;
                payload.new_password = newPassword;
                payload.new_password_confirmation = newPasswordConfirmation;
            }

            const res = await apiClient.put<ApiResponse<any>>('/customer/profile', payload);

            if (res.data.status === 'success') {
                // Apply theme and locale changes immediately in store
                if (selectedLocale !== localeStore.locale) {
                    await localeStore.setLocale(selectedLocale, true);
                }
                if (selectedTheme !== themeStore.theme) {
                    themeStore.setTheme(selectedTheme, true);
                }
                if (selectedAccent !== themeStore.accentColor) {
                    themeStore.setAccentColor(selectedAccent, true);
                }

                if (res.data.data?.user && authStore.user) {
                    authStore.user = {
                        ...authStore.user,
                        ...res.data.data.user,
                    };
                    if (typeof localStorage !== 'undefined') {
                        localStorage.setItem('tikpulse_user', JSON.stringify(authStore.user));
                    }
                }

                if (customerData && res.data.data?.user) {
                    customerData.email = res.data.data.user.email;
                    customerData.is_email_verified = Boolean(res.data.data.user.is_email_verified);
                    customerData.email_verified_at = res.data.data.user.email_verified_at;
                }

                if (typeof window !== 'undefined') {
                    window.dispatchEvent(new CustomEvent('email-verified'));
                }

                toast.success(localeStore.t('customer.profileUpdated'));
                onSuccess();
                onClose();
            }
        } catch (err: any) {
            if (err.response?.status === 422 && err.response?.data?.errors) {
                errors = err.response.data.errors;
                if (errors.email) {
                    activeTab = 'contact';
                } else if (errors.current_password || errors.new_password) {
                    activeTab = 'security';
                }
            } else {
                toast.error(err.response?.data?.message || 'Failed to update profile.');
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
        <div class="relative w-full max-w-xl max-h-[90vh] flex flex-col rounded-2xl border border-border bg-card shadow-2xl z-10 animate-in fade-in zoom-in-95 duration-150 overflow-hidden">
            <!-- Modal Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-border bg-muted/20">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center shadow-md shadow-sky-500/20">
                        <UserCog class="size-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            {localeStore.t('customer.editProfile')}
                        </h3>
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5 font-mono">
                            <span>{customerData?.username}</span>
                            <span>•</span>
                            <span class="capitalize">{customerData?.account_type === 'hotspot_customer' ? 'Hotspot Voucher' : 'PPPoE Dedicated'}</span>
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={onClose}
                    class="size-8 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors cursor-pointer"
                    aria-label="Close"
                >
                    <X class="size-4.5" />
                </button>
            </div>

            <!-- Tab Navigation -->
            <div class="flex border-b border-border px-6 bg-muted/10 gap-2 overflow-x-auto text-xs font-semibold">
                <button
                    type="button"
                    onclick={() => (activeTab = 'contact')}
                    class="py-3 px-3 border-b-2 flex items-center gap-1.5 transition-colors cursor-pointer {activeTab === 'contact' ? 'border-sky-500 text-sky-500 font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'}"
                >
                    <User class="size-3.5" />
                    <span>{localeStore.t('customer.contactInformation')}</span>
                    {#if errors.email || errors.customer_name || errors.customer_phone || errors.customer_address}
                        <span class="size-1.5 rounded-full bg-destructive animate-ping"></span>
                    {/if}
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'security')}
                    class="py-3 px-3 border-b-2 flex items-center gap-1.5 transition-colors cursor-pointer {activeTab === 'security' ? 'border-sky-500 text-sky-500 font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'}"
                >
                    <KeyRound class="size-3.5" />
                    <span>{localeStore.t('customer.changePassword')}</span>
                    {#if errors.current_password || errors.new_password}
                        <span class="size-1.5 rounded-full bg-destructive animate-ping"></span>
                    {/if}
                </button>
                <button
                    type="button"
                    onclick={() => (activeTab = 'appearance')}
                    class="py-3 px-3 border-b-2 flex items-center gap-1.5 transition-colors cursor-pointer {activeTab === 'appearance' ? 'border-sky-500 text-sky-500 font-bold' : 'border-transparent text-muted-foreground hover:text-foreground'}"
                >
                    <Palette class="size-3.5" />
                    <span>{localeStore.t('customer.personalization')}</span>
                </button>
            </div>

            <!-- Scrollable Content Body -->
            <form onsubmit={handleSubmit} class="flex-1 overflow-y-auto p-6 space-y-5">
                <!-- 1. Contact & Account Information Tab -->
                {#if activeTab === 'contact'}
                    <div class="space-y-4 animate-in fade-in duration-100">
                        <!-- Email Input (Available for all subscribers) -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label for="customer-email" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                    <Mail class="size-3.5 text-sky-500" />
                                    <span>{localeStore.t('customer.customerEmail')}</span>
                                </label>
                                {#if isCurrentEmailVerified}
                                    <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                        <Check class="size-3" />
                                        <span>{localeStore.t('customer.emailVerified')}</span>
                                    </span>
                                {:else if customerEmail && !customerEmail.includes('@customer.tikpulse.local')}
                                    <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                        <ShieldAlert class="size-3" />
                                        <span>{localeStore.t('customer.emailUnverified')}</span>
                                    </span>
                                {/if}
                            </div>
                            <input
                                id="customer-email"
                                type="email"
                                bind:value={customerEmail}
                                placeholder={localeStore.t('customer.emailPlaceholder')}
                                class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                            />
                            {#if errors.email}
                                <p class="text-[11px] text-destructive">{errors.email[0]}</p>
                            {:else if customerEmail.includes('@customer.tikpulse.local')}
                                <p class="text-[11px] text-muted-foreground">{localeStore.t('customer.placeholderEmailNotice')}</p>
                            {:else}
                                <p class="text-[11px] text-muted-foreground">{localeStore.t('customer.emailVerificationNotice')}</p>
                            {/if}

                            {#if !isCurrentEmailVerified && customerEmail && !customerEmail.includes('@customer.tikpulse.local')}
                                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-2 mt-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 font-semibold">
                                            <ShieldAlert class="size-3.5 shrink-0" />
                                            <span>{localeStore.t('verification.unverifiedNotice')}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                onclick={handleSendVerificationCode}
                                                disabled={isSendingVerification}
                                                class="px-2.5 py-1 rounded-lg bg-primary text-primary-foreground text-[11px] font-bold hover:opacity-90 transition-opacity cursor-pointer disabled:opacity-50 inline-flex items-center gap-1"
                                            >
                                                {#if isSendingVerification}
                                                    <Loader2 class="size-3 animate-spin" />
                                                    <span>{localeStore.locale === 'id' ? 'Mengirim...' : 'Sending...'}</span>
                                                {:else}
                                                    <Send class="size-3" />
                                                    <span>{localeStore.t('verification.sendCode')}</span>
                                                {/if}
                                            </button>
                                            <button
                                                type="button"
                                                onclick={() => (showVerifyCodeInput = !showVerifyCodeInput)}
                                                class="px-2 py-1 rounded-lg border border-border bg-card/60 text-[11px] font-semibold text-foreground hover:bg-muted transition-colors cursor-pointer"
                                            >
                                                {showVerifyCodeInput ? localeStore.t('verification.hideCodeInput') : localeStore.t('verification.toggleCodeInput')}
                                            </button>
                                        </div>
                                    </div>

                                    {#if showVerifyCodeInput}
                                        <div class="pt-2 border-t border-amber-500/20 flex items-center justify-between gap-2 animate-in fade-in duration-200">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                maxlength="6"
                                                bind:value={verificationCode}
                                                placeholder={localeStore.t('verification.placeholder')}
                                                class="w-28 text-center font-mono font-bold tracking-widest text-xs px-2.5 py-1 rounded-lg border border-input bg-background text-foreground shadow-xs focus:outline-none focus:ring-2 focus:ring-sky-500"
                                            />
                                            <button
                                                type="button"
                                                onclick={handleVerifyEmailCode}
                                                disabled={isVerifyingCode || verificationCode.trim().length !== 6}
                                                class="px-3 py-1 rounded-lg bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shadow-xs"
                                            >
                                                {#if isVerifyingCode}
                                                    <Loader2 class="size-3 animate-spin" />
                                                    <span>{localeStore.t('verification.verifying')}</span>
                                                {:else}
                                                    <CheckCircle2 class="size-3" />
                                                    <span>{localeStore.t('verification.verify')}</span>
                                                {/if}
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>

                        {#if customerData?.account_type === 'ppp_customer'}
                            <div class="space-y-1.5">
                                <label for="customer-name" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                    <User class="size-3.5 text-sky-500" />
                                    <span>{localeStore.t('customer.customerName')}</span>
                                </label>
                                <input
                                    id="customer-name"
                                    type="text"
                                    bind:value={customerName}
                                    placeholder="e.g. John Doe"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                                />
                                {#if errors.customer_name}
                                    <p class="text-[11px] text-destructive">{errors.customer_name[0]}</p>
                                {/if}
                            </div>

                            <div class="space-y-1.5">
                                <label for="customer-phone" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                    <Phone class="size-3.5 text-emerald-500" />
                                    <span>{localeStore.t('customer.customerPhone')}</span>
                                </label>
                                <input
                                    id="customer-phone"
                                    type="tel"
                                    bind:value={customerPhone}
                                    placeholder="081234567890"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                                />
                                {#if errors.customer_phone}
                                    <p class="text-[11px] text-destructive">{errors.customer_phone[0]}</p>
                                {/if}
                            </div>

                            <div class="space-y-1.5">
                                <label for="customer-address" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                    <MapPin class="size-3.5 text-rose-500" />
                                    <span>{localeStore.t('customer.customerAddress')}</span>
                                </label>
                                <textarea
                                    id="customer-address"
                                    bind:value={customerAddress}
                                    rows="3"
                                    placeholder="e.g. Jl. Kenanga No. 12, RT 01/RW 02"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500 resize-none"
                                ></textarea>
                                {#if errors.customer_address}
                                    <p class="text-[11px] text-destructive">{errors.customer_address[0]}</p>
                                {/if}
                            </div>
                        {/if}
                    </div>
                {/if}

                <!-- 2. Security / Change Password Tab -->
                {#if activeTab === 'security'}
                    <div class="space-y-4 animate-in fade-in duration-100">
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-xs flex items-start gap-2.5">
                            <ShieldAlert class="size-4 shrink-0 mt-0.5" />
                            <div class="space-y-1">
                                <p class="font-bold">{localeStore.t('customer.passwordChangedNote')}</p>
                                <p class="text-[11px] opacity-90">{localeStore.t('customer.leavePasswordBlank')}</p>
                            </div>
                        </div>

                        <!-- Default Router Password (Secret from MikroTik) - Web View -->
                        {#if customerData?.default_password}
                            <div class="space-y-1.5 p-3 rounded-xl bg-muted/20 border border-border">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                        <Lock class="size-3.5 text-sky-500" />
                                        <span>{localeStore.locale === 'id' ? 'Kata Sandi Default Router (Secret)' : 'Default Router Password (Secret)'}</span>
                                    </span>
                                    <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-500 font-bold border border-sky-500/20">
                                        Router Secret
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 pt-1">
                                    <div class="relative flex-1">
                                        <input
                                            type={showDefaultPassword ? 'text' : 'password'}
                                            readonly
                                            value={customerData.default_password}
                                            class="w-full pl-3 pr-9 py-2 text-xs rounded-xl border border-input bg-card text-foreground font-mono select-all focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            onclick={() => (showDefaultPassword = !showDefaultPassword)}
                                            class="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                                            title={showDefaultPassword ? 'Hide password' : 'View default password'}
                                        >
                                            {#if showDefaultPassword}
                                                <EyeOff class="size-3.5" />
                                            {:else}
                                                <Eye class="size-3.5" />
                                            {/if}
                                        </button>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={() => {
                                            currentPassword = customerData!.default_password || '';
                                            toast.success(localeStore.locale === 'id' ? 'Kata sandi router disalin ke Kata Sandi Saat Ini.' : 'Router password set to Current Password.');
                                        }}
                                        class="text-xs font-semibold h-9 shrink-0 cursor-pointer hover:bg-sky-500/10 hover:text-sky-500"
                                        title="Gunakan sebagai kata sandi saat ini"
                                    >
                                        {localeStore.locale === 'id' ? 'Gunakan' : 'Use'}
                                    </Button>
                                </div>
                                <p class="text-[11px] text-muted-foreground">
                                    {localeStore.locale === 'id'
                                        ? 'Kata sandi default ini tersimpan langsung pada gateway router MikroTik Anda.'
                                        : 'This default secret credential is synchronised from your MikroTik RouterOS gateway.'}
                                </p>
                            </div>
                        {/if}

                        <div class="space-y-1.5">
                            <label for="current-password" class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <KeyRound class="size-3.5 text-sky-500" />
                                <span>{localeStore.t('customer.currentPassword')}</span>
                            </label>
                            <input
                                id="current-password"
                                type="password"
                                bind:value={currentPassword}
                                placeholder="••••••••"
                                class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                            />
                            {#if errors.current_password}
                                <p class="text-[11px] text-destructive">{errors.current_password[0]}</p>
                            {/if}
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label for="new-password" class="text-xs font-bold text-foreground">
                                    {localeStore.t('customer.newPassword')}
                                </label>
                                <input
                                    id="new-password"
                                    type="password"
                                    bind:value={newPassword}
                                    placeholder="••••••••"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                                />
                                {#if errors.new_password}
                                    <p class="text-[11px] text-destructive">{errors.new_password[0]}</p>
                                {/if}
                            </div>

                            <div class="space-y-1.5">
                                <label for="new-password-confirmation" class="text-xs font-bold text-foreground">
                                    {localeStore.t('customer.confirmNewPassword')}
                                </label>
                                <input
                                    id="new-password-confirmation"
                                    type="password"
                                    bind:value={newPasswordConfirmation}
                                    placeholder="••••••••"
                                    class="w-full px-3 py-2 text-xs rounded-xl border border-input bg-background text-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
                                />
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- 3. Appearance & Personalization Tab -->
                {#if activeTab === 'appearance'}
                    <div class="space-y-5 animate-in fade-in duration-100">
                        <!-- Language Switcher -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Globe class="size-3.5 text-sky-500" />
                                <span>{localeStore.t('layout.language')}</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    onclick={() => (selectedLocale = 'id')}
                                    class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {selectedLocale === 'id' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm">🇮🇩</span>
                                        <span>Bahasa Indonesia</span>
                                    </span>
                                    {#if selectedLocale === 'id'}
                                        <Check class="size-3.5" />
                                    {/if}
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (selectedLocale = 'en')}
                                    class="flex items-center justify-between p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {selectedLocale === 'en' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                                >
                                    <span class="flex items-center gap-2">
                                        <span class="text-sm">🇬🇧</span>
                                        <span>English</span>
                                    </span>
                                    {#if selectedLocale === 'en'}
                                        <Check class="size-3.5" />
                                    {/if}
                                </button>
                            </div>
                        </div>

                        <!-- Theme Mode -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Sun class="size-3.5 text-amber-500" />
                                <span>{localeStore.t('users.theme')}</span>
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    onclick={() => (selectedTheme = 'light')}
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {selectedTheme === 'light' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                                >
                                    <Sun class="size-3.5" />
                                    <span>Light</span>
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (selectedTheme = 'dark')}
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {selectedTheme === 'dark' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                                >
                                    <Moon class="size-3.5" />
                                    <span>Dark</span>
                                </button>
                                <button
                                    type="button"
                                    onclick={() => (selectedTheme = 'system')}
                                    class="flex items-center justify-center gap-2 p-2.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer {selectedTheme === 'system' ? 'border-sky-500 bg-sky-500/10 text-sky-500 shadow-xs' : 'border-border bg-card/50 text-muted-foreground hover:text-foreground hover:bg-muted'}"
                                >
                                    <Monitor class="size-3.5" />
                                    <span>System</span>
                                </button>
                            </div>
                        </div>

                        <!-- Accent Color -->
                        <div class="space-y-2">
                            <label class="text-xs font-bold text-foreground flex items-center gap-1.5">
                                <Palette class="size-3.5 text-sky-500" />
                                <span>{localeStore.t('users.accentColor')}</span>
                            </label>
                            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                                {#each Object.entries(ACCENT_COLORS) as [colorKey, colorConfig]}
                                    <button
                                        type="button"
                                        onclick={() => (selectedAccent = colorKey as AccentColor)}
                                        class="flex flex-col items-center gap-1.5 p-2 rounded-xl border text-xs font-medium transition-all cursor-pointer {selectedAccent === colorKey ? 'border-sky-500 bg-sky-500/10 shadow-xs ring-1 ring-sky-500' : 'border-border bg-card/50 hover:bg-muted'}"
                                    >
                                        <span class="size-5 rounded-full {colorConfig.bg} shadow-inner"></span>
                                        <span class="text-[11px] capitalize text-foreground">{colorConfig.label}</span>
                                    </button>
                                {/each}
                            </div>
                        </div>
                    </div>
                {/if}

                <!-- Modal Footer Actions -->
                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-border mt-6">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onclick={onClose}
                        disabled={isSaving}
                        class="text-xs font-semibold cursor-pointer"
                    >
                        {localeStore.t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        disabled={isSaving}
                        class="text-xs font-bold gap-1.5 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-700 hover:to-indigo-700 text-white shadow-md shadow-sky-500/20 cursor-pointer"
                    >
                        {#if isSaving}
                            <Loader2 class="size-3.5 animate-spin" />
                            <span>{localeStore.t('customer.saving')}</span>
                        {:else}
                            <Check class="size-3.5" />
                            <span>{localeStore.t('customer.saveChanges')}</span>
                        {/if}
                    </Button>
                </div>
            </form>
        </div>
    </div>
{/if}
