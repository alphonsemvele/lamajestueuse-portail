import { Link, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import { Card, ErrorSummary, Input, Select, Textarea } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { cn, routes } from '@/lib/utils';
import { Bouton, Champ, fcfa, Modale, Statut, Tableau, Vide } from '../parts';

interface Agent {
    id: number;
    nom: string | null;
    matricule: string | null;
    email: string | null;
    telephone: string | null;
    photoUrl: string | null;
    initiales: string | null;
    dateNaissance: string | null;
    lieuNaissance: string | null;
    situationFamiliale: string | null;
    enfants: number;
    cni: string | null;
    numeroCnps: string | null;
    adresse: string | null;
    urgenceNom: string | null;
    urgenceTelephone: string | null;
    observations: string | null;
    anciennete: number | null;
}

interface Diplome {
    id: number;
    intitule: string;
    niveau: string | null;
    specialite: string | null;
    etablissement: string | null;
    anneeObtention: number | null;
    pieceFournie: boolean;
}

interface Contrat {
    id: number;
    employeurId: number;
    employeur: string | null;
    type: string;
    typeLibelle: string;
    poste: string;
    dateDebut: string | null;
    dateFin: string | null;
    quotite: number;
    profilId: number | null;
    profil: string | null;
    echelonId: number | null;
    echelon: string | null;
    salaireBase: number;
    statut: string;
    motifFin: string | null;
    observations: string | null;
}

interface Evenement {
    id: number;
    date: string | null;
    type: string;
    typeLibelle: string;
    libelle: string;
    details: string | null;
    employeur: string | null;
    contratId: number | null;
}

interface BulletinLigne {
    id: number;
    periode: string;
    employeur: string | null;
    salaireNet: number;
    statut: string;
}

interface Props {
    agent: Agent;
    diplomes: Diplome[];
    contrats: Contrat[];
    evenements: Evenement[];
    bulletins: BulletinLigne[];
    referentiels: {
        employeurs: { id: number; sigle: string; nom: string }[];
        profils: { id: number; nom: string; echelon: string | null; salaireBase: number }[];
        types: Record<string, string>;
        evenements: Record<string, string>;
        niveaux: string[];
    };
    peutGerer: boolean;
}

const SITUATIONS: Record<string, string> = {
    celibataire: 'Célibataire',
    marie: 'Marié(e)',
    divorce: 'Divorcé(e)',
    veuf: 'Veuf / veuve',
};

function dateCourte(valeur: string | null): string {
    if (!valeur) return '—';

    const [annee, mois, jour] = valeur.split('-');

    return `${jour}/${mois}/${annee}`;
}

/** Ligne libellé / valeur du dossier administratif. */
function Info({ libelle, valeur }: { libelle: string; valeur: string | number | null }) {
    return (
        <div>
            <p className="text-[11px] uppercase tracking-wide text-ink-400">{libelle}</p>
            <p className="mt-0.5 text-sm text-ink-900 dark:text-white">{valeur === null || valeur === '' ? '—' : valeur}</p>
        </div>
    );
}

export default function FicheAgent({ agent, diplomes, contrats, evenements, bulletins, referentiels, peutGerer }: Props) {
    const [onglet, setOnglet] = useState<'dossier' | 'diplomes' | 'contrats' | 'carriere' | 'bulletins'>('dossier');

    const onglets = [
        { cle: 'dossier', libelle: 'Dossier', icon: 'user', compte: null },
        { cle: 'diplomes', libelle: 'Diplômes', icon: 'award', compte: diplomes.length },
        { cle: 'contrats', libelle: 'Contrats', icon: 'briefcase', compte: contrats.length },
        { cle: 'carriere', libelle: 'Carrière', icon: 'layers', compte: evenements.length },
        { cle: 'bulletins', libelle: 'Bulletins', icon: 'wallet', compte: bulletins.length },
    ] as const;

    return (
        <PersonnelLayout title={agent.nom ?? 'Dossier'} peutGerer={peutGerer}>
                <Link
                href={routes.personnel.agents}
                className="inline-flex items-center gap-1.5 text-sm text-ink-500 transition hover:text-ink-900 dark:text-ink-400 dark:hover:text-white"
            >
                <Icon name="chevron-right" className="h-4 w-4 rotate-180" />
                Retour au personnel
            </Link>

            <Card className="p-6">
                <div className="flex flex-wrap items-start gap-5">
                    <Avatar url={agent.photoUrl} initials={agent.initiales ?? '?'} className="h-16 w-16 text-lg" />
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-semibold text-ink-900 dark:text-white">{agent.nom}</h1>
                        <p className="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                            {agent.matricule ?? 'sans matricule'}
                            {agent.anciennete !== null && ` · ${agent.anciennete} an(s) dans le groupe`}
                        </p>
                        <div className="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-ink-600 dark:text-ink-300">
                            {agent.email && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="mail" className="h-3.5 w-3.5 text-ink-400" />
                                    {agent.email}
                                </span>
                            )}
                            {agent.telephone && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="phone" className="h-3.5 w-3.5 text-ink-400" />
                                    {agent.telephone}
                                </span>
                            )}
                        </div>
                    </div>
                </div>

                <nav className="mt-6 flex flex-wrap gap-1 border-t border-ink-100 pt-4 dark:border-white/5">
                    {onglets.map((item) => (
                        <button
                            key={item.cle}
                            type="button"
                            onClick={() => setOnglet(item.cle)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition',
                                onglet === item.cle
                                    ? 'bg-teal-600 text-white'
                                    : 'text-ink-600 hover:bg-ink-50 dark:text-ink-300 dark:hover:bg-white/5',
                            )}
                        >
                            <Icon name={item.icon} className="h-4 w-4" />
                            {item.libelle}
                            {item.compte !== null && item.compte > 0 && (
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 text-[11px]',
                                        onglet === item.cle ? 'bg-white/20' : 'bg-ink-100 dark:bg-white/10',
                                    )}
                                >
                                    {item.compte}
                                </span>
                            )}
                        </button>
                    ))}
                </nav>
            </Card>

            {onglet === 'dossier' && <Dossier agent={agent} peutGerer={peutGerer} />}
            {onglet === 'diplomes' && <Diplomes agent={agent} diplomes={diplomes} niveaux={referentiels.niveaux} peutGerer={peutGerer} />}
            {onglet === 'contrats' && <Contrats agent={agent} contrats={contrats} referentiels={referentiels} peutGerer={peutGerer} />}
            {onglet === 'carriere' && (
                <Carriere agent={agent} evenements={evenements} contrats={contrats} types={referentiels.evenements} peutGerer={peutGerer} />
            )}
                {onglet === 'bulletins' && <Bulletins bulletins={bulletins} />}
        </PersonnelLayout>
    );
}

// ------------------------------------------------------------------ dossier

function Dossier({ agent, peutGerer }: { agent: Agent; peutGerer: boolean }) {
    const [edition, setEdition] = useState(false);

    const formulaire = useForm({
        date_naissance: agent.dateNaissance ?? '',
        lieu_naissance: agent.lieuNaissance ?? '',
        situation_familiale: agent.situationFamiliale ?? '',
        enfants: agent.enfants ?? 0,
        cni: agent.cni ?? '',
        numero_cnps: agent.numeroCnps ?? '',
        adresse: agent.adresse ?? '',
        urgence_nom: agent.urgenceNom ?? '',
        urgence_telephone: agent.urgenceTelephone ?? '',
        observations: agent.observations ?? '',
    });

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();
        formulaire.put(routes.personnel.agent(agent.id), { onSuccess: () => setEdition(false) });
    };

    if (!edition) {
        return (
            <Card className="p-6">
                <div className="flex items-start justify-between gap-4">
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Dossier administratif</h2>
                    {peutGerer && (
                        <Bouton variante="secondaire" icon="pencil" onClick={() => setEdition(true)}>
                            Modifier
                        </Bouton>
                    )}
                </div>

                <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Info libelle="Date de naissance" valeur={dateCourte(agent.dateNaissance)} />
                    <Info libelle="Lieu de naissance" valeur={agent.lieuNaissance} />
                    <Info libelle="Situation familiale" valeur={agent.situationFamiliale ? SITUATIONS[agent.situationFamiliale] : null} />
                    <Info libelle="Enfants à charge" valeur={agent.enfants} />
                    <Info libelle="CNI" valeur={agent.cni} />
                    <Info libelle="N° CNPS" valeur={agent.numeroCnps} />
                    <Info libelle="Adresse" valeur={agent.adresse} />
                    <Info libelle="Personne à prévenir" valeur={agent.urgenceNom} />
                    <Info libelle="Téléphone d'urgence" valeur={agent.urgenceTelephone} />
                </div>

                {agent.observations && (
                    <div className="mt-5 rounded-xl bg-ink-50 p-4 text-sm text-ink-700 dark:bg-white/5 dark:text-ink-200">
                        {agent.observations}
                    </div>
                )}
            </Card>
        );
    }

    return (
        <Card className="p-6">
            <form onSubmit={enregistrer} className="space-y-5">
                <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Modifier le dossier</h2>

                <ErrorSummary errors={formulaire.errors} title="Corrigez ces points" />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Champ libelle="Date de naissance" erreur={formulaire.errors.date_naissance}>
                        <Input
                            type="date"
                            value={formulaire.data.date_naissance}
                            onChange={(event) => formulaire.setData('date_naissance', event.target.value)}
                        />
                    </Champ>
                    <Champ libelle="Lieu de naissance" erreur={formulaire.errors.lieu_naissance}>
                        <Input value={formulaire.data.lieu_naissance} onChange={(event) => formulaire.setData('lieu_naissance', event.target.value)} />
                    </Champ>
                    <Champ libelle="Situation familiale" erreur={formulaire.errors.situation_familiale}>
                        <Select
                            value={formulaire.data.situation_familiale}
                            onChange={(event) => formulaire.setData('situation_familiale', event.target.value)}
                        >
                            <option value="">—</option>
                            {Object.entries(SITUATIONS).map(([cle, libelle]) => (
                                <option key={cle} value={cle}>
                                    {libelle}
                                </option>
                            ))}
                        </Select>
                    </Champ>
                    <Champ libelle="Enfants à charge" erreur={formulaire.errors.enfants}>
                        <Input
                            type="number"
                            min={0}
                            max={30}
                            value={formulaire.data.enfants}
                            onChange={(event) => formulaire.setData('enfants', Number(event.target.value))}
                        />
                    </Champ>
                    <Champ libelle="CNI" erreur={formulaire.errors.cni}>
                        <Input value={formulaire.data.cni} onChange={(event) => formulaire.setData('cni', event.target.value)} />
                    </Champ>
                    <Champ libelle="N° CNPS" erreur={formulaire.errors.numero_cnps}>
                        <Input value={formulaire.data.numero_cnps} onChange={(event) => formulaire.setData('numero_cnps', event.target.value)} />
                    </Champ>
                    <Champ libelle="Adresse" erreur={formulaire.errors.adresse} className="sm:col-span-2">
                        <Input value={formulaire.data.adresse} onChange={(event) => formulaire.setData('adresse', event.target.value)} />
                    </Champ>
                    <Champ libelle="Personne à prévenir" erreur={formulaire.errors.urgence_nom}>
                        <Input value={formulaire.data.urgence_nom} onChange={(event) => formulaire.setData('urgence_nom', event.target.value)} />
                    </Champ>
                    <Champ libelle="Téléphone d'urgence" erreur={formulaire.errors.urgence_telephone}>
                        <Input
                            value={formulaire.data.urgence_telephone}
                            onChange={(event) => formulaire.setData('urgence_telephone', event.target.value)}
                        />
                    </Champ>
                </div>

                <Champ libelle="Observations" erreur={formulaire.errors.observations}>
                    <Textarea
                        rows={3}
                        value={formulaire.data.observations}
                        onChange={(event) => formulaire.setData('observations', event.target.value)}
                    />
                </Champ>

                <div className="flex justify-end gap-2">
                    <Bouton type="button" variante="secondaire" onClick={() => setEdition(false)}>
                        Annuler
                    </Bouton>
                    <Bouton type="submit" icon="check" disabled={formulaire.processing}>
                        {formulaire.processing ? 'Enregistrement…' : 'Enregistrer'}
                    </Bouton>
                </div>
            </form>
        </Card>
    );
}

// ----------------------------------------------------------------- diplomes

function Diplomes({ agent, diplomes, niveaux, peutGerer }: { agent: Agent; diplomes: Diplome[]; niveaux: string[]; peutGerer: boolean }) {
    const [ouvert, setOuvert] = useState(false);
    const [edite, setEdite] = useState<Diplome | null>(null);

    const vide = {
        intitule: '',
        niveau: '',
        specialite: '',
        etablissement: '',
        annee_obtention: '',
        piece_fournie: false as boolean,
    };

    const formulaire = useForm(vide);

    const ouvrir = (diplome: Diplome | null) => {
        setEdite(diplome);
        formulaire.clearErrors();
        formulaire.setData(
            diplome
                ? {
                      intitule: diplome.intitule,
                      niveau: diplome.niveau ?? '',
                      specialite: diplome.specialite ?? '',
                      etablissement: diplome.etablissement ?? '',
                      annee_obtention: diplome.anneeObtention ? String(diplome.anneeObtention) : '',
                      piece_fournie: diplome.pieceFournie,
                  }
                : vide,
        );
        setOuvert(true);
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        const apres = {
            onSuccess: () => {
                setOuvert(false);
                formulaire.reset();
            },
        };

        edite ? formulaire.put(routes.personnel.diplome(edite.id), apres) : formulaire.post(routes.personnel.diplomes(agent.id), apres);
    };

    const supprimer = (diplome: Diplome) => {
        if (confirm(`Supprimer « ${diplome.intitule} » ?`)) {
            formulaire.delete(routes.personnel.diplome(diplome.id), { preserveScroll: true });
        }
    };

    return (
        <Card className="p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Diplômes et qualifications</h2>
                    <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">Du plus récent au plus ancien.</p>
                </div>
                {peutGerer && (
                    <Bouton icon="plus" onClick={() => ouvrir(null)}>
                        Ajouter
                    </Bouton>
                )}
            </div>

            <div className="mt-5">
                {diplomes.length === 0 ? (
                    <Vide message="Aucun diplôme enregistré." icon="award" />
                ) : (
                    <Tableau entetes={['Intitulé', 'Niveau', 'Établissement', 'Année', 'Pièce', peutGerer ? '' : null].filter((e) => e !== null)}>
                        {diplomes.map((diplome) => (
                            <tr key={diplome.id}>
                                <td className="px-3 py-2.5">
                                    <p className="font-medium text-ink-900 dark:text-white">{diplome.intitule}</p>
                                    {diplome.specialite && <p className="text-xs text-ink-500 dark:text-ink-400">{diplome.specialite}</p>}
                                </td>
                                <td className="px-3 py-2.5 text-ink-600 dark:text-ink-300">{diplome.niveau ?? '—'}</td>
                                <td className="px-3 py-2.5 text-ink-600 dark:text-ink-300">{diplome.etablissement ?? '—'}</td>
                                <td className="px-3 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">{diplome.anneeObtention ?? '—'}</td>
                                <td className="px-3 py-2.5">
                                    {diplome.pieceFournie ? (
                                        <span className="inline-flex items-center gap-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                            <Icon name="check" className="h-3.5 w-3.5" /> fournie
                                        </span>
                                    ) : (
                                        <span className="text-xs text-amber-700 dark:text-amber-300">à réclamer</span>
                                    )}
                                </td>
                                {peutGerer && (
                                    <td className="px-3 py-2.5">
                                        <div className="flex justify-end gap-1">
                                            <button
                                                type="button"
                                                onClick={() => ouvrir(diplome)}
                                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-white/10"
                                                aria-label="Modifier"
                                            >
                                                <Icon name="pencil" className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => supprimer(diplome)}
                                                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                aria-label="Supprimer"
                                            >
                                                <Icon name="trash" className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        ))}
                    </Tableau>
                )}
            </div>

            <Modale titre={edite ? 'Modifier le diplôme' : 'Ajouter un diplôme'} ouverte={ouvert} onFermer={() => setOuvert(false)}>
                <form onSubmit={enregistrer} className="space-y-4">
                    <Champ libelle="Intitulé" erreur={formulaire.errors.intitule}>
                        <Input value={formulaire.data.intitule} onChange={(event) => formulaire.setData('intitule', event.target.value)} required />
                    </Champ>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Niveau" erreur={formulaire.errors.niveau}>
                            <Select value={formulaire.data.niveau} onChange={(event) => formulaire.setData('niveau', event.target.value)}>
                                <option value="">—</option>
                                {niveaux.map((niveau) => (
                                    <option key={niveau} value={niveau}>
                                        {niveau}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ libelle="Année d'obtention" erreur={formulaire.errors.annee_obtention}>
                            <Input
                                type="number"
                                min={1950}
                                max={new Date().getFullYear() + 1}
                                value={formulaire.data.annee_obtention}
                                onChange={(event) => formulaire.setData('annee_obtention', event.target.value)}
                            />
                        </Champ>
                        <Champ libelle="Spécialité" erreur={formulaire.errors.specialite}>
                            <Input value={formulaire.data.specialite} onChange={(event) => formulaire.setData('specialite', event.target.value)} />
                        </Champ>
                        <Champ libelle="Établissement" erreur={formulaire.errors.etablissement}>
                            <Input
                                value={formulaire.data.etablissement}
                                onChange={(event) => formulaire.setData('etablissement', event.target.value)}
                            />
                        </Champ>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={formulaire.data.piece_fournie}
                            onChange={(event) => formulaire.setData('piece_fournie', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        La copie du diplôme est au dossier
                    </label>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={formulaire.processing}>
                            {formulaire.processing ? 'Enregistrement…' : 'Enregistrer'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </Card>
    );
}

// ----------------------------------------------------------------- contrats

function Contrats({
    agent,
    contrats,
    referentiels,
    peutGerer,
}: {
    agent: Agent;
    contrats: Contrat[];
    referentiels: Props['referentiels'];
    peutGerer: boolean;
}) {
    const [ouvert, setOuvert] = useState(false);
    const [edite, setEdite] = useState<Contrat | null>(null);

    const vide = {
        employeur_id: '',
        type: 'cdi',
        poste: '',
        date_debut: '',
        date_fin: '',
        quotite: 100,
        profil_salaire_id: '',
        echelon_id: '',
        statut: 'actif',
        motif_fin: '',
        observations: '',
    };

    const formulaire = useForm(vide);

    const ouvrir = (contrat: Contrat | null) => {
        setEdite(contrat);
        formulaire.clearErrors();
        formulaire.setData(
            contrat
                ? {
                      employeur_id: String(contrat.employeurId),
                      type: contrat.type,
                      poste: contrat.poste,
                      date_debut: contrat.dateDebut ?? '',
                      date_fin: contrat.dateFin ?? '',
                      quotite: contrat.quotite,
                      profil_salaire_id: contrat.profilId ? String(contrat.profilId) : '',
                      echelon_id: contrat.echelonId ? String(contrat.echelonId) : '',
                      statut: contrat.statut,
                      motif_fin: contrat.motifFin ?? '',
                      observations: contrat.observations ?? '',
                  }
                : vide,
        );
        setOuvert(true);
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        const apres = {
            onSuccess: () => {
                setOuvert(false);
                formulaire.reset();
            },
        };

        edite ? formulaire.put(routes.personnel.contrat(edite.id), apres) : formulaire.post(routes.personnel.contrats(agent.id), apres);
    };

    const supprimer = (contrat: Contrat) => {
        if (confirm('Supprimer ce contrat ? Un contrat qui a déjà produit des bulletins doit être terminé, pas supprimé.')) {
            formulaire.delete(routes.personnel.contrat(contrat.id), { preserveScroll: true });
        }
    };

    const profilChoisi = referentiels.profils.find((profil) => String(profil.id) === formulaire.data.profil_salaire_id);

    return (
        <Card className="p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Contrats</h2>
                    <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        Un contrat par employeur : c'est lui qui porte le poste et la rémunération.
                    </p>
                </div>
                {peutGerer && (
                    <Bouton icon="plus" onClick={() => ouvrir(null)}>
                        Nouveau contrat
                    </Bouton>
                )}
            </div>

            <div className="mt-5 space-y-3">
                {contrats.length === 0 && <Vide message="Aucun contrat enregistré." icon="briefcase" />}

                {contrats.map((contrat) => (
                    <div key={contrat.id} className="rounded-xl border border-ink-200 p-4 dark:border-white/10">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-sm font-semibold text-ink-900 dark:text-white">{contrat.poste}</p>
                                    <Statut valeur={contrat.statut} />
                                </div>
                                <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                    {contrat.employeur} · {contrat.typeLibelle} · du {dateCourte(contrat.dateDebut)}
                                    {contrat.dateFin ? ` au ${dateCourte(contrat.dateFin)}` : ' (sans terme)'}
                                </p>
                            </div>

                            {peutGerer && (
                                <div className="flex gap-1">
                                    <button
                                        type="button"
                                        onClick={() => ouvrir(contrat)}
                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-white/10"
                                        aria-label="Modifier"
                                    >
                                        <Icon name="pencil" className="h-4 w-4" />
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => supprimer(contrat)}
                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                        aria-label="Supprimer"
                                    >
                                        <Icon name="trash" className="h-4 w-4" />
                                    </button>
                                </div>
                            )}
                        </div>

                        <div className="mt-3 grid gap-4 border-t border-ink-100 pt-3 sm:grid-cols-4 dark:border-white/5">
                            <Info libelle="Profil" valeur={contrat.profil} />
                            <Info libelle="Échelon" valeur={contrat.echelon} />
                            <Info libelle="Quotité" valeur={`${contrat.quotite} %`} />
                            <Info libelle="Salaire de base" valeur={fcfa(contrat.salaireBase)} />
                        </div>

                        {contrat.motifFin && (
                            <p className="mt-3 text-xs text-ink-500 dark:text-ink-400">Motif de fin : {contrat.motifFin}</p>
                        )}
                    </div>
                ))}
            </div>

            <Modale titre={edite ? 'Modifier le contrat' : 'Nouveau contrat'} ouverte={ouvert} onFermer={() => setOuvert(false)} large>
                <form onSubmit={enregistrer} className="space-y-4">
                    <ErrorSummary errors={formulaire.errors} title="Corrigez ces points" />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Employeur" erreur={formulaire.errors.employeur_id}>
                            <Select
                                value={formulaire.data.employeur_id}
                                onChange={(event) => formulaire.setData('employeur_id', event.target.value)}
                                required
                            >
                                <option value="">Choisir…</option>
                                {referentiels.employeurs.map((employeur) => (
                                    <option key={employeur.id} value={employeur.id}>
                                        {employeur.sigle} — {employeur.nom}
                                    </option>
                                ))}
                            </Select>
                        </Champ>

                        <Champ libelle="Nature" erreur={formulaire.errors.type}>
                            <Select value={formulaire.data.type} onChange={(event) => formulaire.setData('type', event.target.value)}>
                                {Object.entries(referentiels.types).map(([cle, libelle]) => (
                                    <option key={cle} value={cle}>
                                        {libelle}
                                    </option>
                                ))}
                            </Select>
                        </Champ>

                        <Champ libelle="Poste" erreur={formulaire.errors.poste} className="sm:col-span-2">
                            <Input value={formulaire.data.poste} onChange={(event) => formulaire.setData('poste', event.target.value)} required />
                        </Champ>

                        <Champ libelle="Début" erreur={formulaire.errors.date_debut}>
                            <Input
                                type="date"
                                value={formulaire.data.date_debut}
                                onChange={(event) => formulaire.setData('date_debut', event.target.value)}
                                required
                            />
                        </Champ>

                        <Champ libelle="Fin" erreur={formulaire.errors.date_fin} aide="À laisser vide pour un CDI.">
                            <Input
                                type="date"
                                value={formulaire.data.date_fin}
                                onChange={(event) => formulaire.setData('date_fin', event.target.value)}
                            />
                        </Champ>

                        <Champ libelle="Profil de salaire" erreur={formulaire.errors.profil_salaire_id}>
                            <Select
                                value={formulaire.data.profil_salaire_id}
                                onChange={(event) => formulaire.setData('profil_salaire_id', event.target.value)}
                            >
                                <option value="">Aucun</option>
                                {referentiels.profils.map((profil) => (
                                    <option key={profil.id} value={profil.id}>
                                        {profil.nom}
                                        {profil.echelon ? ` — ${profil.echelon}` : ''}
                                    </option>
                                ))}
                            </Select>
                        </Champ>

                        <Champ
                            libelle="Quotité (%)"
                            erreur={formulaire.errors.quotite}
                            aide={
                                profilChoisi
                                    ? `Base du profil : ${fcfa((profilChoisi.salaireBase * Number(formulaire.data.quotite || 0)) / 100)}`
                                    : undefined
                            }
                        >
                            <Input
                                type="number"
                                min={1}
                                max={100}
                                value={formulaire.data.quotite}
                                onChange={(event) => formulaire.setData('quotite', Number(event.target.value))}
                                required
                            />
                        </Champ>

                        <Champ libelle="Statut" erreur={formulaire.errors.statut}>
                            <Select value={formulaire.data.statut} onChange={(event) => formulaire.setData('statut', event.target.value)}>
                                <option value="actif">Actif</option>
                                <option value="suspendu">Suspendu</option>
                                <option value="termine">Terminé</option>
                            </Select>
                        </Champ>

                        {formulaire.data.statut === 'termine' && (
                            <Champ libelle="Motif de fin" erreur={formulaire.errors.motif_fin}>
                                <Input value={formulaire.data.motif_fin} onChange={(event) => formulaire.setData('motif_fin', event.target.value)} />
                            </Champ>
                        )}
                    </div>

                    <Champ libelle="Observations" erreur={formulaire.errors.observations}>
                        <Textarea
                            rows={2}
                            value={formulaire.data.observations}
                            onChange={(event) => formulaire.setData('observations', event.target.value)}
                        />
                    </Champ>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={formulaire.processing}>
                            {formulaire.processing ? 'Enregistrement…' : 'Enregistrer'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </Card>
    );
}

// ----------------------------------------------------------------- carriere

function Carriere({
    agent,
    evenements,
    contrats,
    types,
    peutGerer,
}: {
    agent: Agent;
    evenements: Evenement[];
    contrats: Contrat[];
    types: Record<string, string>;
    peutGerer: boolean;
}) {
    const [ouvert, setOuvert] = useState(false);

    const formulaire = useForm({
        contrat_id: '',
        date_evenement: new Date().toISOString().slice(0, 10),
        type: 'avancement',
        libelle: '',
        details: '',
    });

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();
        formulaire.post(routes.personnel.carriere(agent.id), {
            onSuccess: () => {
                setOuvert(false);
                formulaire.reset();
            },
        });
    };

    const supprimer = (evenement: Evenement) => {
        if (confirm('Retirer cet événement du dossier ?')) {
            formulaire.delete(routes.personnel.evenement(evenement.id), { preserveScroll: true });
        }
    };

    return (
        <Card className="p-6">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Carrière</h2>
                    <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                        Recrutements, avancements, affectations, formations et sanctions.
                    </p>
                </div>
                {peutGerer && (
                    <Bouton icon="plus" onClick={() => setOuvert(true)}>
                        Ajouter
                    </Bouton>
                )}
            </div>

            <div className="mt-5">
                {evenements.length === 0 ? (
                    <Vide message="Aucun événement enregistré." icon="layers" />
                ) : (
                    <ol className="relative space-y-4 border-l border-ink-200 pl-6 dark:border-white/10">
                        {evenements.map((evenement) => (
                            <li key={evenement.id} className="relative">
                                <span className="absolute -left-[1.85rem] top-1.5 h-2.5 w-2.5 rounded-full bg-teal-500 ring-4 ring-white dark:ring-ink-900" />
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="rounded-full bg-teal-50 px-2.5 py-0.5 text-[11px] font-semibold text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                                                {evenement.typeLibelle}
                                            </span>
                                            <span className="text-xs tabular-nums text-ink-400">{dateCourte(evenement.date)}</span>
                                            {evenement.employeur && <span className="text-xs text-ink-400">· {evenement.employeur}</span>}
                                        </div>
                                        <p className="mt-1 text-sm font-medium text-ink-900 dark:text-white">{evenement.libelle}</p>
                                        {evenement.details && (
                                            <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{evenement.details}</p>
                                        )}
                                    </div>
                                    {peutGerer && (
                                        <button
                                            type="button"
                                            onClick={() => supprimer(evenement)}
                                            className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                            aria-label="Supprimer"
                                        >
                                            <Icon name="trash" className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ol>
                )}
            </div>

            <Modale titre="Ajouter un événement" ouverte={ouvert} onFermer={() => setOuvert(false)}>
                <form onSubmit={enregistrer} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Type" erreur={formulaire.errors.type}>
                            <Select value={formulaire.data.type} onChange={(event) => formulaire.setData('type', event.target.value)}>
                                {Object.entries(types).map(([cle, libelle]) => (
                                    <option key={cle} value={cle}>
                                        {libelle}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ libelle="Date" erreur={formulaire.errors.date_evenement}>
                            <Input
                                type="date"
                                value={formulaire.data.date_evenement}
                                onChange={(event) => formulaire.setData('date_evenement', event.target.value)}
                                required
                            />
                        </Champ>
                    </div>

                    <Champ libelle="Intitulé" erreur={formulaire.errors.libelle}>
                        <Input value={formulaire.data.libelle} onChange={(event) => formulaire.setData('libelle', event.target.value)} required />
                    </Champ>

                    <Champ libelle="Contrat concerné" erreur={formulaire.errors.contrat_id}>
                        <Select value={formulaire.data.contrat_id} onChange={(event) => formulaire.setData('contrat_id', event.target.value)}>
                            <option value="">Aucun en particulier</option>
                            {contrats.map((contrat) => (
                                <option key={contrat.id} value={contrat.id}>
                                    {contrat.employeur} — {contrat.poste}
                                </option>
                            ))}
                        </Select>
                    </Champ>

                    <Champ libelle="Détails" erreur={formulaire.errors.details}>
                        <Textarea rows={3} value={formulaire.data.details} onChange={(event) => formulaire.setData('details', event.target.value)} />
                    </Champ>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={formulaire.processing}>
                            {formulaire.processing ? 'Enregistrement…' : 'Enregistrer'}
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </Card>
    );
}

// ---------------------------------------------------------------- bulletins

function Bulletins({ bulletins }: { bulletins: BulletinLigne[] }) {
    return (
        <Card className="p-6">
            <h2 className="text-sm font-semibold text-ink-900 dark:text-white">Douze derniers bulletins</h2>

            <div className="mt-5">
                {bulletins.length === 0 ? (
                    <Vide message="Aucun bulletin édité pour cet agent." icon="wallet" />
                ) : (
                    <Tableau entetes={['Période', 'Employeur', 'Net', 'Statut', '']}>
                        {bulletins.map((bulletin) => (
                            <tr key={bulletin.id}>
                                <td className="px-3 py-2.5 font-medium text-ink-900 dark:text-white">{bulletin.periode}</td>
                                <td className="px-3 py-2.5 text-ink-600 dark:text-ink-300">{bulletin.employeur}</td>
                                <td className="px-3 py-2.5 font-semibold tabular-nums text-ink-900 dark:text-white">{fcfa(bulletin.salaireNet)}</td>
                                <td className="px-3 py-2.5">
                                    <Statut valeur={bulletin.statut} />
                                </td>
                                <td className="px-3 py-2.5 text-right">
                                    <Link
                                        href={routes.personnel.bulletin(bulletin.id)}
                                        className="text-sm font-medium text-teal-700 hover:underline dark:text-teal-300"
                                    >
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </Tableau>
                )}
            </div>
        </Card>
    );
}
