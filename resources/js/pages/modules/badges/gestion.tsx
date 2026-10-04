import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input, Select, Textarea } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { useRechercheInstantanee } from '@/lib/recherche';
import { cn, routes } from '@/lib/utils';
import type { Paginated } from '@/types';
import { ApercuBadge, LE_GROUPE, type Institut } from './carte';

interface Demande {
    id: number;
    numero: string;
    demandeur: string | null;
    matricule: string | null;
    nomAffiche: string;
    posteAffiche: string | null;
    photoUrl: string | null;
    modele: string;
    motifLibelle: string;
    commentaire: string | null;
    statut: string;
    statutLibelle: string;
    motifRefus: string | null;
    institut: Institut | null;
    demandeLe: string | null;
    traiteLe: string | null;
    traitePar: string | null;
}

interface Props {
    demandes: Paginated<Demande>;
    filtres: { statut: string | null; institut: string | null; q: string };
    instituts: Institut[];
    statuts: Record<string, string>;
    compteurs: Record<string, number>;
    validite: number;
    mention: string | null;
}

const TONS: Record<string, string> = {
    en_attente: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    approuvee: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200',
    imprimee: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200',
    remise: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    refusee: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

/** L'étape suivante, dans l'ordre où le badge se fabrique. */
const SUITE: Record<string, { statut: string; libelle: string; icon: string } | undefined> = {
    en_attente: { statut: 'approuvee', libelle: 'Approuver', icon: 'check' },
    approuvee: { statut: 'imprimee', libelle: 'Marquer imprimée', icon: 'print' },
    imprimee: { statut: 'remise', libelle: 'Marquer remise', icon: 'user' },
};

export default function GestionBadges({ demandes, filtres, instituts, statuts, compteurs, validite, mention }: Props) {
    const [apercu, setApercu] = useState<Demande | null>(null);
    const [refus, setRefus] = useState<Demande | null>(null);
    const [edition, setEdition] = useState<Demande | null>(null);
    // Recherche au fil de la frappe : plus besoin d'appuyer sur Entrée.
    const [q, setQ] = useRechercheInstantanee(filtres.q, (terme) => filtrer({ q: terme }));

    const filtrer = (params: Record<string, string>) =>
        router.get(
            routes.badges.gestion,
            { q, statut: filtres.statut ?? '', institut: filtres.institut ?? '', ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const avancer = (demande: Demande, statut: string) =>
        router.post(routes.badges.traiter(demande.id), { statut }, { preserveScroll: true });

    const formulaireRefus = useForm({ statut: 'refusee', motif_refus: '' });

    const refuser = (event: FormEvent) => {
        event.preventDefault();
        if (!refus) return;

        formulaireRefus.post(routes.badges.traiter(refus.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRefus(null);
                formulaireRefus.reset();
            },
        });
    };

    /*
     * Le guichet corrige plutôt que de refuser : un nom mal saisi, un
     * institut qui n'est pas le bon, une photo inexploitable.
     */
    const formulaireEdition = useForm({
        nom_affiche: '',
        poste_affiche: '',
        application_id: '',
        photo_file: null as File | null,
    });

    const ouvrirEdition = (demande: Demande) => {
        formulaireEdition.setData({
            nom_affiche: demande.nomAffiche,
            poste_affiche: demande.posteAffiche ?? '',
            application_id: String(demande.institut?.id ?? LE_GROUPE.id),
            photo_file: null,
        });
        formulaireEdition.clearErrors();
        setEdition(demande);
    };

    const modifier = (event: FormEvent) => {
        event.preventDefault();
        if (!edition) return;

        formulaireEdition.post(routes.badges.modifier(edition.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setEdition(null),
        });
    };

    const aImprimer = compteurs.approuvee ?? 0;

    return (
        <PortalLayout title="Demandes de badge">
            <div className="mx-auto max-w-[1400px] space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-ink-900 dark:text-white">Demandes de badge</h1>
                        <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                            {compteurs.en_attente ?? 0} en attente · {aImprimer} prête(s) à imprimer
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={routes.badges.index}
                            className="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:bg-white/5 dark:text-ink-200"
                        >
                            <Icon name="user" className="h-4 w-4" />
                            Mon badge
                        </Link>
                        <a
                            href={routes.badges.photos}
                            className="inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:bg-white/5 dark:text-ink-200"
                            title="Les portraits des badges approuvés ou imprimés, en un seul fichier"
                        >
                            <Icon name="image" className="h-4 w-4" />
                            Photos
                        </a>

                        {aImprimer > 0 && (
                            <Link
                                href={routes.badges.impression}
                                className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                            >
                                <Icon name="print" className="h-4 w-4" />
                                Planche d'impression ({aImprimer})
                            </Link>
                        )}
                    </div>
                </div>

                <Card className="p-4">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            filtrer({});
                        }}
                        className="flex flex-wrap items-center gap-3"
                    >
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Numéro, nom ou matricule…"
                            className="min-w-[220px] flex-1"
                        />

                        <Select
                            value={filtres.statut ?? ''}
                            onChange={(event) => filtrer({ statut: event.target.value })}
                            className="w-auto"
                            aria-label="Statut"
                        >
                            <option value="">Tous les statuts</option>
                            {Object.entries(statuts).map(([cle, libelle]) => (
                                <option key={cle} value={cle}>
                                    {libelle} ({compteurs[cle] ?? 0})
                                </option>
                            ))}
                        </Select>

                        <Select
                            value={filtres.institut ?? ''}
                            onChange={(event) => filtrer({ institut: event.target.value })}
                            className="w-auto"
                            aria-label="Institut"
                        >
                            <option value="">Tous les instituts</option>
                            {/* Les badges qui portent le logo de la maison. */}
                            <option value={LE_GROUPE.id}>{LE_GROUPE.name}</option>
                            {instituts.map((institut) => (
                                <option key={institut.id} value={institut.id}>
                                    {institut.name}
                                </option>
                            ))}
                        </Select>
                    </form>
                </Card>

                <div className="space-y-2">
                    {demandes.data.length === 0 && (
                        <Card className="p-8">
                            <p className="text-center text-sm text-ink-500 dark:text-ink-400">
                                Aucune demande ne correspond.
                            </p>
                        </Card>
                    )}

                    {demandes.data.map((demande) => {
                        const suite = SUITE[demande.statut];

                        return (
                            <Card key={demande.id} className="flex flex-wrap items-center gap-4 p-4">
                                <Avatar url={demande.photoUrl} initials="?" className="h-11 w-11" />

                                <div className="min-w-[200px] flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono text-xs text-ink-400">{demande.numero}</span>
                                        <span className="text-sm font-semibold text-ink-900 dark:text-white">
                                            {demande.nomAffiche}
                                        </span>
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                                TONS[demande.statut] ?? TONS.en_attente,
                                            )}
                                        >
                                            {demande.statutLibelle}
                                        </span>
                                    </div>
                                    <p className="mt-0.5 truncate text-xs text-ink-500 dark:text-ink-400">
                                        {demande.demandeur}
                                        {demande.matricule && ` · ${demande.matricule}`}
                                        {` · ${demande.institut?.name ?? LE_GROUPE.name}`}
                                        {` · ${demande.motifLibelle} · ${demande.demandeLe}`}
                                    </p>
                                    {demande.commentaire && (
                                        <p className="mt-1 text-xs text-ink-600 dark:text-ink-300">
                                            « {demande.commentaire} »
                                        </p>
                                    )}
                                    {demande.motifRefus && (
                                        <p className="mt-1 text-xs text-red-600 dark:text-red-400">
                                            Refus : {demande.motifRefus}
                                        </p>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setApercu(demande)}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                    >
                                        <Icon name="eye" className="h-3.5 w-3.5" />
                                        Aperçu
                                    </button>

                                    {suite && (
                                        <button
                                            type="button"
                                            onClick={() => avancer(demande, suite.statut)}
                                            className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-indigo-700"
                                        >
                                            <Icon name={suite.icon} className="h-3.5 w-3.5" />
                                            {suite.libelle}
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        title="Renvoyer le message correspondant à l'état de cette demande"
                                        onClick={() =>
                                            router.post(routes.badges.renvoyer(demande.id), {}, { preserveScroll: true })
                                        }
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                    >
                                        <Icon name="mail" className="h-3.5 w-3.5" />
                                        Renvoyer
                                    </button>

                                    {!['remise', 'refusee'].includes(demande.statut) && (
                                        <button
                                            type="button"
                                            onClick={() => ouvrirEdition(demande)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-medium text-ink-600 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                        >
                                            <Icon name="pencil" className="h-3.5 w-3.5" />
                                            Modifier
                                        </button>
                                    )}

                                    {/* Revenir sur un refus : la seule porte qui rouvre une demande. */}
                                    {demande.statut === 'refusee' && (
                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (confirm(`Rouvrir ${demande.numero} ? Le refus est levé et la demande repart à l'étude.`)) {
                                                    router.post(routes.badges.rouvrir(demande.id), {}, { preserveScroll: true });
                                                }
                                            }}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                        >
                                            <Icon name="refresh" className="h-3.5 w-3.5" />
                                            Rouvrir
                                        </button>
                                    )}

                                    {/* On refuse tant que rien n'est imprimé ; après, il est trop tard. */}
                                    {['en_attente', 'approuvee'].includes(demande.statut) && (
                                        <button
                                            type="button"
                                            onClick={() => setRefus(demande)}
                                            className="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:hover:bg-red-500/10"
                                        >
                                            Refuser
                                        </button>
                                    )}
                                </div>
                            </Card>
                        );
                    })}
                </div>

                <Pagination page={demandes} />
            </div>

            {apercu && (
                <div
                    role="dialog"
                    aria-modal="true"
                    className="fixed inset-0 z-50 flex items-center justify-center p-4"
                    onClick={() => setApercu(null)}
                >
                    <div className="absolute inset-0 bg-ink-900/60 backdrop-blur-[2px]" />
                    <div className="relative" onClick={(event) => event.stopPropagation()}>
                        <ApercuBadge donnees={apercu} echelle={1.25} validite={validite} mention={mention} />
                        <button
                            type="button"
                            onClick={() => setApercu(null)}
                            className="mt-4 w-full rounded-xl bg-white/90 py-2 text-sm font-medium text-ink-700"
                        >
                            Fermer
                        </button>
                    </div>
                </div>
            )}

            {edition && (
                <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-ink-900/50" onClick={() => setEdition(null)} />
                    <form
                        onSubmit={modifier}
                        className="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-2xl dark:bg-ink-800"
                    >
                        <div>
                            <h2 className="text-lg font-semibold text-ink-900 dark:text-white">
                                Modifier {edition.numero}
                            </h2>
                            <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                                Corriger vaut mieux que refuser : le demandeur n'a rien à refaire.
                            </p>
                        </div>

                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Nom sur la carte
                            </span>
                            <Input
                                value={formulaireEdition.data.nom_affiche}
                                onChange={(event) => formulaireEdition.setData('nom_affiche', event.target.value)}
                                required
                            />
                            {formulaireEdition.errors.nom_affiche && (
                                <p className="mt-1 text-xs font-medium text-red-600">
                                    {formulaireEdition.errors.nom_affiche}
                                </p>
                            )}
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Fonction (facultative)
                            </span>
                            <Input
                                value={formulaireEdition.data.poste_affiche}
                                onChange={(event) => formulaireEdition.setData('poste_affiche', event.target.value)}
                            />
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Logo imprimé
                            </span>
                            <Select
                                value={formulaireEdition.data.application_id}
                                onChange={(event) => formulaireEdition.setData('application_id', event.target.value)}
                            >
                                <option value={LE_GROUPE.id}>{LE_GROUPE.name}</option>
                                {instituts.map((institut) => (
                                    <option key={institut.id} value={institut.id}>
                                        {institut.name}
                                    </option>
                                ))}
                            </Select>
                            {formulaireEdition.errors.application_id && (
                                <p className="mt-1 text-xs font-medium text-red-600">
                                    {formulaireEdition.errors.application_id}
                                </p>
                            )}
                        </label>

                        <label className="block">
                            <span className="mb-1 block text-[13px] font-medium text-ink-700 dark:text-ink-200">
                                Remplacer la photo (facultatif)
                            </span>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    formulaireEdition.setData('photo_file', event.target.files?.[0] ?? null)
                                }
                                className="block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-ink-700 dark:text-ink-300 dark:file:bg-white/10 dark:file:text-ink-200"
                            />
                            {formulaireEdition.errors.photo_file && (
                                <p className="mt-1 text-xs font-medium text-red-600">
                                    {formulaireEdition.errors.photo_file}
                                </p>
                            )}
                        </label>

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setEdition(null)}
                                className="rounded-xl border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 dark:border-white/10 dark:text-ink-200"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={formulaireEdition.processing}
                                className="rounded-xl bg-indigo-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            )}

            {refus && (
                <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div className="absolute inset-0 bg-ink-900/50" onClick={() => setRefus(null)} />
                    <form
                        onSubmit={refuser}
                        className="relative w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-2xl dark:bg-ink-800"
                    >
                        <h2 className="text-lg font-semibold text-ink-900 dark:text-white">
                            Refuser {refus.numero}
                        </h2>
                        <p className="text-sm text-ink-600 dark:text-ink-300">
                            {refus.demandeur} verra ce motif sur sa demande.
                        </p>

                        <Textarea
                            rows={3}
                            value={formulaireRefus.data.motif_refus}
                            onChange={(event) => formulaireRefus.setData('motif_refus', event.target.value)}
                            placeholder="Photo inexploitable, nom incomplet…"
                            required
                        />
                        {formulaireRefus.errors.motif_refus && (
                            <p className="text-xs font-medium text-red-600">{formulaireRefus.errors.motif_refus}</p>
                        )}

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setRefus(null)}
                                className="rounded-xl border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 dark:border-white/10 dark:text-ink-200"
                            >
                                Annuler
                            </button>
                            <button
                                type="submit"
                                disabled={formulaireRefus.processing}
                                className="rounded-xl bg-red-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-60"
                            >
                                Refuser la demande
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </PortalLayout>
    );
}
