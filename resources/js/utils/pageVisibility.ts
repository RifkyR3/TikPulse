/**
 * Utility to track browser tab visibility.
 * Prevents useless background polling when tab is inactive, saving client CPU, battery, and server bandwidth.
 */

let isVisible = typeof document !== 'undefined' ? !document.hidden : true;
const listeners = new Set<(visible: boolean) => void>();

if (typeof document !== 'undefined') {
    document.addEventListener('visibilitychange', () => {
        isVisible = !document.hidden;
        listeners.forEach((callback) => callback(isVisible));
    });
}

export function isDocumentVisible(): boolean {
    return typeof document !== 'undefined' ? !document.hidden : true;
}

export function usePageVisibility() {
    return {
        get isPageVisible() {
            return isDocumentVisible();
        },
        isVisible: {
            get value() {
                return isDocumentVisible();
            },
        },
        onVisibilityChange(callback: (visible: boolean) => void) {
            listeners.add(callback);
            return () => {
                listeners.delete(callback);
            };
        },
    };
}
