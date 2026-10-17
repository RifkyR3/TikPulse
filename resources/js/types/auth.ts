export type UserRole = 'superadmin' | 'admin' | 'operator';

export interface User {
    id: number;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    avatar_url?: string | null;
    preferred_locale?: 'en' | 'id';
    created_at?: string;
}

export interface AuthResponseData {
    user: User;
    token: string;
    token_type: string;
}
