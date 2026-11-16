import { type CSSProperties, useLayoutEffect, useRef, useState } from 'react';
import Icon from '@/components/icon';
import { type Cadrage, styleCadrage } from '@/lib/cadrage';
import { cn } from '@/lib/utils';

export interface Institut {
    /** Un identifiant d'application, ou le mot « groupe » pour la maison. */
    id: number | string;
    name: string;
    color: string | null;
    logoUrl: string | null;
}

/** Le bleu de la maison, relevé sur le logo : toutes les cartes le portent. */
const MARINE = '#002484';

/**
 * Le groupe lui-même, proposé à côté des instituts.
 *
 * Il n'a pas de ligne en base : un badge rattaché à aucun institut est un
 * badge du groupe.
 */
export const LE_GROUPE: Institut = {
    id: 'groupe',
    name: 'LA MAJESTUEUSE',
    color: '#f26522',
    logoUrl: '/images/marque-la-majestueuse.png',
};

export function estLeGroupe(institut: Institut | null | undefined): boolean {
    return (institut ?? LE_GROUPE).id === LE_GROUPE.id;
}

/**
 * Ce que le verso imprime au nom du groupe : la devise et les coordonnées.
 *
 * Elles sont les mêmes pour tous les instituts — c'est le même groupe qui
 * répond. Les changer se fait ici, en un endroit.
 */
export const IDENTITE_GROUPE = {
    raisonSociale: 'LA MAJESTUEUSE SARL',
    adresse: 'Mbankomo, NDAZOA',
    telephone: '+237 6 55 34 19 39',
    email: 'contact@lamajestueuse.cm',
};

/**
 * Le format de la carte, en millimètres.
 *
 * C'est la mesure qui fait foi : le PDF pose la carte sur une page de cette
 * taille, et l'écran la dessine à la même échelle. Les points d'écran s'en
 * déduisent — 3,78 points le millimètre, la résolution de référence du web.
 */
export const CARTE_MM = { largeur: 56, hauteur: 87 };

const POINTS_PAR_MM = 96 / 25.4;

/** La devise, et l'icône qui va avec chaque mot. */
const DEVISE: { mot: string; icone: string }[] = [
    { mot: 'Travail', icone: 'academic' },
    { mot: 'Discipline', icone: 'users' },
    { mot: 'Succès', icone: 'award' },
];

export interface DonneesBadge {
    nomAffiche: string;
    /** Où regarder dans la photo : le fichier reste entier. */
    photoCadrage?: Cadrage | null;
    matricule: string | null;
    photoUrl: string | null;
    initiales?: string | null;
    numero?: string | null;
    institut: Institut | null;
    /** Conservé pour les demandes déjà enregistrées ; le rendu est unique. */
    modele?: string;
}

/** Le marine de la maison, et la couleur propre à l'institut. */
function palette(institut: Institut): { marine: string; accent: string } {
    return { marine: MARINE, accent: institut.color || LE_GROUPE.color || '#f26522' };
}

/**
 * Le décor : un coin marine longé d'un filet, et une vague en pied.
 *
 * Il est dessiné en SVG étiré au format de la carte, donc identique à toutes
 * les échelles — l'aperçu à l'écran et la planche d'impression ne peuvent pas
 * diverger. Les deux faces portent le même décor.
 */
function Decor({ marine, accent }: { marine: string; accent: string }) {
    return (
        <svg viewBox="0 0 204 324" preserveAspectRatio="none" aria-hidden="true" className="pointer-events-none absolute inset-0 h-full w-full">
            <path d="M0 0H86C58 24 30 40 0 46Z" fill={marine} />
            <path d="M0 50C32 44 62 28 92 0H110C76 36 38 58 0 64Z" fill={accent} />
            <path d="M0 324V270C54 292 150 292 204 270V324Z" fill={marine} />
            <path d="M0 264C54 286 150 286 204 264V252C150 274 54 274 0 252Z" fill={accent} />
        </svg>
    );
}

/** Le logo de l'institut, ou son nom quand aucun n'a été téléversé. */
function Logo({ institut, hauteur }: { institut: Institut; hauteur: number }) {
    if (institut.logoUrl) {
        return <img src={institut.logoUrl} alt="" style={{ height: hauteur }} className="w-auto object-contain" />;
    }

    return (
        <span
            className="inline-flex items-center font-bold tracking-wide"
            style={{ fontSize: hauteur * 0.46, lineHeight: `${hauteur}px`, color: MARINE }}
        >
            {institut.name}
        </span>
    );
}

/** Le portrait, cerclé de la couleur de l'institut. */
function Photo({ donnees, taille, accent }: { donnees: DonneesBadge; taille: number; accent: string }) {
    const anneau = { boxShadow: `0 0 0 ${taille * 0.02}px #fff, 0 0 0 ${taille * 0.05}px ${accent}` };
    const style = { width: taille, height: taille, ...anneau };

    /*
     * « block » n'est pas decoratif : un span est inline par defaut, et la
     * largeur comme la hauteur n'y produisent aucun effet. Sans lui, le cadre
     * s'effondre, l'image n'a plus de hauteur de reference et deborde la
     * carte entiere.
     */
    if (donnees.photoUrl) {
        return (
            <span style={style} className="block shrink-0 overflow-hidden rounded-full">
                <img
                    src={donnees.photoUrl}
                    alt=""
                    className="h-full w-full"
                    style={styleCadrage(donnees.photoCadrage)}
                />
            </span>
        );
    }

    return (
        <span
            style={{ ...style, fontSize: taille * 0.3 }}
            className="flex shrink-0 items-center justify-center rounded-full bg-ink-100 font-semibold text-ink-400"
        >
            {donnees.initiales ?? '?'}
        </span>
    );
}

/**
 * Le nom du porteur : entier, ou amputé de ses derniers prénoms.
 *
 * Une carte fait 56 mm de large. Un nom de famille suivi de trois prénoms
 * n'y tient pas, et des points de suspension n'apprennent rien à qui lit le
 * badge — ils donnent même un nom faux. On retire donc les prénoms en trop
 * en partant de la fin ; le nom de famille, qui vient en tête, reste
 * toujours. S'il ne tient pas à lui seul, c'est le corps du texte qui cède.
 */
function NomDuPorteur({ nom, className, style }: { nom: string; className?: string; style?: CSSProperties }) {
    const ligne = useRef<HTMLParagraphElement>(null);
    const [affiche, setAffiche] = useState(nom);
    const [corps, setCorps] = useState<number | null>(null);

    // Sans tableau de dépendances : la mesure doit être refaite à chaque
    // rendu, car l'échelle de la carte change d'un écran à l'impression.
    useLayoutEffect(() => {
        const element = ligne.current;

        if (! element) {
            return;
        }

        // Un pixel de garde : une mesure au ras du bord laisse l'arrondi
        // du navigateur rogner la dernière lettre.
        const place = element.getBoundingClientRect().width - 1;
        const mots = nom.split(/\s+/).filter(Boolean);

        if (place <= 0 || mots.length === 0) {
            return;
        }

        const rendu = getComputedStyle(element);
        // On mesure toujours au corps prévu, jamais au corps déjà réduit :
        // sinon la mesure suivante le jugerait à l'aise et le rendrait, en
        // boucle, à sa taille d'origine.
        const base = typeof style?.fontSize === 'number' ? style.fontSize : parseFloat(rendu.fontSize);
        const sonde = document.createElement('span');

        sonde.style.cssText = 'position:absolute;left:-9999px;top:0;white-space:pre;';
        sonde.style.fontFamily = rendu.fontFamily;
        sonde.style.fontWeight = rendu.fontWeight;
        sonde.style.fontStyle = rendu.fontStyle;
        sonde.style.fontSize = `${base}px`;
        sonde.style.letterSpacing = String(style?.letterSpacing ?? rendu.letterSpacing);
        sonde.style.textTransform = rendu.textTransform;
        document.body.appendChild(sonde);

        const largeur = (texte: string) => {
            sonde.textContent = texte;

            return sonde.getBoundingClientRect().width;
        };

        let garde = mots;

        while (garde.length > 1 && largeur(garde.join(' ')) > place) {
            garde = garde.slice(0, -1);
        }

        const retenu = garde.join(' ');
        const seul = largeur(retenu);

        document.body.removeChild(sonde);

        // Le nom de famille seul déborde encore : on le rapetisse plutôt que
        // de le couper, jusqu'aux deux tiers du corps prévu.
        const reduit = seul > place ? Math.max(base * 0.66, (base * place) / seul) : null;

        setAffiche((avant) => (avant === retenu ? avant : retenu));
        setCorps((avant) => (avant === reduit ? avant : reduit));
    });

    return (
        <p ref={ligne} className={className} style={corps ? { ...style, fontSize: corps } : style}>
            {affiche}
        </p>
    );
}

/**
 * Le badge, au format carte (56 × 87 mm, portrait).
 *
 * `echelle` multiplie toutes les dimensions : 1 pour l'aperçu à l'écran,
 * 1.35 pour l'impression. `face` donne le recto — logo, portrait, identité —
 * ou le verso — devise et coordonnées du groupe.
 */
export default function CarteBadge({
    donnees,
    echelle = 1,
    face = 'recto',
    mention,
    sansRelief = false,
    className,
}: {
    donnees: DonneesBadge;
    echelle?: number;
    face?: 'recto' | 'verso';
    /** La mention imprimée au verso, venue de config/badges.php. */
    mention?: string | null;
    /**
     * Sans l'ombre ni le filet de l'écran : ce qui part à l'impression ou
     * dans un PDF ne porte que le dessin de la carte.
     */
    sansRelief?: boolean;
    className?: string;
}) {
    // Rien de choisi — ou rien à choisir : le badge est celui du groupe.
    const institut = donnees.institut ?? LE_GROUPE;
    const { marine, accent } = palette(institut);

    const L = CARTE_MM.largeur * POINTS_PAR_MM * echelle;
    const H = CARTE_MM.hauteur * POINTS_PAR_MM * echelle;
    const px = (v: number) => v * echelle;

    // L'en-tête des deux faces : le logo, puis le nom de l'institut.
    const enTete = (hauteurLogo: number) => (
        <>
            <Logo institut={institut} hauteur={px(hauteurLogo)} />
            <p
                className="text-center font-bold uppercase leading-tight"
                style={{ marginTop: px(4), fontSize: px(9.5), letterSpacing: '0.02em', color: marine }}
            >
                {institut.name}
            </p>
        </>
    );

    const cadre = cn(
        'relative flex flex-col items-center overflow-hidden bg-white',
        !sansRelief && 'shadow-lg shadow-ink-900/10',
        className,
    );

    /*
     * Le filet du pourtour. Il part aussi à l'impression : c'est lui qui
     * donne le trait de coupe, et il tient la carte sur une planche blanche.
     */
    const bordure = { border: `${Math.max(1, px(1))}px solid ${MARINE}66` };

    if (face === 'verso') {
        return (
            <div className={cadre} style={{ width: L, height: H, borderRadius: px(10), ...bordure }}>
                <Decor marine={marine} accent={accent} />

                <div
                    className="relative flex w-full flex-1 flex-col items-center"
                    style={{ paddingTop: px(20), paddingLeft: px(16), paddingRight: px(16) }}
                >
                    {enTete(44)}

                    {/*
                      * Pas de fonction sur la carte : une mutation la rend
                      * fausse, et un badge se garde des annees.
                      */}
                    <span className="rounded-full" style={{ marginTop: px(10), width: px(44), height: px(2.5), backgroundColor: accent }} />

                    {/* La devise, un mot par colonne, séparés d'un filet. */}
                    <div className="flex w-full items-start justify-center" style={{ marginTop: px(16), gap: px(10) }}>
                        {DEVISE.map(({ mot, icone }, rang) => (
                            <div key={mot} className="flex items-start" style={{ gap: px(10) }}>
                                {rang > 0 && <span style={{ width: 1, height: px(26), backgroundColor: '#e2e8f0' }} />}
                                <div className="flex flex-col items-center" style={{ width: px(42) }}>
                                    <Icon name={icone} style={{ width: px(16), height: px(16), color: accent }} />
                                    <span
                                        className="text-center font-semibold uppercase leading-none"
                                        style={{ marginTop: px(4), fontSize: px(6.5), letterSpacing: '0.04em', color: marine }}
                                    >
                                        {mot}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Les coordonnées du groupe : les mêmes pour tous. */}
                    <div className="flex w-full flex-col" style={{ marginTop: px(16), gap: px(8) }}>
                        {[
                            { icone: 'pin', texte: IDENTITE_GROUPE.adresse },
                            { icone: 'phone', texte: IDENTITE_GROUPE.telephone },
                            { icone: 'mail', texte: IDENTITE_GROUPE.email },
                        ].map(({ icone, texte }) => (
                            <div key={icone} className="flex items-center" style={{ gap: px(8) }}>
                                <span
                                    className="flex shrink-0 items-center justify-center rounded-full"
                                    style={{ width: px(17), height: px(17), backgroundColor: marine }}
                                >
                                    <Icon name={icone} style={{ width: px(9.5), height: px(9.5), color: '#fff' }} />
                                </span>
                                <span className="truncate font-medium" style={{ fontSize: px(7.5), color: '#1f2937' }}>
                                    {texte}
                                </span>
                            </div>
                        ))}
                    </div>

                    {mention && (
                        <>
                            <span style={{ marginTop: px(12), width: '100%', height: 1, backgroundColor: '#e2e8f0' }} />
                            <p
                                className="text-center"
                                style={{ marginTop: px(7), fontSize: px(5.4), lineHeight: 1.5, color: '#64748b' }}
                            >
                                {mention}
                            </p>
                        </>
                    )}
                </div>

                <div
                    className="relative flex w-full items-center justify-center"
                    style={{ paddingBottom: px(8), fontSize: px(6.5) }}
                >
                    <span className="font-medium tracking-wide text-white/85">{IDENTITE_GROUPE.raisonSociale}</span>
                </div>
            </div>
        );
    }

    return (
        <div className={cadre} style={{ width: L, height: H, borderRadius: px(10), ...bordure }}>
            <Decor marine={marine} accent={accent} />

            <div
                className="relative flex w-full flex-1 flex-col items-center"
                style={{ paddingTop: px(18), paddingLeft: px(14), paddingRight: px(14) }}
            >
                {enTete(48)}

                <p
                    className="flex items-center font-semibold uppercase"
                    style={{ marginTop: px(6), fontSize: px(6.5), letterSpacing: '0.06em', color: '#475569', gap: px(5) }}
                >
                    {DEVISE.map(({ mot }, rang) => (
                        <span key={mot} className="flex items-center" style={{ gap: px(5) }}>
                            {rang > 0 && <span style={{ width: 1, height: px(7), backgroundColor: accent }} />}
                            {mot}
                        </span>
                    ))}
                </p>

                <div style={{ marginTop: px(10) }}>
                    <Photo donnees={donnees} taille={px(90)} accent={accent} />
                </div>

                <NomDuPorteur
                    nom={donnees.nomAffiche || 'Nom du porteur'}
                    className="w-full overflow-hidden text-center font-bold uppercase leading-tight whitespace-nowrap"
                    style={{ marginTop: px(10), fontSize: px(15), letterSpacing: '-0.01em', color: marine }}
                />

                <span className="rounded-full" style={{ marginTop: px(5), width: px(52), height: px(2.5), backgroundColor: accent }} />

                {donnees.matricule && (
                    <span
                        className="inline-flex items-center rounded-full"
                        style={{
                            marginTop: px(10),
                            gap: px(6),
                            paddingLeft: px(9),
                            paddingRight: px(11),
                            paddingTop: px(4),
                            paddingBottom: px(4),
                            backgroundColor: '#eef2f9',
                        }}
                    >
                        <Icon name="id-card" style={{ width: px(10), height: px(10), color: marine }} />
                        <span className="font-semibold" style={{ fontSize: px(8), color: marine }}>
                            Matricule : {donnees.matricule}
                        </span>
                    </span>
                )}
            </div>
        </div>
    );
}

/**
 * Le badge avec ses deux faces, et de quoi les retourner.
 *
 * Partout où l'on montre un badge — la demande, le guichet, le profil — on
 * montre la même chose : on ne se contente pas du recto, le verso porte les
 * coordonnées du groupe.
 */
export function ApercuBadge({
    donnees,
    echelle = 1,
    mention,
    className,
}: {
    donnees: DonneesBadge;
    echelle?: number;
    mention?: string | null;
    className?: string;
}) {
    const [face, setFace] = useState<'recto' | 'verso'>('recto');

    return (
        <div className={cn('flex flex-col items-center gap-3', className)}>
            <CarteBadge donnees={donnees} echelle={echelle} face={face} mention={mention} />

            <div className="inline-flex rounded-full border border-ink-200 p-0.5 dark:border-white/10">
                {(['recto', 'verso'] as const).map((cote) => (
                    <button
                        key={cote}
                        type="button"
                        onClick={() => setFace(cote)}
                        className={cn(
                            'rounded-full px-3.5 py-1 text-xs font-medium capitalize transition',
                            face === cote
                                ? 'bg-ink-900 text-white dark:bg-white dark:text-ink-900'
                                : 'text-ink-500 hover:text-ink-800 dark:text-ink-400 dark:hover:text-white',
                        )}
                    >
                        {cote}
                    </button>
                ))}
            </div>
        </div>
    );
}
