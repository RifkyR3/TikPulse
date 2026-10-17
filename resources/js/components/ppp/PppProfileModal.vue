<script setup lang="ts">
import { ref, reactive, watch } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useLocaleStore } from '@/stores/locale';
import apiClient from '@/services/api';
import type { PppProfileItem, IpPoolItem } from '@/types/ppp';
import { X, Layers, Check, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    profileToEdit?: PppProfileItem | null;
    pools?: IpPoolItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const routerStore = useRouterContextStore();
const localeStore = useLocaleStore();

const form = reactive({
    name: '',
    local_address: '',
    remote_address: '',
    rate_limit: '10M/20M',
    only_one: true,
    dns_server: '8.8.8.8,1.1.1.1',
    price: 250000,
    comment: '',
});

const isSubmitting = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.profileToEdit,
    (item) => {
        if (item) {
            form.name = item.name;
            form.local_address = item.local_address || '';
            form.remote_address = item.remote_address || '';
            form.rate_limit = item.rate_limit || '';
            form.only_one = item.only_one ?? true;
            form.dns_server = item.dns_server || '';
            form.price = item.price || 0;
            form.comment = item.comment || '';
        } else {
            form.name = '';
            form.local_address = '';
            form.remote_address = '';
            form.rate_limit = '10M/20M';
            form.only_one = true;
            form.dns_server = '8.8.8.8,1.1.1.1';
            form.price = 250000;
            form.comment = '';
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
                `/routers/${routerStore.activeRouter.id}/ppp/profiles/${props.profileToEdit.id}`,
                form
            );
        } else {
            await apiClient.post(
                `/routers/${routerStore.activeRouter.id}/ppp/profiles`,
                form
            );
        }
        emit('saved');
        emit('close');
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to save PPP profile.';
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
                class="absolute right-4 top-4 p-1 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <div class="flex items-center space-x-3 mb-5">
                <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                    <Layers class="w-5 h-5" />
                </div>
                <div>
                    <h3 class="text-base font-bold text-foreground">
                        {{ profileToEdit ? (localeStore.locale === 'id' ? 'Edit Profil Bandwidth PPP' : 'Edit PPP Bandwidth Profile') : (localeStore.locale === 'id' ? 'Buat Profil Bandwidth PPP' : 'Create PPP Bandwidth Profile') }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ localeStore.locale === 'id' ? 'Konfigurasi batasan kecepatan (rate-limit) & gateway PPPoE dedicated.' : 'Configure rate-limit & IP gateway for dedicated PPPoE subscribers.' }}
                    </p>
                </div>
            </div>

            <div v-if="error" class="mb-4 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-xs text-red-500">
                {{ error }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5 col-span-2">
                        <label class="text-xs font-semibold text-foreground">
                            {{ localeStore.locale === 'id' ? 'Nama Profil *' : 'Profile Name *' }}
                        </label>
                        <input
                            v-model="form.name"
                            :disabled="!!profileToEdit"
                            type="text"
                            required
                            placeholder="e.g. Dedicated-20M"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none disabled:opacity-60"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label class="text-xs font-semibold text-foreground">
                            {{ localeStore.locale === 'id' ? 'Rate Limit (Upload/Download)' : 'Rate Limit (Rx/Tx)' }}
                        </label>
                        <input
                            v-model="form.rate_limit"
                            type="text"
                            placeholder="e.g. 10M/20M"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                        <p class="text-[10px] text-muted-foreground">e.g. 5M/10M, 20M/50M</p>
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label class="text-xs font-semibold text-foreground">
                            {{ localeStore.locale === 'id' ? 'Biaya Bulanan (Rp)' : 'Monthly Price (Rp)' }}
                        </label>
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="1000"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label class="text-xs font-semibold text-foreground">Local Address (Gateway)</label>
                        <input
                            v-model="form.local_address"
                            type="text"
                            placeholder="e.g. 10.10.10.1"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2 sm:col-span-1">
                        <label class="text-xs font-semibold text-foreground">Remote Address / Pool</label>
                        <input
                            v-model="form.remote_address"
                            list="pool-options"
                            type="text"
                            placeholder="e.g. pool-dedicated"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                        <datalist id="pool-options">
                            <option v-for="pool in pools" :key="pool.name" :value="pool.name">
                                {{ pool.name }} ({{ pool.ranges }})
                            </option>
                        </datalist>
                    </div>

                    <div class="space-y-1.5 col-span-2">
                        <label class="text-xs font-semibold text-foreground">DNS Servers</label>
                        <input
                            v-model="form.dns_server"
                            type="text"
                            placeholder="e.g. 8.8.8.8,1.1.1.1"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="space-y-1.5 col-span-2">
                        <label class="text-xs font-semibold text-foreground">
                            {{ localeStore.locale === 'id' ? 'Keterangan' : 'Comment' }}
                        </label>
                        <input
                            v-model="form.comment"
                            type="text"
                            placeholder="e.g. Paket Kantor Dedicated 20Mbps"
                            class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none"
                        />
                    </div>

                    <div class="col-span-2 pt-1 flex items-center space-x-2">
                        <input
                            id="only_one"
                            v-model="form.only_one"
                            type="checkbox"
                            class="w-4 h-4 rounded text-sky-500 border-border focus:ring-sky-500 cursor-pointer"
                        />
                        <label for="only_one" class="text-xs font-medium text-foreground cursor-pointer select-none">
                            {{ localeStore.locale === 'id' ? 'Hanya 1 Sesi Aktif (Only-One Session)' : 'Enforce Single Session per Secret (Only-One)' }}
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-4 border-t border-border mt-5">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="px-4 py-2 rounded-xl border border-border text-xs font-medium hover:bg-muted transition-colors cursor-pointer"
                    >
                        {{ localeStore.t('common.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="isSubmitting"
                        class="px-5 py-2 rounded-xl bg-sky-500 text-white text-xs font-bold hover:bg-sky-600 transition-colors flex items-center space-x-1.5 shadow-md shadow-sky-500/20 disabled:opacity-50 cursor-pointer"
                    >
                        <Loader2 v-if="isSubmitting" class="w-4 h-4 animate-spin" />
                        <Check v-else class="w-4 h-4" />
                        <span>{{ profileToEdit ? localeStore.t('common.save') : (localeStore.locale === 'id' ? 'Simpan Profil' : 'Save Profile') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
