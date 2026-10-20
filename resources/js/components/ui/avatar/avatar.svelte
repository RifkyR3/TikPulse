<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();
</script>

<div
    bind:this={ref}
    data-slot="avatar"
    class={cn("relative flex size-8 shrink-0 overflow-hidden rounded-lg", className)}
    {...restProps}
>
    {@render children?.()}
</div>
