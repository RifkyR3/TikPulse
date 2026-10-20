<script lang="ts">
    import type { HTMLInputAttributes } from "svelte/elements";
    import { cn, type WithElementRef } from "@/lib/utils";

    let {
        ref = $bindable(null),
        value = $bindable(),
        type = "text",
        class: className,
        "data-slot": dataSlot = "input",
        ...restProps
    }: WithElementRef<HTMLInputAttributes> = $props();
</script>

<input
    bind:this={ref}
    data-slot={dataSlot}
    class={cn(
        "border-border bg-background selection:bg-primary dark:bg-input/30 selection:text-primary-foreground ring-offset-background placeholder:text-muted-foreground/60 flex h-9 w-full min-w-0 rounded-lg border px-3 py-1 text-sm shadow-2xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50",
        "focus-visible:border-sky-500 focus-visible:ring-sky-500/20 focus-visible:ring-2",
        "aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive",
        className
    )}
    {type}
    bind:value
    {...restProps}
/>
