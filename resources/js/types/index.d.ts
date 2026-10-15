import type { Cadrage } from '@/lib/cadrage';

export type Role = 'superadmin' | 'admin' | 'manager' | 'employee';
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
    /** Où regarder dans la photo : le fichier reste entier. */
    avatarCadrage: Cadrage | null;
    email: string | null;
    poste: string | null;
    entite: string | null;
    role: Role;
    isAdmin: boolean;
    /** Seul le super administrateur ouvre le tableau de bord d'administration. */
    isSuperAdmin: boolean;
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
    /** « Mon profil » seulement : la photo de celui qui regarde. */
    photoDeProfil?: string | null;
    initiales?: string | null;
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
    /** Visible sur le site public, sans compte. */
    isPublic: boolean;
    coverLegende: string | null;
    rubriqueId: number | null;
    rubrique: Rubrique | null;
    partages: number;
    partagesDetail: { whatsapp: number; facebook: number; lien: number };
    images: PieceJointe[];
    documents: PieceJointe[];
}

export interface Rubrique {
    id: number;
    nom: string;
    slug: string;
    description: string | null;
    couleur: string;
    famille: PostType;
    familleLibelle: string;
    ordre: number;
    actif: boolean;
    articles: number | null;
}

/** Une image de la galerie ou un document a telecharger. */
export interface PieceJointe {
    id: number;
    genre: 'image' | 'document';
    url: string;
    nom: string;
    legende: string | null;
    poids: string;
    extension: string;
    ordre: number;
}

/** Un article tel que le site public l'affiche. */
export interface Article {
    id: number;
    titre: string;
    slug: string;
    chapeau: string;
    imageUrl: string | null;
    rubrique: { nom: string; slug: string; couleur: string } | null;
    aLaUne: boolean;
    publieLe: string | null;
    publieLeLibelle: string | null;
    auteur: string | null;
    vues: number;
    partages: number;
    lecture: number;
    corps?: string | null;
    legende?: string | null;
    images?: PieceJointe[];
    documents?: PieceJointe[];
    partagesDetail?: { whatsapp: number; facebook: number; lien: number };
}

export interface PortalUser {
    id: number;
    name: string;
    lastname: string | null;
    fullName: string;
    initials: string;
    avatarUrl: string | null;
    /** Où regarder dans la photo : le fichier reste entier. */
    avatarCadrage: Cadrage | null;
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
    /** Fait partie du personnel du groupe, donc des dossiers RH. */
    dansLePersonnel: boolean;
    /** Date de création du compte, telle qu'affichée. */
    inscritLe: string | null;
    inscritLeIso: string | null;
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
    /** Les tutoriels sont-ils en service ? Retirés, on n'y renvoie plus. */
    tutorielsEnService: boolean;
    translations: Record<string, string>;
    flash: { status: string | null; registered?: { nom: string; identifiant: string | null } | null };
    errors: Record<string, string>;
    [key: string]: unknown;
}
