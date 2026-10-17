<script setup lang="ts">
import { ref, reactive } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import apiClient from '@/services/api';
import type { HotspotProfileItem } from '@/types/hotspot';
import { toast } from 'vue-sonner';
import { X, UserPlus, Check, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    profiles: HotspotProfileItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const routerStore = useRouterContextStore();

const form = reactive({
    name: '',
    password: '',
    profile: '',
    comment: '',
    time_limit: '',
    data_limit_mb: null as number | null,
});

const isSubmitting = ref(false);
const error = ref<string | null>(null);

const resetForm = () => {
    form.name = '';
    form.password = '';
    form.profile = props.profiles[0]?.name || 'default';
    form.comment = '';
    form.time_limit = '';
    form.data_limit_mb = null;
    error.value = null;
};

const handleSubmit = async () => {
    if (!routerStore.activeRouter) return;
    if (!form.name.trim() || !form.password.trim()) {
        error.value = 'Username and password are required.';
        return;
    }

    isSubmitting.value = true;
    error.value = null;

    try {
        const payload: Record<string, unknown> = {
            name: form.name.trim(),
            password: form.password.trim(),
            profile: form.profile || props.profiles[0]?.name || 'default',
            comment: form.comment.trim() || null,
            time_limit: form.time_limit.trim() || null,
            data_limit: form.data_limit_mb ? form.data_limit_mb * 1024 * 1024 : null,
        };

        const res = await apiClient.post(`/routers/${routerStore.activeRouter.id}/hotspot/users`, payload);

        if (res.data.status === 'success') {
            toast.success(res.data.message || 'User added to MikroTik.');
            resetForm();
            emit('saved');
            emit('close');
        } else {
            error.value = res.data.message || 'Failed to add user.';
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to add hotspot user to router.';
        toast.error(error.value);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity"
    >
        <div class="relative w-full max-w-md rounded-2xl border border-border bg-card shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-border px-6 py-4 bg-muted/20">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center">
                        <UserPlus class="w-4 h-4" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">Add Hotspot User</h3>
                        <p class="text-[11px] text-muted-foreground font-mono">
                            {{ routerStore.activeRouter?.name }} (/ip/hotspot/user)
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="p-1 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="p-3 mx-6 mt-4 rounded-xl bg-destructive/10 border border-destructive/20 text-destructive text-xs">
                {{ error }}
            </div>

            <!-- Form -->
            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">
                        Username / MAC Address <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="e.g. user01 or AA:BB:CC:DD:EE:FF"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">
                        Password <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="form.password"
                        type="text"
                        required
                        placeholder="Enter password"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">Bandwidth Profile</label>
                    <select
                        v-model="form.profile"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500 cursor-pointer"
                    >
                        <option value="">Default (or select profile)</option>
                        <option
                            v-for="p in profiles"
                            :key="p.id"
                            :value="p.name"
                        >
                            {{ p.name }} ({{ p.rate_limit || 'Unlimited' }})
                        </option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground">Uptime Limit (Optional)</label>
                        <input
                            v-model="form.time_limit"
                            type="text"
                            placeholder="e.g. 1d, 12h, 30m"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-foreground">Data Limit (MB)</label>
                        <input
                            v-model.number="form.data_limit_mb"
                            type="number"
                            min="0"
                            placeholder="e.g. 1024"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-mono text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-foreground">Comment / Note (Optional)</label>
                    <input
                        v-model="form.comment"
                        type="text"
                        placeholder="e.g. Laptop Staff, TV Smart, Guest"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>

                <!-- Footer Actions -->
                <div class="pt-2 flex items-center justify-end space-x-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 rounded-xl border border-border hover:bg-muted text-xs font-medium text-foreground transition-colors cursor-pointer"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="px-4 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-all cursor-pointer disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>Save to MikroTik</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
