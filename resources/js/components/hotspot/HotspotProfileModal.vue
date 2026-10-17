<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import type { HotspotProfileItem } from '@/types/hotspot';
import { X, Layers, Check, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    profileToEdit?: HotspotProfileItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const routerStore = useRouterContextStore();

const form = reactive({
    name: '',
    shared_users: 1,
    rate_limit: '2M/5M',
    validity_duration: '1',
    validity_unit: 'hours' as 'minutes' | 'hours' | 'days',
    price: 3000,
    selling_price: 3000,
    address_pool: '',
});

const isSubmitting = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.profileToEdit,
    (item) => {
        if (item) {
            form.name = item.name;
            form.shared_users = item.shared_users || 1;
            form.rate_limit = item.rate_limit || '';
            form.validity_duration = item.validity_duration || '1';
            form.validity_unit = item.validity_unit || 'hours';
            form.price = item.price || 0;
            form.selling_price = item.selling_price || 0;
            form.address_pool = item.address_pool || '';
        } else {
            form.name = '';
            form.shared_users = 1;
            form.rate_limit = '2M/5M';
            form.validity_duration = '1';
            form.validity_unit = 'hours';
            form.price = 3000;
            form.selling_price = 3000;
            form.address_pool = '';
        }
        error.value = null;
    },
    { immediate: true }
);

const handleSubmit = async () => {
    if (!routerStore.activeRouter) return;
    error.value = null;
    isSubmitting.value = true;

    try {
        if (props.profileToEdit) {
            await apiClient.put(
                `/routers/${routerStore.activeRouter.id}/profiles/${props.profileToEdit.id}`,
                form
            );
        } else {
            await apiClient.post(
                `/routers/${routerStore.activeRouter.id}/profiles`,
                form
            );
        }
        emit('saved');
        emit('close');
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to save hotspot profile.';
    } finally {
        isSubmitting.value = false;
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
            <button
                type="button"
                @click="emit('close')"
                class="absolute top-4 right-4 text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-5">
                <div class="w-11 h-11 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-500 flex items-center justify-center shrink-0">
                    <Layers class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">
                        {{ profileToEdit ? 'Edit Hotspot Profile' : 'Add Hotspot Profile' }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        Configure bandwidth limits, validity periods, and voucher pricing
                    </p>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="mb-4 rounded-xl bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                {{ error }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Profile Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        :disabled="!!profileToEdit"
                        placeholder="e.g. 1Jam-5Mbps or Harian-3M"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 disabled:opacity-60"
                    />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Rate Limit (Rx/Tx)
                        </label>
                        <input
                            v-model="form.rate_limit"
                            type="text"
                            placeholder="e.g. 2M/5M, 512k/1M"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Shared Users
                        </label>
                        <input
                            v-model.number="form.shared_users"
                            type="number"
                            min="1"
                            max="50"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>
                </div>

                <!-- Validity Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Validity Duration
                        </label>
                        <input
                            v-model="form.validity_duration"
                            type="text"
                            placeholder="e.g. 1, 2, 24"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Validity Unit
                        </label>
                        <select
                            v-model="form.validity_unit"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20 cursor-pointer"
                        >
                            <option value="minutes">Minutes (m)</option>
                            <option value="hours">Hours (h)</option>
                            <option value="days">Days (d)</option>
                        </select>
                    </div>
                </div>

                <!-- Pricing Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Modal Price (Rp)
                        </label>
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            placeholder="3000"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Selling Price (Rp)
                        </label>
                        <input
                            v-model.number="form.selling_price"
                            type="number"
                            min="0"
                            placeholder="3000"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-purple-500 focus:outline-none focus:ring-2 focus:ring-purple-500/20"
                        />
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end space-x-3 pt-3 border-t border-border">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 text-xs font-semibold rounded-lg border border-border hover:bg-muted text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow-md shadow-purple-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>Save Profile</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
