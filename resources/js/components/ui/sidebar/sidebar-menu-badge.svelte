<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLElement>> = $props();
</script>

<div
    bind:this={ref}
    data-slot="sidebar-menu-badge"
    data-sidebar="menu-badge"
    class={cn(
        "text-sidebar-foreground pointer-events-none absolute end-1.5 flex h-5 min-w-5 items-center justify-center rounded-md px-1 text-[10px] font-semibold tabular-nums select-none",
        "bg-muted border border-border/60",
        "group-data-[collapsible=icon]:hidden",
        className
    )}
    {...restProps}
>
    {@render children?.()}
</div>
