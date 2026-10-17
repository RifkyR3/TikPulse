export interface HotspotProfileItem {
    id: number;
    router_id: number;
    name: string;
    shared_users: number;
    rate_limit: string | null;
    validity_duration: string | null;
    validity_unit: 'minutes' | 'hours' | 'days' | null;
    price: number;
    selling_price: number;
    address_pool: string | null;
    on_login_script?: string | null;
    on_logout_script?: string | null;
    is_active: boolean;
    vouchers_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface VoucherBatchItem {
    id: string;
    router_id: number;
    profile_id: number;
    name: string;
    quantity: number;
    prefix: string | null;
    character_length: number;
    code_format: 'numeric' | 'alphabet' | 'alphanumeric' | 'random_prefix';
    password_mode: 'same_as_username' | 'random_password';
    created_by: number | null;
    created_at: string;
    updated_at: string;
    vouchers_count?: number;
    profile?: HotspotProfileItem;
    creator?: { id: number; name: string };
    vouchers?: VoucherItem[];
}

export interface VoucherItem {
    id: string;
    batch_id: string | null;
    router_id: number;
    profile_id: number;
    username: string;
    password: string;
    time_limit: string | null;
    data_limit_bytes: number | null;
    comment: string | null;
    status: 'active' | 'used' | 'expired';
    is_printed: boolean;
    printed_at: string | null;
    first_login_at: string | null;
    expires_at: string | null;
    mac_address: string | null;
    ip_address: string | null;
    synced_to_router: boolean;
    sync_failed_reason: string | null;
    created_at: string;
    updated_at: string;
    batch?: { id: string; name: string };
    profile?: HotspotProfileItem;
}

export interface HotspotUserItem {
    id: string;
    name: string;
    profile: string;
    server: string;
    uptime: string;
    bytes_in: number;
    bytes_out: number;
    bytes_total: number;
    bytes_in_formatted: string;
    bytes_out_formatted: string;
    bytes_total_formatted: string;
    packets_in?: number;
    packets_out?: number;
    limit_uptime?: string | null;
    limit_bytes_total?: number | null;
    disabled: boolean;
    dynamic: boolean;
    comment?: string | null;
}
