export type RouterProtocol = 'socket' | 'socket_ssl' | 'rest_https';
export type RouterStatus = 'online' | 'offline' | 'error' | 'unknown';

export interface Router {
    id: number;
    name: string;
    host: string;
    api_port: number;
    ip_address?: string;
    port?: number;
    protocol: RouterProtocol;
    username: string;
    is_safe_mode_enabled: boolean;
    use_tls: boolean;
    timeout_seconds: number;
    description?: string | null;
    status: RouterStatus;
    last_connected_at?: string | null;
    ros_version?: string | null;
    board_model?: string | null;
    board_name?: string | null;
    cpu_load?: number | null;
    free_memory_bytes?: number | null;
    total_memory_bytes?: number | null;
    free_memory?: number | null;
    total_memory?: number | null;
    memory_usage_percent?: number | null;
    uptime?: string | null;
    consecutive_failures?: number;
    created_at?: string;
    updated_at?: string;
}

export type RouterItem = Router;

export interface RouterTelemetry {
    'cpu-load'?: string | number;
    'free-memory'?: string | number;
    'total-memory'?: string | number;
    uptime?: string;
    version?: string;
    'board-name'?: string;
    [key: string]: unknown;
}
