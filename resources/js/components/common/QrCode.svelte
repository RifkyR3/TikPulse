<script lang="ts">
    import QRCode from 'qrcode';

    let {
        value,
        size = 80,
    }: {
        value: string;
        size?: number;
    } = $props();

    let svgContent = $state('');

    $effect(() => {
        if (!value) {
            svgContent = '';
            return;
        }

        QRCode.toString(value, {
            type: 'svg',
            margin: 0,
            width: size,
            errorCorrectionLevel: 'M',
        })
            .then((svg) => {
                svgContent = svg;
            })
            .catch(() => {
                svgContent = '';
            });
    });
</script>

<div class="inline-flex items-center justify-center shrink-0">
    {#if svgContent}
        {@html svgContent}
    {:else}
        <div style="width: {size}px; height: {size}px;" class="bg-gray-200 animate-pulse rounded"></div>
    {/if}
</div>
