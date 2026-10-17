<script setup lang="ts">
import { ref, reactive, computed, watch } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import { useLocaleStore } from '@/stores/locale';
import apiClient from '@/services/api';
import type { DedicatedUserItem, PppProfileItem, IpPoolItem } from '@/types/ppp';
import { X, UserPlus, Check, Loader2, KeyRound, Network, Building, RotateCcw } from 'lucide-vue-next';

const props = defineProps<{
    isOpen: boolean;
    userToEdit?: DedicatedUserItem | null;
    profiles: PppProfileItem[];
    pools: IpPoolItem[];
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'saved'): void;
}>();

const routerStore = useRouterContextStore();
const localeStore = useLocaleStore();

const form = reactive({
    name: '',
    password: '',
    service: 'pppoe' as 'pppoe' | 'any' | 'l2tp' | 'sstp' | 'pptp' | 'ovpn',
    profile: 'default',
    local_address: '',
    remote_address: '',
    caller_id: '',
    routes: '',
    customer_name: '',
    customer_phone: '',
    customer_address: '',
    monthly_price: '' as number | string | null,
    billing_cycle: 'monthly',
    comment: '',
    disabled: false,
});

const isSubmitting = ref(false);
const error = ref<string | null>(null);

const selectedProfile = computed(() => props.profiles.find((p) => p.name === form.profile));
const selectedProfilePrice = computed(() => Number(selectedProfile.value?.price || 0));

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat(localeStore.locale === 'id' ? 'id-ID' : 'en-US', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(val);
};

// Reset monthly price to inherit from profile
const resetToProfilePrice = () => {
    form.monthly_price = '';
};

// When profile is changed, keep monthly_price empty if using auto inheritance
const onProfileChange = () => {
    // If not editing or user cleared price, it automatically reflects selectedProfilePrice
};

watch(
    () => props.userToEdit,
    (item) => {
        if (item) {
            form.name = item.name;
            form.password = item.password || '';
            form.service = item.service || 'pppoe';
            form.profile = item.profile || 'default';
            form.local_address = item.local_address || '';
            form.remote_address = item.remote_address || '';
            form.caller_id = item.caller_id || '';
            form.routes = item.routes || '';
            form.customer_name = item.customer_name || '';
            form.customer_phone = item.customer_phone || '';
            form.customer_address = item.customer_address || '';
            form.monthly_price = item.monthly_price !== null && item.monthly_price !== undefined ? item.monthly_price : '';
            form.billing_cycle = item.billing_cycle || 'monthly';
            form.comment = item.comment || '';
            form.disabled = item.disabled ?? false;
        } else {
            form.name = '';
            form.password = '';
            form.service = 'pppoe';
            form.profile = props.profiles[0]?.name || 'default';
            form.local_address = '';
            form.remote_address = '';
            form.caller_id = '';
            form.routes = '';
            form.customer_name = '';
            form.customer_phone = '';
            form.customer_address = '';
            form.monthly_price = ''; // Empty by default to auto-inherit from profile
            form.billing_cycle = 'monthly';
            form.comment = '';
            form.disabled = false;
        }
        error.value = null;
    },
    { immediate: true }
);

const handleSubmit = async () => {
    if (!routerStore.activeRouter) return;
    error.value = null;
    isSubmitting.value = true;

    // If monthly_price is empty or null, automatically take from profile price
    const parsedPrice = form.monthly_price === '' || form.monthly_price === null || form.monthly_price === undefined
        ? (selectedProfilePrice.value > 0 ? selectedProfilePrice.value : 0)
        : Number(form.monthly_price);

    const payload = {
        ...form,
        monthly_price: isNaN(parsedPrice) ? 0 : parsedPrice,
    };

    try {
        if (props.userToEdit) {
            await apiClient.put(
                `/routers/${routerStore.activeRouter.id}/ppp/secrets/${props.userToEdit.id}`,
                payload
            );
        } else {
            await apiClient.post(
                `/routers/${routerStore.activeRouter.id}/ppp/secrets`,
                payload
            );
        }
        emit('saved');
        emit('close');
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to save dedicated user.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 animate-in fade-in duration-150 overflow-y-auto"
    >
        <div
            class="relative w-full max-w-2xl rounded-2xl border border-border bg-card p-6 shadow-2xl transition-all my-8 max-h-[90vh] flex flex-col"
            role="dialog"
            aria-modal="true"
        >
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-border">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                        <UserPlus class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            {{ userToEdit ? (localeStore.locale === 'id' ? 'Edit Pelanggan Dedicated (PPP Secret)' : 'Edit Dedicated User (PPP Secret)') : (localeStore.locale === 'id' ? 'Tambah Pelanggan Dedicated Baru' : 'Add New Dedicated User') }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{ localeStore.locale === 'id' ? 'Akun PPPoE / L2TP dedicated dengan IP statis dan manajemen langganan.' : 'Dedicated PPPoE / L2TP client with static IP assignment and customer billing.' }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="emit('close')"
                    class="p-1 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer"
                >
                    <X class="w-5 h-5" />
                </button>
            </div>

            <div v-if="error" class="my-3 rounded-xl border border-red-500/20 bg-red-500/10 p-3 text-xs text-red-500">
                {{ error }}
            </div>

            <!-- Body Form with Scroll -->
            <form @submit.prevent="handleSubmit" class="flex-1 overflow-y-auto py-4 space-y-6 pr-1">
                <!-- Section 1: PPPoE / RouterOS Credentials -->
                <div class="space-y-3">
                    <div class="flex items-center space-x-2 text-xs font-bold text-sky-500 uppercase tracking-wider">
                        <KeyRound class="w-4 h-4" />
                        <span>{{ localeStore.locale === 'id' ? 'Kredensial MikroTik (PPP Secret)' : 'MikroTik Credentials (PPP Secret)' }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Username / ID Secret *' : 'Username / Identity *' }}
                            </label>
                            <input
                                v-model="form.name"
                                :disabled="!!userToEdit"
                                type="text"
                                required
                                placeholder="e.g. cust_kantor01"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none disabled:opacity-60"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Password *' : 'Password *' }}
                            </label>
                            <input
                                v-model="form.password"
                                type="text"
                                :required="!userToEdit"
                                placeholder="••••••••"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Service Protocol</label>
                            <select
                                v-model="form.service"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="pppoe">PPPoE (Recommended)</option>
                                <option value="any">Any Service</option>
                                <option value="l2tp">L2TP VPN</option>
                                <option value="sstp">SSTP</option>
                                <option value="pptp">PPTP</option>
                                <option value="ovpn">OpenVPN</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Profil Kecepatan (Profile) *' : 'Bandwidth Profile *' }}
                            </label>
                            <select
                                v-model="form.profile"
                                @change="onProfileChange"
                                required
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="default">default</option>
                                <option v-for="p in profiles" :key="p.id" :value="p.name">
                                    {{ p.name }} {{ p.rate_limit ? `(${p.rate_limit})` : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: IP Assignment & Network Security -->
                <div class="space-y-3 pt-2 border-t border-border/60">
                    <div class="flex items-center space-x-2 text-xs font-bold text-indigo-500 uppercase tracking-wider">
                        <Network class="w-4 h-4" />
                        <span>{{ localeStore.locale === 'id' ? 'Penetapan IP & Keamanan Jaringan' : 'IP Assignment & Network Security' }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'IP Remote / Statis Dedicated' : 'Remote Address (Dedicated IP / Pool)' }}
                            </label>
                            <input
                                v-model="form.remote_address"
                                list="user-pool-options"
                                type="text"
                                placeholder="e.g. 10.10.10.50 or pool-dedicated"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <datalist id="user-pool-options">
                                <option v-for="pool in pools" :key="pool.name" :value="pool.name">
                                    {{ pool.name }} ({{ pool.ranges }})
                                </option>
                            </datalist>
                            <p class="text-[10px] text-muted-foreground">
                                {{ localeStore.locale === 'id' ? 'Isi IP statis agar pelanggan selalu dapat IP tetap.' : 'Assign static IP to ensure dedicated subscriber IP retention.' }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Local Address (Gateway)</label>
                            <input
                                v-model="form.local_address"
                                type="text"
                                placeholder="e.g. 10.10.10.1"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Caller ID (MAC Lock)</label>
                            <input
                                v-model="form.caller_id"
                                type="text"
                                placeholder="e.g. D4:CA:6D:11:22:33"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <p class="text-[10px] text-muted-foreground">
                                {{ localeStore.locale === 'id' ? 'Kunci koneksi ke MAC address modem/router CPE pelanggan.' : 'Binds login strictly to client CPE modem MAC address.' }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">Routes (Optional)</label>
                            <input
                                v-model="form.routes"
                                type="text"
                                placeholder="e.g. 192.168.1.0/24"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 3: Customer & Billing Details -->
                <div class="space-y-3 pt-2 border-t border-border/60">
                    <div class="flex items-center space-x-2 text-xs font-bold text-emerald-500 uppercase tracking-wider">
                        <Building class="w-4 h-4" />
                        <span>{{ localeStore.locale === 'id' ? 'Data Pelanggan & Tagihan' : 'Subscriber Info & Billing' }}</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Nama Pelanggan / Perusahaan' : 'Subscriber / Company Name' }}
                            </label>
                            <input
                                v-model="form.customer_name"
                                type="text"
                                placeholder="e.g. PT Mandiri Nusantara"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'No. WhatsApp / Telepon' : 'WhatsApp / Contact Phone' }}
                            </label>
                            <input
                                v-model="form.customer_phone"
                                type="text"
                                placeholder="e.g. 081234567890"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-foreground">
                                    {{ localeStore.locale === 'id' ? 'Biaya Langganan (Rp / Bulan)' : 'Monthly Rate (Rp / Month)' }}
                                </label>
                                <button
                                    v-if="form.monthly_price !== '' && form.monthly_price !== null"
                                    type="button"
                                    @click="resetToProfilePrice"
                                    class="text-[10px] text-sky-500 hover:text-sky-400 flex items-center space-x-1 cursor-pointer transition-colors"
                                    :title="localeStore.locale === 'id' ? 'Gunakan tarif otomatis dari profil' : 'Reset to auto rate from profile'"
                                >
                                    <RotateCcw class="w-2.5 h-2.5" />
                                    <span>{{ localeStore.locale === 'id' ? 'Ambil Tarif Profil' : 'Use Profile Rate' }}</span>
                                </button>
                            </div>
                            <input
                                v-model.number="form.monthly_price"
                                type="number"
                                min="0"
                                step="1000"
                                :placeholder="selectedProfilePrice > 0 ? ('Auto (' + formatCurrency(selectedProfilePrice) + ')') : (localeStore.locale === 'id' ? 'Otomatis dari profil (Rp 0)' : 'Auto from profile (Rp 0)')"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-mono focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                            <p class="text-[10px] text-muted-foreground flex items-center justify-between">
                                <span>
                                    {{ localeStore.locale === 'id' 
                                        ? 'Kosongkan jika ingin otomatis mengambil tarif paket profil.' 
                                        : 'Leave empty to automatically inherit package profile price.' }}
                                </span>
                                <span v-if="selectedProfilePrice > 0" class="font-medium text-sky-500">
                                    {{ localeStore.locale === 'id' ? 'Tarif profil:' : 'Profile rate:' }} {{ formatCurrency(selectedProfilePrice) }}
                                </span>
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Siklus Penagihan' : 'Billing Cycle' }}
                            </label>
                            <select
                                v-model="form.billing_cycle"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none cursor-pointer"
                            >
                                <option value="monthly">{{ localeStore.locale === 'id' ? 'Bulanan (Monthly)' : 'Monthly' }}</option>
                                <option value="quarterly">{{ localeStore.locale === 'id' ? 'Triwulan (3 Bulan)' : 'Quarterly (3 Months)' }}</option>
                                <option value="annual">{{ localeStore.locale === 'id' ? 'Tahunan (Annual)' : 'Annual' }}</option>
                            </select>
                        </div>

                        <div class="space-y-1.5 col-span-1 sm:col-span-2">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Alamat Pemasangan' : 'Installation Site Address' }}
                            </label>
                            <textarea
                                v-model="form.customer_address"
                                rows="2"
                                placeholder="e.g. Gedung Wisma Lt. 3, Jl. Thamrin No. 12"
                                class="w-full p-2.5 rounded-lg border border-border bg-background text-xs font-medium focus:ring-2 focus:ring-sky-500 focus:outline-none resize-none"
                            ></textarea>
                        </div>

                        <div class="space-y-1.5 col-span-1 sm:col-span-2">
                            <label class="text-xs font-semibold text-foreground">
                                {{ localeStore.locale === 'id' ? 'Catatan / Komentar' : 'Notes / RouterOS Comment' }}
                            </label>
                            <input
                                v-model="form.comment"
                                type="text"
                                placeholder="e.g. TikPulse Dedicated PPPoE - Paket Bisnis"
                                class="w-full h-9 px-3 rounded-lg border border-border bg-background text-xs focus:ring-2 focus:ring-sky-500 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 4: Account Status -->
                <div class="pt-2 border-t border-border/60 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold text-foreground">
                            {{ localeStore.locale === 'id' ? 'Status Akun Dedicated' : 'Dedicated Account Status' }}
                        </div>
                        <p class="text-[10px] text-muted-foreground">
                            {{ localeStore.locale === 'id' ? 'Jika dinonaktifkan, akun akan segera diputus dari jaringan.' : 'If disabled, account is isolated and disconnected immediately.' }}
                        </p>
                    </div>

                    <label class="relative inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="!form.disabled"
                            @change="form.disabled = !($event.target as HTMLInputElement).checked"
                            class="sr-only peer"
                        />
                        <div class="w-9 h-5 bg-muted peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                        <span class="ml-2 text-xs font-bold" :class="!form.disabled ? 'text-emerald-500' : 'text-amber-500'">
                            {{ !form.disabled ? localeStore.t('common.active') : localeStore.t('common.disabled') }}
                        </span>
                    </label>
                </div>

                <!-- Footer Buttons -->
                <div class="flex items-center justify-end space-x-2 pt-4 border-t border-border mt-2">
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
                        <span>{{ userToEdit ? localeStore.t('common.save') : (localeStore.locale === 'id' ? 'Simpan Pelanggan' : 'Save Subscriber') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
