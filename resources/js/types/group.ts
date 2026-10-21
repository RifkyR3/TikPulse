export interface ModulePermission {
    module_key: string;
    can_read: boolean;
    can_write: boolean;
    can_update: boolean;
    can_delete: boolean;
}

export interface Group {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_superadmin: boolean;
    all_routers: boolean;
    permissions?: ModulePermission[];
    router_ids?: number[];
    users_count?: number;
    created_at?: string;
}

export interface ModuleItem {
    id: number;
    name: string;
    key: string;
    description: string | null;
    available_actions: string[];
}
