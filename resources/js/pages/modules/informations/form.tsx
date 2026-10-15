import { Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Icon from '@/components/icon';
import Spinner from '@/components/spinner';
import SwitchField from '@/components/switch-field';
import MediaField from '@/components/media-field';
import Label from '@/components/label';
import { Card, Input, Select, Textarea } from '@/components/ui';
import PortalLayout from '@/layouts/portal-layout';
import { routes, useT } from '@/lib/utils';
import type { PieceJointe, Post, Rubrique } from '@/types';

interface Props {
    post: Post | null;
    rubriques: Rubrique[];
    lienPublic?: string | null;
}

/** Les pièces déjà en ligne : on les retire une à une, jamais en bloc. */
function PiecesEnPlace({ pieces, intitule }: { pieces: PieceJointe[]; intitule: string }) {
    const t = useT();

    if (pieces.length === 0) return null;

    return (
        <div>
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-ink-500 dark:text-ink-400">{intitule}</p>
            <ul className="space-y-2">
                {pieces.map((piece) => (
                    <li
                        key={piece.id}
                        className="flex items-center gap-3 rounded-xl border border-ink-100 p-2 dark:border-white/10"
                    >
                        {piece.genre === 'image' ? (
                            <img src={piece.url} alt="" className="h-11 w-11 shrink-0 rounded-lg object-cover" />
                        ) : (
                            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-[10px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                {piece.extension}
                            </span>
                        )}

                        <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm text-ink-800 dark:text-ink-100">
                                {piece.legende ?? piece.nom}
                            </span>
                            <span className="text-xs text-ink-400">{piece.poids}</span>
                        </span>

                        <button
                            type="button"
                            title={t('Retirer')}
                            onClick={() => {
                                if (confirm(t('Retirer cette pièce ?'))) {
                                    router.delete(routes.informations.mediaDestroy(piece.id), { preserveScroll: true });
                                }
                            }}
                            className="shrink-0 rounded-lg p-1.5 text-ink-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10"
                        >
                            <Icon name="trash" className="h-4 w-4" />
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/** Le choix de fichiers en attente d'envoi. */
function ChoixFichiers({
    id,
    intitule,
    aide,
    accept,
    fichiers,
    onChange,
}: {
    id: string;
    intitule: string;
    aide: string;
    accept: string;
    fichiers: File[];
    onChange: (fichiers: File[]) => void;
}) {
    const t = useT();

    return (
        <div>
            <Label htmlFor={id}>{intitule}</Label>
            <input
                id={id}
                type="file"
                multiple
                accept={accept}
                onChange={(event) => onChange(Array.from(event.target.files ?? []))}
                className="mt-2 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-brand-700 hover:file:bg-brand-100 dark:text-ink-300 dark:file:bg-brand-500/15 dark:file:text-brand-300"
            />
            <p className="mt-1.5 text-xs text-ink-400">{aide}</p>

            {fichiers.length > 0 && (
                <ul className="mt-2 space-y-1 text-xs text-ink-500 dark:text-ink-400">
                    {fichiers.map((fichier) => (
                        <li key={fichier.name} className="truncate">
                            {t('À envoyer')} · {fichier.name}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

export default function InformationForm({ post, rubriques, lienPublic }: Props) {
    const t = useT();
    const editing = post !== null;
    const [imagePreview, setImagePreview] = useState<string | null>(post?.imageUrl ?? null);

    const { data, setData, post: submitPost, processing, errors } = useForm({
        title: post?.title ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        image: post?.image ?? '',
        image_file: null as File | null,
        remove_image: false,
        cover_legende: post?.coverLegende ?? '',
        rubrique_id: post?.rubriqueId ?? rubriques[0]?.id ?? '',
        published_at: post?.publishedAt ? post.publishedAt.slice(0, 16) : '',
        is_featured: post?.isFeatured ?? false,
        is_visible: post?.isVisible ?? true,
        is_public: post?.isPublic ?? true,
        images: [] as File[],
        documents: [] as File[],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Des fichiers accompagnent l'envoi : Inertia passe en FormData, et
        // une modification transite alors par POST avec _method.
        const options = { forceFormData: true } as const;

        if (editing) {
            router.post(routes.informations.update(post.slug), { ...data, _method: 'put' }, options);
        } else {
            submitPost(routes.informations.store, options);
        }
    };

    return (
        <PortalLayout title={editing ? t("Modifier l'article") : t('Nouvel article')}>
            <div className="mx-auto max-w-5xl">
                <nav className="mb-6 flex items-center gap-1.5 text-sm text-ink-400">
                    <Link href={routes.dashboard} className="font-medium transition hover:text-brand-600">
                        {t('Portail')}
                    </Link>
                    <Icon name="chevron-right" className="h-3.5 w-3.5" />
                    <Link href={routes.informations.index} className="font-medium transition hover:text-brand-600">
                        LA MAJESTUEUSE Info
                    </Link>
                </nav>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">
                            {editing ? post.title : t('Nouvel article')}
                        </h1>
                        <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                            {t('Paraît sur le site public et dans le portail.')}
                        </p>
                    </div>

                    {lienPublic && (
                        <a href={lienPublic} target="_blank" rel="noopener" className="btn-ghost">
                            <Icon name="external" className="h-4 w-4" />
                            {t("Voir l'article en ligne")}
                        </a>
                    )}
                </div>

                <form onSubmit={submit} className="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="space-y-6">
                        <Card className="space-y-5 p-6">
                            <div>
                                <Label htmlFor="title" required>
                                    {t('Titre')}
                                </Label>
                                <Input
                                    id="title"
                                    className="mt-2"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    maxLength={180}
                                    required
                                />
                                {errors.title && <p className="mt-1.5 text-xs text-red-600">{errors.title}</p>}
                            </div>
                            <div>
                                <Label htmlFor="excerpt">{t('Chapô')}</Label>
                                <Textarea
                                    id="excerpt"
                                    rows={2}
                                    className="mt-2"
                                    value={data.excerpt ?? ''}
                                    onChange={(e) => setData('excerpt', e.target.value)}
                                    maxLength={400}
                                />
                                <p className="mt-1.5 text-xs text-ink-400">
                                    {t('Les deux lignes qui donnent envie de lire. Elles accompagnent aussi le lien partagé.')}
                                </p>
                            </div>
                            <div>
                                <Label htmlFor="body">{t('Contenu')}</Label>
                                <Textarea
                                    id="body"
                                    rows={14}
                                    className="mt-2"
                                    value={data.body ?? ''}
                                    onChange={(e) => setData('body', e.target.value)}
                                />
                                <p className="mt-1.5 text-xs text-ink-400">{t('Une ligne vide sépare deux paragraphes.')}</p>
                            </div>
                        </Card>

                        <Card className="space-y-5 p-6">
                            <div>
                                <h2 className="text-sm font-semibold text-ink-800 dark:text-ink-100">{t('Pièces jointes')}</h2>
                                <p className="mt-1 text-xs text-ink-400">
                                    {t("Les images nourrissent la galerie de l'article ; les documents se téléchargent.")}
                                </p>
                            </div>

                            <PiecesEnPlace pieces={post?.images ?? []} intitule={t('Images en ligne')} />
                            <PiecesEnPlace pieces={post?.documents ?? []} intitule={t('Documents en ligne')} />

                            <ChoixFichiers
                                id="images"
                                intitule={t('Ajouter des images')}
                                aide={t('JPG, PNG ou WebP — 6 Mo par image, 12 au plus.')}
                                accept="image/jpeg,image/png,image/webp"
                                fichiers={data.images}
                                onChange={(fichiers) => setData('images', fichiers)}
                            />

                            <ChoixFichiers
                                id="documents"
                                intitule={t('Ajouter des documents')}
                                aide={t('PDF, Word, Excel, PowerPoint — 15 Mo par document.')}
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt"
                                fichiers={data.documents}
                                onChange={(fichiers) => setData('documents', fichiers)}
                            />
                        </Card>
                    </div>

                    <div className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                        <Card className="space-y-4 p-5">
                            <div>
                                <Label htmlFor="rubrique_id" required>
                                    {t('Rubrique')}
                                </Label>
                                <Select
                                    id="rubrique_id"
                                    className="mt-2"
                                    value={data.rubrique_id}
                                    onChange={(e) => setData('rubrique_id', Number(e.target.value))}
                                >
                                    {rubriques.map((rubrique) => (
                                        <option key={rubrique.id} value={rubrique.id}>
                                            {rubrique.nom}
                                        </option>
                                    ))}
                                </Select>
                                {errors.rubrique_id && <p className="mt-1.5 text-xs text-red-600">{errors.rubrique_id}</p>}
                            </div>

                            <MediaField
                                label={t('Image de une')}
                                hint={t('JPG, PNG ou WebP — 4 Mo. C’est elle qui s’affiche quand on partage le lien : 1200 × 630 pixels pour une grande vignette.')}
                                emptyLabel={t('Aucune image')}
                                preview={imagePreview}
                                externalValue={data.image ?? ''}
                                externalPlaceholder={t('ou une URL externe')}
                                onExternalChange={(value) => setData('image', value)}
                                onPick={(file) => {
                                    setData((current) => ({ ...current, image_file: file, remove_image: false }));
                                    setImagePreview(URL.createObjectURL(file));
                                }}
                                onDrop={() => {
                                    setData((current) => ({ ...current, image_file: null, remove_image: true, image: '' }));
                                    setImagePreview(null);
                                }}
                            />

                            <div>
                                <Label htmlFor="cover_legende">{t("Légende de l'image")}</Label>
                                <Input
                                    id="cover_legende"
                                    className="mt-2"
                                    value={data.cover_legende ?? ''}
                                    onChange={(e) => setData('cover_legende', e.target.value)}
                                    maxLength={220}
                                />
                            </div>

                            <div>
                                <Label htmlFor="published_at">{t('Date de publication')}</Label>
                                <Input
                                    id="published_at"
                                    type="datetime-local"
                                    className="mt-2"
                                    value={data.published_at}
                                    onChange={(e) => setData('published_at', e.target.value)}
                                />
                                <p className="mt-1.5 text-xs text-ink-400">{t('Vide = brouillon, invisible de tous.')}</p>
                            </div>

                            <SwitchField
                                checked={data.is_visible}
                                onChange={(value) => setData('is_visible', value)}
                                label={t('En ligne')}
                                description={t("Inactif, l'article n'apparaît nulle part et son lien direct renvoie une erreur.")}
                            />

                            <SwitchField
                                checked={data.is_public}
                                onChange={(value) => setData('is_public', value)}
                                label={t('Visible par tous')}
                                description={t('Actif, il paraît sur le site public et se partage sur WhatsApp et Facebook. Inactif, il reste réservé au personnel connecté.')}
                            />

                            <label className="flex items-center gap-2.5 text-sm text-ink-700 dark:text-ink-200">
                                <input
                                    type="checkbox"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
                                    className="h-4 w-4 rounded border border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
                                />
                                {t('Mettre à la une')}
                            </label>
                        </Card>

                        <Card className="space-y-2.5 p-5">
                            <button type="submit" disabled={processing} className="btn-primary w-full">
                                {processing ? <Spinner /> : <Icon name="check" className="h-4 w-4" />}
                                {processing ? t('Enregistrement…') : editing ? t('Enregistrer') : t('Publier')}
                            </button>
                            <Link href={routes.informations.index} className="btn-ghost w-full">
                                {t('Annuler')}
                            </Link>
                        </Card>
                    </div>
                </form>
            </div>
        </PortalLayout>
    );
}
