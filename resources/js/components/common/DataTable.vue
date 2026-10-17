<script setup lang="ts" generic="TData, TValue">
import { ref } from 'vue';
import {
    useVueTable,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    getFilteredRowModel,
    FlexRender,
    type ColumnDef,
    type SortingState,
    type PaginationState,
} from '@tanstack/vue-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    ArrowUpDown,
    ArrowUp,
    ArrowDown,
    Search,
    Inbox
} from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        columns: ColumnDef<TData, TValue>[];
        data: TData[];
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
    }>(),
    {
        isLoading: false,
        emptyMessage: 'No records found',
        showSearch: false,
        searchPlaceholder: 'Search records...',
        showPagination: true,
        pageSize: 10,
        manualPagination: false,
        pageCount: -1,
        pageIndex: 0,
        totalRows: undefined,
    }
);

const emit = defineEmits<{
    (e: 'update:pageIndex', index: number): void;
    (e: 'update:pageSize', size: number): void;
    (e: 'pageChange', page: number): void;
}>();

const sorting = ref<SortingState>([]);
const globalFilter = ref('');
const internalPagination = ref<PaginationState>({
    pageIndex: props.pageIndex,
    pageSize: props.pageSize,
});

const table = useVueTable({
    get data() {
        return props.data;
    },
    get columns() {
        return props.columns;
    },
    manualPagination: props.manualPagination,
    get pageCount() {
        return props.pageCount;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
        get globalFilter() {
            return globalFilter.value;
        },
        get pagination() {
            if (props.manualPagination) {
                return {
                    pageIndex: props.pageIndex,
                    pageSize: props.pageSize,
                };
            }
            return internalPagination.value;
        },
    },
    onSortingChange: (updaterOrValue) => {
        sorting.value = typeof updaterOrValue === 'function' ? updaterOrValue(sorting.value) : updaterOrValue;
    },
    onGlobalFilterChange: (updaterOrValue) => {
        globalFilter.value = typeof updaterOrValue === 'function' ? updaterOrValue(globalFilter.value) : updaterOrValue;
    },
    onPaginationChange: (updaterOrValue) => {
        const next = typeof updaterOrValue === 'function'
            ? updaterOrValue(props.manualPagination ? { pageIndex: props.pageIndex, pageSize: props.pageSize } : internalPagination.value)
            : updaterOrValue;

        if (props.manualPagination) {
            emit('update:pageIndex', next.pageIndex);
            emit('update:pageSize', next.pageSize);
            emit('pageChange', next.pageIndex + 1);
        } else {
            internalPagination.value = next;
        }
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getPaginationRowModel: props.manualPagination ? undefined : getPaginationRowModel(),
});

const pageSizes = [10, 25, 50, 100];

const totalDisplayRows = () => {
    if (props.manualPagination && props.totalRows !== undefined) {
        return props.totalRows;
    }
    return table.getFilteredRowModel().rows.length;
};

const handlePageChange = (targetIndex: number) => {
    if (props.manualPagination) {
        emit('update:pageIndex', targetIndex);
        emit('pageChange', targetIndex + 1);
    } else {
        table.setPageIndex(targetIndex);
    }
};

const handlePageSizeChange = (newSize: number) => {
    if (props.manualPagination) {
        emit('update:pageSize', newSize);
        emit('pageChange', 1);
    } else {
        table.setPageSize(newSize);
    }
};

const currentPageIndex = () => {
    return props.manualPagination ? props.pageIndex : table.getState().pagination.pageIndex;
};

const currentPageSize = () => {
    return props.manualPagination ? props.pageSize : table.getState().pagination.pageSize;
};

const canPreviousPage = () => {
    return currentPageIndex() > 0;
};

const canNextPage = () => {
    if (props.manualPagination) {
        return props.pageCount > 0 ? currentPageIndex() < props.pageCount - 1 : false;
    }
    return table.getCanNextPage();
};

const totalPages = () => {
    if (props.manualPagination) {
        return Math.max(1, props.pageCount);
    }
    return Math.max(1, table.getPageCount());
};
</script>

<template>
    <div class="space-y-3">
        <!-- Optional Search Bar in Table Header -->
        <div v-if="showSearch" class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <div class="relative max-w-sm w-full">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                <input
                    v-model="globalFilter"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full pl-9 pr-3 py-1.5 text-xs bg-background border border-border rounded-xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-sky-500"
                />
            </div>
            <slot name="toolbar" />
        </div>

        <!-- Table Container -->
        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                        class="border-b border-border bg-muted/40 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider"
                    >
                        <th
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                            :colSpan="header.colSpan"
                            :class="[
                                'py-3 px-4',
                                header.column.getCanSort() ? 'cursor-pointer select-none hover:text-foreground transition-colors' : ''
                            ]"
                            @click="header.column.getCanSort() ? header.column.toggleSorting(undefined) : null"
                        >
                            <div class="flex items-center space-x-1.5">
                                <span v-if="!header.isPlaceholder">
                                    <FlexRender
                                        :render="header.column.columnDef.header"
                                        :props="header.getContext()"
                                    />
                                </span>
                                <span v-if="header.column.getCanSort()" class="inline-flex items-center">
                                    <ArrowUp
                                        v-if="header.column.getIsSorted() === 'asc'"
                                        class="w-3.5 h-3.5 text-sky-500"
                                    />
                                    <ArrowDown
                                        v-else-if="header.column.getIsSorted() === 'desc'"
                                        class="w-3.5 h-3.5 text-sky-500"
                                    />
                                    <ArrowUpDown
                                        v-else
                                        class="w-3 h-3 text-muted-foreground/40 hover:text-muted-foreground"
                                    />
                                </span>
                            </div>
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border/60">
                    <!-- Loading State -->
                    <tr v-if="isLoading">
                        <td :colspan="columns.length" class="py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <div class="w-6 h-6 border-2 border-sky-500 border-t-transparent rounded-full animate-spin"></div>
                                <span class="text-xs font-medium">Loading data...</span>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty State -->
                    <tr v-else-if="table.getRowModel().rows.length === 0">
                        <td :colspan="columns.length" class="py-12 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center space-y-2">
                                <Inbox class="w-8 h-8 text-muted-foreground/40" />
                                <span class="text-xs font-medium">{{ emptyMessage }}</span>
                            </div>
                        </td>
                    </tr>

                    <!-- Data Rows -->
                    <tr
                        v-else
                        v-for="row in table.getRowModel().rows"
                        :key="row.id"
                        class="hover:bg-muted/30 transition-colors"
                    >
                        <td
                            v-for="cell in row.getVisibleCells()"
                            :key="cell.id"
                            class="py-3 px-4 text-foreground text-xs"
                        >
                            <!-- Scoped slot for custom cell renderer based on column id -->
                            <slot
                                :name="`cell-${cell.column.id}`"
                                :row="row.original"
                                :cell="cell"
                                :value="cell.getValue()"
                            >
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div
            v-if="showPagination && (totalDisplayRows() > 0 || totalPages() > 1)"
            class="flex flex-col sm:flex-row items-center justify-between gap-3 px-1 py-1 text-xs text-muted-foreground"
        >
            <div class="flex items-center space-x-2">
                <span>
                    Showing
                    <strong class="font-semibold text-foreground">
                        {{ totalDisplayRows() === 0 ? 0 : currentPageIndex() * currentPageSize() + 1 }}
                    </strong>
                    to
                    <strong class="font-semibold text-foreground">
                        {{ Math.min((currentPageIndex() + 1) * currentPageSize(), totalDisplayRows()) }}
                    </strong>
                    of
                    <strong class="font-semibold text-foreground">
                        {{ totalDisplayRows() }}
                    </strong>
                    entries
                </span>

                <div class="hidden sm:flex items-center space-x-1.5 pl-3 border-l border-border">
                    <span>Rows per page:</span>
                    <select
                        :value="currentPageSize()"
                        @change="handlePageSizeChange(Number(($event.target as HTMLSelectElement).value))"
                        class="bg-background border border-border rounded-md px-1.5 py-0.5 text-xs text-foreground focus:outline-none"
                    >
                        <option v-for="size in pageSizes" :key="size" :value="size">
                            {{ size }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex items-center space-x-1">
                <button
                    type="button"
                    @click="handlePageChange(0)"
                    :disabled="!canPreviousPage()"
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="First page"
                >
                    <ChevronsLeft class="w-3.5 h-3.5" />
                </button>
                <button
                    type="button"
                    @click="handlePageChange(currentPageIndex() - 1)"
                    :disabled="!canPreviousPage()"
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Previous page"
                >
                    <ChevronLeft class="w-3.5 h-3.5" />
                </button>

                <span class="px-2 font-mono text-[11px]">
                    Page {{ currentPageIndex() + 1 }} of {{ totalPages() }}
                </span>

                <button
                    type="button"
                    @click="handlePageChange(currentPageIndex() + 1)"
                    :disabled="!canNextPage()"
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Next page"
                >
                    <ChevronRight class="w-3.5 h-3.5" />
                </button>
                <button
                    type="button"
                    @click="handlePageChange(totalPages() - 1)"
                    :disabled="!canNextPage()"
                    class="p-1.5 rounded-lg border border-border bg-card hover:bg-muted disabled:opacity-40 disabled:cursor-not-allowed transition-colors text-foreground cursor-pointer"
                    title="Last page"
                >
                    <ChevronsRight class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
