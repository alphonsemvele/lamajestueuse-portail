import { Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import PhotoField from '@/components/photo-field';
import { Alert, Card, Input, Select, Textarea } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes } from '@/lib/utils';
import type { SharedProps } from '@/types';
import CarteBadge, { type DonneesBadge, type Institut } from './carte';

interface Demande {
    id: number;
    numero: string;
    nomAffiche: string;
    posteAffiche: string | null;
    matricule: string | null;
    photoUrl: string | null;
    modele: string;
    motifLibelle: string;
    statut: string;
    statutLibelle: string;
    motifRefus: string | null;
    institut: Institut | null;
    demandeLe: string | null;
    traiteLe: string | null;
    traitePar: string | null;
}

interface Props {
    demandes: Demande[];
    enCours: boolean;
    instituts: (Institut & { poste: string | null })[];
    identite: {
        nom: string;
        matricule: string | null;
        poste: string | null;
        photoUrl: string | null;
        initiales: string;
    };
    motifs: Record<string, string>;
    validite: number;
    peutGerer: boolean;
}

const TONS: Record<string, string> = {
    en_attente: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    approuvee: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200',
    imprimee: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200',
    remise: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    refusee: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

export default function MonBadge({
    demandes,
    enCours,
    instituts,
    identite,
    motifs,
    validite,
    peutGerer,
}: Props) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    // Un seul institut : il est retenu d'office, sans question posée.
    const institutUnique = instituts.length === 1 ? instituts[0] : null;

    const [photoApercu, setPhotoApercu] = useState<string | null>(identite.photoUrl);

    const formulaire = useForm<{
        nom_affiche: string;
        poste_affiche: string;
        motif: string;
        application_id: string;
        commentaire: string;
        photo_file: File | null;
    }>({
        nom_affiche: identite.nom,
        poste_affiche: institutUnique?.poste ?? identite.poste ?? '',
        motif: 'premiere',
        application_id: institutUnique ? String(institutUnique.id) : '',
        commentaire: '',
        photo_file: null,
    });

    const institutChoisi =
        instituts.find((i) => String(i.id) === formulaire.data.application_id) ?? institutUnique ?? null;

    const apercu = {
        nomAffiche: formulaire.data.nom_affiche,
        posteAffiche: formulaire.data.poste_affiche || null,
        matricule: identite.matricule,
        photoUrl: photoApercu,
        initiales: identite.initiales,
        institut: institutChoisi,
    };

    // Badge montré en grand : après l'envoi, ou depuis la liste des demandes.
    const [agrandi, setAgrandi] = useState<DonneesBadge | null>(null);
    const [envoye, setEnvoye] = useState(false);

    const envoyer = (event: FormEvent) => {
        event.preventDefault();

        // On fige ce qui part : le formulaire se vide juste après.
        const soumis = { ...apercu };

        formulaire.post(routes.badges.store, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                formulaire.reset('commentaire', 'photo_file');
                setPhotoApercu(identite.photoUrl);
                setEnvoye(true);
                setAgrandi(soumis);
            },
        });
    };

    return (
        <PortalLayout title="Badge">
            <div className="mx-auto max-w-[1100px] space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-ink-900 dark:text-white">Mon badge</h1>
                        <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                            Choisissez le nom qui figurera sur votre badge et le modèle qui vous convient.
                        </p>
                    </div>
                    {peutGerer && (
                        <Link
                            href={routes.badges.gestion}
                            className="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:bg-white/5 dark:text-ink-200"
                        >
                            <Icon name="layers" className="h-4 w-4" />
                            Traiter les demandes
                        </Link>
                    )}
                </div>

                {errors?.badge && <Alert tone="danger">{errors.badge}</Alert>}

                {enCours ? (
                    <Alert tone="info" icon="clock">
                        Une demande est en cours. Vous pourrez en faire une nouvelle une fois celle-ci remise.
                    </Alert>
                ) : (
                    <form onSubmit={envoyer} className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
                        <div className="space-y-5">
                            <Card className="p-5">
                                <h2 className="text-sm font-semibold text-ink-900 dark:text-white">
                                    Ce qui sera imprimé
                                </h2>

                                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                    <label className="block">
                                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                            Nom à faire figurer
                                        </span>
                                        <Input
                                            value={formulaire.data.nom_affiche}
                                            onChange={(event) => formulaire.setData('nom_affiche', event.target.value)}
                                            maxLength={80}
                                            required
                                        />
                                        {formulaire.errors.nom_affiche ? (
                                            <span className="mt-1 block text-xs font-medium text-red-600">
                                                {formulaire.errors.nom_affiche}
                                            </span>
                                        ) : (
                                            <span className="mt-1 block text-xs text-ink-400">
                                                Tel que vous souhaitez être appelé, pas forcément l'état civil complet.
                                            </span>
                                        )}
                                    </label>

                                    <label className="block">
                                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                            Fonction
                                        </span>
                                        <Input
                                            value={formulaire.data.poste_affiche}
                                            onChange={(event) => formulaire.setData('poste_affiche', event.target.value)}
                                            maxLength={120}
                                        />
                                        {formulaire.errors.poste_affiche && (
                                            <span className="mt-1 block text-xs font-medium text-red-600">
                                                {formulaire.errors.poste_affiche}
                                            </span>
                                        )}
                                    </label>
                                </div>

                                <div className="mt-5">
                                    <span className="mb-2 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                        Photo
                                    </span>
                                    <PhotoField
                                        preview={photoApercu}
                                        initials={identite.initiales}
                                        error={formulaire.errors.photo_file}
                                        hint="Par défaut, la photo de votre compte."
                                        onPick={(fichier) => {
                                            formulaire.setData('photo_file', fichier);
                                            setPhotoApercu(URL.createObjectURL(fichier));
                                        }}
                                        onDrop={() => {
                                            formulaire.setData('photo_file', null);
                                            setPhotoApercu(identite.photoUrl);
                                        }}
                                    />
                                </div>
                            </Card>

                            {/* Le choix du logo : posé seulement s'il y a matière à choisir. */}
                            <Card className="p-5">
                                <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Logo de l'institut</h2>

                                {instituts.length === 0 && (
                                    <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                        Aucun institut ne vous est rattaché : votre badge portera les seules couleurs du
                                        groupe. Signalez-le aux ressources humaines si c'est une erreur.
                                    </p>
                                )}

                                {institutUnique && (
                                    <p className="mt-2 text-sm text-ink-600 dark:text-ink-300">
                                        Votre badge portera le logo de <strong>{institutUnique.name}</strong>.
                                    </p>
                                )}

                                {instituts.length > 1 && (
                                    <>
                                        <p className="mt-1 text-xs text-ink-500 dark:text-ink-400">
                                            Vous servez {instituts.length} instituts : choisissez celui dont le logo
                                            figurera sur votre badge.
                                        </p>

                                        <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                            {instituts.map((institut) => {
                                                const actif = formulaire.data.application_id === String(institut.id);

                                                return (
                                                    <button
                                                        key={institut.id}
                                                        type="button"
                                                        onClick={() => {
                                                            formulaire.setData('application_id', String(institut.id));
                                                            if (institut.poste) {
                                                                formulaire.setData('poste_affiche', institut.poste);
                                                            }
                                                        }}
                                                        className={cn(
                                                            'flex items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition',
                                                            actif
                                                                ? 'border-transparent ring-2'
                                                                : 'border-ink-200 hover:bg-ink-50 dark:border-white/10 dark:hover:bg-white/5',
                                                        )}
                                                        style={actif ? { ['--tw-ring-color' as string]: institut.color ?? '#334155' } : undefined}
                                                    >
                                                        <span
                                                            className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg text-[10px] font-semibold text-white"
                                                            style={{ backgroundColor: institut.color ?? '#334155' }}
                                                        >
                                                            {institut.logoUrl ? (
                                                                <img src={institut.logoUrl} alt="" className="h-full w-full object-contain p-1" />
                                                            ) : (
                                                                institut.name.slice(0, 4)
                                                            )}
                                                        </span>
                                                        <span className="min-w-0">
                                                            <span className="block truncate text-sm font-medium text-ink-900 dark:text-white">
                                                                {institut.name}
                                                            </span>
                                                            {institut.poste && (
                                                                <span className="block truncate text-xs text-ink-500 dark:text-ink-400">
                                                                    {institut.poste}
                                                                </span>
                                                            )}
                                                        </span>
                                                        {actif && <Icon name="check" className="ml-auto h-4 w-4 text-ink-400" />}
                                                    </button>
                                                );
                                            })}
                                        </div>

                                        {formulaire.errors.application_id && (
                                            <p className="mt-2 text-xs font-medium text-red-600">
                                                {formulaire.errors.application_id}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Card>

                            <Card className="p-5">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="block">
                                        <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                            Motif de la demande
                                        </span>
                                        <Select
                                            value={formulaire.data.motif}
                                            onChange={(event) => formulaire.setData('motif', event.target.value)}
                                        >
                                            {Object.entries(motifs).map(([cle, libelle]) => (
                                                <option key={cle} value={cle}>
                                                    {libelle}
                                                </option>
                                            ))}
                                        </Select>
                                    </label>
                                </div>

                                <label className="mt-4 block">
                                    <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                        Précision (facultatif)
                                    </span>
                                    <Textarea
                                        rows={2}
                                        value={formulaire.data.commentaire}
                                        onChange={(event) => formulaire.setData('commentaire', event.target.value)}
                                        maxLength={500}
                                    />
                                </label>

                                <div className="mt-4 flex justify-end">
                                    <button
                                        type="submit"
                                        disabled={formulaire.processing}
                                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-60"
                                    >
                                        <Icon name="check" className="h-4 w-4" />
                                        {formulaire.processing ? 'Envoi…' : 'Envoyer la demande'}
                                    </button>
                                </div>
                            </Card>
                        </div>

                        {/* L'aperçu suit la saisie, lettre à lettre. */}
                        <div className="lg:sticky lg:top-24 lg:self-start">
                            <p className="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-ink-400">
                                Aperçu
                            </p>
                            <CarteBadge donnees={apercu} validite={validite} />
                            <p className="mt-3 text-xs text-ink-400">
                                Rendu indicatif. Le numéro du badge est attribué à la fabrication.
                            </p>
                        </div>
                    </form>
                )}

                {demandes.length > 0 && (
                    <Card className="p-5">
                        <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Mes demandes</h2>

                        <div className="mt-4 space-y-2">
                            {demandes.map((demande) => (
                                <div
                                    key={demande.id}
                                    className="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 px-4 py-3 dark:border-white/10"
                                >
                                    <span className="font-mono text-xs text-ink-400">{demande.numero}</span>
                                    <span className="text-sm font-medium text-ink-900 dark:text-white">
                                        {demande.nomAffiche}
                                    </span>
                                    <span className="text-xs text-ink-500 dark:text-ink-400">
                                        {demande.motifLibelle} · demandé le {demande.demandeLe}
                                        {demande.institut && ` · ${demande.institut.name}`}
                                    </span>
                                    <span
                                        className={cn(
                                            'ml-auto rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                            TONS[demande.statut] ?? TONS.en_attente,
                                        )}
                                    >
                                        {demande.statutLibelle}
                                    </span>

                                    <button
                                        type="button"
                                        onClick={() => {
                                            setEnvoye(false);
                                            setAgrandi({
                                                nomAffiche: demande.nomAffiche,
                                                posteAffiche: demande.posteAffiche,
                                                matricule: demande.matricule,
                                                photoUrl: demande.photoUrl,
                                                initiales: identite.initiales,
                                                institut: demande.institut,
                                                numero: demande.numero,
                                            });
                                        }}
                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-white/10"
                                        aria-label={`Voir le badge ${demande.numero}`}
                                    >
                                        <Icon name="eye" className="h-4 w-4" />
                                    </button>

                                    {demande.statut === 'en_attente' && (
                                        <Link
                                            href={routes.badges.destroy(demande.id)}
                                            method="delete"
                                            as="button"
                                            className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                            aria-label="Annuler la demande"
                                        >
                                            <Icon name="trash" className="h-4 w-4" />
                                        </Link>
                                    )}

                                    {demande.motifRefus && (
                                        <p className="w-full text-xs text-red-600 dark:text-red-400">
                                            Refus : {demande.motifRefus}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Card>
                )}
            </div>

            {agrandi && (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Aperçu du badge"
                    className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 py-10"
                    onClick={() => setAgrandi(null)}
                >
                    {/* fixed, sinon le voile défile avec l'aperçu. */}
                    <div className="fixed inset-0 bg-ink-900/60 backdrop-blur-[2px]" />

                    <div className="relative z-10 flex flex-col items-center gap-4" onClick={(event) => event.stopPropagation()}>
                        {envoye && (
                            <p className="max-w-[280px] text-center text-sm font-medium text-white">
                                Demande envoyée. Voici le badge tel qu'il sera fabriqué.
                            </p>
                        )}

                        <CarteBadge donnees={agrandi} echelle={1.2} validite={validite} />

                        <button
                            type="button"
                            onClick={() => {
                                setAgrandi(null);
                                setEnvoye(false);
                            }}
                            className="rounded-xl bg-white px-5 py-2.5 text-sm font-medium text-ink-800 shadow-lg"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            )}
        </PortalLayout>
    );
}
