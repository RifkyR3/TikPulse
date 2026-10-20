<script lang="ts">
    import { Button } from "@/components/ui/button";
    import { cn } from "@/lib/utils";
    import { Menu } from "@lucide/svelte";
    import type { ComponentProps } from "svelte";
    import { useSidebar } from "./context.svelte";

    let {
        ref = $bindable(null),
        class: className,
        onclick,
        ...restProps
    }: ComponentProps<typeof Button> & {
        onclick?: (e: MouseEvent) => void;
    } = $props();

    const sidebar = useSidebar();
</script>

<Button
    bind:ref
    data-sidebar="trigger"
    data-slot="sidebar-trigger"
    variant="ghost"
    size="icon-sm"
    class={cn("size-8 text-foreground", className)}
    type="button"
    onclick={(e) => {
        onclick?.(e);
        sidebar.toggle();
    }}
    {...restProps}
>
    <Menu class="size-4" />
    <span class="sr-only">Toggle Sidebar</span>
</Button>
