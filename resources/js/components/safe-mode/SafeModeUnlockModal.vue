<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useSafeModeStore } from '@/stores/safeMode';
import { useRouterContextStore } from '@/stores/routerContext';
import { ShieldAlert, Lock, AlertTriangle, X, Check, Loader2, KeyRound } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'unlocked'): void;
}>();

const safeModeStore = useSafeModeStore();
const routerStore = useRouterContextStore();

const form = reactive({
    password: '',
    verificationText: '',
    reason: '',
});

const error = ref<string | null>(null);

const handleClose = () => {
    form.password = '';
    form.verificationText = '';
    form.reason = '';
    error.value = null;
    emit('close');
};

const handleUnlock = async () => {
    error.value = null;

    if (!form.password) {
        error.value = 'Please enter your administrator account password.';
        return;
    }

    if (form.verificationText.trim().toUpperCase() !== 'UNLOCK') {
        error.value = 'Please type UNLOCK in the verification field to confirm.';
        return;
    }

    if (!routerStore.activeRouter) {
        error.value = 'No active router selected.';
        return;
    }

    try {
        await safeModeStore.unlock(routerStore.activeRouter.id, form.password, form.reason || 'Manual administrative unlock');
        handleClose();
        emit('unlocked');
    } catch (err: any) {
        error.value = err.message || 'Unlock sequence failed.';
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
    >
        <div
            class="relative w-full max-w-lg rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
            role="dialog"
            aria-modal="true"
        >
            <!-- Close Button -->
            <button
                type="button"
                @click="handleClose"
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
                        Target Router: <span class="font-semibold text-foreground">{{ routerStore.activeRouter?.name }}</span>
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
            <div v-if="error" class="mb-4 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                {{ error }}
            </div>

            <!-- Form -->
            <form @submit.prevent="handleUnlock" class="space-y-4">
                <!-- Step 1: Admin Password -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Step 1: Admin Password
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-muted-foreground">
                            <KeyRound class="h-4 w-4" />
                        </div>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="Enter your login password"
                            class="block w-full rounded-lg border border-border bg-background py-2 pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                        />
                    </div>
                </div>

                <!-- Step 2: Verification Word -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Step 2: Type <span class="text-amber-500 font-mono">UNLOCK</span> to confirm
                    </label>
                    <input
                        v-model="form.verificationText"
                        type="text"
                        required
                        placeholder="Type UNLOCK here"
                        class="block w-full font-mono rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20 uppercase"
                    />
                </div>

                <!-- Reason (Optional) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Audit Reason (Optional)
                    </label>
                    <input
                        v-model="form.reason"
                        type="text"
                        placeholder="e.g. Firmware maintenance / voucher bulk cleanup"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                    />
                </div>

                <!-- Action Buttons -->
                <div class="mt-6 flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <button
                        type="button"
                        @click="handleClose"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="safeModeStore.isLoading"
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-amber-500 hover:bg-amber-600 text-white shadow-md shadow-amber-500/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        <Loader2 v-if="safeModeStore.isLoading" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>Unlock for 15 Minutes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
