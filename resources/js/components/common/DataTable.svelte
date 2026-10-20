<script module lang="ts">
    export interface Column<T = any> {
        id: string;
        header: string;
        sortable?: boolean;
        accessor?: (row: T) => any;
        cell?: (row: T) => any;
        align?: 'left' | 'center' | 'right';
    }
</script>

<script lang="ts" generics="T">
    import {
        ChevronLeft,
        ChevronRight,
        ChevronsLeft,
        ChevronsRight,
        ArrowUpDown,
        ArrowUp,
        ArrowDown,
        Search,
        Inbox,
    } from '@lucide/svelte';
    import type { Snippet } from 'svelte';

    let {
        columns = [],
        data = [],
        isLoading = false,
        emptyMessage = 'No records found',
        showSearch = false,
        searchPlaceholder = 'Search records...',
        showPagination = true,
        pageSize = 10,
        manualPagination = false,
        pageCount = 1,
        pageIndex = 0,
        totalRows,
        onPageChange,
        onPageSizeChange,
        toolbarSnippet,
        customCells = {},
    }: {
        columns: Column<T>[];
        data: T[];
        isLoading?: boolean;
        emptyMessage?: string;
        showSearch?: boolean;
        searchPlaceholder?: string;
        showPagination?: boolean;
        pageSize?: number;
        manualPagination?: boolean;
        pageCount?: number;
        pageIndex?: number;
        totalRows?: number;
        onPageChange?: (page: number) => void;
        onPageSizeChange?: (size: number) => void;
        toolbarSnippet?: Snippet;
        customCells?: Record<string, Snippet<[T, any]>>;
    } = $props();

    let internalPageIndex = $state(0);
    let userPageSize = $state<number | null>(null);
    let internalPageSize = $derived(userPageSize ?? pageSize);
    let searchFilter = $state('');
    let sortColumn = $state<string | null>(null);
    let sortDirection = $state<'asc' | 'desc'>('asc');

    const pageSizes = [10, 25, 50, 100];

    // Filtered data (for client-side mode)
    const filteredData = $derived.by(() => {
        if (manualPagination || !searchFilter.trim()) return data;
        const q = searchFilter.toLowerCase().trim();
        return data.filter((row: any) => {
            return Object.values(row).some((val) =>
                val !== null && val !== undefined && String(val).toLowerCase().includes(q)
            );
        });
    });

    // Sorted data (for client-side mode)
    const sortedData = $derived.by(() => {
        if (manualPagination || !sortColumn) return filteredData;
        const col = columns.find((c) => c.id === sortColumn);
        return [...filteredData].sort((a: any, b: any) => {
            const valA = col?.accessor ? col.accessor(a) : a[sortColumn!];
            const valB = col?.accessor ? col.accessor(b) : b[sortColumn!];
            if (valA === valB) return 0;
            if (valA === null || valA === undefined) return 1;
            if (valB === null || valB === undefined) return -1;
            const res = valA < valB ? -1 : 1;
            return sortDirection === 'asc' ? res : -res;
        });
    });

    // Paginated rows
    const displayRows = $derived.by(() => {
        if (manualPagination) return data;
        const start = internalPageIndex * internalPageSize;
        return sortedData.slice(start, start + internalPageSize);
    });

    const activePageIndex = $derived(manualPagination ? pageIndex : internalPageIndex);
    const activePageSize = $derived(manualPagination ? pageSize : internalPageSize);
    const totalCount = $derived(manualPagination && totalRows !== undefined ? totalRows : filteredData.length);
    const maxPages = $derived(
        manualPagination ? Math.max(1, pageCount) : Math.max(1, Math.ceil(totalCount / activePageSize))
    );

    function toggleSort(col: Column<T>) {
        if (!col.sortable) return;
        if (sortColumn === col.id) {
            if (sortDirection === 'asc') {
                sortDirection = 'desc';
            } else {
                sortColumn = null;
                sortDirection = 'asc';
            }
        } else {
            sortColumn = col.id;
            sortDirection = 'asc';
        }
    }

    function handlePage(idx: number) {
        if (idx < 0 || idx >= maxPages) return;
        if (manualPagination) {
            onPageChange?.(idx + 1);
        } else {
            internalPageIndex = idx;
        }
    }

    function handleSizeChange(newSize: number) {
        if (manualPagination) {
            onPageSizeChange?.(newSize);
        } else {
            userPageSize = newSize;
            internalPageIndex = 0;
        }
    }

    function getCellValue(row: T, col: Column<T>): any {
        if (col.accessor) return col.accessor(row);
        if (col.cell) return col.cell(row);
        return (row as any)[col.id];
    }
</script>

<div class="space-y-3">
    <!-- Optional Search Bar in Table Header -->
    {#if showSearch || toolbarSnippet}
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            {#if showSearch}
                <div class="relative max-w-sm w-full">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                    <input
                        bind:value={searchFilter}
                        type="text"
                        placeholder={searchPlaceholder}
                        class="w-full pl-9 pr-3 py-1.5 text-xs bg-background border border-border rounded-xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />
                </div>
            {/if}
            {#if toolbarSnippet}
                {@render toolbarSnippet()}
            {/if}
        </div>
    {/if}

    <!-- Table Container -->
    <div class="overflow-x-auto rounded-xl border border-border bg-card">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="border-b border-border bg-muted/40 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">
                    {#each columns as col (col.id)}
                        <th
                            class="py-3 px-4 {col.sortable ? 'cursor-pointer select-none hover:text-foreground transition-colors' : ''} {col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'}"
                            onclick={() => toggleSort(col)}
                        >
                            <div class="flex items-center space-x-1.5 {col.align === 'right' ? 'justify-end' : col.align === 'center' ? 'justify-center' : ''}">
                                <span>{col.header}</span>
                                {#if col.sortable}
                                    <span class="inline-flex items-center">
                                        {#if sortColumn === col.id && sortDirection === 'asc'}
                                            <ArrowUp class="w-3.5 h-3.5 text-sky-500" />
                                        {:else if sortColumn === col.id && sortDirection === 'desc'}
                                            <ArrowDown class="w-3.5 h-3.5 text-sky-500" />
                                        {:else}
                                            <ArrowUpDown class="w-3 h-3 text-muted-foreground/40 hover:text-muted-foreground" />
                                        {/if}
                                    </span>
                                {/if}
                            </div>
                        </th>
                    {/each}
                </tr>
            </thead>

            <tbody class="divide-y divide-border/60">
                {#if isLoading}
                    <tr>
                        <td colspan={columns.length} class="py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                                <span class="text-xs font-medium">Loading data...</span>
                            </div>
                        </td>
                    </tr>
                {:else if displayRows.length === 0}
                    <tr>
                        <td colspan={columns.length} class="py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <Inbox class="w-8 h-8 text-muted-foreground/40" />
                                <span class="text-xs font-medium">{emptyMessage}</span>
                            </div>
                        </td>
                    </tr>
                {:else}
                    {#each displayRows as row, rIdx (rIdx)}
                        <tr class="hover:bg-muted/30 transition-colors">
                            {#each columns as col (col.id)}
                                {@const val = getCellValue(row, col)}
                                <td class="py-3 px-4 text-foreground text-xs {col.align === 'right' ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left'}">
                                    {#if customCells[col.id]}
                                        {@render customCells[col.id](row, val)}
                                    {:else}
                                        {val ?? ''}
                                    {/if}
                                </td>
                            {/each}
                        </tr>
                    {/each}
                {/if}
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    {#if showPagination && (totalCount > 0 || maxPages > 1)}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-1 py-1 text-xs text-muted-foreground">
            <div class="flex items-center space-x-2">
                <span>
                    Showing
                    <strong class="font-semibold text-foreground">
                        {totalCount === 0 ? 0 : activePageIndex * activePageSize + 1}
                    </strong>
                    to
                    <strong class="font-semibold text-foreground">
                        {Math.min((activePageIndex + 1) * activePageSize, totalCount)}
                    </strong>
                    of
                    <strong class="font-semibold text-foreground">
                        {totalCount}
                    </strong>
                    entries
                </span>

                <div class="hidden sm:flex items-center space-x-1.5 pl-3 border-l border-border">
                    <span>Rows per page:</span>
                    <select
                        value={activePageSize}
                        onchange={(e) => handleSizeChange(Number((e.target as HTMLSelectElement).value))}
                        class="bg-background border border-border rounded-md px-1.5 py-0.5 text-xs text-foreground focus:outline-none cursor-pointer"
                    >
                        {#each pageSizes as size (size)}
                            <option value={size}>{size}</option>
                        {/each}
                    </select>
                </div>
            </div>

            <div class="flex items-center space-x-1">
                <button
                    type="button"
                    onclick={() => handlePage(0)}
                    disabled={activePageIndex === 0}
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="First page"
                >
                    <ChevronsLeft class="w-3.5 h-3.5" />
                </button>
                <button
                    type="button"
                    onclick={() => handlePage(activePageIndex - 1)}
                    disabled={activePageIndex === 0}
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Previous page"
                >
                    <ChevronLeft class="w-3.5 h-3.5" />
                </button>

                <span class="px-2 font-mono text-[11px]">
                    Page {activePageIndex + 1} of {maxPages}
                </span>

                <button
                    type="button"
                    onclick={() => handlePage(activePageIndex + 1)}
                    disabled={activePageIndex >= maxPages - 1}
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Next page"
                >
                    <ChevronRight class="w-3.5 h-3.5" />
                </button>
                <button
                    type="button"
                    onclick={() => handlePage(maxPages - 1)}
                    disabled={activePageIndex >= maxPages - 1}
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Last page"
                >
                    <ChevronsRight class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    {/if}
</div>
