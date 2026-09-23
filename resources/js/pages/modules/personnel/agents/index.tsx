import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, Input, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { Paginated } from '@/types';
import { Bouton, Champ, Entete, fcfa, Modale, Vide } from '../parts';

interface ContratLigne {
    id: number;
    employeur: string | null;
    poste: string;
    typeLibelle: string;
    quotite: number;
    salaireBase: number;
    statut: string;
}

interface AgentLigne {
    id: number;
    nom: string | null;
    matricule: string | null;
    email: string | null;
    telephone: string | null;
    photoUrl: string | null;
    initiales: string | null;
    anciennete: number | null;
    contrats: ContratLigne[];
}

interface Props {
    agents: Paginated<AgentLigne>;
    filtres: { q: string; employeur: string | null; statut: string | null };
    employeurs: { id: number; sigle: string; nom: string }[];
    comptesSansDossier: { id: number; nom: string; matricule: string | null; email: string; poste: string | null }[];
    peutGerer: boolean;
}

export default function ListePersonnel({ agents, filtres, employeurs, comptesSansDossier, peutGerer }: Props) {
    const [q, setQ] = useState(filtres.q);
    const [ouvrir, setOuvrir] = useState(false);

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

    const formulaire = useForm({ user_id: '' });

    const ouvrirDossier = (event: FormEvent) => {
        event.preventDefault();
        formulaire.post(routes.personnel.agentStore, {
            onSuccess: () => {
                setOuvrir(false);
                formulaire.reset();
            },
        });
    };

    return (
        <PersonnelLayout
            title="Personnel"
            peutGerer={peutGerer}
            entete={<Entete titre="Personnel" sous={`${agents.total} dossier(s) au total.`} />}
        >
            <Card className="p-4">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        chercher();
                    }}
                    className="flex flex-wrap items-end gap-3"
                >
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
                            <option value="sans_contrat">Sans contrat actif</option>
                            <option value="plusieurs">Plusieurs employeurs</option>
                        </Select>
                    </Champ>

                    {peutGerer && (
                        <Bouton type="button" icon="plus" onClick={() => setOuvrir(true)} className="ml-auto">
                            Ouvrir un dossier
                        </Bouton>
                    )}
                </form>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {agents.data.length === 0 && (
                    <div className="sm:col-span-2 xl:col-span-3">
                        <Card className="p-4">
                            <Vide message="Aucun dossier ne correspond à cette recherche." icon="users" />
                        </Card>
                    </div>
                )}

                {agents.data.map((agent) => (
                    <Link key={agent.id} href={routes.personnel.agent(agent.id)}>
                        <Card className="h-full p-5 transition hover:border-teal-400 hover:shadow-md dark:hover:border-teal-500/40">
                            <div className="flex items-start gap-3">
                                <Avatar url={agent.photoUrl} initials={agent.initiales ?? '?'} className="h-11 w-11" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">{agent.nom}</p>
                                    <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                        {agent.matricule ?? 'sans matricule'}
                                        {agent.anciennete !== null && ` · ${agent.anciennete} an(s) d'ancienneté`}
                                    </p>
                                </div>
                            </div>

                            <div className="mt-4 space-y-2">
                                {agent.contrats.length === 0 && (
                                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                                        Aucun contrat actif.
                                    </p>
                                )}

                                {agent.contrats.map((contrat) => (
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

            <Modale titre="Ouvrir un dossier personnel" ouverte={ouvrir} onFermer={() => setOuvrir(false)}>
                <form onSubmit={ouvrirDossier} className="space-y-4">
                    <p className="text-sm text-ink-600 dark:text-ink-300">
                        Le dossier se rattache à un compte existant du portail : l'identité, la photo et les contacts
                        restent gérés dans l'annuaire.
                    </p>

                    <Champ libelle="Compte du portail" erreur={formulaire.errors.user_id}>
                        <Select
                            value={formulaire.data.user_id}
                            onChange={(event) => formulaire.setData('user_id', event.target.value)}
                            required
                        >
                            <option value="">Choisir…</option>
                            {comptesSansDossier.map((compte) => (
                                <option key={compte.id} value={compte.id}>
                                    {compte.nom} {compte.matricule ? `(${compte.matricule})` : ''}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    {comptesSansDossier.length === 0 && (
                        <p className="rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">
                            Tous les comptes actifs du portail ont déjà un dossier.
                        </p>
                    )}

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvrir(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={formulaire.processing || !formulaire.data.user_id}>
                            {formulaire.processing ? 'Ouverture…' : 'Ouvrir le dossier'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </PersonnelLayout>
    );
}
