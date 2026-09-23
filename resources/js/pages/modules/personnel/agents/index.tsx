import { Link, router } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Pagination from '@/components/pagination';
import { Card, Input, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Champ, Entete, fcfa, Vide } from '../parts';

interface ContratLigne {
    id: number;
    employeur: string | null;
    poste: string;
    typeLibelle: string;
    quotite: number;
    salaireBase: number;
    statut: string;
}

interface Membre {
    userId: number;
    id: number | null;
    dossierOuvert: boolean;
    nom: string;
    matricule: string | null;
    email: string | null;
    poste: string | null;
    entite: string | null;
    photoUrl: string | null;
    initiales: string;
    anciennete: number | null;
    contrats: ContratLigne[];
}

interface Props {
    agents: Paginated<Membre>;
    filtres: { q: string; employeur: string | null; statut: string | null };
    employeurs: { id: number; sigle: string; nom: string }[];
    peutGerer: boolean;
    /** Combien de personnes n'ont pas encore de dossier renseigné. */
    sansDossier: number;
}

export default function ListePersonnel({ agents, filtres, employeurs, peutGerer, sansDossier }: Props) {
    const [q, setQ] = useState(filtres.q);

    const chercher = (params: Record<string, string> = {}) =>
        router.get(
            routes.personnel.agents,
            { q, employeur: filtres.employeur ?? '', statut: filtres.statut ?? '', ...params },
            { preserveState: true, preserveScroll: true, replace: true, only: ['agents', 'filtres'] },
        );

    // Recherche au fil de la frappe, sans une requête par lettre.
    const premierRendu = useRef(true);

    useEffect(() => {
        if (premierRendu.current) {
            premierRendu.current = false;
            return;
        }

        if (q === filtres.q) return;

        const minuteur = setTimeout(() => chercher(), 250);

        return () => clearTimeout(minuteur);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const soumettre = (event: FormEvent) => {
        event.preventDefault();
        chercher();
    };

    return (
        <PersonnelLayout
            title="Personnel"
            peutGerer={peutGerer}
            entete={
                <Entete
                    titre="Personnel"
                    sous={`${agents.total} personne(s)${sansDossier > 0 ? ` · ${sansDossier} dossier(s) à renseigner` : ''}`}
                />
            }
        >
            <Card className="p-4">
                <form onSubmit={soumettre} className="flex flex-wrap items-end gap-3">
                    <Champ libelle="Rechercher" className="min-w-[220px] flex-1">
                        <Input
                            type="search"
                            value={q}
                            onChange={(event) => setQ(event.target.value)}
                            placeholder="Nom, matricule ou e-mail…"
                        />
                    </Champ>

                    <Champ libelle="Employeur">
                        <Select
                            value={filtres.employeur ?? ''}
                            onChange={(event) => chercher({ employeur: event.target.value })}
                            className="w-auto"
                        >
                            <option value="">Tous</option>
                            {employeurs.map((employeur) => (
                                <option key={employeur.id} value={employeur.id}>
                                    {employeur.sigle}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    <Champ libelle="Situation">
                        <Select
                            value={filtres.statut ?? ''}
                            onChange={(event) => chercher({ statut: event.target.value })}
                            className="w-auto"
                        >
                            <option value="">Toutes</option>
                            <option value="sans_dossier">Dossier non renseigné</option>
                            <option value="sans_contrat">Sans contrat actif</option>
                            <option value="plusieurs">Plusieurs employeurs</option>
                        </Select>
                    </Champ>
                </form>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {agents.data.length === 0 && (
                    <div className="sm:col-span-2 xl:col-span-3">
                        <Card className="p-4">
                            <Vide
                                message={
                                    filtres.q || filtres.employeur || filtres.statut
                                        ? 'Personne ne correspond à cette recherche.'
                                        : "Aucun membre du personnel n'est rattaché à vos entités. Le rattachement se fait dans l'administration du portail."
                                }
                                icon="users"
                            />
                        </Card>
                    </div>
                )}

                {agents.data.map((membre) => (
                    <Link key={membre.userId} href={routes.personnel.agent(membre.userId)}>
                        <Card className="h-full p-5 transition hover:border-teal-400 hover:shadow-md dark:hover:border-teal-500/40">
                            <div className="flex items-start gap-3">
                                <Avatar url={membre.photoUrl} initials={membre.initiales} className="h-11 w-11" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">{membre.nom}</p>
                                    <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                        {membre.matricule ?? 'sans matricule'}
                                        {membre.poste && ` · ${membre.poste}`}
                                    </p>
                                </div>
                                {!membre.dossierOuvert && (
                                    <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">
                                        à renseigner
                                    </span>
                                )}
                            </div>

                            <div className="mt-4 space-y-2">
                                {membre.contrats.length === 0 && (
                                    <p className="rounded-lg bg-ink-50 px-3 py-2 text-xs text-ink-500 dark:bg-white/5 dark:text-ink-400">
                                        {membre.entite ? `Rattaché à ${membre.entite}` : 'Aucun contrat enregistré.'}
                                    </p>
                                )}

                                {membre.contrats.map((contrat) => (
                                    <div
                                        key={contrat.id}
                                        className="flex items-center justify-between gap-3 rounded-lg bg-ink-50 px-3 py-2 dark:bg-white/5"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-xs font-medium text-ink-900 dark:text-white">
                                                {contrat.employeur} · {contrat.poste}
                                            </p>
                                            <p className="text-[11px] text-ink-500 dark:text-ink-400">
                                                {contrat.typeLibelle}
                                                {contrat.quotite < 100 && ` · ${contrat.quotite} %`}
                                            </p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold tabular-nums text-ink-700 dark:text-ink-200">
                                            {fcfa(contrat.salaireBase)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Card>
                    </Link>
                ))}
            </div>

            <Pagination page={agents} />
        </PersonnelLayout>
    );
}
