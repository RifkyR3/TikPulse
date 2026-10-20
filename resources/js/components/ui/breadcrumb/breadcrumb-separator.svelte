<script lang="ts">
    import { ChevronRight } from "@lucide/svelte";
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLLiAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLLiAttributes> = $props();
</script>

<li
    bind:this={ref}
    data-slot="breadcrumb-separator"
    role="presentation"
    aria-hidden="true"
    class={cn("[&>svg]:size-3.5 text-muted-foreground/60", className)}
    {...restProps}
>
    {#if children}
        {@render children?.()}
    {:else}
        <ChevronRight class="size-3.5" />
    {/if}
</li>
