export * from './auth';
export * from './navigation';
export * from './ui';

import type { Auth } from './auth';

export interface Flash {
    error?: string | null;
    success?: string | null;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    auth: Auth;
    sidebarOpen: boolean;
    flash: Flash;
    [key: string]: unknown;
};
