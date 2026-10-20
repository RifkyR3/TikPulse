<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        children,
        class: className,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();
</script>

<div
    bind:this={ref}
    data-slot="sidebar-group-label"
    data-sidebar="group-label"
    class={cn(
        "text-muted-foreground/70 flex h-7 shrink-0 items-center px-2 text-[11px] font-semibold tracking-wider uppercase transition-[margin,opacity] duration-200 ease-linear",
        "group-data-[collapsible=icon]:opacity-0 group-data-[collapsible=icon]:h-0 group-data-[collapsible=icon]:overflow-hidden",
        className
    )}
    {...restProps}
>
    {@render children?.()}
</div>
