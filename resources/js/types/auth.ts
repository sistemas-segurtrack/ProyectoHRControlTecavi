export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    // `null` sin sesión: /modulos/rutas se sirve sin `auth` middleware (ver
    // RutasController::index) y cubre el listado con un formulario de acceso
    // en vez de redirigir a /login.
    user: User | null;
    roles: string[];
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
