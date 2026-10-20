<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        orientation = "horizontal",
        class: className,
        "data-slot": dataSlot = "separator",
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> & {
        orientation?: "horizontal" | "vertical";
    } = $props();
</script>

<div
    bind:this={ref}
    data-slot={dataSlot}
    role="separator"
    aria-orientation={orientation}
    class={cn(
        "bg-border shrink-0",
        orientation === "horizontal" ? "h-px w-full" : "min-h-full w-px",
        className
    )}
    {...restProps}
></div>
