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
    data-slot="sidebar-inset"
    class={cn(
        "bg-background relative flex w-full flex-1 flex-col min-w-0 transition-all duration-200",
        className
    )}
    {...restProps}
>
    {@render children?.()}
</div>
