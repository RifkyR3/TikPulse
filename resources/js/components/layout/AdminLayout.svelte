<script lang="ts">
    import AppSidebar from '@/components/layout/AppSidebar.svelte';
    import AppHeader from '@/components/layout/AppHeader.svelte';
    import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.svelte';
    import RouterModal from '@/components/routers/RouterModal.svelte';
    import { Toaster } from 'svelte-sonner';

    let { children, title }: { children?: import('svelte').Snippet; title?: string } = $props();

    let isSidebarCollapsed = $state(false);
    let isMobileSidebarOpen = $state(false);
    let isUnlockModalOpen = $state(false);
    let isRouterModalOpen = $state(false);

    function toggleSidebarCollapse() {
        isSidebarCollapsed = !isSidebarCollapsed;
    }

    function toggleMobileSidebar() {
        isMobileSidebarOpen = !isMobileSidebarOpen;
    }
</script>

<Toaster richColors position="top-right" />

<div class="min-h-screen bg-background text-foreground flex">
    <!-- Sidebar Navigation -->
    <AppSidebar
        isCollapsed={isSidebarCollapsed}
        isMobileOpen={isMobileSidebarOpen}
        onToggleCollapse={toggleSidebarCollapse}
        onCloseMobile={() => (isMobileSidebarOpen = false)}
    />

    <!-- Main Wrapper -->
    <div
        class="flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out {isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'}"
    >
        <!-- Top Header -->
        <AppHeader
            onToggleMobile={toggleMobileSidebar}
            onOpenUnlockModal={() => (isUnlockModalOpen = true)}
            onAddRouter={() => (isRouterModalOpen = true)}
        />

        <!-- Page Content -->
        <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
            {@render children?.()}
        </main>
    </div>

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
</div>
