<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { Snippet } from "svelte";
    import type { HTMLButtonAttributes } from "svelte/elements";

    let {
        ref = $bindable(null),
        class: className,
        children,
        child,
        isActive = false,
        size = "default",
        ...restProps
    }: WithElementRef<HTMLButtonAttributes> & {
        isActive?: boolean;
        size?: "default" | "sm" | "lg";
        child?: Snippet<[{ props: Record<string, unknown> }]>;
    } = $props();

    const mergedProps = $derived({
        class: cn(
            "peer/menu-button flex w-full items-center gap-2.5 overflow-hidden rounded-lg p-2 text-start text-xs font-medium outline-hidden transition-all duration-150 cursor-pointer select-none",
            "text-muted-foreground hover:bg-muted/80 hover:text-foreground active:bg-muted",
            isActive
                ? "bg-gradient-to-r from-sky-500/15 to-indigo-500/10 text-sky-600 dark:text-sky-400 font-bold border border-sky-500/20 shadow-2xs"
                : "border border-transparent",
            size === "sm" && "h-7 text-xs",
            size === "default" && "h-9",
            size === "lg" && "h-11 text-sm",
            "group-data-[collapsible=icon]:size-9! group-data-[collapsible=icon]:p-2! group-data-[collapsible=icon]:justify-center",
            "[&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0",
            isActive && "[&>svg]:text-sky-500",
            className
        ),
        "data-slot": "sidebar-menu-button",
        "data-sidebar": "menu-button",
        "data-size": size,
        "data-active": isActive,
        ...restProps,
    });
</script>

{#if child}
    {@render child({ props: mergedProps })}
{:else}
    <button bind:this={ref} {...mergedProps}>
        {@render children?.()}
    </button>
{/if}
