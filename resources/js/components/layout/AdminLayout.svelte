<script lang="ts">
    import * as Sidebar from '@/components/ui/sidebar';
    import AppSidebar from '@/components/layout/AppSidebar.svelte';
    import AppHeader from '@/components/layout/AppHeader.svelte';
    import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.svelte';
    import RouterModal from '@/components/routers/RouterModal.svelte';
    import { Toaster } from 'svelte-sonner';

    let { children }: { children?: import('svelte').Snippet; title?: string } = $props();

    let isUnlockModalOpen = $state(false);
    let isRouterModalOpen = $state(false);
</script>

<Toaster richColors position="top-right" />

<Sidebar.Provider>
    <!-- SvelteForge App Sidebar -->
    <AppSidebar />

    <!-- SvelteForge Inset Content Area -->
    <Sidebar.Inset>
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
