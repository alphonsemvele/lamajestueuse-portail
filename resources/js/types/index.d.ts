export type Role = 'admin' | 'manager' | 'employee';
export type Status = 'active' | 'suspended' | 'pending';
export type AppType = 'application' | 'quick_link' | 'module';
export type PostType = 'news' | 'announcement' | 'billboard';

export interface AuthUser {
    id: number;
    name: string;
    lastname: string | null;
    fullName: string;
    initials: string;
    avatarUrl: string | null;
    email: string | null;
    poste: string | null;
    entite: string | null;
    role: Role;
    isAdmin: boolean;
}

export interface Category {
    id: number;
    name: string;
    slug?: string;
    color: string;
    sortOrder?: number;
    applicationsCount?: number;
}

export interface Application {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    url: string | null;
    destination: string | null;
    host: string;
    moduleKey: string | null;
    type: AppType;
    icon: string;
    color: string;
    cover: string | null;
    logo: string | null;
    coverUrl: string | null;
    logoUrl: string | null;
    isActive: boolean;
    opensNewTab: boolean;
    sortOrder: number;
    clientId: string | null;
    hasClientSecret: boolean;
    usesPortalSignOn: boolean;
    /** Codes des rôles que l'application accepte ; vide = saisie libre. */
    roles: string[];
    /** Rôles avec libellé et description, envoyés par l'application. */
    roleCatalogue: RoleOption[];
    rolesSyncedAt: string | null;
    categoryId: number | null;
    category: Category | null;
    usersCount: number | null;
    pivot: { roleInApp: string | null; roles: string[]; poste: string | null } | null;
}

export interface RoleOption {
    code: string;
    libelle: string;
    description: string | null;
}

export interface UserAccess {
    slug: string;
    name: string;
    color: string;
    /** Libellés des rôles tenus dans l'application. */
    roles: string[];
}

export interface Post {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    body: string | null;
    image: string | null;
    imageUrl: string | null;
    type: PostType;
    isFeatured: boolean;
    isVisible: boolean;
    visibility: 'visible' | 'hidden' | 'draft' | 'scheduled';
    publishedAt: string | null;
    publishedAtLabel: string | null;
    views: number;
    author: string | null;
}

export interface PortalUser {
    id: number;
    name: string;
    lastname: string | null;
    fullName: string;
    initials: string;
    avatarUrl: string | null;
    sexe: 'M' | 'F' | null;
    matricule: string | null;
    email: string | null;
    phone: string | null;
    poste: string | null;
    entite: string | null;
    role: Role;
    status: Status;
    locale: string;
    selfRegistered: boolean;
    applicationsCount: number | null;
    pivot: { roleInApp: string | null; roles: string[]; poste: string | null } | null;
    /** Applications accessibles et rôles tenus (liste du personnel). */
    acces: UserAccess[] | null;
}

export interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface SharedProps {
    appName: string;
    auth: { user: AuthUser | null };
    locale: string;
    translations: Record<string, string>;
    flash: { status: string | null; registered?: string | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
