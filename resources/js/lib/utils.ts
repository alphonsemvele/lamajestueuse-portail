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
    tutoriels: {
        index: '/tutoriels',
        show: (cle: string) => `/tutoriels/${cle}`,
    },
    profil: {
        index: '/mon-profil',
        diplomes: '/mon-profil/diplomes',
        diplome: (id: number) => `/mon-profil/diplomes/${id}`,
        documents: '/mon-profil/documents',
        document: (id: number) => `/mon-profil/documents/${id}`,
        documentRetrait: (id: number) => `/mon-profil/documents/${id}`,
    },
    mesBulletins: {
        index: '/mes-bulletins',
        pdf: (id: number, apercu = false) => `/mes-bulletins/${id}/pdf${apercu ? '?apercu=1' : ''}`,
    },
    badges: {
        index: '/badges',
        store: '/badges',
        destroy: (id: number) => `/badges/${id}`,
        gestion: '/badges/gestion',
        pour: '/badges/pour',
        modifier: (id: number) => `/badges/${id}/modifier`,
        rouvrir: (id: number) => `/badges/${id}/rouvrir`,
        impression: '/badges/impression',
        photos: '/badges/photos',
        traiter: (id: number) => `/badges/${id}/traiter`,
        renvoyer: (id: number) => `/badges/${id}/renvoyer`,
    },
    personnel: {
        index: '/personnel',
        agents: '/personnel/agents',
        agentStore: '/personnel/agents',
        matriculesAPourvoir: '/personnel/matricules/a-pourvoir',
        matriculeProchain: '/personnel/matricules/prochain',
        matriculesAttribuer: '/personnel/matricules',
        export: '/personnel/export',
        // Le dossier est identifié par le compte du portail : il n'a pas
        // besoin d'exister pour qu'on ouvre la fiche.
        agent: (user: number) => `/personnel/dossier/${user}`,
        diplomes: (user: number) => `/personnel/dossier/${user}/diplomes`,
        diplome: (id: number) => `/personnel/diplomes/${id}`,
        contrats: (user: number) => `/personnel/dossier/${user}/contrats`,
        contrat: (id: number) => `/personnel/contrats/${id}`,
        carriere: (user: number) => `/personnel/dossier/${user}/carriere`,
        documents: (user: number) => `/personnel/dossier/${user}/documents`,
        rattachement: (user: number) => `/personnel/dossier/${user}/rattachement`,
        document: (id: number) => `/personnel/documents/${id}`,
        trancherPiece: (genre: 'diplome' | 'document', id: number) => `/personnel/pieces/${genre}/${id}`,
        evenement: (id: number) => `/personnel/carriere/${id}`,
        paie: '/personnel/paie',
        bulletins: '/personnel/bulletins',
        paieGenerer: '/personnel/paie/generer',
        paieLot: '/personnel/paie/lot',
        paieVider: '/personnel/paie/periode',
        bulletin: (id: number) => `/personnel/paie/bulletins/${id}`,
        bulletinRecalculer: (id: number) => `/personnel/paie/bulletins/${id}/recalculer`,
        bulletinValider: (id: number) => `/personnel/paie/bulletins/${id}/valider`,
        bulletinPayer: (id: number) => `/personnel/paie/bulletins/${id}/payer`,
        bulletinNote: (id: number) => `/personnel/paie/bulletins/${id}/note`,
        bulletinSupprimer: (id: number) => `/personnel/paie/bulletins/${id}`,
        bulletinRelancer: (id: number) => `/personnel/paie/bulletins/${id}/relancer`,
        bulletinPdf: (id: number, apercu = false) =>
            `/personnel/paie/bulletins/${id}/pdf${apercu ? '?apercu=1' : ''}`,
        ajustements: (contrat: number) => `/personnel/contrats/${contrat}/ajustements`,
        ajustement: (id: number) => `/personnel/ajustements/${id}`,
        employeurs: '/personnel/employeurs',
        employeur: (id: number) => `/personnel/employeurs/${id}`,
        categories: '/personnel/categories',
        categorie: (id: number) => `/personnel/categories/${id}`,
        echelons: (categorie: number) => `/personnel/categories/${categorie}/echelons`,
        echelon: (id: number) => `/personnel/echelons/${id}`,
        indemnites: '/personnel/indemnites',
        indemnite: (id: number) => `/personnel/indemnites/${id}`,
        retenues: '/personnel/retenues',
        retenue: (id: number) => `/personnel/retenues/${id}`,
        profils: '/personnel/profils',
        profilsExport: '/personnel/profils/export',
        profil: (id: number) => `/personnel/profils/${id}`,
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
        userMatricules: '/admin/users/matricules',
        listePersonnel: (apercu = false) => `/admin/users/liste${apercu ? '?apercu=1' : ''}`,
        userEdit: (id: number) => `/admin/users/${id}/edit`,
        userApprove: (id: number) => `/admin/users/${id}/valider`,
        userReject: (id: number) => `/admin/users/${id}/refuser`,
        userRenvoyer: (id: number) => `/admin/users/${id}/renvoyer`,
        categories: '/admin/categories',
        category: (id: number) => `/admin/categories/${id}`,
        posts: '/admin/posts',
        post: (slug: string) => `/admin/posts/${slug}`,
        postCreate: '/admin/posts/create',
        postEdit: (slug: string) => `/admin/posts/${slug}/edit`,
        postVisibility: (slug: string) => `/admin/posts/${slug}/visibilite`,
        modules: '/admin/modules',
        moduleToggle: (slug: string) => `/admin/modules/${slug}/etat`,
        moduleAttribuer: (slug: string) => `/admin/modules/${slug}/attribuer`,
        email: '/admin/email',
        emailTest: '/admin/email/essai',
        emailModeles: '/admin/email/modeles',
        emailApercu: (cle: string) => `/admin/email/modeles/${cle}/apercu`,
        emailEnvoyer: (cle: string) => `/admin/email/modeles/${cle}/envoyer`,
        logs: '/admin/journal',
    },
};
