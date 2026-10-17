<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import apiClient from '@/services/api';
import type { ApiResponse } from '@/types/api';
import type { HotspotProfileItem, VoucherBatchItem } from '@/types/hotspot';
import {
    Ticket,
    X,
    Sparkles,
    Check,
    Loader2,
    Eye,
    Layers,
    Clock,
    HardDrive,
    ShieldCheck
} from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'generated', batch: VoucherBatchItem): void;
}>();

const routerStore = useRouterContextStore();

const profiles = ref<HotspotProfileItem[]>([]);
const isLoadingProfiles = ref(false);
const isGenerating = ref(false);
const error = ref<string | null>(null);

const form = reactive({
    name: '',
    profile_id: null as number | null,
    quantity: 25,
    code_format: 'numeric' as 'numeric' | 'alphabet' | 'alphanumeric' | 'random_prefix',
    character_length: 6,
    prefix: '',
    password_mode: 'same_as_username' as 'same_as_username' | 'random_password',
    time_limit: '',
    data_limit_mb: null as number | null,
    sync_to_mikrotik: true,
});

const quickQuantities = [10, 25, 50, 100, 250, 500];

const selectedProfile = computed(() => {
    return profiles.value.find((p) => p.id === form.profile_id) || null;
});

// Live preview sample code
const sampleVoucherCode = computed(() => {
    const pfx = form.prefix ? form.prefix.trim() : (form.code_format === 'random_prefix' ? 'VIP-' : '');
    const len = Math.max(4, form.character_length);
    let sample = '';
    if (form.code_format === 'numeric') {
        sample = '849201537'.slice(0, len);
    } else if (form.code_format === 'alphabet') {
        sample = 'kmpqxyzab'.slice(0, len);
    } else {
        sample = '7x2k9m4b'.slice(0, len);
    }
    return pfx + sample;
});

const fetchProfiles = async () => {
    if (!routerStore.activeRouter) return;
    isLoadingProfiles.value = true;
    try {
        const res = await apiClient.get<ApiResponse<HotspotProfileItem[]>>(
            `/routers/${routerStore.activeRouter.id}/profiles`
        );
        if (res.data.status === 'success' && Array.isArray(res.data.data)) {
            profiles.value = res.data.data;
            if (profiles.value.length > 0 && !form.profile_id) {
                form.profile_id = profiles.value[0].id;
            }
        }
    } catch {
        // Fallback
    } finally {
        isLoadingProfiles.value = false;
    }
};

watch(() => props.isOpen, (open) => {
    if (open) {
        error.value = null;
        fetchProfiles();
        const now = new Date();
        form.name = `Batch-${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}-${String(now.getHours()).padStart(2, '0')}${String(now.getMinutes()).padStart(2, '0')}`;
    }
});

const handleSubmit = async () => {
    if (!routerStore.activeRouter) {
        error.value = 'No router selected.';
        return;
    }

    if (!form.profile_id) {
        error.value = 'Please select a hotspot profile.';
        return;
    }

    error.value = null;
    isGenerating.value = true;

    try {
        const res = await apiClient.post<ApiResponse<{ batch: VoucherBatchItem; vouchers_count: number; synced_count: number }>>(
            `/routers/${routerStore.activeRouter.id}/vouchers/generate`,
            form
        );

        if (res.data.status === 'success' && res.data.data) {
            emit('generated', res.data.data.batch);
            emit('close');
        }
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to generate voucher batch.';
    } finally {
        isGenerating.value = false;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150"
    >
        <div
            class="relative w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all"
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
                <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-sky-500 to-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-sky-500/20">
                    <Sparkles class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">Generate Hotspot Vouchers</h3>
                    <p class="text-xs text-muted-foreground">
                        Batch voucher generator with instant MikroTik RouterOS synchronization
                    </p>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="mb-4 rounded-xl bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                {{ error }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Batch Name & Profile Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Batch Name
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="e.g. Batch-20261015-01"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Hotspot Profile
                        </label>
                        <select
                            v-model="form.profile_id"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer"
                        >
                            <option v-if="profiles.length === 0" disabled :value="null">
                                {{ isLoadingProfiles ? 'Loading profiles...' : 'No profiles found (Create or sync one first)' }}
                            </option>
                            <option
                                v-for="p in profiles"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }} ({{ p.rate_limit || 'Unlimited' }} - Rp {{ Number(p.selling_price).toLocaleString() }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Quantity Quick Selector -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Voucher Quantity
                        </label>
                        <span class="text-xs text-muted-foreground font-mono font-bold">{{ form.quantity }} vouchers</span>
                    </div>

                    <div class="flex flex-wrap gap-2 mb-2">
                        <button
                            v-for="q in quickQuantities"
                            :key="q"
                            type="button"
                            @click="form.quantity = q"
                            :class="[
                                'px-3 py-1 text-xs font-bold rounded-lg border transition-all cursor-pointer',
                                form.quantity === q
                                    ? 'bg-sky-500/10 border-sky-500/30 text-sky-600 dark:text-sky-400'
                                    : 'border-border text-muted-foreground hover:bg-muted'
                            ]"
                        >
                            {{ q }}
                        </button>
                    </div>

                    <input
                        v-model.number="form.quantity"
                        type="number"
                        min="1"
                        max="1000"
                        required
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Code Format & Character Length Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Code Format
                        </label>
                        <select
                            v-model="form.code_format"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer"
                        >
                            <option value="numeric">Numeric (Digits only: 849201)</option>
                            <option value="alphabet">Alphabet (Letters only: kmpqxy)</option>
                            <option value="alphanumeric">Alphanumeric (Letters & Digits)</option>
                            <option value="random_prefix">Custom Prefix + Random</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Length (Digits)
                        </label>
                        <input
                            v-model.number="form.character_length"
                            type="number"
                            min="4"
                            max="12"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Prefix (Optional)
                        </label>
                        <input
                            v-model="form.prefix"
                            type="text"
                            placeholder="e.g. VIP-, CAFE-"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Password Mode -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1.5">
                        Login Mode
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label
                            :class="[
                                'flex items-center space-x-3 p-3 rounded-xl border cursor-pointer transition-all',
                                form.password_mode === 'same_as_username'
                                    ? 'border-sky-500 bg-sky-500/10'
                                    : 'border-border bg-background hover:bg-muted/40'
                            ]"
                        >
                            <input
                                type="radio"
                                value="same_as_username"
                                v-model="form.password_mode"
                                class="w-4 h-4 text-sky-500"
                            />
                            <div>
                                <span class="font-bold text-xs text-foreground block">Username = Password</span>
                                <span class="text-[11px] text-muted-foreground block">Single-field voucher code (Mikhmon standard)</span>
                            </div>
                        </label>

                        <label
                            :class="[
                                'flex items-center space-x-3 p-3 rounded-xl border cursor-pointer transition-all',
                                form.password_mode === 'random_password'
                                    ? 'border-sky-500 bg-sky-500/10'
                                    : 'border-border bg-background hover:bg-muted/40'
                            ]"
                        >
                            <input
                                type="radio"
                                value="random_password"
                                v-model="form.password_mode"
                                class="w-4 h-4 text-sky-500"
                            />
                            <div>
                                <span class="font-bold text-xs text-foreground block">Username ≠ Password</span>
                                <span class="text-[11px] text-muted-foreground block">Separate username and password credentials</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Overrides (Time Limit / Data Limit) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Time Limit (Optional Override)
                        </label>
                        <input
                            v-model="form.time_limit"
                            type="text"
                            placeholder="e.g. 2h, 1d, 30d (blank = profile default)"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Data Quota MB (Optional)
                        </label>
                        <input
                            v-model.number="form.data_limit_mb"
                            type="number"
                            min="1"
                            placeholder="e.g. 1024 for 1GB"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-xs text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Live Preview Card -->
                <div class="p-3.5 rounded-xl border border-sky-500/30 bg-sky-500/5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-sky-600 dark:text-sky-400 flex items-center space-x-1.5">
                            <Eye class="w-3.5 h-3.5" />
                            <span>Voucher Live Sample Preview</span>
                        </span>
                        <span class="text-[11px] font-mono text-muted-foreground">
                            Price: <strong>Rp {{ selectedProfile ? Number(selectedProfile.selling_price).toLocaleString() : '0' }}</strong>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 rounded-lg bg-background border border-border font-mono text-xs">
                        <div>
                            <span class="text-muted-foreground block text-[10px]">USERNAME:</span>
                            <strong class="text-foreground text-sm tracking-wider">{{ sampleVoucherCode }}</strong>
                        </div>
                        <div v-if="form.password_mode === 'random_password'">
                            <span class="text-muted-foreground block text-[10px]">PASSWORD:</span>
                            <strong class="text-foreground text-sm tracking-wider">9x8m2k</strong>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[10px]">SPEED:</span>
                            <span class="text-foreground">{{ selectedProfile?.rate_limit || 'Profile Default' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Sync to MikroTik Checkbox -->
                <div class="flex items-center space-x-3 pt-1">
                    <input
                        id="sync_checkbox"
                        v-model="form.sync_to_mikrotik"
                        type="checkbox"
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <label for="sync_checkbox" class="text-xs text-foreground font-medium cursor-pointer flex items-center space-x-1.5">
                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
                        <span>Synchronize instantly to MikroTik (/ip/hotspot/user)</span>
                    </label>
                </div>

                <!-- Actions -->
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
                        :disabled="isGenerating || profiles.length === 0"
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-500 text-white shadow-md shadow-sky-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        <Loader2 v-if="isGenerating" class="w-3.5 h-3.5 animate-spin" />
                        <Sparkles v-else class="w-3.5 h-3.5" />
                        <span>Generate {{ form.quantity }} Vouchers</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
