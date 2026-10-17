export interface PppProfileItem {
    id: number;
    router_id: number;
    name: string;
    local_address: string | null;
    remote_address: string | null;
    rate_limit: string | null;
    only_one: boolean;
    dns_server: string | null;
    price: number;
    comment: string | null;
    is_active: boolean;
    dedicated_users_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface DedicatedUserItem {
    id: string;
    router_id: number;
    ppp_profile_id: number | null;
    name: string;
    password?: string;
    service: 'pppoe' | 'any' | 'l2tp' | 'sstp' | 'pptp' | 'ovpn';
    profile: string;
    local_address: string | null;
    remote_address: string | null;
    caller_id: string | null;
    routes: string | null;
    customer_name: string | null;
    customer_phone: string | null;
    customer_address: string | null;
    monthly_price: number;
    billing_cycle: string;
    comment: string | null;
    disabled: boolean;
    synced_to_router: boolean;
    sync_failed_reason: string | null;
    created_at?: string;
    updated_at?: string;
    is_online?: boolean;
    active_session?: PppActiveItem | null;
    profile_detail?: {
        id: number;
        name: string;
        rate_limit: string | null;
    } | null;
}

export interface PppActiveItem {
    id: string;
    name: string;
    service: string;
    caller_id: string;
    address: string;
    uptime: string;
    encoding: string;
    session_id: string;
    radius?: boolean;
}

export interface IpPoolItem {
    name: string;
    ranges: string;
}
