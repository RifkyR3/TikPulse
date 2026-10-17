<script setup lang="ts">
import { ref } from 'vue';
import AppSidebar from '@/components/layout/AppSidebar.vue';
import AppHeader from '@/components/layout/AppHeader.vue';
import SafeModeUnlockModal from '@/components/safe-mode/SafeModeUnlockModal.vue';
import RouterModal from '@/components/routers/RouterModal.vue';

const isSidebarCollapsed = ref(false);
const isMobileSidebarOpen = ref(false);
const isUnlockModalOpen = ref(false);
const isRouterModalOpen = ref(false);

const toggleSidebarCollapse = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

const toggleMobileSidebar = () => {
    isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
};
</script>

<template>
    <div class="min-h-screen bg-background text-foreground flex">
        <!-- Sidebar Navigation -->
        <AppSidebar
            :is-collapsed="isSidebarCollapsed"
            :is-mobile-open="isMobileSidebarOpen"
            @toggle-collapse="toggleSidebarCollapse"
            @close-mobile="isMobileSidebarOpen = false"
        />

        <!-- Main Wrapper -->
        <div
            :class="[
                'flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out',
                isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'
            ]"
        >
            <!-- Top Header -->
            <AppHeader
                @toggle-mobile="toggleMobileSidebar"
                @open-unlock-modal="isUnlockModalOpen = true"
                @add-router="isRouterModalOpen = true"
            />

            <!-- Page Content -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
                <slot />
            </main>
        </div>

        <!-- Global Modals -->
        <SafeModeUnlockModal
            :is-open="isUnlockModalOpen"
            @close="isUnlockModalOpen = false"
            @unlocked="isUnlockModalOpen = false"
        />

        <RouterModal
            :is-open="isRouterModalOpen"
            @close="isRouterModalOpen = false"
            @created="isRouterModalOpen = false"
        />
    </div>
</template>
