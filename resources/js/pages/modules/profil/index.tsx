import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import { Card, Input, Select, Textarea } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { cn, routes } from '@/lib/utils';
import { ApercuBadge, LE_GROUPE, type Institut } from '@/pages/modules/badges/carte';

interface Piece {
    id: number;
    statut: 'en_attente' | 'valide' | 'refuse';
    statutLibelle: string;
    soumisParLAgent: boolean;
    motifRefus: string | null;
}

interface Diplome extends Piece {
    intitule: string;
    niveau: string | null;
    specialite: string | null;
    etablissement: string | null;
    anneeObtention: number | null;
}

interface Document extends Piece {
    type: string;
    typeLibelle: string;
    libelle: string;
    nomOrigine: string;
    extension: string;
    poids: string | null;
    note: string | null;
    deposeLe: string | null;
    manquant: boolean;
}

interface Ligne {
    libelle: string;
    typeCalcul: 'fixe' | 'pourcentage';
    valeur: number;
}

interface Props {
    identite: {
        nom: string | null;
        matricule: string | null;
        email: string | null;
        telephone: string | null;
        poste: string | null;
        photoUrl: string | null;
        initiales: string | null;
        inscritLe: string | null;
        employeur: { sigle: string; nom: string } | null;
    };
    dossier: Record<string, string | number | boolean | null>;
    instituts: { id: number; nom: string; logoUrl: string | null; couleur: string | null; poste: string | null }[];
    badge: { validite: number; mention: string | null; moduleOuvert: boolean };
    affectations: {
        id: number;
        employeur: string | null;
        poste: string | null;
        typeLibelle: string;
        dateDebut: string | null;
        dateFin: string | null;
        quotite: number;
        statut: string;
    }[];
    remuneration: {
        employeur: string | null;
        profil: string | null;
        categorie: string | null;
        echelon: string | null;
        salaireBase: number;
        quotite: number;
        indemnites: Ligne[];
        retenues: Ligne[];
    } | null;
    parcours: { id: number; dateEvenement: string | null; typeLibelle: string; libelle: string; details: string | null }[];
    diplomes: Diplome[];
    documents: Document[];
    referentiels: { niveaux: string[]; typesDocument: Record<string, string> };
    saisie: Saisie;
    situationsFamiliales: Record<string, string>;
}

type Onglet = 'informations' | 'parcours' | 'remuneration' | 'badge' | 'pieces';

/** Ce que chacun peut corriger de lui-même. */
interface Saisie {
    name: string;
    lastname: string;
    email: string;
    phone: string;
    date_naissance: string;
    lieu_naissance: string;
    situation_familiale: string;
    enfants: number;
    cni: string;
    numero_cnps: string;
    adresse: string;
    urgence_nom: string;
    urgence_telephone: string;
}

/** Les champs libres du formulaire, hors la liste déroulante et la photo. */
const CHAMPS: [keyof Saisie, string, string][] = [
    ['name', 'Prénom', 'text'],
    ['lastname', 'Nom de famille', 'text'],
    ['email', 'Adresse professionnelle', 'email'],
    ['phone', 'Téléphone', 'tel'],
    ['date_naissance', 'Date de naissance', 'date'],
    ['lieu_naissance', 'Lieu de naissance', 'text'],
    ['cni', 'Numéro de CNI', 'text'],
    ['numero_cnps', 'Numéro CNPS', 'text'],
    ['enfants', 'Enfants à charge', 'number'],
    ['adresse', 'Adresse', 'text'],
    ['urgence_nom', 'Personne à prévenir', 'text'],
    ['urgence_telephone', 'Son téléphone', 'tel'],
];

const fcfa = (montant: number) => `${new Intl.NumberFormat('fr-FR').format(Math.round(montant))} F`;

/**
 * Un repère chiffré, sur une seule ligne.
 *
 * En carte, les quatre prenaient une bande entière pour quatre nombres — et
 * sur un téléphone, quatre bandes empilées avant d'atteindre le contenu.
 */
function Repere({ icon, valeur, libelle }: { icon: string; valeur: string; libelle: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-lg bg-ink-100/70 px-2.5 py-1 text-xs text-ink-600 dark:bg-white/5 dark:text-ink-300">
            <Icon name={icon} className="h-3.5 w-3.5 text-ink-400" />
            <span className="font-semibold text-ink-900 dark:text-white">{valeur}</span>
            {libelle}
        </span>
    );
}

/** Un chiffre clé encadré, pour la rémunération. */
function Chiffre({ icon, valeur, libelle }: { icon: string; valeur: string; libelle: string }) {
    return (
        <div className="flex items-center gap-3 rounded-xl border border-ink-200 px-3.5 py-2.5 dark:border-white/10">
            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">
                <Icon name={icon} className="h-4 w-4" />
            </span>
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">{valeur}</p>
                <p className="truncate text-[11px] text-ink-500 dark:text-ink-400">{libelle}</p>
            </div>
        </div>
    );
}

/** Une donnée en lecture seule, étiquette au-dessus. */
function Donnee({ libelle, valeur }: { libelle: string; valeur: React.ReactNode }) {
    return (
        <div className="border-b border-ink-100 py-2.5 last:border-0 dark:border-white/5">
            <dt className="text-[11px] uppercase tracking-wide text-ink-400">{libelle}</dt>
            <dd className="mt-0.5 text-sm text-ink-900 dark:text-white">{valeur || <span className="text-ink-300">—</span>}</dd>
        </div>
    );
}

function Etat({ piece }: { piece: Piece }) {
    const tons: Record<string, string> = {
        en_attente: 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
        valide: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
        refuse: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
    };

    return <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium', tons[piece.statut])}>{piece.statutLibelle}</span>;
}

/** Un bloc titré à l'intérieur d'un onglet. */
function Bloc({
    titre,
    sous,
    action,
    children,
    className,
}: {
    titre: string;
    sous?: string;
    action?: React.ReactNode;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('p-5', className)}>
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-[13px] font-semibold uppercase tracking-[0.08em] text-ink-500">{titre}</h2>
                    {sous && <p className="mt-1 text-xs text-ink-500 dark:text-ink-400">{sous}</p>}
                </div>
                {action}
            </div>
            {children}
        </Card>
    );
}

function Vide({ message }: { message: string }) {
    return (
        <p className="rounded-xl border border-dashed border-ink-200 px-4 py-8 text-center text-sm text-ink-400 dark:border-white/10">
            {message}
        </p>
    );
}

export default function MonProfil({
    identite,
    dossier,
    instituts,
    badge,
    affectations,
    remuneration,
    parcours,
    diplomes,
    documents,
    referentiels,
    saisie,
    situationsFamiliales,
}: Props) {
    const [onglet, setOnglet] = useState<Onglet>('informations');
    const [modification, setModification] = useState(false);
    const [apercuPhoto, setApercuPhoto] = useState<string | null>(identite.photoUrl);
    const [ouvertDiplome, setOuvertDiplome] = useState(false);
    const [ouvertDocument, setOuvertDocument] = useState(false);

    const mesInfos = useForm({ ...saisie, photo: null as File | null });

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();
        mesInfos.post(routes.profil.index, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setModification(false),
        });
    };

    const diplome = useForm({ intitule: '', niveau: '', specialite: '', etablissement: '', annee_obtention: '' });
    const document = useForm({ type: 'cv', libelle: '', note: '', fichier: null as File | null });

    const envoyerDiplome = (event: FormEvent) => {
        event.preventDefault();
        diplome.post(routes.profil.diplomes, {
            preserveScroll: true,
            onSuccess: () => {
                diplome.reset();
                setOuvertDiplome(false);
            },
        });
    };

    const envoyerDocument = (event: FormEvent) => {
        event.preventDefault();
        document.post(routes.profil.documents, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                document.reset();
                setOuvertDocument(false);
            },
        });
    };

    const retirer = (url: string, question: string) => {
        if (confirm(question)) router.delete(url, { preserveScroll: true });
    };

    const enAttente = [...diplomes, ...documents].filter((piece) => piece.statut === 'en_attente').length;
    const contratsActifs = affectations.filter((contrat) => contrat.statut === 'actif').length;

    const onglets: { cle: Onglet; libelle: string; icon: string; compte: number | null }[] = [
        { cle: 'informations', libelle: 'Informations', icon: 'user', compte: null },
        { cle: 'parcours', libelle: 'Parcours', icon: 'layers', compte: affectations.length + parcours.length },
        { cle: 'remuneration', libelle: 'Rémunération', icon: 'wallet', compte: null },
        { cle: 'badge', libelle: 'Mon badge', icon: 'id-card', compte: null },
        { cle: 'pieces', libelle: 'Mes pièces', icon: 'document', compte: diplomes.length + documents.length },
    ];

    /*
     * Le badge se décline par institut. Rattaché à un seul, c'est le sien
     * d'office ; rattaché à plusieurs, on demande lequel. Le groupe reste
     * proposé pour qui porte les couleurs de la maison.
     */
    const choixBadge: Institut[] = [
        ...instituts.map((institut) => ({
            id: institut.id,
            name: institut.nom,
            color: institut.couleur,
            logoUrl: institut.logoUrl,
        })),
        LE_GROUPE,
    ];

    const [institutBadge, setInstitutBadge] = useState<string>(
        instituts.length === 1 ? String(instituts[0].id) : String(LE_GROUPE.id),
    );

    const badgeChoisi = choixBadge.find((institut) => String(institut.id) === institutBadge) ?? LE_GROUPE;

    return (
        <PortalLayout title="Mon profil">
            <div className="space-y-5">
                {/* ------------------------------------------------- en-tête */}
                <Card className="overflow-hidden">
                    <div className="h-20 bg-gradient-to-r from-teal-600 to-teal-500" />

                    <div className="px-6 pb-5">
                        <div className="-mt-10 flex flex-wrap items-end gap-4">
                            <Avatar
                                url={identite.photoUrl}
                                initials={identite.initiales ?? '?'}
                                className="h-24 w-24 text-2xl ring-4 ring-white dark:ring-ink-900"
                            />

                            <div className="min-w-0 flex-1 pb-1">
                                <h1 className="truncate text-xl font-semibold text-ink-900 dark:text-white">{identite.nom}</h1>

                                <p className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500 dark:text-ink-400">
                                    {/* Le matricule tient sa place a cote du nom : c'est lui
                                        qu'on cherche, pas une mention releguee au bout de la ligne. */}
                                    <span className="rounded-lg bg-ink-100 px-2 py-0.5 font-mono text-[13px] font-medium text-ink-800 dark:bg-white/10 dark:text-ink-100">
                                        {identite.matricule ?? 'matricule à venir'}
                                    </span>
                                    <span className="truncate">
                                        {identite.poste ?? 'Poste à préciser'}
                                        {identite.employeur && ` · ${identite.employeur.sigle}`}
                                    </span>
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => {
                                    setOnglet('informations');
                                    setModification(true);
                                }}
                                className="mb-1 inline-flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:bg-ink-900 dark:text-ink-200 dark:hover:bg-white/5"
                            >
                                <Icon name="pencil" className="h-4 w-4" />
                                Modifier
                            </button>
                        </div>

                        <div className="mt-3 flex flex-wrap items-center gap-1.5">
                            <Repere
                                icon="building"
                                valeur={String(instituts.length)}
                                libelle={instituts.length > 1 ? 'instituts' : 'institut'}
                            />
                            <Repere icon="briefcase" valeur={String(contratsActifs)} libelle="contrat(s)" />
                            <Repere
                                icon="calendar"
                                valeur={dossier.anciennete ? `${dossier.anciennete}` : '—'}
                                libelle="an(s) dans le groupe"
                            />
                            {enAttente > 0 && <Repere icon="document" valeur={String(enAttente)} libelle="pièce(s) en attente" />}
                        </div>

                        <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-xs text-ink-600 dark:text-ink-300">
                            {identite.email && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="mail" className="h-3.5 w-3.5 text-ink-400" />
                                    {identite.email}
                                </span>
                            )}
                            {identite.telephone && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="phone" className="h-3.5 w-3.5 text-ink-400" />
                                    {identite.telephone}
                                </span>
                            )}
                            {identite.inscritLe && (
                                <span className="inline-flex items-center gap-1.5">
                                    <Icon name="calendar" className="h-3.5 w-3.5 text-ink-400" />
                                    Inscrit le {identite.inscritLe}
                                </span>
                            )}
                        </div>

                        <nav className="mt-5 flex flex-wrap gap-1 border-t border-ink-100 pt-4 dark:border-white/5">
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
                    </div>
                </Card>

                {/* ------------------------------------------------ informations */}
                {onglet === 'informations' && modification && (
                    <Bloc
                        titre="Modifier mes informations"
                        sous="Vous les connaissez mieux que quiconque. Le matricule et le poste restent attribués par le service du personnel."
                        action={
                            <button
                                type="button"
                                onClick={() => setModification(false)}
                                className="rounded-xl border border-ink-200 px-3.5 py-2 text-sm text-ink-700 dark:border-white/10 dark:text-ink-200"
                            >
                                Annuler
                            </button>
                        }
                    >
                        <form onSubmit={enregistrer} className="space-y-5">
                            <div className="flex flex-wrap items-center gap-4">
                                <Avatar url={apercuPhoto} initials={identite.initiales ?? '?'} className="h-16 w-16 text-lg" />
                                <div>
                                    <label className="text-xs text-ink-600 dark:text-ink-300">Photo de profil</label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(event) => {
                                            const fichier = event.target.files?.[0] ?? null;
                                            mesInfos.setData('photo', fichier);
                                            setApercuPhoto(fichier ? URL.createObjectURL(fichier) : identite.photoUrl);
                                        }}
                                        className="mt-1 block text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-sm file:text-ink-700 dark:text-ink-300 dark:file:bg-white/10 dark:file:text-ink-200"
                                    />
                                    {mesInfos.errors.photo && <p className="mt-1 text-xs text-red-600">{mesInfos.errors.photo}</p>}
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {CHAMPS.map(([champ, libelle, type]) => (
                                    <div key={String(champ)}>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">{libelle}</label>
                                        <Input
                                            type={type}
                                            value={String(mesInfos.data[champ] ?? '')}
                                            onChange={(event) => mesInfos.setData(champ, event.target.value)}
                                        />
                                        {mesInfos.errors[champ] && (
                                            <p className="mt-1 text-xs text-red-600">{mesInfos.errors[champ]}</p>
                                        )}
                                    </div>
                                ))}

                                <div>
                                    <label className="text-xs text-ink-600 dark:text-ink-300">Situation familiale</label>
                                    <Select
                                        value={String(mesInfos.data.situation_familiale ?? '')}
                                        onChange={(event) => mesInfos.setData('situation_familiale', event.target.value)}
                                    >
                                        <option value="">Non précisée</option>
                                        {Object.entries(situationsFamiliales).map(([cle, libelle]) => (
                                            <option key={cle} value={cle}>
                                                {libelle}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>

                            <button
                                type="submit"
                                disabled={mesInfos.processing}
                                className="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-teal-700 disabled:opacity-60"
                            >
                                {mesInfos.processing ? 'Enregistrement…' : 'Enregistrer'}
                            </button>
                        </form>
                    </Bloc>
                )}

                {onglet === 'informations' && !modification && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Bloc titre="Mes instituts" sous="Les entités du groupe auxquelles vous êtes rattaché.">
                            {instituts.length === 0 ? (
                                <Vide message="Aucun institut rattaché pour l’instant." />
                            ) : (
                                <div className="space-y-2">
                                    {instituts.map((institut) => (
                                        <div
                                            key={institut.id}
                                            className="flex items-center gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                        >
                                            <span
                                                className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-ink-50 dark:bg-white/5"
                                                style={{ color: institut.couleur ?? undefined }}
                                            >
                                                {institut.logoUrl ? (
                                                    <img src={institut.logoUrl} alt="" className="h-full w-full object-contain p-1" />
                                                ) : (
                                                    <Icon name="building" className="h-4 w-4" />
                                                )}
                                            </span>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-ink-900 dark:text-white">{institut.nom}</p>
                                                <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                                    {institut.poste ?? 'poste à préciser'}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Bloc>

                        <Bloc
                            titre="Mon dossier administratif"
                            sous="Rempli par le service du personnel. Signalez-lui toute erreur."
                        >
                            {dossier.ouvert ? (
                                <dl>
                                    <Donnee libelle="Date de naissance" valeur={dossier.dateNaissance as string} />
                                    <Donnee libelle="Lieu de naissance" valeur={dossier.lieuNaissance as string} />
                                    <Donnee libelle="Situation familiale" valeur={dossier.situationFamiliale as string} />
                                    <Donnee libelle="Enfants à charge" valeur={String(dossier.enfants ?? 0)} />
                                    <Donnee libelle="CNI" valeur={dossier.cni as string} />
                                    <Donnee libelle="N° CNPS" valeur={dossier.numeroCnps as string} />
                                    <Donnee libelle="Adresse" valeur={dossier.adresse as string} />
                                    <Donnee
                                        libelle="Personne à prévenir"
                                        valeur={
                                            dossier.urgenceNom
                                                ? `${dossier.urgenceNom}${dossier.urgenceTelephone ? ` · ${dossier.urgenceTelephone}` : ''}`
                                                : null
                                        }
                                    />
                                </dl>
                            ) : (
                                <Vide message="Votre dossier n’a pas encore été ouvert par le service du personnel." />
                            )}
                        </Bloc>
                    </div>
                )}

                {/* ---------------------------------------------------- parcours */}
                {onglet === 'parcours' && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Bloc titre="Mes affectations" sous="Vos contrats, en cours et passés.">
                            {affectations.length === 0 ? (
                                <Vide message="Aucun contrat enregistré." />
                            ) : (
                                <div className="space-y-2">
                                    {affectations.map((contrat) => (
                                        <div
                                            key={contrat.id}
                                            className={cn(
                                                'rounded-xl border p-3',
                                                contrat.statut === 'actif'
                                                    ? 'border-teal-200 bg-teal-50/40 dark:border-teal-500/30 dark:bg-teal-500/5'
                                                    : 'border-ink-200 dark:border-white/10',
                                            )}
                                        >
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <p className="text-sm font-medium text-ink-900 dark:text-white">
                                                    {contrat.poste || <span className="italic text-ink-400">Poste à préciser</span>}
                                                </p>
                                                {contrat.statut === 'actif' && (
                                                    <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[11px] font-medium text-teal-800 dark:bg-teal-500/20 dark:text-teal-200">
                                                        en cours
                                                    </span>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">
                                                {contrat.employeur} · {contrat.typeLibelle}
                                                {contrat.quotite !== 100 && ` · ${contrat.quotite} %`}
                                            </p>
                                            <p className="mt-1 text-xs text-ink-400">
                                                {contrat.dateDebut ?? 'début non précisé'}
                                                {contrat.dateFin ? ` → ${contrat.dateFin}` : ''}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Bloc>

                        <Bloc titre="Mon parcours" sous="Les mouvements inscrits à votre dossier.">
                            {parcours.length === 0 ? (
                                <Vide message="Aucun mouvement enregistré." />
                            ) : (
                                <ol className="space-y-4 border-l border-ink-200 pl-5 dark:border-white/10">
                                    {parcours.map((evenement) => (
                                        <li key={evenement.id} className="relative">
                                            <span className="absolute -left-[23px] top-1.5 h-2.5 w-2.5 rounded-full bg-teal-600 ring-4 ring-white dark:ring-ink-900" />
                                            <p className="text-sm font-medium text-ink-900 dark:text-white">{evenement.libelle}</p>
                                            <p className="text-xs text-ink-500 dark:text-ink-400">
                                                {evenement.typeLibelle}
                                                {evenement.dateEvenement && ` · ${evenement.dateEvenement}`}
                                            </p>
                                            {evenement.details && <p className="mt-0.5 text-xs text-ink-400">{evenement.details}</p>}
                                        </li>
                                    ))}
                                </ol>
                            )}
                        </Bloc>
                    </div>
                )}

                {/* ------------------------------------------------- rémunération */}
                {onglet === 'remuneration' && (
                    <>
                        {remuneration ? (
                            <Bloc
                                titre="Mon profil de salaire"
                                sous="Tel que votre contrat en cours le porte. Les montants réellement versés figurent sur vos bulletins."
                                action={
                                    <a
                                        href={routes.mesBulletins.index}
                                        className="inline-flex items-center gap-2 rounded-xl border border-ink-200 px-3.5 py-2 text-sm font-medium text-ink-700 transition hover:bg-ink-50 dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/5"
                                    >
                                        <Icon name="wallet" className="h-4 w-4" />
                                        Mes bulletins
                                    </a>
                                }
                            >
                                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <Chiffre icon="layers" valeur={remuneration.categorie ?? '—'} libelle="catégorie" />
                                    <Chiffre icon="award" valeur={remuneration.echelon ?? '—'} libelle="échelon" />
                                    <Chiffre icon="briefcase" valeur={remuneration.profil ?? '—'} libelle="profil appliqué" />
                                    <Chiffre
                                        icon="wallet"
                                        valeur={fcfa(remuneration.salaireBase)}
                                        libelle={
                                            remuneration.quotite !== 100
                                                ? `salaire de base · quotité ${remuneration.quotite} %`
                                                : 'salaire de base'
                                        }
                                    />
                                </div>

                                {(remuneration.indemnites.length > 0 || remuneration.retenues.length > 0) && (
                                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <p className="mb-2 text-[11px] uppercase tracking-wide text-ink-400">Indemnités</p>
                                            {remuneration.indemnites.length === 0 ? (
                                                <p className="text-sm text-ink-400">Aucune.</p>
                                            ) : (
                                                <ul className="space-y-1.5">
                                                    {remuneration.indemnites.map((ligne) => (
                                                        <li
                                                            key={ligne.libelle}
                                                            className="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"
                                                        >
                                                            <span className="truncate">{ligne.libelle}</span>
                                                            <span className="shrink-0 tabular-nums font-medium">
                                                                {ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>

                                        <div>
                                            <p className="mb-2 text-[11px] uppercase tracking-wide text-ink-400">Retenues</p>
                                            {remuneration.retenues.length === 0 ? (
                                                <p className="text-sm text-ink-400">Aucune.</p>
                                            ) : (
                                                <ul className="space-y-1.5">
                                                    {remuneration.retenues.map((ligne) => (
                                                        <li
                                                            key={ligne.libelle}
                                                            className="flex items-center justify-between gap-3 rounded-lg bg-red-50 px-3 py-1.5 text-sm text-red-900 dark:bg-red-500/10 dark:text-red-200"
                                                        >
                                                            <span className="truncate">{ligne.libelle}</span>
                                                            <span className="shrink-0 tabular-nums font-medium">
                                                                {ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </Bloc>
                        ) : (
                            <Bloc titre="Mon profil de salaire">
                                <Vide message="Aucun contrat en cours : votre rémunération n’est pas encore établie." />
                            </Bloc>
                        )}
                    </>
                )}

                {/* ------------------------------------------------------- badge */}
                {onglet === 'badge' && (
                    <Bloc
                        titre="Mon badge professionnel"
                        sous="Le badge tel qu'il sera fabriqué, recto et verso."
                        action={
                            badge.moduleOuvert ? (
                                <Link
                                    href={routes.badges.index}
                                    className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
                                >
                                    <Icon name="id-card" className="h-4 w-4" />
                                    Demander mon badge
                                </Link>
                            ) : null
                        }
                    >
                        <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                            <ApercuBadge
                                donnees={{
                                    nomAffiche: identite.nom ?? '',
                                    posteAffiche: identite.poste,
                                    matricule: identite.matricule,
                                    photoUrl: identite.photoUrl,
                                    initiales: identite.initiales,
                                    institut: badgeChoisi,
                                }}
                                validite={badge.validite}
                                mention={badge.mention}
                                className="shrink-0"
                            />

                            <div className="min-w-0 flex-1 space-y-4">
                                {instituts.length > 1 ? (
                                    <p className="text-sm text-ink-600 dark:text-ink-300">
                                        Vous servez {instituts.length} instituts : choisissez celui dont le logo
                                        figurera sur votre badge.
                                    </p>
                                ) : (
                                    <p className="text-sm text-ink-600 dark:text-ink-300">
                                        {instituts.length === 1
                                            ? `Le logo de ${instituts[0].nom} est retenu d'office. Vous pouvez lui préférer celui du groupe.`
                                            : "Aucun institut ne vous est rattaché : votre badge porte les couleurs du groupe."}
                                    </p>
                                )}

                                <div className="grid gap-2 sm:grid-cols-2">
                                    {choixBadge.map((institut) => {
                                        const actif = String(institut.id) === institutBadge;

                                        return (
                                            <button
                                                key={institut.id}
                                                type="button"
                                                onClick={() => setInstitutBadge(String(institut.id))}
                                                className={cn(
                                                    'flex items-center gap-3 rounded-xl border px-3.5 py-3 text-left transition',
                                                    actif
                                                        ? 'border-transparent ring-2'
                                                        : 'border-ink-200 hover:bg-ink-50 dark:border-white/10 dark:hover:bg-white/5',
                                                )}
                                                style={actif ? { ['--tw-ring-color' as string]: institut.color ?? '#334155' } : undefined}
                                            >
                                                <span className="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-lg bg-white ring-1 ring-ink-200">
                                                    {institut.logoUrl ? (
                                                        <img src={institut.logoUrl} alt="" className="h-full w-full object-contain p-1" />
                                                    ) : (
                                                        <span className="text-[10px] font-semibold text-ink-600">
                                                            {institut.name.slice(0, 4)}
                                                        </span>
                                                    )}
                                                </span>
                                                <span className="block min-w-0 truncate text-sm font-medium text-ink-900 dark:text-white">
                                                    {institut.name}
                                                </span>
                                                {actif && <Icon name="check" className="ml-auto h-4 w-4 text-ink-400" />}
                                            </button>
                                        );
                                    })}
                                </div>

                                <p className="text-xs text-ink-400">
                                    Rendu indicatif : le numéro et la photo définitive sont fixés à la fabrication.
                                    Le choix fait ici ne vaut pas demande.
                                </p>
                            </div>
                        </div>
                    </Bloc>
                )}

                {/* ------------------------------------------------------ pièces */}
                {onglet === 'pieces' && (
                    <div className="space-y-5">
                        {enAttente > 0 && (
                            <p className="flex items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                                <Icon name="alert" className="h-4 w-4 shrink-0" />
                                {enAttente} pièce(s) en attente de validation par le service du personnel.
                            </p>
                        )}

                        <Bloc
                            titre="Mes diplômes"
                            sous="Déclarez-les ici : le service du personnel les validera."
                            action={
                                <button
                                    type="button"
                                    onClick={() => setOuvertDiplome((ouvert) => !ouvert)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
                                >
                                    <Icon name="plus" className="h-4 w-4" />
                                    Déclarer
                                </button>
                            }
                        >
                            {ouvertDiplome && (
                                <form
                                    onSubmit={envoyerDiplome}
                                    className="mb-4 grid gap-3 rounded-xl bg-ink-50 p-4 sm:grid-cols-2 dark:bg-white/5"
                                >
                                    <div className="sm:col-span-2">
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Intitulé</label>
                                        <Input
                                            value={diplome.data.intitule}
                                            onChange={(event) => diplome.setData('intitule', event.target.value)}
                                            placeholder="Licence en sciences de gestion"
                                            required
                                        />
                                        {diplome.errors.intitule && (
                                            <p className="mt-1 text-xs text-red-600">{diplome.errors.intitule}</p>
                                        )}
                                    </div>

                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Niveau</label>
                                        <Select
                                            value={diplome.data.niveau}
                                            onChange={(event) => diplome.setData('niveau', event.target.value)}
                                        >
                                            <option value="">Non précisé</option>
                                            {referentiels.niveaux.map((niveau) => (
                                                <option key={niveau} value={niveau}>
                                                    {niveau}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>

                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Année d’obtention</label>
                                        <Input
                                            type="number"
                                            value={diplome.data.annee_obtention}
                                            onChange={(event) => diplome.setData('annee_obtention', event.target.value)}
                                            placeholder="2019"
                                        />
                                    </div>

                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Spécialité</label>
                                        <Input
                                            value={diplome.data.specialite}
                                            onChange={(event) => diplome.setData('specialite', event.target.value)}
                                        />
                                    </div>

                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Établissement</label>
                                        <Input
                                            value={diplome.data.etablissement}
                                            onChange={(event) => diplome.setData('etablissement', event.target.value)}
                                        />
                                    </div>

                                    <div className="flex gap-2 sm:col-span-2">
                                        <button
                                            type="submit"
                                            disabled={diplome.processing}
                                            className="rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700 disabled:opacity-60"
                                        >
                                            Soumettre
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setOuvertDiplome(false)}
                                            className="rounded-xl border border-ink-200 px-3.5 py-2 text-sm text-ink-700 dark:border-white/10 dark:text-ink-200"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {diplomes.length === 0 ? (
                                <Vide message="Aucun diplôme déclaré." />
                            ) : (
                                <div className="space-y-2">
                                    {diplomes.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                        >
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-ink-900 dark:text-white">{item.intitule}</p>
                                                <p className="text-xs text-ink-500 dark:text-ink-400">
                                                    {[item.niveau, item.specialite, item.etablissement, item.anneeObtention]
                                                        .filter(Boolean)
                                                        .join(' · ') || 'Aucune précision'}
                                                </p>
                                                {item.motifRefus && (
                                                    <p className="mt-1 text-xs text-red-600">Motif : {item.motifRefus}</p>
                                                )}
                                            </div>

                                            <div className="flex shrink-0 items-center gap-2">
                                                <Etat piece={item} />
                                                {item.statut === 'en_attente' && item.soumisParLAgent && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            retirer(routes.profil.diplome(item.id), `Retirer « ${item.intitule} » ?`)
                                                        }
                                                        aria-label={`Retirer ${item.intitule}`}
                                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                    >
                                                        <Icon name="trash" className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Bloc>

                        <Bloc
                            titre="Mes pièces justificatives"
                            sous="CV, contrat signé, copies de diplômes… Elles rejoignent votre dossier après validation."
                            action={
                                <button
                                    type="button"
                                    onClick={() => setOuvertDocument((ouvert) => !ouvert)}
                                    className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
                                >
                                    <Icon name="plus" className="h-4 w-4" />
                                    Déposer
                                </button>
                            }
                        >
                            {ouvertDocument && (
                                <form
                                    onSubmit={envoyerDocument}
                                    className="mb-4 grid gap-3 rounded-xl bg-ink-50 p-4 sm:grid-cols-2 dark:bg-white/5"
                                >
                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Nature</label>
                                        <Select
                                            value={document.data.type}
                                            onChange={(event) => document.setData('type', event.target.value)}
                                        >
                                            {Object.entries(referentiels.typesDocument).map(([cle, libelle]) => (
                                                <option key={cle} value={cle}>
                                                    {libelle}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>

                                    <div>
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Intitulé (facultatif)</label>
                                        <Input
                                            value={document.data.libelle}
                                            onChange={(event) => document.setData('libelle', event.target.value)}
                                        />
                                    </div>

                                    <div className="sm:col-span-2">
                                        <label className="text-xs text-ink-600 dark:text-ink-300">Fichier</label>
                                        <input
                                            type="file"
                                            onChange={(event) => document.setData('fichier', event.target.files?.[0] ?? null)}
                                            required
                                            className="mt-1 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-ink-100 file:px-3 file:py-2 file:text-sm file:text-ink-700 dark:text-ink-300 dark:file:bg-white/10 dark:file:text-ink-200"
                                        />
                                        <p className="mt-1 text-xs text-ink-400">PDF, image, Word ou Excel — 10 Mo maximum.</p>
                                        {document.errors.fichier && (
                                            <p className="mt-1 text-xs text-red-600">{document.errors.fichier}</p>
                                        )}
                                    </div>

                                    <div className="sm:col-span-2">
                                        <label className="text-xs text-ink-600 dark:text-ink-300">
                                            Note pour le service du personnel
                                        </label>
                                        <Textarea
                                            rows={2}
                                            value={document.data.note}
                                            onChange={(event) => document.setData('note', event.target.value)}
                                        />
                                    </div>

                                    <div className="flex gap-2 sm:col-span-2">
                                        <button
                                            type="submit"
                                            disabled={document.processing}
                                            className="rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700 disabled:opacity-60"
                                        >
                                            Déposer
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setOuvertDocument(false)}
                                            className="rounded-xl border border-ink-200 px-3.5 py-2 text-sm text-ink-700 dark:border-white/10 dark:text-ink-200"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            )}

                            {documents.length === 0 ? (
                                <Vide message="Aucune pièce déposée." />
                            ) : (
                                <div className="space-y-2">
                                    {documents.map((piece) => (
                                        <div
                                            key={piece.id}
                                            className="flex flex-wrap items-center gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                        >
                                            <span className="shrink-0 rounded-lg bg-ink-100 px-2 py-1 text-[10px] font-bold uppercase text-ink-600 dark:bg-white/10 dark:text-ink-300">
                                                {piece.extension || '?'}
                                            </span>

                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-medium text-ink-900 dark:text-white">{piece.libelle}</p>
                                                <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                                    {piece.typeLibelle}
                                                    {piece.poids && ` · ${piece.poids}`}
                                                    {piece.deposeLe && ` · déposée le ${piece.deposeLe}`}
                                                </p>
                                                {piece.motifRefus && (
                                                    <p className="mt-1 text-xs text-red-600">Motif : {piece.motifRefus}</p>
                                                )}
                                                {piece.manquant && (
                                                    <p className="mt-1 text-xs text-red-600">Fichier introuvable sur le serveur.</p>
                                                )}
                                            </div>

                                            <div className="flex shrink-0 items-center gap-2">
                                                <Etat piece={piece} />

                                                {!piece.manquant && (
                                                    <a
                                                        href={routes.profil.document(piece.id)}
                                                        aria-label={`Télécharger ${piece.libelle}`}
                                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-800 dark:hover:bg-white/10"
                                                    >
                                                        <Icon name="download" className="h-4 w-4" />
                                                    </a>
                                                )}

                                                {piece.statut === 'en_attente' && piece.soumisParLAgent && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            retirer(
                                                                routes.profil.documentRetrait(piece.id),
                                                                `Retirer « ${piece.libelle} » ?`,
                                                            )
                                                        }
                                                        aria-label={`Retirer ${piece.libelle}`}
                                                        className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                                                    >
                                                        <Icon name="trash" className="h-4 w-4" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </Bloc>
                    </div>
                )}
            </div>
        </PortalLayout>
    );
}
