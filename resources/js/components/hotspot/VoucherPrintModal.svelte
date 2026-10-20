<script lang="ts">
    import { routerStore } from '@/stores/routerContext.svelte';
    import apiClient from '@/services/api';
    import type { VoucherBatchItem, VoucherItem } from '@/types/hotspot';
    import QrCode from '@/components/common/QrCode.svelte';
    import {
        Printer,
        X,
        Receipt,
        Grid,
    } from '@lucide/svelte';

    let {
        isOpen = false,
        batch = null,
        vouchers = [],
        onClose,
        onPrinted,
    }: {
        isOpen: boolean;
        batch: VoucherBatchItem | null;
        vouchers: VoucherItem[];
        onClose: () => void;
        onPrinted?: () => void;
    } = $props();

    let printMode = $state<'thermal_58' | 'thermal_80' | 'sheet_a4'>('thermal_58');
    let showQrCode = $state(true);
    let showPrice = $state(true);
    let hotspotBrandName = $state('TIKPULSE HOTSPOT');
    let footerNote = $state('Connect to WiFi & scan QR or enter code at http://hotspot.net');

    function loginUrl(voucher: VoucherItem): string {
        const host = routerStore.activeRouter?.host || routerStore.activeRouter?.ip_address || '192.168.88.1';
        return `http://${host}/login?username=${encodeURIComponent(voucher.username)}&password=${encodeURIComponent(voucher.password)}`;
    }

    async function handlePrint() {
        if (!batch || vouchers.length === 0) return;

        window.print();

        try {
            if (routerStore.activeRouter) {
                await apiClient.post(`/routers/${routerStore.activeRouter.id}/vouchers/mark-printed`, {
                    batch_id: batch.id,
                });
                onPrinted?.();
            }
        } catch {
            // Continue
        }
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/80 animate-in fade-in duration-150"
    >
        <div
            class="relative w-full max-w-5xl max-h-[95vh] flex flex-col rounded-2xl border border-border bg-card shadow-2xl overflow-hidden print:border-none print:shadow-none print:p-0 print:m-0 print:max-w-none print:w-full print:h-auto"
            role="dialog"
            aria-modal="true"
        >
            <!-- Screen Header (Hidden on Print) -->
            <div class="p-4 sm:p-5 border-b border-border flex items-center justify-between bg-card shrink-0 print:hidden">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center shrink-0">
                        <Printer class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-foreground">
                            Thermal & Voucher Print Engine
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Batch: <strong class="text-foreground font-mono">{batch?.name || 'Vouchers'}</strong> ({vouchers.length} Vouchers)
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        type="button"
                        onclick={handlePrint}
                        class="px-5 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-2 transition-colors cursor-pointer"
                    >
                        <Printer class="w-4 h-4 mr-1.5" />
                        <span>Print Now</span>
                    </button>
                    <button
                        type="button"
                        onclick={onClose}
                        class="p-2 text-muted-foreground hover:text-foreground rounded-lg transition-colors cursor-pointer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Configuration Toolbar (Hidden on Print) -->
            <div class="p-4 bg-muted/40 border-b border-border flex flex-wrap items-center justify-between gap-4 shrink-0 print:hidden text-xs">
                <!-- Mode Switcher -->
                <div class="flex items-center space-x-2">
                    <span class="font-semibold text-muted-foreground uppercase text-[11px]">Format:</span>
                    <div class="inline-flex rounded-xl p-1 bg-background border border-border">
                        <button
                            type="button"
                            onclick={() => (printMode = 'thermal_58')}
                            class="px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer flex items-center space-x-1.5 {printMode === 'thermal_58'
                                ? 'bg-sky-500 text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'}"
                        >
                            <Receipt class="w-3.5 h-3.5 mr-1" />
                            <span>Thermal 58mm</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => (printMode = 'thermal_80')}
                            class="px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer flex items-center space-x-1.5 {printMode === 'thermal_80'
                                ? 'bg-sky-500 text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'}"
                        >
                            <Receipt class="w-3.5 h-3.5 mr-1" />
                            <span>Thermal 80mm</span>
                        </button>
                        <button
                            type="button"
                            onclick={() => (printMode = 'sheet_a4')}
                            class="px-3 py-1.5 rounded-lg font-bold transition-all cursor-pointer flex items-center space-x-1.5 {printMode === 'sheet_a4'
                                ? 'bg-sky-500 text-white shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'}"
                        >
                            <Grid class="w-3.5 h-3.5 mr-1" />
                            <span>Sheet A4 Grid</span>
                        </button>
                    </div>
                </div>

                <!-- Custom Header & Toggles -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center space-x-1.5">
                        <span class="text-muted-foreground">Title:</span>
                        <input
                            bind:value={hotspotBrandName}
                            type="text"
                            class="px-2.5 py-1 text-xs rounded-lg border border-border bg-background text-foreground w-40"
                        />
                    </div>

                    <label class="flex items-center space-x-1.5 cursor-pointer">
                        <input type="checkbox" bind:checked={showQrCode} class="w-3.5 h-3.5 text-sky-500 rounded" />
                        <span>QR Code</span>
                    </label>

                    <label class="flex items-center space-x-1.5 cursor-pointer">
                        <input type="checkbox" bind:checked={showPrice} class="w-3.5 h-3.5 text-sky-500 rounded" />
                        <span>Price Tag</span>
                    </label>
                </div>
            </div>

            <!-- Printable Voucher Area -->
            <div class="flex-1 overflow-y-auto p-4 sm:p-8 bg-muted/20 flex justify-center print:p-0 print:m-0 print:bg-white print:overflow-visible">
                <!-- MODE 1: THERMAL 58MM -->
                {#if printMode === 'thermal_58'}
                    <div
                        id="printable-area"
                        class="w-[58mm] max-w-full bg-white text-black font-mono text-[11px] leading-tight space-y-4 shadow-md p-2 print:shadow-none print:p-0 print:w-[58mm]"
                    >
                        {#each vouchers as voucher (voucher.id)}
                            <div
                                class="voucher-ticket py-3 border-b-2 border-dashed border-gray-400 space-y-2 text-center break-inside-avoid"
                            >
                                <div class="border-b border-gray-300 pb-1.5">
                                    <h4 class="font-extrabold text-[13px] tracking-wider uppercase text-black">
                                        {hotspotBrandName}
                                    </h4>
                                    <p class="text-[10px] text-gray-700 font-sans">
                                        {voucher.profile?.name || batch?.profile?.name || 'Hotspot Voucher'}
                                    </p>
                                </div>

                                {#if showQrCode}
                                    <div class="flex justify-center py-1">
                                        <QrCode value={loginUrl(voucher)} size={80} />
                                    </div>
                                {/if}

                                <div class="bg-gray-100 p-2 rounded border border-gray-300 text-left space-y-1">
                                    {#if voucher.username === voucher.password}
                                        <div>
                                            <span class="text-[9px] text-gray-600 block uppercase font-sans">Voucher Code:</span>
                                            <strong class="text-base font-black tracking-widest block text-center text-black">
                                                {voucher.username}
                                            </strong>
                                        </div>
                                    {:else}
                                        <div class="space-y-0.5">
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-600">USER:</span>
                                                <strong class="font-bold text-xs text-black">{voucher.username}</strong>
                                            </div>
                                            <div class="flex justify-between items-center text-[10px]">
                                                <span class="text-gray-600">PASS:</span>
                                                <strong class="font-bold text-xs text-black">{voucher.password}</strong>
                                            </div>
                                        </div>
                                    {/if}
                                </div>

                                <div class="text-[10px] text-gray-800 space-y-0.5">
                                    {#if voucher.time_limit}
                                        <div class="flex justify-between">
                                            <span>Duration:</span>
                                            <strong class="font-bold">{voucher.time_limit}</strong>
                                        </div>
                                    {/if}
                                    {#if voucher.profile?.rate_limit}
                                        <div class="flex justify-between">
                                            <span>Speed:</span>
                                            <span>{voucher.profile.rate_limit}</span>
                                        </div>
                                    {/if}
                                    {#if showPrice && voucher.profile?.selling_price}
                                        <div class="flex justify-between font-bold text-black border-t border-gray-300 pt-1 mt-1">
                                            <span>Price:</span>
                                            <span>Rp {Number(voucher.profile.selling_price).toLocaleString()}</span>
                                        </div>
                                    {/if}
                                </div>

                                <p class="text-[8px] text-gray-600 pt-1 font-sans">
                                    {footerNote}
                                </p>
                            </div>
                        {/each}
                    </div>
                {:else if printMode === 'thermal_80'}
                    <!-- MODE 2: THERMAL 80MM -->
                    <div
                        id="printable-area"
                        class="w-[80mm] max-w-full bg-white text-black font-mono text-[12px] leading-tight space-y-5 shadow-md p-3 print:shadow-none print:p-0 print:w-[80mm]"
                    >
                        {#each vouchers as voucher (voucher.id)}
                            <div
                                class="voucher-ticket py-4 border-b-2 border-dashed border-gray-400 space-y-2.5 text-center break-inside-avoid"
                            >
                                <div class="border-b border-gray-300 pb-2">
                                    <h4 class="font-black text-sm tracking-widest uppercase text-black">
                                        {hotspotBrandName}
                                    </h4>
                                    <p class="text-[11px] text-gray-700 font-sans font-semibold">
                                        {voucher.profile?.name || batch?.profile?.name || 'Hotspot Access'}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between gap-3 bg-gray-50 p-2.5 rounded border border-gray-300">
                                    {#if showQrCode}
                                        <div class="shrink-0">
                                            <QrCode value={loginUrl(voucher)} size={90} />
                                        </div>
                                    {/if}

                                    <div class="flex-1 text-left space-y-1">
                                        {#if voucher.username === voucher.password}
                                            <div>
                                                <span class="text-[9px] text-gray-600 uppercase font-sans">LOGIN CODE:</span>
                                                <strong class="text-base font-black tracking-widest block text-black">
                                                    {voucher.username}
                                                </strong>
                                            </div>
                                        {:else}
                                            <div class="space-y-1">
                                                <div>
                                                    <span class="text-[9px] text-gray-600 block">USERNAME:</span>
                                                    <strong class="font-bold text-xs text-black">{voucher.username}</strong>
                                                </div>
                                                <div>
                                                    <span class="text-[9px] text-gray-600 block">PASSWORD:</span>
                                                    <strong class="font-bold text-xs text-black">{voucher.password}</strong>
                                                </div>
                                            </div>
                                        {/if}

                                        {#if showPrice && voucher.profile?.selling_price}
                                            <div class="pt-1 text-black font-bold text-xs">
                                                Rp {Number(voucher.profile.selling_price).toLocaleString()}
                                            </div>
                                        {/if}
                                    </div>
                                </div>

                                <div class="flex justify-between text-[10px] text-gray-700 px-1 font-sans">
                                    <span>Duration: <strong>{voucher.time_limit || 'Package Limit'}</strong></span>
                                    <span>Speed: <strong>{voucher.profile?.rate_limit || 'Best Effort'}</strong></span>
                                </div>

                                <p class="text-[9px] text-gray-500 font-sans">
                                    {footerNote}
                                </p>
                            </div>
                        {/each}
                    </div>
                {:else}
                    <!-- MODE 3: SHEET A4 GRID -->
                    <div
                        id="printable-area"
                        class="w-[210mm] max-w-full bg-white text-black p-4 grid grid-cols-3 gap-3 print:p-2 print:m-0 print:w-full print:grid-cols-3"
                    >
                        {#each vouchers as voucher (voucher.id)}
                            <div
                                class="voucher-card border-2 border-dashed border-gray-400 p-2.5 rounded-lg flex flex-col justify-between space-y-2 bg-white break-inside-avoid text-xs"
                            >
                                <div class="flex items-center justify-between border-b border-gray-200 pb-1">
                                    <div>
                                        <span class="font-black text-[11px] text-black tracking-wider block uppercase">{hotspotBrandName}</span>
                                        <span class="text-[9px] text-gray-600 font-sans">{voucher.profile?.name || 'Hotspot'}</span>
                                    </div>
                                    {#if showPrice && voucher.profile?.selling_price}
                                        <span class="font-extrabold text-[11px] text-black">
                                            Rp {Number(voucher.profile.selling_price).toLocaleString()}
                                        </span>
                                    {/if}
                                </div>

                                <div class="flex items-center justify-between gap-2 py-1">
                                    <div class="space-y-0.5 flex-1 font-mono">
                                        {#if voucher.username === voucher.password}
                                            <div>
                                                <span class="text-[8px] text-gray-500 uppercase block">VOUCHER CODE:</span>
                                                <strong class="text-sm font-black tracking-wider text-black block">{voucher.username}</strong>
                                            </div>
                                        {:else}
                                            <div>
                                                <div class="text-[10px]">U: <strong class="text-black">{voucher.username}</strong></div>
                                                <div class="text-[10px]">P: <strong class="text-black">{voucher.password}</strong></div>
                                            </div>
                                        {/if}
                                        <div class="text-[9px] text-gray-600 font-sans">
                                            {voucher.time_limit || 'Package Validity'}
                                        </div>
                                    </div>

                                    {#if showQrCode}
                                        <div class="shrink-0">
                                            <QrCode value={loginUrl(voucher)} size={54} />
                                        </div>
                                    {/if}
                                </div>

                                <div class="border-t border-gray-200 pt-1 flex items-center justify-between text-[8px] text-gray-500 font-sans">
                                    <span>Scan QR to connect</span>
                                    <span class="font-mono">{voucher.profile?.rate_limit || ''}</span>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            </div>
        </div>
    </div>
{/if}

<style>
@media print {
    :global(body) {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    :global(nav), :global(header), :global(aside), :global(.print\:hidden) {
        display: none !important;
    }

    #printable-area {
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        color: #000000 !important;
    }

    .break-inside-avoid {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .voucher-ticket {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    .voucher-card {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
}
</style>
