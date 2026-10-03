import { cn } from '@/lib/utils';

export interface Institut {
    /** Un identifiant d'application, ou le mot « groupe » pour la maison. */
    id: number | string;
    name: string;
    color: string | null;
    logoUrl: string | null;
}

/**
 * Le groupe lui-même, proposé à côté des instituts.
 *
 * Il n'a pas de ligne en base : un badge rattaché à aucun institut est un
 * badge du groupe. Son logo est bichrome et dessiné pour un fond clair — le
 * bandeau reste donc blanc là où celui d'un institut prend sa couleur.
 */
export const LE_GROUPE: Institut = {
    id: 'groupe',
    name: 'LA MAJESTUEUSE',
    color: '#002484',
    logoUrl: '/images/marque-la-majestueuse.png',
};

export function estLeGroupe(institut: Institut | null | undefined): boolean {
    return (institut ?? LE_GROUPE).id === LE_GROUPE.id;
}

export interface DonneesBadge {
    nomAffiche: string;
    posteAffiche: string | null;
    matricule: string | null;
    photoUrl: string | null;
    initiales?: string | null;
    numero?: string | null;
    institut: Institut | null;
    /** Conservé pour les demandes déjà enregistrées ; le rendu est unique. */
    modele?: string;
}

function Logo({ institut, taille, fond }: { institut: Institut; taille: number; fond: 'clair' | 'sombre' }) {
    if (institut.logoUrl) {
        return <img src={institut.logoUrl} alt="" style={{ height: taille }} className="w-auto object-contain" />;
    }

    // Sans logo téléversé, le sigle de l'institut tient la place.
    return (
        <span
            className={cn(
                'inline-flex items-center rounded px-1.5 font-semibold tracking-wide',
                fond === 'sombre' ? 'bg-white/20 text-white' : 'text-ink-700',
            )}
            style={{ fontSize: taille * 0.52, lineHeight: `${taille}px` }}
        >
            {institut.name}
        </span>
    );
}

function Photo({ donnees, taille, rond }: { donnees: DonneesBadge; taille: number; rond: boolean }) {
    const style = { width: taille, height: taille };

    if (donnees.photoUrl) {
        return (
            <img
                src={donnees.photoUrl}
                alt=""
                style={style}
                className={cn('shrink-0 object-cover ring-2 ring-white', rond ? 'rounded-full' : 'rounded-lg')}
            />
        );
    }

    return (
        <span
            style={{ ...style, fontSize: taille * 0.34 }}
            className={cn(
                'flex shrink-0 items-center justify-center bg-ink-200 font-semibold text-ink-500 ring-2 ring-white',
                rond ? 'rounded-full' : 'rounded-lg',
            )}
        >
            {donnees.initiales ?? '?'}
        </span>
    );
}

/**
 * Le badge, au format carte (54 × 86 mm, portrait). `echelle` multiplie
 * toutes les dimensions : 1 pour l'aperçu à l'écran, 1.35 pour l'impression.
 */
export default function CarteBadge({
    donnees,
    echelle = 1,
    validite = 2,
    className,
}: {
    donnees: DonneesBadge;
    echelle?: number;
    validite?: number;
    className?: string;
}) {
    // Rien de choisi — ou rien à choisir : le badge est celui du groupe.
    const institut = donnees.institut ?? LE_GROUPE;
    const duGroupe = estLeGroupe(institut);
    const couleur = institut.color || '#334155';
    const L = 204 * echelle;
    const H = 324 * echelle;
    const px = (v: number) => v * echelle;

    const expiration = new Date();
    expiration.setFullYear(expiration.getFullYear() + validite);
    const finValidite = `${String(expiration.getMonth() + 1).padStart(2, '0')}/${expiration.getFullYear()}`;

    const pied = (
        <div
            className="flex items-center justify-between"
            style={{ fontSize: px(6.5), paddingLeft: px(12), paddingRight: px(12), paddingBottom: px(9) }}
        >
            <span className="font-medium tracking-wide text-ink-400">LA MAJESTUEUSE</span>
            <span className="text-ink-400">Valide {finValidite}</span>
        </div>
    );

    const identite = (aligne: 'center' | 'left') => (
        <div className={cn('min-w-0', aligne === 'center' ? 'text-center' : 'text-left')}>
            <p
                className="truncate font-semibold leading-tight text-ink-900"
                style={{ fontSize: px(14), letterSpacing: '-0.01em' }}
            >
                {donnees.nomAffiche || 'Nom du porteur'}
            </p>
            {donnees.posteAffiche && (
                <p className="truncate leading-snug text-ink-500" style={{ fontSize: px(8.5), marginTop: px(2) }}>
                    {donnees.posteAffiche}
                </p>
            )}
            {donnees.matricule && (
                <p
                    className="font-mono tracking-wider text-ink-400"
                    style={{ fontSize: px(7.5), marginTop: px(5) }}
                >
                    {donnees.matricule}
                </p>
            )}
        </div>
    );

    const cadre = 'flex flex-col overflow-hidden bg-white';

    // Le badge du groupe : logo de l'institut au centre, photo, identité.
    return (
        <div
            className={cn(cadre, 'shadow-lg shadow-ink-900/10 ring-1 ring-ink-900/10', className)}
            style={{ width: L, height: H, borderRadius: px(10) }}
        >
            <div
                className="relative flex items-center justify-center"
                style={{
                    // Le logo du groupe est dessiné pour un fond clair : son
                    // bandeau est blanc, souligné d'un filet bleu.
                    backgroundColor: duGroupe ? '#ffffff' : couleur,
                    borderBottom: duGroupe ? `${px(2)}px solid ${couleur}` : undefined,
                    padding: `${px(14)}px ${px(12)}px ${px(15)}px`,
                }}
            >
                <Logo institut={institut} taille={px(duGroupe ? 40 : 30)} fond={duGroupe ? 'clair' : 'sombre'} />
                {/* Le numéro se range à droite sans décaler le logo du centre. */}
                {donnees.numero && (
                    <span
                        className={cn('absolute font-mono', duGroupe ? 'text-ink-400' : 'text-white/70')}
                        style={{ fontSize: px(6.5), right: px(10), bottom: px(6) }}
                    >
                        {donnees.numero}
                    </span>
                )}
            </div>

            <div
                className="flex flex-1 flex-col items-center justify-center"
                style={{ gap: px(13), paddingLeft: px(14), paddingRight: px(14) }}
            >
                <Photo donnees={donnees} taille={px(96)} rond />
                {identite('center')}
            </div>

            {pied}
        </div>
    );
}
