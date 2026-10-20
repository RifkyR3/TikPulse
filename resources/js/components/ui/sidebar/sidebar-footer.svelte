<script lang="ts">
    import type { HTMLAttributes } from "svelte/elements";
    import { cn, type WithElementRef } from "@/lib/utils";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLElement>> = $props();
</script>

<div
    bind:this={ref}
    data-slot="sidebar-footer"
    data-sidebar="footer"
    class={cn("flex flex-col gap-2 p-2 border-t border-sidebar-border", className)}
    {...restProps}
>
    {@render children?.()}
</div>
