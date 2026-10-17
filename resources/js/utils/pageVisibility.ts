import { ref, onMounted, onUnmounted } from 'vue';

const isPageVisible = ref(typeof document !== 'undefined' ? !document.hidden : true);

const listeners = new Set<(visible: boolean) => void>();

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        isPageVisible.value = !document.hidden;
        listeners.forEach((callback) => callback(isPageVisible.value));
    });
}

/**
 * Composable to track browser tab visibility.
 * Prevents useless background polling when tab is inactive, saving client CPU, battery, and server bandwidth.
 */
export function usePageVisibility() {
    const onVisibilityChange = (callback: (visible: boolean) => void) => {
        listeners.add(callback);
        onUnmounted(() => {
            listeners.delete(callback);
        });
    };

    return {
        isPageVisible,
        onVisibilityChange,
    };
}
