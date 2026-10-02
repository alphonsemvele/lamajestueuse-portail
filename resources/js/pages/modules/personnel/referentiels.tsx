import { router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import MediaField from '@/components/media-field';
import { Alert, Card, Input, Select, Textarea } from '@/components/ui';
import PersonnelLayout from '@/layouts/personnel-layout';
import { routes } from '@/lib/utils';
import type { SharedProps } from '@/types';
import { Bouton, Champ, Entete, fcfa, Modale, Tableau, Vide } from './parts';

interface Employeur {
    id: number;
    nom: string;
    sigle: string;
    applicationId: number | null;
    niu: string | null;
    numeroCnps: string | null;
    banque: string | null;
    compteBancaire: string | null;
    signataire: string | null;
    logo: string | null;
    logoUrl: string | null;
    actif: boolean;
    contratsActifs: number;
}

interface Echelon {
    id: number;
    categorieId: number;
    numero: number;
    libelle: string | null;
    salaire: number;
    ancienneteMin: number;
    actif: boolean;
}

interface Categorie {
    id: number;
    libelle: string;
    description: string | null;
    actif: boolean;
    echelons: Echelon[];
}

interface Element {
    id: number;
    libelle: string;
    description: string | null;
    imposable?: boolean;
    actif: boolean;
}

interface LigneProfil {
    id: number;
    libelle: string;
    typeCalcul: 'fixe' | 'pourcentage';
    valeur: number;
    /** « indemnite:12 », « retenue:5 », ou null pour le salaire de base. */
    baseCalcul: string | null;
}

interface Profil {
    id: number;
    nom: string;
    description: string | null;
    categorieId: number | null;
    echelonId: number | null;
    echelon: string | null;
    salaireBase: number;
    actif: boolean;
    indemnites: LigneProfil[];
    retenues: LigneProfil[];
    contratsActifs: number;
    totalIndemnites: number;
    totalRetenues: number;
    salaireNet: number;
}

interface Props {
    section: Section;
    /** Les entités du groupe ne se créent que depuis l'administration. */
    estAdministrateur: boolean;
    employeurs: Employeur[];
    applications: { id: number; name: string }[];
    categories: Categorie[];
    indemnites: Element[];
    retenues: Element[];
    profils: Profil[];
}

type Section = 'employeurs' | 'categories' | 'profils' | 'indemnites' | 'retenues';

/** Titre et sous-titre de chaque référentiel, repris dans l'en-tête. */
const ENTETES: Record<Section, { titre: string; sous: string }> = {
    employeurs: {
        titre: 'Employeurs',
        sous: "Un par institut : chacun déclare à la CNPS et signe ses propres bulletins.",
    },
    categories: {
        titre: 'Catégories & échelons',
        sous: "Une catégorie par corps de métier, un échelon par niveau de rémunération.",
    },
    profils: {
        titre: 'Profils salaires',
        sous: "Un profil assemble un échelon, des indemnités et des retenues : on l'attache ensuite à un contrat.",
    },
    indemnites: { titre: 'Indemnités', sous: "Ce qui s'ajoute au salaire de base." },
    retenues: { titre: 'Retenues', sous: 'Ce qui se déduit du salaire brut.' },
};

export default function Referentiels({
    section,
    estAdministrateur,
    employeurs,
    applications,
    categories,
    indemnites,
    retenues,
    profils,
}: Props) {
    const { errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;

    const messages = Object.entries(errors ?? {})
        .filter(([cle]) => ['employeur', 'categorie', 'echelon', 'profil'].includes(cle))
        .map(([, message]) => message);

    return (
        <PersonnelLayout
            title={ENTETES[section].titre}
            peutGerer
            entete={<Entete titre={ENTETES[section].titre} sous={ENTETES[section].sous} />}
        >
            {messages.map((message) => (
                <Alert key={message} tone="danger">
                    {message}
                </Alert>
            ))}

            {section === 'employeurs' && (
                <Employeurs employeurs={employeurs} applications={applications} estAdministrateur={estAdministrateur} />
            )}
            {section === 'categories' && <Grille categories={categories} />}
            {section === 'indemnites' && <Elements nature="indemnite" elements={indemnites} />}
            {section === 'retenues' && <Elements nature="retenue" elements={retenues} />}
            {section === 'profils' && (
                <Profils profils={profils} categories={categories} indemnites={indemnites} retenues={retenues} />
            )}
        </PersonnelLayout>
    );
}

/** Boutons d'édition et de suppression d'une ligne de référentiel. */
function Actions({ onModifier, onSupprimer }: { onModifier: () => void; onSupprimer: () => void }) {
    return (
        <div className="flex justify-end gap-1">
            <button
                type="button"
                onClick={onModifier}
                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-ink-100 hover:text-ink-700 dark:hover:bg-white/10"
                aria-label="Modifier"
            >
                <Icon name="pencil" className="h-4 w-4" />
            </button>
            <button
                type="button"
                onClick={onSupprimer}
                className="rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                aria-label="Supprimer"
            >
                <Icon name="trash" className="h-4 w-4" />
            </button>
        </div>
    );
}

// --------------------------------------------------------------- employeurs

function Employeurs({
    employeurs,
    applications,
    estAdministrateur,
}: {
    employeurs: Employeur[];
    applications: { id: number; name: string }[];
    estAdministrateur: boolean;
}) {
    const [ouvert, setOuvert] = useState(false);
    const [edite, setEdite] = useState<Employeur | null>(null);

    const vide = {
        nom: '',
        sigle: '',
        application_id: '',
        niu: '',
        numero_cnps: '',
        banque: '',
        compte_bancaire: '',
        signataire: '',
        logo: '',
        logo_file: null as File | null,
        remove_logo: false,
        actif: true as boolean,
    };

    const formulaire = useForm(vide);
    const [apercuLogo, setApercuLogo] = useState<string | null>(null);

    const ouvrir = (employeur: Employeur | null) => {
        setEdite(employeur);
        formulaire.clearErrors();
        formulaire.setData(
            employeur
                ? {
                      nom: employeur.nom,
                      sigle: employeur.sigle,
                      application_id: employeur.applicationId ? String(employeur.applicationId) : '',
                      niu: employeur.niu ?? '',
                      numero_cnps: employeur.numeroCnps ?? '',
                      banque: employeur.banque ?? '',
                      compte_bancaire: employeur.compteBancaire ?? '',
                      signataire: employeur.signataire ?? '',
                      logo: employeur.logo && employeur.logo.startsWith('http') ? employeur.logo : '',
                      logo_file: null,
                      remove_logo: false,
                      actif: employeur.actif,
                  }
                : vide,
        );
        setApercuLogo(employeur?.logoUrl ?? null);
        setOuvert(true);
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        // Un logo peut accompagner l'envoi : Inertia doit passer en FormData,
        // et une modification transite alors par POST avec _method.
        const apres = { forceFormData: true, onSuccess: () => setOuvert(false) } as const;

        edite
            ? router.post(routes.personnel.employeur(edite.id), { ...formulaire.data, _method: 'put' }, apres)
            : formulaire.post(routes.personnel.employeurs, apres);
    };

    return (
        <Card className="p-5">
            {estAdministrateur ? (
                <div className="flex justify-end">
                    <Bouton icon="plus" onClick={() => ouvrir(null)}>
                        Nouvel employeur
                    </Bouton>
                </div>
            ) : (
                <p className="rounded-xl bg-ink-50 px-4 py-2.5 text-xs text-ink-600 dark:bg-white/5 dark:text-ink-300">
                    Voici les entités dont vous suivez le personnel. Leur création et leur configuration relèvent de
                    l'administration du portail.
                </p>
            )}

            <div className="mt-5">
                {employeurs.length === 0 ? (
                    <Vide message="Aucun employeur enregistré." icon="building" />
                ) : (
                    <Tableau entetes={['Logo', 'Sigle', 'Raison sociale', 'NIU', 'CNPS', 'Effectif', 'État', '']}>
                        {employeurs.map((employeur) => (
                            <tr key={employeur.id}>
                                <td className="px-3 py-2.5">
                                    {employeur.logoUrl ? (
                                        <img
                                            src={employeur.logoUrl}
                                            alt={`Logo ${employeur.sigle}`}
                                            className="h-8 w-8 rounded-lg object-contain"
                                        />
                                    ) : (
                                        <span
                                            className="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-100 text-ink-400 dark:bg-white/5"
                                            title="Sans logo : le bulletin prendra celui de l'institut, puis celui du module"
                                        >
                                            <Icon name="building" className="h-4 w-4" />
                                        </span>
                                    )}
                                </td>
                                <td className="px-3 py-2.5 font-semibold text-ink-900 dark:text-white">{employeur.sigle}</td>
                                <td className="px-3 py-2.5 text-ink-600 dark:text-ink-300">{employeur.nom}</td>
                                <td className="px-3 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">{employeur.niu ?? '—'}</td>
                                <td className="px-3 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">{employeur.numeroCnps ?? '—'}</td>
                                <td className="px-3 py-2.5 tabular-nums text-ink-600 dark:text-ink-300">{employeur.contratsActifs}</td>
                                <td className="px-3 py-2.5">
                                    {employeur.actif ? (
                                        <span className="text-xs font-medium text-emerald-700 dark:text-emerald-300">actif</span>
                                    ) : (
                                        <span className="text-xs text-ink-400">inactif</span>
                                    )}
                                </td>
                                <td className="px-3 py-2.5">
                                    {estAdministrateur && (
                                        <Actions
                                            onModifier={() => ouvrir(employeur)}
                                            onSupprimer={() => {
                                                if (confirm(`Supprimer ${employeur.sigle} ?`)) {
                                                    formulaire.delete(routes.personnel.employeur(employeur.id), { preserveScroll: true });
                                                }
                                            }}
                                        />
                                    )}
                                </td>
                            </tr>
                        ))}
                    </Tableau>
                )}
            </div>

            <Modale titre={edite ? 'Modifier l’employeur' : 'Nouvel employeur'} ouverte={ouvert} onFermer={() => setOuvert(false)} large>
                <form onSubmit={enregistrer} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Raison sociale" erreur={formulaire.errors.nom} className="sm:col-span-2">
                            <Input value={formulaire.data.nom} onChange={(event) => formulaire.setData('nom', event.target.value)} required />
                        </Champ>
                        <Champ libelle="Sigle" erreur={formulaire.errors.sigle}>
                            <Input value={formulaire.data.sigle} onChange={(event) => formulaire.setData('sigle', event.target.value)} required />
                        </Champ>
                        <Champ libelle="Institut correspondant" erreur={formulaire.errors.application_id}>
                            <Select
                                value={formulaire.data.application_id}
                                onChange={(event) => formulaire.setData('application_id', event.target.value)}
                            >
                                <option value="">Aucun</option>
                                {applications.map((application) => (
                                    <option key={application.id} value={application.id}>
                                        {application.name}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ libelle="NIU" erreur={formulaire.errors.niu}>
                            <Input value={formulaire.data.niu} onChange={(event) => formulaire.setData('niu', event.target.value)} />
                        </Champ>
                        <Champ libelle="N° employeur CNPS" erreur={formulaire.errors.numero_cnps}>
                            <Input value={formulaire.data.numero_cnps} onChange={(event) => formulaire.setData('numero_cnps', event.target.value)} />
                        </Champ>
                        <Champ libelle="Banque" erreur={formulaire.errors.banque}>
                            <Input value={formulaire.data.banque} onChange={(event) => formulaire.setData('banque', event.target.value)} />
                        </Champ>
                        <Champ libelle="Compte bancaire" erreur={formulaire.errors.compte_bancaire}>
                            <Input
                                value={formulaire.data.compte_bancaire}
                                onChange={(event) => formulaire.setData('compte_bancaire', event.target.value)}
                            />
                        </Champ>
                        <div className="sm:col-span-2">
                            <MediaField
                                label="Logo de l'employeur"
                                hint="JPG, PNG ou WebP — 1 Mo maximum. C'est ce logo qui s'imprime sur les bulletins de paie ; sans lui, celui de l'institut rattaché, puis celui du module."
                                emptyLabel="Aucun logo"
                                preview={apercuLogo}
                                externalValue={formulaire.data.logo}
                                externalPlaceholder="ou une URL externe"
                                onExternalChange={(valeur) => {
                                    formulaire.setData('logo', valeur);
                                    setApercuLogo(valeur || null);
                                }}
                                onPick={(fichier) => {
                                    formulaire.setData((actuel) => ({
                                        ...actuel,
                                        logo_file: fichier,
                                        remove_logo: false,
                                    }));
                                    setApercuLogo(URL.createObjectURL(fichier));
                                }}
                                onDrop={() => {
                                    formulaire.setData((actuel) => ({
                                        ...actuel,
                                        logo_file: null,
                                        remove_logo: true,
                                        logo: '',
                                    }));
                                    setApercuLogo(null);
                                }}
                            />
                            {formulaire.errors.logo_file && (
                                <p className="mt-1.5 text-xs text-red-600 dark:text-red-400">{formulaire.errors.logo_file}</p>
                            )}
                        </div>

                        <Champ libelle="Signataire des bulletins" erreur={formulaire.errors.signataire} className="sm:col-span-2">
                            <Input value={formulaire.data.signataire} onChange={(event) => formulaire.setData('signataire', event.target.value)} />
                        </Champ>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={formulaire.data.actif}
                            onChange={(event) => formulaire.setData('actif', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        Employeur actif
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

// ------------------------------------------------------------------- grille

function Grille({ categories }: { categories: Categorie[] }) {
    const [categorieOuverte, setCategorieOuverte] = useState(false);
    const [categorieEditee, setCategorieEditee] = useState<Categorie | null>(null);
    const [echelonOuvert, setEchelonOuvert] = useState(false);
    const [echelonEdite, setEchelonEdite] = useState<{ echelon: Echelon | null; categorie: Categorie } | null>(null);

    const categorie = useForm({ libelle: '', description: '', actif: true as boolean });
    const echelon = useForm({ numero: 1, libelle: '', salaire: '', anciennete_min: 0, actif: true as boolean });

    const ouvrirCategorie = (valeur: Categorie | null) => {
        setCategorieEditee(valeur);
        categorie.clearErrors();
        categorie.setData(
            valeur
                ? { libelle: valeur.libelle, description: valeur.description ?? '', actif: valeur.actif }
                : { libelle: '', description: '', actif: true },
        );
        setCategorieOuverte(true);
    };

    const ouvrirEchelon = (valeur: Echelon | null, parente: Categorie) => {
        setEchelonEdite({ echelon: valeur, categorie: parente });
        echelon.clearErrors();
        echelon.setData(
            valeur
                ? {
                      numero: valeur.numero,
                      libelle: valeur.libelle ?? '',
                      salaire: String(valeur.salaire),
                      anciennete_min: valeur.ancienneteMin,
                      actif: valeur.actif,
                  }
                : {
                      numero: parente.echelons.length + 1,
                      libelle: '',
                      salaire: '',
                      anciennete_min: 0,
                      actif: true,
                  },
        );
        setEchelonOuvert(true);
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <Bouton icon="plus" onClick={() => ouvrirCategorie(null)}>
                    Nouvelle catégorie
                </Bouton>
            </div>

            {categories.length === 0 && (
                <Card className="p-5">
                    <Vide message="Aucune catégorie : commencez par en créer une." icon="layers" />
                </Card>
            )}

            {categories.map((item) => (
                <Card key={item.id} className="p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h3 className="text-sm font-semibold text-ink-900 dark:text-white">{item.libelle}</h3>
                                {!item.actif && <span className="text-xs text-ink-400">(inactive)</span>}
                            </div>
                            {item.description && <p className="mt-0.5 text-xs text-ink-500 dark:text-ink-400">{item.description}</p>}
                        </div>
                        <div className="flex items-center gap-2">
                            <Bouton variante="secondaire" icon="plus" onClick={() => ouvrirEchelon(null, item)}>
                                Échelon
                            </Bouton>
                            <Actions
                                onModifier={() => ouvrirCategorie(item)}
                                onSupprimer={() => {
                                    if (confirm(`Supprimer la catégorie « ${item.libelle} » et ses échelons ?`)) {
                                        categorie.delete(routes.personnel.categorie(item.id), { preserveScroll: true });
                                    }
                                }}
                            />
                        </div>
                    </div>

                    <div className="mt-4">
                        {item.echelons.length === 0 ? (
                            <p className="py-4 text-center text-sm text-ink-400">Aucun échelon dans cette catégorie.</p>
                        ) : (
                            <Tableau entetes={['N°', 'Libellé', 'Salaire mensuel', 'Ancienneté min.', 'État', '']}>
                                {item.echelons.map((ligne) => (
                                    <tr key={ligne.id}>
                                        <td className="px-3 py-2 font-semibold tabular-nums text-ink-900 dark:text-white">{ligne.numero}</td>
                                        <td className="px-3 py-2 text-ink-600 dark:text-ink-300">{ligne.libelle ?? '—'}</td>
                                        <td className="px-3 py-2 font-medium tabular-nums text-ink-900 dark:text-white">{fcfa(ligne.salaire)}</td>
                                        <td className="px-3 py-2 tabular-nums text-ink-600 dark:text-ink-300">{ligne.ancienneteMin} an(s)</td>
                                        <td className="px-3 py-2">
                                            {ligne.actif ? (
                                                <span className="text-xs font-medium text-emerald-700 dark:text-emerald-300">actif</span>
                                            ) : (
                                                <span className="text-xs text-ink-400">inactif</span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Actions
                                                onModifier={() => ouvrirEchelon(ligne, item)}
                                                onSupprimer={() => {
                                                    if (confirm(`Supprimer l’échelon ${ligne.numero} ?`)) {
                                                        echelon.delete(routes.personnel.echelon(ligne.id), { preserveScroll: true });
                                                    }
                                                }}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </Tableau>
                        )}
                    </div>
                </Card>
            ))}

            <Modale
                titre={categorieEditee ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
                ouverte={categorieOuverte}
                onFermer={() => setCategorieOuverte(false)}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        const apres = { onSuccess: () => setCategorieOuverte(false) };
                        categorieEditee
                            ? categorie.put(routes.personnel.categorie(categorieEditee.id), apres)
                            : categorie.post(routes.personnel.categories, apres);
                    }}
                    className="space-y-4"
                >
                    <Champ libelle="Libellé" erreur={categorie.errors.libelle}>
                        <Input value={categorie.data.libelle} onChange={(event) => categorie.setData('libelle', event.target.value)} required />
                    </Champ>
                    <Champ libelle="Description" erreur={categorie.errors.description}>
                        <Textarea rows={2} value={categorie.data.description} onChange={(event) => categorie.setData('description', event.target.value)} />
                    </Champ>
                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={categorie.data.actif}
                            onChange={(event) => categorie.setData('actif', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        Catégorie active
                    </label>
                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setCategorieOuverte(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={categorie.processing}>
                            Enregistrer
                        </Bouton>
                    </div>
                </form>
            </Modale>

            <Modale
                titre={echelonEdite?.echelon ? 'Modifier l’échelon' : 'Nouvel échelon'}
                ouverte={echelonOuvert}
                onFermer={() => setEchelonOuvert(false)}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        if (!echelonEdite) return;

                        const apres = { onSuccess: () => setEchelonOuvert(false) };
                        echelonEdite.echelon
                            ? echelon.put(routes.personnel.echelon(echelonEdite.echelon.id), apres)
                            : echelon.post(routes.personnel.echelons(echelonEdite.categorie.id), apres);
                    }}
                    className="space-y-4"
                >
                    <p className="text-sm text-ink-500 dark:text-ink-400">Catégorie : {echelonEdite?.categorie.libelle}</p>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Numéro" erreur={echelon.errors.numero}>
                            <Input
                                type="number"
                                min={1}
                                max={60}
                                value={echelon.data.numero}
                                onChange={(event) => echelon.setData('numero', Number(event.target.value))}
                                required
                            />
                        </Champ>
                        <Champ libelle="Salaire mensuel (FCFA)" erreur={echelon.errors.salaire}>
                            <Input
                                type="number"
                                min={0}
                                step="1"
                                value={echelon.data.salaire}
                                onChange={(event) => echelon.setData('salaire', event.target.value)}
                                required
                            />
                        </Champ>
                        <Champ libelle="Libellé" erreur={echelon.errors.libelle}>
                            <Input value={echelon.data.libelle} onChange={(event) => echelon.setData('libelle', event.target.value)} />
                        </Champ>
                        <Champ libelle="Ancienneté minimale (années)" erreur={echelon.errors.anciennete_min}>
                            <Input
                                type="number"
                                min={0}
                                max={50}
                                value={echelon.data.anciennete_min}
                                onChange={(event) => echelon.setData('anciennete_min', Number(event.target.value))}
                            />
                        </Champ>
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={echelon.data.actif}
                            onChange={(event) => echelon.setData('actif', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        Échelon actif
                    </label>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setEchelonOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={echelon.processing}>
                            Enregistrer
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </div>
    );
}

// ----------------------------------------------------- indemnites/retenues

function Elements({ nature, elements }: { nature: 'indemnite' | 'retenue'; elements: Element[] }) {
    const [ouvert, setOuvert] = useState(false);
    const [edite, setEdite] = useState<Element | null>(null);

    const formulaire = useForm({ libelle: '', description: '', imposable: true as boolean, actif: true as boolean });

    const collection = nature === 'indemnite' ? routes.personnel.indemnites : routes.personnel.retenues;
    const unite = nature === 'indemnite' ? routes.personnel.indemnite : routes.personnel.retenue;

    const ouvrir = (element: Element | null) => {
        setEdite(element);
        formulaire.clearErrors();
        formulaire.setData({
            libelle: element?.libelle ?? '',
            description: element?.description ?? '',
            imposable: element?.imposable ?? true,
            actif: element?.actif ?? true,
        });
        setOuvert(true);
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        const apres = { onSuccess: () => setOuvert(false) };

        edite ? formulaire.put(unite(edite.id), apres) : formulaire.post(collection, apres);
    };

    return (
        <Card className="p-5">
            <div className="flex justify-end">
                <Bouton icon="plus" onClick={() => ouvrir(null)}>
                    {nature === 'indemnite' ? 'Nouvelle indemnité' : 'Nouvelle retenue'}
                </Bouton>
            </div>

            <div className="mt-4 space-y-2">
                {elements.length === 0 && (
                    <Vide
                        message={nature === 'indemnite' ? 'Aucune indemnité définie.' : 'Aucune retenue définie.'}
                        icon="sliders"
                    />
                )}

                {elements.map((element) => (
                    <div
                        key={element.id}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-ink-200 px-4 py-2.5 dark:border-white/10"
                    >
                        <div className="min-w-0">
                            <p className="flex items-center gap-2 truncate text-sm font-medium text-ink-900 dark:text-white">
                                {element.libelle}
                                {!element.actif && <span className="text-xs font-normal text-ink-400">(inactif)</span>}
                                {element.imposable === false && (
                                    <span className="rounded-full bg-ink-100 px-2 py-0.5 text-[10px] text-ink-600 dark:bg-white/10 dark:text-ink-300">
                                        non imposable
                                    </span>
                                )}
                            </p>
                            {element.description && <p className="truncate text-xs text-ink-500 dark:text-ink-400">{element.description}</p>}
                        </div>
                        <Actions
                            onModifier={() => ouvrir(element)}
                            onSupprimer={() => {
                                if (confirm(`Supprimer « ${element.libelle} » ?`)) {
                                    formulaire.delete(unite(element.id), { preserveScroll: true });
                                }
                            }}
                        />
                    </div>
                ))}
            </div>

            <Modale
                titre={`${edite ? 'Modifier' : 'Ajouter'} ${nature === 'retenue' ? 'une retenue' : 'une indemnité'}`}
                ouverte={ouvert}
                onFermer={() => setOuvert(false)}
            >
                <form onSubmit={enregistrer} className="space-y-4">
                    <Champ libelle="Libellé" erreur={formulaire.errors.libelle}>
                        <Input value={formulaire.data.libelle} onChange={(event) => formulaire.setData('libelle', event.target.value)} required />
                    </Champ>
                    <Champ libelle="Description" erreur={formulaire.errors.description}>
                        <Textarea
                            rows={2}
                            value={formulaire.data.description}
                            onChange={(event) => formulaire.setData('description', event.target.value)}
                        />
                    </Champ>

                    {nature === 'indemnite' && (
                        <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                            <input
                                type="checkbox"
                                checked={formulaire.data.imposable}
                                onChange={(event) => formulaire.setData('imposable', event.target.checked)}
                                className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                            />
                            Indemnité imposable
                        </label>
                    )}

                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={formulaire.data.actif}
                            onChange={(event) => formulaire.setData('actif', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        Actif
                    </label>

                    <div className="flex justify-end gap-2">
                        <Bouton type="button" variante="secondaire" onClick={() => setOuvert(false)}>
                            Annuler
                        </Bouton>
                        <Bouton type="submit" icon="check" disabled={formulaire.processing}>
                            Enregistrer
                        </Bouton>
                    </div>
                </form>
            </Modale>
        </Card>
    );
}

// ------------------------------------------------------------------ profils

function Profils({
    profils,
    categories,
    indemnites,
    retenues,
}: {
    profils: Profil[];
    categories: Categorie[];
    indemnites: Element[];
    retenues: Element[];
}) {
    const [ouvert, setOuvert] = useState(false);
    const [edite, setEdite] = useState<Profil | null>(null);

    type LigneSaisie = {
        id: number;
        type_calcul: 'fixe' | 'pourcentage';
        valeur: number;
        base_calcul: string | null;
    };

    const vide = {
        nom: '',
        description: '',
        categorie_rh_id: '',
        echelon_id: '',
        actif: true as boolean,
        indemnites: [] as LigneSaisie[],
        retenues: [] as LigneSaisie[],
    };

    const formulaire = useForm(vide);

    // Ce que la RH a tape, tel quel : « 4, » reste affiche le temps qu'elle
    // finisse de saisir « 4,2 », alors que le nombre vaut deja 4.
    const [saisies, setSaisies] = useState<Record<string, string>>({});

    // Ce qu'on vient de décocher, gardé le temps de la saisie.
    const [retirees, setRetirees] = useState<Record<string, LigneSaisie>>({});

    const tousEchelons = categories.flatMap((categorie) =>
        categorie.echelons.map((echelon) => ({ ...echelon, categorie: categorie.libelle })),
    );

    const ouvrir = (profil: Profil | null) => {
        setEdite(profil);
        formulaire.clearErrors();
        formulaire.setData(
            profil
                ? {
                      nom: profil.nom,
                      description: profil.description ?? '',
                      categorie_rh_id: profil.categorieId ? String(profil.categorieId) : '',
                      echelon_id: profil.echelonId ? String(profil.echelonId) : '',
                      actif: profil.actif,
                      indemnites: (profil.indemnites ?? []).map((ligne) => ({
                          id: ligne.id,
                          type_calcul: ligne.typeCalcul,
                          valeur: ligne.valeur,
                          base_calcul: ligne.baseCalcul,
                      })),
                      retenues: (profil.retenues ?? []).map((ligne) => ({
                          id: ligne.id,
                          type_calcul: ligne.typeCalcul,
                          valeur: ligne.valeur,
                          base_calcul: ligne.baseCalcul,
                      })),
                  }
                : vide,
        );
        setSaisies({});
        setRetirees({});
        setOuvert(true);
    };

    const enregistrer = (event: FormEvent) => {
        event.preventDefault();

        const apres = { onSuccess: () => setOuvert(false) };

        edite ? formulaire.put(routes.personnel.profil(edite.id), apres) : formulaire.post(routes.personnel.profils, apres);
    };

    /**
     * Coche ou décoche un élément.
     *
     * Ce qui était saisi est mis de côté au lieu d'être jeté : décoché par
     * mégarde, l'élément retrouve sa valeur et son assiette dès qu'on le
     * recoche, sans avoir à tout ressaisir.
     */
    const basculer = (champ: 'indemnites' | 'retenues', id: number) => {
        const lignes = formulaire.data[champ];
        const presente = lignes.find((ligne) => ligne.id === id);
        const memoire = `${champ}:${id}`;

        if (presente) {
            setRetirees((actuelles) => ({ ...actuelles, [memoire]: presente }));
            formulaire.setData(champ, lignes.filter((ligne) => ligne.id !== id));

            return;
        }

        const reprise = retirees[memoire] ?? { id, type_calcul: 'fixe' as const, valeur: 0, base_calcul: null };

        formulaire.setData(champ, [...lignes, reprise]);
    };

    const majLigne = (champ: 'indemnites' | 'retenues', id: number, modif: Partial<LigneSaisie>) =>
        formulaire.setData(
            champ,
            formulaire.data[champ].map((ligne) => (ligne.id === id ? { ...ligne, ...modif } : ligne)),
        );

    const salaireReference = Number(
        tousEchelons.find((echelon) => String(echelon.id) === formulaire.data.echelon_id)?.salaire ?? 0,
    );

    /** Le libellé d'une ligne cochée, pour nommer les assiettes offertes. */
    const nomDe = (cle: string) => {
        const [sens, id] = cle.split(':');
        const source = sens === 'indemnite' ? indemnites : retenues;

        return source.find((element) => String(element.id) === id)?.libelle ?? cle;
    };

    /**
     * Le montant d'une ligne, en suivant les renvois comme le fait le moteur
     * de paie. La profondeur bornée évite de tourner en rond pendant la
     * saisie, le temps que la validation refuse la boucle à l'envoi.
     */
    const montantDe = (cle: string, profondeur = 0): number => {
        if (profondeur > 10) return 0;

        const [sens, id] = cle.split(':');
        const champ = sens === 'indemnite' ? 'indemnites' : 'retenues';
        const ligne = formulaire.data[champ].find((item) => String(item.id) === id);

        if (!ligne) return 0;
        if (ligne.type_calcul !== 'pourcentage') return ligne.valeur;

        const assiette = ligne.base_calcul ? montantDe(ligne.base_calcul, profondeur + 1) : salaireReference;

        return (assiette * ligne.valeur) / 100;
    };

    /** Les assiettes qu'une ligne peut viser : tout le reste, sauf elle-même. */
    const assiettesPour = (cle: string) =>
        [
            ...formulaire.data.indemnites.map((ligne) => `indemnite:${ligne.id}`),
            ...formulaire.data.retenues.map((ligne) => `retenue:${ligne.id}`),
        ].filter((autre) => autre !== cle);

    const selecteur = (champ: 'indemnites' | 'retenues', elements: Element[], titre: string) => (
        <div>
            <p className="mb-2 text-[13px] font-medium text-ink-700 dark:text-ink-200">{titre}</p>
            <div className="space-y-2">
                {elements.length === 0 && <p className="text-xs text-ink-400">Aucun élément défini.</p>}

                {elements.map((element) => {
                    const ligne = formulaire.data[champ].find((item) => item.id === element.id);

                    return (
                        <div key={element.id} className="rounded-xl border border-ink-200 px-3 py-2 dark:border-white/10">
                            {/*
                              * inline-flex et w-fit : sans eux le label
                              * occupe toute la largeur de la ligne, et un
                              * clic dans le vide a droite du libelle decoche
                              * l'element qu'on venait de renseigner.
                              */}
                            <label className="inline-flex w-fit cursor-pointer items-center gap-2 text-sm text-ink-800 dark:text-ink-100">
                                <input
                                    type="checkbox"
                                    checked={Boolean(ligne)}
                                    onChange={() => basculer(champ, element.id)}
                                    className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                                />
                                {element.libelle}
                            </label>

                            {ligne && (
                                <div className="mt-2 flex flex-wrap items-center gap-2 pl-6">
                                    <Select
                                        value={ligne.type_calcul}
                                        onChange={(event) =>
                                            majLigne(champ, element.id, {
                                                type_calcul: event.target.value as 'fixe' | 'pourcentage',
                                                // Un montant fixe ne s'assoit sur rien.
                                                ...(event.target.value === 'fixe' ? { base_calcul: null } : {}),
                                            })
                                        }
                                        className="w-auto py-1 text-xs"
                                    >
                                        <option value="fixe">Montant fixe</option>
                                        <option value="pourcentage">Pourcentage</option>
                                    </Select>

                                    <Input
                                        type="text"
                                        inputMode="decimal"
                                        value={saisies[`${champ}:${element.id}`] ?? String(ligne.valeur)}
                                        onChange={(event) => {
                                            const brut = event.target.value;
                                            setSaisies((actuelles) => ({ ...actuelles, [`${champ}:${element.id}`]: brut }));
                                            // La virgule vaut le point : on tape « 4,2 » comme « 4.2 ».
                                            const nombre = Number(brut.replace(',', '.'));
                                            majLigne(champ, element.id, {
                                                valeur: Number.isFinite(nombre) ? nombre : 0,
                                            });
                                        }}
                                        className="w-24 py-1 text-xs"
                                        aria-label={`Valeur pour ${element.libelle}`}
                                    />

                                    {ligne.type_calcul === 'pourcentage' && (
                                        <>
                                            <span className="text-xs text-ink-400">% de</span>
                                            <Select
                                                value={ligne.base_calcul ?? ''}
                                                onChange={(event) =>
                                                    majLigne(champ, element.id, { base_calcul: event.target.value || null })
                                                }
                                                className="w-auto py-1 text-xs"
                                                aria-label={`Assiette de ${element.libelle}`}
                                            >
                                                <option value="">Salaire de base</option>
                                                {assiettesPour(`${champ === 'indemnites' ? 'indemnite' : 'retenue'}:${element.id}`).map(
                                                    (cle) => (
                                                        <option key={cle} value={cle}>
                                                            {nomDe(cle)}
                                                        </option>
                                                    ),
                                                )}
                                            </Select>
                                        </>
                                    )}

                                    <span className="text-xs text-ink-400">
                                        soit{' '}
                                        {fcfa(
                                            montantDe(`${champ === 'indemnites' ? 'indemnite' : 'retenue'}:${element.id}`),
                                        )}
                                    </span>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );

    return (
        <Card className="p-5">
            <div className="flex justify-end">
                <Bouton icon="plus" onClick={() => ouvrir(null)}>
                    Nouveau profil
                </Bouton>
            </div>

            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                {profils.length === 0 && (
                    <div className="sm:col-span-2">
                        <Vide message="Aucun profil de salaire." icon="clipboard" />
                    </div>
                )}

                {profils.map((profil) => (
                    <div key={profil.id} className="rounded-xl border border-ink-200 p-4 dark:border-white/10">
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-ink-900 dark:text-white">
                                    {profil.nom}
                                    {!profil.actif && <span className="ml-2 text-xs font-normal text-ink-400">(inactif)</span>}
                                </p>
                                <p className="truncate text-xs text-ink-500 dark:text-ink-400">
                                    {profil.echelon ?? 'sans échelon'} · {profil.contratsActifs} contrat(s)
                                </p>
                            </div>
                            <Actions
                                onModifier={() => ouvrir(profil)}
                                onSupprimer={() => {
                                    if (confirm(`Supprimer le profil « ${profil.nom} » ?`)) {
                                        formulaire.delete(routes.personnel.profil(profil.id), { preserveScroll: true });
                                    }
                                }}
                            />
                        </div>

                        <dl className="mt-3 space-y-1 rounded-lg bg-ink-50 px-3 py-2 text-xs dark:bg-white/5">
                            <div className="flex justify-between gap-3">
                                <dt className="text-ink-500 dark:text-ink-400">Salaire de base</dt>
                                <dd className="tabular-nums text-ink-700 dark:text-ink-200">{fcfa(profil.salaireBase)}</dd>
                            </div>
                            {profil.totalIndemnites > 0 && (
                                <div className="flex justify-between gap-3">
                                    <dt className="text-ink-500 dark:text-ink-400">Indemnités</dt>
                                    <dd className="tabular-nums text-emerald-700 dark:text-emerald-300">
                                        + {fcfa(profil.totalIndemnites)}
                                    </dd>
                                </div>
                            )}
                            {profil.totalRetenues > 0 && (
                                <div className="flex justify-between gap-3">
                                    <dt className="text-ink-500 dark:text-ink-400">Retenues</dt>
                                    <dd className="tabular-nums text-red-700 dark:text-red-300">
                                        − {fcfa(profil.totalRetenues)}
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-between gap-3 border-t border-ink-200 pt-1 dark:border-white/10">
                                <dt className="font-semibold text-ink-700 dark:text-ink-200">Net perçu</dt>
                                <dd className="tabular-nums text-sm font-semibold text-ink-900 dark:text-white">
                                    {fcfa(profil.salaireNet)}
                                </dd>
                            </div>
                        </dl>

                        <p className="mt-1.5 text-[11px] text-ink-400">
                            À quotité pleine, hors ajustement du mois.
                        </p>

                        <div className="mt-3 flex flex-wrap gap-1.5">
                            {(profil.indemnites ?? []).map((ligne) => (
                                <span
                                    key={`i${ligne.id}`}
                                    className="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300"
                                >
                                    + {ligne.libelle} ({ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)})
                                </span>
                            ))}
                            {(profil.retenues ?? []).map((ligne) => (
                                <span
                                    key={`r${ligne.id}`}
                                    className="rounded-full bg-red-50 px-2 py-0.5 text-[11px] text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    − {ligne.libelle} ({ligne.typeCalcul === 'pourcentage' ? `${ligne.valeur} %` : fcfa(ligne.valeur)})
                                </span>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <Modale titre={edite ? 'Modifier le profil' : 'Nouveau profil'} ouverte={ouvert} onFermer={() => setOuvert(false)} large>
                <form onSubmit={enregistrer} className="space-y-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Champ libelle="Nom" erreur={formulaire.errors.nom}>
                            <Input value={formulaire.data.nom} onChange={(event) => formulaire.setData('nom', event.target.value)} required />
                        </Champ>
                        <Champ libelle="Catégorie" erreur={formulaire.errors.categorie_rh_id}>
                            <Select
                                value={formulaire.data.categorie_rh_id}
                                onChange={(event) => formulaire.setData('categorie_rh_id', event.target.value)}
                            >
                                <option value="">Aucune</option>
                                {categories.map((categorie) => (
                                    <option key={categorie.id} value={categorie.id}>
                                        {categorie.libelle}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ libelle="Échelon de référence" erreur={formulaire.errors.echelon_id} className="sm:col-span-2">
                            <Select value={formulaire.data.echelon_id} onChange={(event) => formulaire.setData('echelon_id', event.target.value)}>
                                <option value="">Aucun</option>
                                {tousEchelons.map((echelon) => (
                                    <option key={echelon.id} value={echelon.id}>
                                        {echelon.categorie} · échelon {echelon.numero} — {fcfa(echelon.salaire)}
                                    </option>
                                ))}
                            </Select>
                        </Champ>
                        <Champ libelle="Description" erreur={formulaire.errors.description} className="sm:col-span-2">
                            <Textarea
                                rows={2}
                                value={formulaire.data.description}
                                onChange={(event) => formulaire.setData('description', event.target.value)}
                            />
                        </Champ>
                    </div>

                    <div className="grid gap-5 border-t border-ink-100 pt-4 sm:grid-cols-2 dark:border-white/5">
                        {selecteur('indemnites', indemnites, 'Indemnités du profil')}
                        {selecteur('retenues', retenues, 'Retenues du profil')}
                    </div>

                    <label className="flex items-center gap-2 text-sm text-ink-700 dark:text-ink-200">
                        <input
                            type="checkbox"
                            checked={formulaire.data.actif}
                            onChange={(event) => formulaire.setData('actif', event.target.checked)}
                            className="h-4 w-4 rounded border-ink-300 text-teal-600 focus:ring-teal-500"
                        />
                        Profil actif
                    </label>

                    {edite && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                            Les bulletins déjà édités gardent leur décompte : seul le prochain calcul tiendra compte de ces
                            changements.
                        </p>
                    )}

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
