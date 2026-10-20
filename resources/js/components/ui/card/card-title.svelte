<script lang="ts">
    import type { HTMLAttributes } from "svelte/elements";
    import { cn, type WithElementRef } from "@/lib/utils";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLHeadingElement>> = $props();
</script>

<h3
    bind:this={ref}
    data-slot="card-title"
    class={cn("leading-none font-semibold text-foreground tracking-tight", className)}
    {...restProps}
>
    {@render children?.()}
</h3>
