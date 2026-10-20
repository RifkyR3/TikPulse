<script lang="ts">
    import type { HTMLLabelAttributes } from "svelte/elements";
    import { cn, type WithElementRef } from "@/lib/utils";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLLabelAttributes> = $props();
</script>

<label
    bind:this={ref}
    data-slot="label"
    class={cn(
        "flex items-center gap-2 text-xs font-semibold leading-none select-none tracking-wider uppercase text-foreground/80 peer-disabled:cursor-not-allowed peer-disabled:opacity-50",
        className
    )}
    {...restProps}
>
    {@render children?.()}
</label>
