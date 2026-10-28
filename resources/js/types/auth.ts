import type { Group, ModulePermission } from './group';

export type UserRole = 'superadmin' | 'admin' | 'operator' | string;

export interface UserSettings {
    theme?: 'light' | 'dark' | 'system';
    accent_color?: 'sky' | 'indigo' | 'emerald' | 'violet' | 'amber' | 'rose';
    preferred_locale?: 'en' | 'id';
    refresh_interval?: number;
    table_compact_mode?: boolean;
    [key: string]: any;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    group_id?: number | null;
    group?: {
        id: number;
        name: string;
        slug: string;
        is_superadmin: boolean;
        all_routers: boolean;
    } | null;
    is_active: boolean;
    is_email_verified?: boolean;
    email_verified_at?: string | null;
    avatar_url?: string | null;
    preferred_locale?: 'en' | 'id';
    settings?: UserSettings;
    is_superadmin?: boolean;
    can_switch_role?: boolean;
    is_simulating?: boolean;
    simulated_group_id?: number | null;
    simulated_group?: {
        id: number;
        name: string;
        slug: string;
        is_superadmin: boolean;
        all_routers: boolean;
    } | null;
    effective_permissions?: Record<string, {
        can_read: boolean;
        can_write: boolean;
        can_update: boolean;
        can_delete: boolean;
    }>;
    allowed_router_ids?: number[] | null;
    created_at?: string;
}

export interface AuthResponseData {
    user: User;
    token: string;
    token_type: string;
    account_type?: string;
    remember?: boolean;
}

