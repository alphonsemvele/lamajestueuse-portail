import { router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Avatar from '@/components/avatar';
import Icon from '@/components/icon';
import { Card, Input, Select, Textarea } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { routes } from '@/lib/utils';

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
        prenom: string;
        nomFamille: string | null;
        matricule: string | null;
        email: string | null;
        telephone: string | null;
        poste: string | null;
        sexe: string | null;
        photoUrl: string | null;
        initiales: string | null;
        inscritLe: string | null;
        employeur: { sigle: string; nom: string } | null;
    };
    dossier: Record<string, string | number | boolean | null>;
    instituts: { id: number; nom: string; logoUrl: string | null; couleur: string | null; poste: string | null }[];
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
}

const fcfa = (montant: number) => `${new Intl.NumberFormat('fr-FR').format(Math.round(montant))} F`;

/** La pastille d'état d'une pièce : en attente, validée ou refusée. */
function Etat({ piece }: { piece: Piece }) {
    const tons: Record<string, string> = {
        en_attente: 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
        valide: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
        refuse: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
    };

    return (
        <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${tons[piece.statut] ?? ''}`}>
            {piece.statutLibelle}
        </span>
    );
}

/** Un bloc de la page, avec son titre et sa raison d'être. */
function Section({
    titre,
    sous,
    action,
    children,
}: {
    titre: string;
    sous?: string;
    action?: React.ReactNode;
    children: React.ReactNode;
}) {
    return (
        <Card className="p-5">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-semibold text-ink-900 dark:text-white">{titre}</h2>
                    {sous && <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{sous}</p>}
                </div>
                {action}
            </div>
            {children}
        </Card>
    );
}

function Donnee({ libelle, valeur }: { libelle: string; valeur: React.ReactNode }) {
    return (
        <div>
            <dt className="text-[11px] uppercase tracking-wide text-ink-400">{libelle}</dt>
            <dd className="mt-0.5 text-sm text-ink-900 dark:text-white">{valeur || <span className="text-ink-400">—</span>}</dd>
        </div>
    );
}

export default function MonProfil({
    identite,
    dossier,
    instituts,
    affectations,
    remuneration,
    parcours,
    diplomes,
    documents,
    referentiels,
}: Props) {
    const [ouvertDiplome, setOuvertDiplome] = useState(false);
    const [ouvertDocument, setOuvertDocument] = useState(false);

    const diplome = useForm({
        intitule: '',
        niveau: '',
        specialite: '',
        etablissement: '',
        annee_obtention: '',
    });

    const document = useForm({
        type: 'cv',
        libelle: '',
        note: '',
        fichier: null as File | null,
    });

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

    return (
        <PortalLayout title="Mon profil">
            <div className="space-y-5">
                {/* ------------------------------------------------ identité */}
                <Card className="p-6">
                    <div className="flex flex-wrap items-start gap-5">
                        <Avatar url={identite.photoUrl} initials={identite.initiales ?? '?'} className="h-20 w-20 text-xl" />

                        <div className="min-w-0 flex-1">
                            <h1 className="text-xl font-semibold text-ink-900 dark:text-white">{identite.nom}</h1>
                            <p className="mt-0.5 text-sm text-ink-500 dark:text-ink-400">
                                {identite.matricule ?? 'matricule à venir'}
                                {identite.poste && ` · ${identite.poste}`}
                                {identite.employeur && ` · ${identite.employeur.sigle}`}
                            </p>

                            <div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-ink-600 dark:text-ink-300">
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
                        </div>
                    </div>

                    {enAttente > 0 && (
                        <p className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                            <Icon name="alert" className="h-3.5 w-3.5" />
                            {enAttente} pièce(s) en attente de validation par le service du personnel.
                        </p>
                    )}
                </Card>

                {/* ------------------------------------------------ instituts */}
                <Section titre="Mes instituts" sous="Les entités du groupe auxquelles vous êtes rattaché.">
                    {instituts.length === 0 ? (
                        <p className="text-sm text-ink-400">Aucun institut rattaché pour l’instant.</p>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
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
                </Section>

                {/* --------------------------------------------- rémunération */}
                {remuneration && (
                    <Section
                        titre="Mon profil de salaire"
                        sous="Tel que votre contrat en cours le porte. Les montants versés figurent sur vos bulletins."
                    >
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Donnee libelle="Profil" valeur={remuneration.profil} />
                            <Donnee libelle="Catégorie" valeur={remuneration.categorie} />
                            <Donnee libelle="Échelon" valeur={remuneration.echelon} />
                            <Donnee
                                libelle="Salaire de base"
                                valeur={
                                    <>
                                        {fcfa(remuneration.salaireBase)}
                                        {remuneration.quotite !== 100 && (
                                            <span className="text-ink-400"> · quotité {remuneration.quotite} %</span>
                                        )}
                                    </>
                                }
                            />
                        </dl>

                        {(remuneration.indemnites.length > 0 || remuneration.retenues.length > 0) && (
                            <div className="mt-4 flex flex-wrap gap-1.5">
                                {remuneration.indemnites.map((ligne) => (
                                    <span
                                        key={`i${ligne.libelle}`}
                                        className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300"
                                    >
                                        + {ligne.libelle} ({ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)})
                                    </span>
                                ))}
                                {remuneration.retenues.map((ligne) => (
                                    <span
                                        key={`r${ligne.libelle}`}
                                        className="rounded-full bg-red-50 px-2 py-0.5 text-[11px] text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                    >
                                        − {ligne.libelle} ({ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)})
                                    </span>
                                ))}
                            </div>
                        )}
                    </Section>
                )}

                {/* --------------------------------------------- affectations */}
                <Section titre="Mes affectations" sous="Vos contrats, en cours et passés.">
                    {affectations.length === 0 ? (
                        <p className="text-sm text-ink-400">Aucun contrat enregistré.</p>
                    ) : (
                        <div className="space-y-2">
                            {affectations.map((contrat) => (
                                <div
                                    key={contrat.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-ink-900 dark:text-white">
                                            {contrat.poste || <span className="italic text-ink-400">Poste à préciser</span>}
                                        </p>
                                        <p className="text-xs text-ink-500 dark:text-ink-400">
                                            {contrat.employeur} · {contrat.typeLibelle}
                                            {contrat.quotite !== 100 && ` · ${contrat.quotite} %`}
                                        </p>
                                    </div>
                                    <p className="text-xs text-ink-500 dark:text-ink-400">
                                        {contrat.dateDebut ?? '—'}
                                        {contrat.dateFin ? ` → ${contrat.dateFin}` : ''}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </Section>

                {/* -------------------------------------------------- parcours */}
                <Section titre="Mon parcours" sous="Les mouvements inscrits à votre dossier.">
                    {parcours.length === 0 ? (
                        <p className="text-sm text-ink-400">Aucun mouvement enregistré.</p>
                    ) : (
                        <ol className="space-y-3 border-l border-ink-200 pl-4 dark:border-white/10">
                            {parcours.map((evenement) => (
                                <li key={evenement.id} className="relative">
                                    <span className="absolute -left-[21px] top-1.5 h-2 w-2 rounded-full bg-teal-600" />
                                    <p className="text-sm font-medium text-ink-900 dark:text-white">{evenement.libelle}</p>
                                    <p className="text-xs text-ink-500 dark:text-ink-400">
                                        {evenement.typeLibelle}
                                        {evenement.dateEvenement && ` · ${evenement.dateEvenement}`}
                                    </p>
                                    {evenement.details && <p className="mt-0.5 text-xs text-ink-500">{evenement.details}</p>}
                                </li>
                            ))}
                        </ol>
                    )}
                </Section>

                {/* -------------------------------------------------- diplômes */}
                <Section
                    titre="Mes diplômes"
                    sous="Déclarez-les ici : le service du personnel les validera."
                    action={
                        <button
                            type="button"
                            onClick={() => setOuvertDiplome((ouvert) => !ouvert)}
                            className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
                        >
                            <Icon name="plus" className="h-4 w-4" />
                            Déclarer un diplôme
                        </button>
                    }
                >
                    {ouvertDiplome && (
                        <form onSubmit={envoyerDiplome} className="mb-4 grid gap-3 rounded-xl bg-ink-50 p-4 sm:grid-cols-2 dark:bg-white/5">
                            <div className="sm:col-span-2">
                                <label className="text-xs text-ink-600 dark:text-ink-300">Intitulé</label>
                                <Input
                                    value={diplome.data.intitule}
                                    onChange={(event) => diplome.setData('intitule', event.target.value)}
                                    placeholder="Licence en sciences de gestion"
                                    required
                                />
                                {diplome.errors.intitule && <p className="mt-1 text-xs text-red-600">{diplome.errors.intitule}</p>}
                            </div>

                            <div>
                                <label className="text-xs text-ink-600 dark:text-ink-300">Niveau</label>
                                <Select value={diplome.data.niveau} onChange={(event) => diplome.setData('niveau', event.target.value)}>
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
                        <p className="text-sm text-ink-400">Aucun diplôme déclaré.</p>
                    ) : (
                        <div className="space-y-2">
                            {diplomes.map((item) => (
                                <div
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-ink-900 dark:text-white">{item.intitule}</p>
                                        <p className="text-xs text-ink-500 dark:text-ink-400">
                                            {[item.niveau, item.specialite, item.etablissement, item.anneeObtention]
                                                .filter(Boolean)
                                                .join(' · ') || 'Aucune précision'}
                                        </p>
                                        {item.motifRefus && <p className="mt-1 text-xs text-red-600">Motif : {item.motifRefus}</p>}
                                    </div>

                                    <div className="flex items-center gap-2">
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
                </Section>

                {/* ------------------------------------------------- documents */}
                <Section
                    titre="Mes pièces"
                    sous="CV, contrat signé, copies de diplômes… Elles rejoignent votre dossier après validation."
                    action={
                        <button
                            type="button"
                            onClick={() => setOuvertDocument((ouvert) => !ouvert)}
                            className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-teal-700"
                        >
                            <Icon name="plus" className="h-4 w-4" />
                            Déposer une pièce
                        </button>
                    }
                >
                    {ouvertDocument && (
                        <form onSubmit={envoyerDocument} className="mb-4 grid gap-3 rounded-xl bg-ink-50 p-4 sm:grid-cols-2 dark:bg-white/5">
                            <div>
                                <label className="text-xs text-ink-600 dark:text-ink-300">Nature</label>
                                <Select value={document.data.type} onChange={(event) => document.setData('type', event.target.value)}>
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
                                {document.errors.fichier && <p className="mt-1 text-xs text-red-600">{document.errors.fichier}</p>}
                            </div>

                            <div className="sm:col-span-2">
                                <label className="text-xs text-ink-600 dark:text-ink-300">Note pour le service du personnel</label>
                                <Textarea rows={2} value={document.data.note} onChange={(event) => document.setData('note', event.target.value)} />
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
                        <p className="text-sm text-ink-400">Aucune pièce déposée.</p>
                    ) : (
                        <div className="space-y-2">
                            {documents.map((piece) => (
                                <div
                                    key={piece.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 p-3 dark:border-white/10"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-ink-900 dark:text-white">{piece.libelle}</p>
                                        <p className="text-xs text-ink-500 dark:text-ink-400">
                                            {piece.typeLibelle}
                                            {piece.poids && ` · ${piece.poids}`}
                                            {piece.deposeLe && ` · déposée le ${piece.deposeLe}`}
                                        </p>
                                        {piece.motifRefus && <p className="mt-1 text-xs text-red-600">Motif : {piece.motifRefus}</p>}
                                        {piece.manquant && <p className="mt-1 text-xs text-red-600">Fichier introuvable sur le serveur.</p>}
                                    </div>

                                    <div className="flex items-center gap-2">
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
                                                    retirer(routes.profil.documentRetrait(piece.id), `Retirer « ${piece.libelle} » ?`)
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
                </Section>

                {/* ---------------------------------------- dossier administratif */}
                {dossier.ouvert ? (
                    <Section
                        titre="Mon dossier administratif"
                        sous="Rempli par le service du personnel. Signalez-lui toute erreur."
                    >
                        <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Donnee libelle="Date de naissance" valeur={dossier.dateNaissance as string} />
                            <Donnee libelle="Lieu de naissance" valeur={dossier.lieuNaissance as string} />
                            <Donnee libelle="Situation familiale" valeur={dossier.situationFamiliale as string} />
                            <Donnee libelle="Enfants" valeur={String(dossier.enfants ?? 0)} />
                            <Donnee libelle="CNI" valeur={dossier.cni as string} />
                            <Donnee libelle="N° CNPS" valeur={dossier.numeroCnps as string} />
                            <Donnee libelle="Adresse" valeur={dossier.adresse as string} />
                            <Donnee
                                libelle="Ancienneté"
                                valeur={dossier.anciennete ? `${dossier.anciennete} an(s)` : null}
                            />
                            <Donnee libelle="Personne à prévenir" valeur={dossier.urgenceNom as string} />
                            <Donnee libelle="Son téléphone" valeur={dossier.urgenceTelephone as string} />
                        </dl>
                    </Section>
                ) : (
                    <Section titre="Mon dossier administratif">
                        <p className="text-sm text-ink-400">
                            Votre dossier n’a pas encore été ouvert par le service du personnel. Vous pouvez déjà
                            déclarer vos diplômes et déposer vos pièces : elles l’attendront.
                        </p>
                    </Section>
                )}
            </div>
        </PortalLayout>
    );
}
