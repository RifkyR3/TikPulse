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
    data-slot="avatar-fallback"
    class={cn("bg-muted text-muted-foreground flex size-full items-center justify-center rounded-lg text-xs font-semibold uppercase", className)}
    {...restProps}
>
    {@render children?.()}
</div>
