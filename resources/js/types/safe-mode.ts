export interface SafeModeStatus {
    router_id: number;
    router_name: string;
    is_safe_mode_enabled: boolean;
    is_bypassed: boolean;
    remaining_bypass_seconds: number | null;
    effective_state: 'PROTECTED' | 'UNLOCKED';
    badge_color: 'emerald' | 'amber';
    status_label: string;
}

export interface SafeModeUnlockResponse {
    router_id: number;
    bypass_token: string;
    expires_in_seconds: number;
    expires_at: string;
}
