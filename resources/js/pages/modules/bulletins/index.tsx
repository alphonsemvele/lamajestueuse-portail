import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import ApercuPdf from '@/components/apercu-pdf';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input, Select } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { useRechercheInstantanee } from '@/lib/recherche';
import { cn, routes } from '@/lib/utils';
import type { Paginated } from '@/types';

interface LigneDetail {
    libelle: string;
    type: 'fixe' | 'pourcentage';
    valeur: number;
    montant: number;
    source: 'profil' | 'ajustement';
}

interface Bulletin {
    id: number;
    periode: string;
    mois: number;
    annee: number;
    employeur: string | null;
    poste: string | null;
    salaireBase: number;
    totalIndemnites: number;
    totalRetenues: number;
    salaireNet: number;
    detail: { indemnites: LigneDetail[]; retenues: LigneDetail[] };
    statut: string;
    payeLe: string | null;
    valideLe: string | null;
}

interface Props {
    bulletins: Paginated<Bulletin>;
    filtres: { q: string; annee: string | null };
    annees: number[];
    identite: { nom: string; matricule: string | null };
    enTete: { nom: string; couleur: string; groupe: boolean };
}

/** Montants en francs CFA : pas de décimales. */
function fcfa(montant: number): string {
    return `${Math.round(montant).toLocaleString('fr-FR')} F`;
}

export default function MesBulletins({ bulletins, filtres, annees, identite, enTete }: Props) {
    const chercher = (params: Record<string, string> = {}) =>
        router.get(
            routes.mesBulletins.index,
            { q: filtres.q, annee: filtres.annee ?? '', ...params },
            { preserveState: true, preserveScroll: true, replace: true, only: ['bulletins', 'filtres'] },
        );

    const [apercu, setApercu] = useState<Bulletin | null>(null);

    const [q, setQ] = useRechercheInstantanee(filtres.q, (terme) => chercher({ q: terme }));

    const soumettre = (event: FormEvent) => {
        event.preventDefault();
        chercher({ q });
    };

    const dernier = bulletins.data[0];

    return (
        <PortalLayout title="Mes bulletins de paie">
            <div className="mx-auto max-w-[1000px] space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold text-ink-900 dark:text-white">Mes bulletins de paie</h1>
                    <p className="mt-1 text-sm text-ink-500 dark:text-ink-400">
                        {identite.nom}
                        {identite.matricule && ` · ${identite.matricule}`} · {enTete.nom}
                    </p>
                </div>

                {/* Le dernier bulletin, mis en avant : c'est celui qu'on vient chercher. */}
                {dernier && (
                    <Card className="p-6" >
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <p className="text-[11px] uppercase tracking-wide text-ink-400">Dernier bulletin</p>
                                <p className="mt-0.5 text-lg font-semibold text-ink-900 dark:text-white">
                                    {dernier.periode}
                                </p>
                                <p className="text-sm text-ink-500 dark:text-ink-400">
                                    {dernier.employeur}
                                    {dernier.poste && ` · ${dernier.poste}`}
                                </p>
                            </div>

                            <div className="text-right">
                                <p className="text-[11px] uppercase tracking-wide text-ink-400">Net à payer</p>
                                <p
                                    className="text-2xl font-semibold tabular-nums"
                                    style={{ color: enTete.couleur }}
                                >
                                    {fcfa(dernier.salaireNet)}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => setApercu(dernier)}
                                    className="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium text-white transition hover:opacity-90"
                                    style={{ backgroundColor: enTete.couleur }}
                                >
                                    <Icon name="document" className="h-4 w-4" />
                                    Consulter mon bulletin
                                </button>

                                <a
                                    href={routes.mesBulletins.pdf(dernier.id)}
                                    className="inline-flex items-center gap-2 rounded-xl border border-ink-200 px-4 py-2.5 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/5"
                                >
                                    <Icon name="download" className="h-4 w-4" />
                                    Télécharger
                                </a>
                            </div>
                        </div>
                    </Card>
                )}

                <Card className="p-4">
                    <form onSubmit={soumettre} className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[220px] flex-1">
                            <Icon
                                name="search"
                                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400"
                            />
                            <Input
                                type="search"
                                value={q}
                                onChange={(event) => setQ(event.target.value)}
                                placeholder="Institut ou poste…"
                                className="pl-10"
                            />
                        </div>

                        <Select
                            value={filtres.annee ?? ''}
                            onChange={(event) => chercher({ annee: event.target.value })}
                            className="w-auto"
                            aria-label="Année"
                        >
                            <option value="">Toutes les années</option>
                            {annees.map((annee) => (
                                <option key={annee} value={annee}>
                                    {annee}
                                </option>
                            ))}
                        </Select>
                    </form>
                </Card>

                <div className="space-y-2">
                    {bulletins.data.length === 0 && (
                        <Card className="p-10">
                            <p className="text-center text-sm text-ink-500 dark:text-ink-400">
                                {filtres.q || filtres.annee
                                    ? 'Aucun bulletin ne correspond à cette recherche.'
                                    : "Aucun bulletin pour l'instant. Ils apparaissent ici une fois validés par le service des ressources humaines."}
                            </p>
                        </Card>
                    )}

                    {bulletins.data.map((bulletin) => (
                        <Card key={bulletin.id} className="flex flex-wrap items-center gap-4 p-4">
                            <div className="min-w-[160px] flex-1">
                                <p className="text-sm font-semibold text-ink-900 dark:text-white">{bulletin.periode}</p>
                                <p className="text-xs text-ink-500 dark:text-ink-400">
                                    {bulletin.employeur}
                                    {bulletin.poste && ` · ${bulletin.poste}`}
                                </p>
                            </div>

                            <div className="hidden text-right sm:block">
                                <p className="text-[11px] uppercase tracking-wide text-ink-400">Base</p>
                                <p className="text-sm tabular-nums text-ink-600 dark:text-ink-300">
                                    {fcfa(bulletin.salaireBase)}
                                </p>
                            </div>

                            <div className="text-right">
                                <p className="text-[11px] uppercase tracking-wide text-ink-400">Net</p>
                                <p className="text-sm font-semibold tabular-nums text-ink-900 dark:text-white">
                                    {fcfa(bulletin.salaireNet)}
                                </p>
                            </div>

                            <span
                                className={cn(
                                    'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                                    bulletin.statut === 'paye'
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
                                )}
                            >
                                {bulletin.statut === 'paye' ? `Payé ${bulletin.payeLe ?? ''}` : 'Validé'}
                            </span>

                            <div className="flex gap-1.5">
                                <button
                                    type="button"
                                    onClick={() => setApercu(bulletin)}
                                    title={`Consulter le bulletin de ${bulletin.periode}`}
                                    aria-label={`Consulter le bulletin de ${bulletin.periode}`}
                                    className="rounded-lg border border-ink-200 p-2 text-ink-500 transition hover:bg-ink-50 hover:text-ink-800 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                >
                                    <Icon name="document" className="h-4 w-4" />
                                </button>

                                <a
                                    href={routes.mesBulletins.pdf(bulletin.id)}
                                    title={`Télécharger le bulletin de ${bulletin.periode}`}
                                    aria-label={`Télécharger le bulletin de ${bulletin.periode}`}
                                    className="rounded-lg border border-ink-200 p-2 text-ink-500 transition hover:bg-ink-50 hover:text-ink-800 dark:border-white/10 dark:text-ink-300 dark:hover:bg-white/5"
                                >
                                    <Icon name="download" className="h-4 w-4" />
                                </a>
                            </div>
                        </Card>
                    ))}
                </div>

                <Pagination page={bulletins} />
            </div>
            {apercu && (
                <ApercuPdf
                    titre={`Bulletin de ${apercu.periode}`}
                    sousTitre={apercu.employeur}
                    source={routes.mesBulletins.pdf(apercu.id, true)}
                    telechargement={routes.mesBulletins.pdf(apercu.id)}
                    onFermer={() => setApercu(null)}
                />
            )}
        </PortalLayout>
    );
}
