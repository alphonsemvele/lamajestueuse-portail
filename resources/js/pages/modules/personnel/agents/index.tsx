import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
import Avatar from '@/components/avatar';
import Pagination from '@/components/pagination';
import { Card, ErrorSummary, Input, Select } from '@/components/ui';
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
    const [nouvelle, setNouvelle] = useState(false);

    const arrivant = useForm({
        name: '',
        lastname: '',
        matricule: '',
        email: '',
        phone: '',
        poste: '',
        employeur_id: employeurs.length === 1 ? String(employeurs[0].id) : '',
        password: '',
        password_confirmation: '',
    });

    const creer = (event: FormEvent) => {
        event.preventDefault();
        arrivant.post(routes.personnel.agentStore, {
            onSuccess: () => {
                setNouvelle(false);
                arrivant.reset();
            },
        });
    };

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

                    {peutGerer && (
                        <Bouton type="button" icon="plus" onClick={() => setNouvelle(true)} className="ml-auto">
                            Nouvel arrivant
                        </Bouton>
                    )}
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

            <Modale titre="Nouvel arrivant" ouverte={nouvelle} onFermer={() => setNouvelle(false)} large>
                <form onSubmit={creer} className="space-y-4">
                    <p className="text-sm text-ink-600 dark:text-ink-300">
                        Le compte est créé actif et rattaché à l'entité choisie. Il n'ouvre encore aucune application :
                        c'est un administrateur du portail qui décidera de ce à quoi il donne droit.
                    </p>

                    <ErrorSummary errors={arrivant.errors} title="Corrigez ces points" />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Prénom" erreur={arrivant.errors.name}>
                            <Input
                                value={arrivant.data.name}
                                onChange={(event) => arrivant.setData('name', event.target.value)}
                                maxLength={80}
                                required
                            />
                        </Champ>
                        <Champ libelle="Nom de famille" erreur={arrivant.errors.lastname}>
                            <Input
                                value={arrivant.data.lastname}
                                onChange={(event) => arrivant.setData('lastname', event.target.value)}
                                maxLength={80}
                            />
                        </Champ>
                        <Champ libelle="Matricule" erreur={arrivant.errors.matricule} aide="Unique dans tout le groupe.">
                            <Input
                                value={arrivant.data.matricule}
                                onChange={(event) => arrivant.setData('matricule', event.target.value)}
                                maxLength={40}
                                className="font-mono text-[13px]"
                            />
                        </Champ>
                        <Champ libelle="Entité" erreur={arrivant.errors.employeur_id}>
                            <Select
                                value={arrivant.data.employeur_id}
                                onChange={(event) => arrivant.setData('employeur_id', event.target.value)}
                                required
                            >
                                <option value="">Choisir…</option>
                                {employeurs.map((employeur) => (
                                    <option key={employeur.id} value={employeur.id}>
                                        {employeur.sigle} — {employeur.nom}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ
                            libelle="Adresse professionnelle"
                            erreur={arrivant.errors.email}
                            aide="Elle lui servira d'identifiant de connexion."
                        >
                            <Input
                                type="email"
                                value={arrivant.data.email}
                                onChange={(event) => arrivant.setData('email', event.target.value)}
                                maxLength={150}
                            />
                        </Champ>
                        <Champ libelle="Téléphone" erreur={arrivant.errors.phone}>
                            <Input
                                value={arrivant.data.phone}
                                onChange={(event) => arrivant.setData('phone', event.target.value)}
                                maxLength={40}
                            />
                        </Champ>
                        <Champ libelle="Poste" erreur={arrivant.errors.poste} className="sm:col-span-2">
                            <Input
                                value={arrivant.data.poste}
                                onChange={(event) => arrivant.setData('poste', event.target.value)}
                                maxLength={120}
                            />
                        </Champ>
                        <Champ
                            libelle="Mot de passe provisoire"
                            erreur={arrivant.errors.password}
                            aide="Huit caractères au moins. À lui transmettre, il pourra le changer."
                        >
                            <Input
                                type="password"
                                value={arrivant.data.password}
                                onChange={(event) => arrivant.setData('password', event.target.value)}
                                required
                            />
                        </Champ>
                        <Champ libelle="Confirmation" erreur={arrivant.errors.password_confirmation}>
                            <Input
                                type="password"
                                value={arrivant.data.password_confirmation}
                                onChange={(event) => arrivant.setData('password_confirmation', event.target.value)}
                                required
                            />
                        </Champ>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setNouvelle(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={arrivant.processing}>
                            {arrivant.processing ? 'Création…' : 'Créer la fiche'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </PersonnelLayout>
    );
}
