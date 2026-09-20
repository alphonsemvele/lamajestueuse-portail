import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

/** Concatene des classes en ignorant les valeurs vides. */
export function cn(...classes: (string | false | null | undefined)[]): string {
    return classes.filter(Boolean).join(' ');
}

/**
 * Traduction cote client. La cle est la chaine francaise : en francais on
 * renvoie la cle telle quelle, sinon on cherche dans lang/<locale>.json.
 */
export function useT() {
    const { translations } = usePage<SharedProps>().props;

    return (key: string, replace: Record<string, string | number> = {}): string => {
        let value = translations[key] ?? key;

        for (const [token, replacement] of Object.entries(replace)) {
            value = value.split(`:${token}`).join(String(replacement));
        }

        return value;
    };
}

/** Choisit la forme singulier|pluriel selon le nombre. */
export function useChoice() {
    const t = useT();

    return (key: string, count: number, replace: Record<string, string | number> = {}): string => {
        const [singular, plural] = t(key).split('|');

        return (count > 1 ? (plural ?? singular) : singular).split(':count').join(String(count));
    };
}

export const routes = {
    login: '/connexion',
    logout: '/deconnexion',
    register: '/inscription',
    dashboard: '/',
    locale: (code: string) => `/locale/${code}`,
    openApp: (slug: string) => `/applications/${slug}/ouvrir`,
    support: '/support',
    checkIn: '/pointage',
    post: (slug: string) => `/actualites/${slug}`,
    annuaire: '/annuaire',
    informations: {
        index: '/informations',
        create: '/informations/nouvelle',
        store: '/informations',
        edit: (slug: string) => `/informations/${slug}/modifier`,
        update: (slug: string) => `/informations/${slug}`,
        destroy: (slug: string) => `/informations/${slug}`,
        visibility: (slug: string) => `/informations/${slug}/visibilite`,
    },
    admin: {
        dashboard: '/admin',
        applications: '/admin/applications',
        // Application et Post sont lies par slug (getRouteKeyName), pas par id.
        application: (slug: string) => `/admin/applications/${slug}`,
        applicationCreate: '/admin/applications/create',
        applicationEdit: (slug: string) => `/admin/applications/${slug}/edit`,
        applicationAccess: (slug: string) => `/admin/applications/${slug}/acces`,
        applicationSync: (slug: string) => `/admin/applications/${slug}/synchroniser`,
        users: '/admin/users',
        user: (id: number) => `/admin/users/${id}`,
        userCreate: '/admin/users/create',
        userEdit: (id: number) => `/admin/users/${id}/edit`,
        userApprove: (id: number) => `/admin/users/${id}/valider`,
        userReject: (id: number) => `/admin/users/${id}/refuser`,
        categories: '/admin/categories',
        category: (id: number) => `/admin/categories/${id}`,
        posts: '/admin/posts',
        post: (slug: string) => `/admin/posts/${slug}`,
        postCreate: '/admin/posts/create',
        postEdit: (slug: string) => `/admin/posts/${slug}/edit`,
        postVisibility: (slug: string) => `/admin/posts/${slug}/visibilite`,
        logs: '/admin/journal',
    },
};
