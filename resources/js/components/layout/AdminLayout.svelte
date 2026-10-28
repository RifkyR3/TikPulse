<script lang="ts">
    import { onMount } from 'svelte';
    import { page } from '@inertiajs/svelte';
    import { authStore } from '@/stores/auth.svelte';
    import * as Sidebar from '@/components/ui/sidebar';
    import AppSidebar from '@/components/layout/AppSidebar.svelte';
    import AppHeader from '@/components/layout/AppHeader.svelte';
    import SimulationBanner from '@/components/layout/SimulationBanner.svelte';
    import EmailVerificationBanner from '@/components/layout/EmailVerificationBanner.svelte';
    import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.svelte';
    import RouterModal from '@/components/routers/RouterModal.svelte';
    import { router } from '@inertiajs/svelte';
    import { Toaster } from 'svelte-sonner';

    let { children }: { children?: import('svelte').Snippet; title?: string } = $props();

    let isUnlockModalOpen = $state(false);
    let isRouterModalOpen = $state(false);

    $effect(() => {
        const sharedUser = (page.props as any)?.auth?.user;
        if (sharedUser && (!authStore.user || authStore.user.id !== sharedUser.id || authStore.user.simulated_group_id !== sharedUser.simulated_group_id)) {
            authStore.initFromPageProps(sharedUser);
        }
        if (authStore.user?.role === 'customer') {
            router.visit('/status');
        }
    });

    onMount(() => {
        if (authStore.token && !authStore.user) {
            authStore.fetchMe();
        }
    });
</script>

<Toaster richColors position="top-right" />

<Sidebar.Provider>
    <!-- SvelteForge App Sidebar -->
    <AppSidebar />

    <!-- SvelteForge Inset Content Area -->
    <Sidebar.Inset>
        <!-- Email Verification Banner (Active when logged in user is unverified) -->
        <EmailVerificationBanner />

        <!-- Simulation Warning Banner (Active when superadmin tests role) -->
        <SimulationBanner />

        <!-- Top Sticky Header -->
        <AppHeader
            onOpenUnlockModal={() => (isUnlockModalOpen = true)}
            onAddRouter={() => (isRouterModalOpen = true)}
        />

        <!-- Page Main View -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
            {@render children?.()}
        </main>
    </Sidebar.Inset>

    <!-- Global Modals -->
    <SafeModeUnlockModal
        isOpen={isUnlockModalOpen}
        onClose={() => (isUnlockModalOpen = false)}
        onUnlocked={() => (isUnlockModalOpen = false)}
    />

    <RouterModal
        isOpen={isRouterModalOpen}
        onClose={() => (isRouterModalOpen = false)}
        onCreated={() => (isRouterModalOpen = false)}
    />
</Sidebar.Provider>
