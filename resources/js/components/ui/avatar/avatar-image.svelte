<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLImgAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        class: className,
        src,
        alt = "Avatar",
        ...restProps
    }: WithElementRef<HTMLImgAttributes> = $props();
</script>

<img
    bind:this={ref}
    data-slot="avatar-image"
    class={cn("aspect-square size-full object-cover", className)}
    {src}
    {alt}
    {...restProps}
/>
