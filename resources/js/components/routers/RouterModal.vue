<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue';
import { useRouterContextStore } from '@/stores/routerContext';
import apiClient from '@/services/api';
import type { RouterItem, RouterProtocol } from '@/types/router';
import { X, Server, Check, Loader2, ShieldCheck, Terminal, Copy, Sparkles } from 'lucide-vue-next';
import { PROTOCOL_GUIDES, type ProtocolGuide } from '@/constants/protocolGuides';

const props = defineProps<{
    isOpen: boolean;
    routerToEdit?: RouterItem | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'created'): void;
    (e: 'updated'): void;
}>();

const routerStore = useRouterContextStore();

const isSetupCliVisible = ref(false);
const copiedCli = ref(false);

const form = reactive({
    name: '',
    host: '192.168.88.1',
    api_port: 8728,
    protocol: 'socket',
    username: 'admin',
    password: '',
    is_safe_mode_enabled: true,
    timeout_seconds: 5,
    description: '',
});

const isSubmitting = ref(false);
const error = ref<string | null>(null);

const currentProtocolGuide = computed<ProtocolGuide>(() => {
    return PROTOCOL_GUIDES[(form.protocol as RouterProtocol) || 'socket'];
});

const copyModalCliScript = async () => {
    try {
        await navigator.clipboard.writeText(currentProtocolGuide.value.mikrotikCommands);
        copiedCli.value = true;
        setTimeout(() => {
            copiedCli.value = false;
        }, 2500);
    } catch {
        // Fallback
    }
};

watch(
    () => props.routerToEdit,
    (item) => {
        if (item) {
            form.name = item.name;
            form.host = item.ip_address;
            form.api_port = item.port;
            form.protocol = item.protocol;
            form.username = item.username;
            form.password = '';
            form.is_safe_mode_enabled = item.is_safe_mode_enabled;
            form.timeout_seconds = 5;
            form.description = item.description || '';
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

function resetForm() {
    form.name = '';
    form.host = '192.168.88.1';
    form.api_port = 8728;
    form.protocol = 'socket';
    form.username = 'admin';
    form.password = '';
    form.is_safe_mode_enabled = true;
    form.timeout_seconds = 5;
    form.description = '';
    error.value = null;
}

const onProtocolChange = () => {
    if (form.protocol === 'socket') {
        form.api_port = 8728;
    } else if (form.protocol === 'socket_ssl') {
        form.api_port = 8729;
    } else if (form.protocol === 'rest_https') {
        form.api_port = 443;
    }
};

const handleClose = () => {
    resetForm();
    emit('close');
};

const handleSubmit = async () => {
    error.value = null;
    isSubmitting.value = true;

    try {
        const payload: Record<string, unknown> = {
            name: form.name,
            host: form.host,
            api_port: form.api_port,
            protocol: form.protocol,
            username: form.username,
            is_safe_mode_enabled: form.is_safe_mode_enabled,
            timeout_seconds: form.timeout_seconds,
            description: form.description,
        };

        if (form.password) {
            payload.password = form.password;
        }

        if (props.routerToEdit) {
            await apiClient.put(`/routers/${props.routerToEdit.id}`, payload);
            await routerStore.fetchRouters();
            emit('updated');
        } else {
            if (!form.password) {
                error.value = 'Password is required for new router profiles.';
                isSubmitting.value = false;
                return;
            }
            payload.password = form.password;
            await apiClient.post('/routers', payload);
            await routerStore.fetchRouters();
            emit('created');
        }

        handleClose();
    } catch (err: unknown) {
        const axiosErr = err as { response?: { data?: { message?: string } } };
        error.value = axiosErr.response?.data?.message || 'Failed to save router profile.';
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
                @click="handleClose"
                class="absolute top-4 right-4 text-muted-foreground hover:text-foreground p-1 rounded-lg transition-colors cursor-pointer"
            >
                <X class="w-5 h-5" />
            </button>

            <!-- Header -->
            <div class="flex items-center space-x-3 mb-5">
                <div class="w-11 h-11 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-500 flex items-center justify-center shrink-0">
                    <Server class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-foreground leading-tight">
                        {{ routerToEdit ? 'Edit Router Profile' : 'Register MikroTik Router' }}
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ routerToEdit ? 'Update connection & guardrail settings' : 'Add new router profile for hotspot & monitoring' }}
                    </p>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="mb-4 rounded-lg bg-destructive/10 border border-destructive/20 p-3 text-xs text-destructive font-medium">
                {{ error }}
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Router Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="e.g. Core-RB4011-Hotspot"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Host & Port Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            Host / IP Address
                        </label>
                        <input
                            v-model="form.host"
                            type="text"
                            required
                            placeholder="192.168.88.1"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Port
                        </label>
                        <input
                            v-model.number="form.api_port"
                            type="number"
                            required
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground font-mono focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Protocol with Recommendation and Setup Guide -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Connection Driver
                        </label>
                        <button
                            type="button"
                            @click="isSetupCliVisible = !isSetupCliVisible"
                            class="text-[11px] font-semibold text-sky-500 hover:text-sky-400 flex items-center space-x-1 cursor-pointer"
                        >
                            <Terminal class="w-3 h-3" />
                            <span>{{ isSetupCliVisible ? 'Hide Setup CLI' : 'Show MikroTik Setup CLI' }}</span>
                        </button>
                    </div>

                    <select
                        v-model="form.protocol"
                        @change="onProtocolChange"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 cursor-pointer font-medium"
                    >
                        <option value="socket">Socket API (Port 8728) — ★ Recommended (Local LAN)</option>
                        <option value="socket_ssl">Socket SSL (Port 8729) — Recommended (Secure Remote / WAN)</option>
                        <option value="rest_https">RouterOS REST (Port 443) — Recommended (ROS v7+ Cloud)</option>
                    </select>

                    <!-- Active Protocol Recommendation & Info Pill -->
                    <div class="p-3 rounded-xl border border-sky-500/20 bg-sky-500/5 text-xs space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center space-x-1 font-bold text-sky-600 dark:text-sky-400 text-[11px]">
                                <Sparkles v-if="currentProtocolGuide.isPrimaryRecommended" class="w-3.5 h-3.5 text-amber-500" />
                                <span>{{ currentProtocolGuide.badge }}</span>
                            </span>
                            <span class="text-[10px] text-muted-foreground font-mono">Default Port: {{ currentProtocolGuide.port }}</span>
                        </div>
                        <p class="text-muted-foreground text-[11px] leading-relaxed">
                            {{ currentProtocolGuide.description }}
                        </p>
                    </div>

                    <!-- Collapsible MikroTik Terminal Script -->
                    <div
                        v-if="isSetupCliVisible"
                        class="rounded-xl bg-slate-950 border border-slate-800 text-slate-100 p-3 space-y-2 font-mono text-xs shadow-inner animate-in fade-in duration-150"
                    >
                        <div class="flex items-center justify-between border-b border-slate-800 pb-1.5">
                            <div class="flex items-center space-x-1.5 text-[10px] text-slate-400">
                                <Terminal class="w-3 h-3 text-sky-400" />
                                <span>MikroTik Setup CLI Commands</span>
                            </div>
                            <button
                                type="button"
                                @click="copyModalCliScript"
                                class="px-2 py-0.5 rounded text-[10px] font-sans font-bold transition-all flex items-center space-x-1 cursor-pointer"
                                :class="copiedCli ? 'bg-emerald-500 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200'"
                            >
                                <Check v-if="copiedCli" class="w-3 h-3" />
                                <Copy v-else class="w-3 h-3" />
                                <span>{{ copiedCli ? 'Copied!' : 'Copy Script' }}</span>
                            </button>
                        </div>
                        <pre class="overflow-x-auto text-[10px] leading-relaxed text-sky-300 whitespace-pre-wrap select-all">{{ currentProtocolGuide.mikrotikCommands }}</pre>
                        <p class="text-[9px] text-slate-400 font-sans">
                            💡 {{ currentProtocolGuide.setupNote }}
                        </p>
                    </div>
                </div>

                <!-- Username & Password Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Username
                        </label>
                        <input
                            v-model="form.username"
                            type="text"
                            required
                            placeholder="admin"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                            API Password
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!routerToEdit"
                            :placeholder="routerToEdit ? 'Leave blank to keep' : '••••••••'"
                            class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                        />
                    </div>
                </div>

                <!-- Safe Mode Default Flag -->
                <div class="flex items-center space-x-3 pt-2">
                    <input
                        id="safe_mode_checkbox"
                        v-model="form.is_safe_mode_enabled"
                        type="checkbox"
                        class="w-4 h-4 rounded border-border text-sky-500 focus:ring-sky-500 cursor-pointer"
                    />
                    <label for="safe_mode_checkbox" class="text-xs text-foreground font-medium cursor-pointer flex items-center space-x-1.5">
                        <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
                        <span>Enable Safe Mode Guardrail (Blocks accidental reboots & mass deletions)</span>
                    </label>
                </div>

                <!-- Description / Location -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-foreground mb-1">
                        Location / Description
                    </label>
                    <input
                        v-model="form.description"
                        type="text"
                        placeholder="e.g. Branch Office Floor 2"
                        class="block w-full rounded-lg border border-border bg-background py-2 px-3 text-sm text-foreground placeholder:text-muted-foreground/60 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20"
                    />
                </div>

                <!-- Submit Buttons -->
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
                        :disabled="isSubmitting"
                        class="px-5 py-2 text-xs font-semibold rounded-lg bg-sky-600 hover:bg-sky-500 text-white shadow-md shadow-sky-600/20 flex items-center space-x-2 disabled:opacity-50 transition-colors cursor-pointer"
                    >
                        <Loader2 v-if="isSubmitting" class="w-3.5 h-3.5 animate-spin" />
                        <Check v-else class="w-3.5 h-3.5" />
                        <span>{{ routerToEdit ? 'Save Changes' : 'Save Router Profile' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
