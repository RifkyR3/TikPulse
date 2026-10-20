<script lang="ts">
    import { cn, type WithElementRef } from "@/lib/utils";
    import type { HTMLAttributes } from "svelte/elements";
    import { SIDEBAR_WIDTH_MOBILE } from "./constants";
    import { useSidebar } from "./context.svelte";

    let {
        ref = $bindable(null),
        side = "left",
        variant = "sidebar",
        collapsible = "icon",
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> & {
        side?: "left" | "right";
        variant?: "sidebar" | "floating" | "inset";
        collapsible?: "offcanvas" | "icon" | "none";
    } = $props();

    const sidebar = useSidebar();
</script>

{#if collapsible === "none"}
    <div
        class={cn(
            "bg-sidebar text-sidebar-foreground flex h-full w-[var(--sidebar-width)] flex-col",
            className
        )}
        bind:this={ref}
        {...restProps}
    >
        {@render children?.()}
    </div>
{:else if sidebar.isMobile}
    <!-- Mobile Drawer & Backdrop -->
    {#if sidebar.openMobile}
        <button
            type="button"
            class="fixed inset-0 z-40 bg-black/60 lg:hidden border-0 cursor-default animate-in fade-in duration-200"
            onclick={() => sidebar.setOpenMobile(false)}
            aria-label="Close mobile sidebar"
        ></button>
        <div
            class="fixed inset-y-0 start-0 z-50 flex h-svh w-[var(--sidebar-width-mobile,18rem)] flex-col bg-sidebar text-sidebar-foreground shadow-2xl transition-transform duration-300 ease-out animate-in slide-in-from-left"
            style="--sidebar-width-mobile: {SIDEBAR_WIDTH_MOBILE};"
            data-sidebar="sidebar"
            data-mobile="true"
        >
            <div class="flex h-full w-full flex-col">
                {@render children?.()}
            </div>
        </div>
    {/if}
{:else}
    <!-- Desktop Sidebar Container -->
    <div
        bind:this={ref}
        class="text-sidebar-foreground group peer hidden md:block"
        data-state={sidebar.state}
        data-collapsible={sidebar.state === "collapsed" ? collapsible : ""}
        data-variant={variant}
        data-side={side}
        data-slot="sidebar"
    >
        <!-- Gap element maintaining layout flow -->
        <div
            data-slot="sidebar-gap"
            class={cn(
                "relative w-[var(--sidebar-width)] bg-transparent transition-[width] duration-200 ease-linear",
                "group-data-[collapsible=offcanvas]:w-0",
                "group-data-[collapsible=icon]:w-[var(--sidebar-width-icon)]"
            )}
        ></div>

        <!-- Fixed Sidebar Content -->
        <div
            data-slot="sidebar-container"
            class={cn(
                "fixed inset-y-0 z-20 hidden h-svh w-[var(--sidebar-width)] transition-[left,right,width] duration-200 ease-linear md:flex",
                side === "left"
                    ? "start-0 group-data-[collapsible=offcanvas]:start-[calc(var(--sidebar-width)*-1)]"
                    : "end-0 group-data-[collapsible=offcanvas]:end-[calc(var(--sidebar-width)*-1)]",
                "group-data-[collapsible=icon]:w-[var(--sidebar-width-icon)] group-data-[side=left]:border-e group-data-[side=left]:border-sidebar-border",
                className
            )}
            {...restProps}
        >
            <div
                data-sidebar="sidebar"
                data-slot="sidebar-inner"
                class="bg-sidebar flex h-full w-full flex-col"
            >
                {@render children?.()}
            </div>
        </div>
    </div>
{/if}
