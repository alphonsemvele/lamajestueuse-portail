import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import Pagination from '@/components/pagination';
import { Card, ErrorSummary, Input, Select } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { useRechercheInstantanee } from '@/lib/recherche';
import { cn, routes } from '@/lib/utils';
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
    statutCompte: string;
    photoUrl: string | null;
    initiales: string;
    anciennete: number | null;
    contrats: ContratLigne[];
}

interface Props {
    agents: Paginated<Membre>;
    filtres: { q: string; employeur: string | null; statut: string | null; compte: string | null };
    statutsCompte: Record<string, string>;
    employeurs: { id: number; sigle: string; nom: string }[];
    peutGerer: boolean;
    /** Combien de personnes n'ont pas encore de dossier renseigné. */
    sansDossier: number;
    /** Comptes dont l'inscription attend encore une validation. */
    enAttente: number;
}

/** L'état du compte au portail, distinct de la situation contractuelle. */
const TONS_COMPTE: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    suspended: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
};

interface APourvoir {
    id: number;
    nom: string;
    poste: string | null;
    entite: string | null;
    email: string | null;
    /** Année de recrutement retenue : premier contrat, sinon création du compte. */
    annee: number;
    matricule: string;
}

export default function ListePersonnel({
    agents,
    filtres,
    statutsCompte,
    employeurs,
    peutGerer,
    sansDossier,
    enAttente,
}: Props) {
    // Recherche au fil de la frappe : plus besoin d'appuyer sur Entrée.
    const [q, setQ] = useRechercheInstantanee(filtres.q, (terme) => chercher({ q: terme }));
    const [nouvelle, setNouvelle] = useState(false);

    // Attribution des matricules : on montre d'abord qui recevrait quoi.
    const [matricules, setMatricules] = useState<{ dernier: string | null; personnes: APourvoir[] } | null>(null);
    const [chargement, setChargement] = useState(false);
    const [retenus, setRetenus] = useState<number[]>([]);
    const attribution = useForm<{ personnes: number[] }>({ personnes: [] });

    const ouvrirMatricules = async () => {
        setChargement(true);

        try {
            const reponse = await fetch(routes.personnel.matriculesAPourvoir, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const donnees = (await reponse.json()) as { dernier: string | null; personnes: APourvoir[] };

            setMatricules(donnees);
            setRetenus(donnees.personnes.map((personne) => personne.id));
        } finally {
            setChargement(false);
        }
    };

    const attribuer = (event: FormEvent) => {
        event.preventDefault();

        attribution.transform(() => ({ personnes: retenus }));
        attribution.post(routes.personnel.matriculesAttribuer, {
            preserveScroll: true,
            onSuccess: () => setMatricules(null),
        });
    };

    /**
     * Numéro de chacun, recalculé quand on décoche : chaque année a sa file,
     * et les personnes retenues y prennent les numéros dans l'ordre.
     */
    const numerosRetenus = (() => {
        const files: Record<number, string[]> = {};

        for (const personne of matricules?.personnes ?? []) {
            (files[personne.annee] ??= []).push(personne.matricule);
        }

        const curseurs: Record<number, number> = {};
        const numeros: Record<number, string> = {};

        for (const personne of matricules?.personnes ?? []) {
            if (!retenus.includes(personne.id)) continue;

            curseurs[personne.annee] ??= 0;
            numeros[personne.id] = files[personne.annee][curseurs[personne.annee]++];
        }

        return numeros;
    })();

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
            {
                q,
                employeur: filtres.employeur ?? '',
                statut: filtres.statut ?? '',
                compte: filtres.compte ?? '',
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true, only: ['agents', 'filtres'] },
        );

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
                    sous={[
                        `${agents.total} personne(s)`,
                        sansDossier > 0 ? `${sansDossier} dossier(s) à renseigner` : null,
                        enAttente > 0 ? `${enAttente} compte(s) en attente de validation` : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
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

                    <Champ libelle="Compte">
                        <Select
                            value={filtres.compte ?? ''}
                            onChange={(event) => chercher({ compte: event.target.value })}
                            className="w-auto"
                        >
                            <option value="">Tous</option>
                            {Object.entries(statutsCompte).map(([cle, libelle]) => (
                                <option key={cle} value={cle}>
                                    {libelle}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    {peutGerer && (
                        <div className="ml-auto flex flex-wrap gap-2">
                            <Bouton
                                type="button"
                                variante="secondaire"
                                icon="key"
                                onClick={ouvrirMatricules}
                                disabled={chargement}
                            >
                                {chargement ? 'Lecture…' : 'Matricules'}
                            </Bouton>
                            <a
                                href={routes.personnel.export}
                                className="inline-flex items-center justify-center gap-2 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:bg-white/5 dark:text-ink-200 dark:hover:bg-white/10"
                            >
                                <Icon name="upload" className="h-4 w-4 rotate-180" />
                                Exporter
                            </a>
                            <Bouton type="button" icon="plus" onClick={() => setNouvelle(true)}>
                                Nouvel arrivant
                            </Bouton>
                        </div>
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
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    {membre.statutCompte !== 'active' && (
                                        <span
                                            className={cn(
                                                'rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                                TONS_COMPTE[membre.statutCompte] ?? TONS_COMPTE.pending,
                                            )}
                                        >
                                            {statutsCompte[membre.statutCompte] ?? membre.statutCompte}
                                        </span>
                                    )}
                                    {!membre.dossierOuvert && (
                                        <span className="rounded-full bg-ink-100 px-2 py-0.5 text-[10px] font-semibold text-ink-600 dark:bg-white/10 dark:text-ink-300">
                                            à renseigner
                                        </span>
                                    )}
                                </div>
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

            <Modale
                titre="Attribuer les matricules"
                ouverte={matricules !== null}
                onFermer={() => setMatricules(null)}
                large
            >
                {matricules && (
                    <form onSubmit={attribuer} className="space-y-4">
                        {matricules.personnes.length === 0 ? (
                            <p className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200">
                                Tout le monde a déjà un matricule.
                            </p>
                        ) : (
                            <>
                                <p className="text-sm text-ink-600 dark:text-ink-300">
                                    {matricules.personnes.length} personne(s) sans matricule. Chaque année a sa propre
                                    séquence
                                    {matricules.dernier ? ` ; celle de cette année reprend après ${matricules.dernier}` : ''}.
                                    Décochez qui ne doit pas en recevoir maintenant.
                                </p>

                                <div className="max-h-[380px] overflow-y-auto rounded-xl border border-ink-200 dark:border-white/10">
                                    <table className="w-full text-left text-sm">
                                        <thead className="sticky top-0 bg-ink-50 text-[11px] uppercase tracking-wide text-ink-400 dark:bg-ink-800">
                                            <tr>
                                                <th className="w-10 px-3 py-2">
                                                    <input
                                                        type="checkbox"
                                                        checked={retenus.length === matricules.personnes.length}
                                                        onChange={(event) =>
                                                            setRetenus(
                                                                event.target.checked
                                                                    ? matricules.personnes.map((p) => p.id)
                                                                    : [],
                                                            )
                                                        }
                                                        className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                                                        aria-label="Tout sélectionner"
                                                    />
                                                </th>
                                                <th className="px-3 py-2 font-medium">Personne</th>
                                                <th className="px-3 py-2 font-medium">Poste</th>
                                                <th className="px-3 py-2 font-medium">Recruté en</th>
                                                <th className="px-3 py-2 font-medium">Matricule</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-ink-100 dark:divide-white/5">
                                            {matricules.personnes.map((personne) => {
                                                const retenu = retenus.includes(personne.id);
                                                // Le numéro affiché tient compte des cases décochées.
                                                const numero = numerosRetenus[personne.id] ?? null;

                                                return (
                                                    <tr key={personne.id} className={retenu ? '' : 'opacity-50'}>
                                                        <td className="px-3 py-2">
                                                            <input
                                                                type="checkbox"
                                                                checked={retenu}
                                                                onChange={() =>
                                                                    setRetenus((actuels) =>
                                                                        retenu
                                                                            ? actuels.filter((id) => id !== personne.id)
                                                                            : [...actuels, personne.id],
                                                                    )
                                                                }
                                                                className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                                                                aria-label={`Attribuer à ${personne.nom}`}
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <p className="font-medium text-ink-900 dark:text-white">
                                                                {personne.nom}
                                                            </p>
                                                            <p className="text-xs text-ink-500 dark:text-ink-400">
                                                                {personne.email}
                                                                {personne.entite && ` · ${personne.entite}`}
                                                            </p>
                                                        </td>
                                                        <td className="px-3 py-2 text-ink-600 dark:text-ink-300">
                                                            {personne.poste ?? '—'}
                                                        </td>
                                                        <td className="px-3 py-2 tabular-nums text-ink-600 dark:text-ink-300">
                                                            {personne.annee}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-[13px] text-ink-900 dark:text-white">
                                                            {numero ?? '—'}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>

                                <p className="text-xs text-ink-400">
                                    L'année vient du premier contrat de la personne, ou à défaut de la création de son
                                    compte. Un matricule attribué n'est jamais réattribué à quelqu'un d'autre.
                                </p>

                                <div className="flex justify-end gap-2">
                                    <Bouton type="button" variante="secondaire" onClick={() => setMatricules(null)}>
                                        Annuler
                                    </Bouton>
                                    <Bouton type="submit" icon="check" disabled={attribution.processing || retenus.length === 0}>
                                        {attribution.processing
                                            ? 'Attribution…'
                                            : `Attribuer ${retenus.length} matricule(s)`}
                                    </Bouton>
                                </div>
                            </>
                        )}
                    </form>
                )}
            </Modale>

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
