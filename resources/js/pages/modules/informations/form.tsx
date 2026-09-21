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
import type { Post } from '@/types';

export default function InformationForm({ post }: { post: Post | null }) {
    const t = useT();
    const editing = post !== null;
    const [imagePreview, setImagePreview] = useState<string | null>(post?.imageUrl ?? null);

    const { data, setData, post: submitPost, put, processing, errors } = useForm({
        title: post?.title ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        image: post?.image ?? '',
        image_file: null as File | null,
        remove_image: false,
        type: post?.type ?? 'news',
        published_at: post?.publishedAt ? post.publishedAt.slice(0, 16) : '',
        is_featured: post?.isFeatured ?? false,
        is_visible: post?.isVisible ?? true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        // Une image peut accompagner l'envoi : Inertia doit passer en FormData,
        // et une modification transite alors par POST avec _method.
        const options = { forceFormData: true } as const;

        if (editing) {
            router.post(routes.informations.update(post.slug), { ...data, _method: 'put' }, options);
        } else {
            submitPost(routes.informations.store, options);
        }
    };

    return (
        <PortalLayout title={editing ? t('Modifier la publication') : t('Nouvelle publication')}>
            <div className="mx-auto max-w-5xl">
                {/* Fil d'ariane : portail → module → publication */}
                <nav className="mb-6 flex items-center gap-1.5 text-sm text-ink-400">
                    <Link href={routes.dashboard} className="font-medium transition hover:text-brand-600">
                        {t('Portail')}
                    </Link>
                    <Icon name="chevron-right" className="h-3.5 w-3.5" />
                    <Link href={routes.informations.index} className="font-medium transition hover:text-brand-600">
                        {t("Centre d'information")}
                    </Link>
                </nav>

                <h1 className="text-2xl font-semibold tracking-tight text-ink-900 dark:text-white">
                    {editing ? post.title : t('Nouvelle publication')}
                </h1>
                <p className="mt-1.5 text-sm text-ink-500 dark:text-ink-400">
                    {t('Alimente le centre d’information du tableau de bord.')}
                </p>

                <form onSubmit={submit} className="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <Card className="space-y-5 p-6">
                        <div>
                            <Label htmlFor="title" required>
                                {t('Titre')}
                            </Label>
                            <Input id="title" className="mt-2" value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={180} required />
                            {errors.title && <p className="mt-1.5 text-xs text-red-600">{errors.title}</p>}
                        </div>
                        <div>
                            <Label htmlFor="excerpt">{t('Chapô')}</Label>
                            <Textarea id="excerpt" rows={2} className="mt-2" value={data.excerpt ?? ''} onChange={(e) => setData('excerpt', e.target.value)} maxLength={400} />
                            <p className="mt-1.5 text-xs text-ink-400">{t('Texte affiché sur la carte du centre d’information.')}</p>
                        </div>
                        <div>
                            <Label htmlFor="body">{t('Contenu')}</Label>
                            <Textarea id="body" rows={12} className="mt-2" value={data.body ?? ''} onChange={(e) => setData('body', e.target.value)} />
                        </div>
                    </Card>

                    <div className="space-y-4 lg:sticky lg:top-24 lg:self-start">
                        <Card className="space-y-4 p-5">
                            <div>
                                <Label htmlFor="type" required>
                                    {t('Rubrique')}
                                </Label>
                                <Select id="type" className="mt-2" value={data.type} onChange={(e) => setData('type', e.target.value as Post['type'])}>
                                    <option value="news">{t('Actualité')}</option>
                                    <option value="announcement">{t('Annonce')}</option>
                                    <option value="billboard">{t('Affichage')}</option>
                                </Select>
                            </div>
                            <MediaField
                                label={t('Image')}
                                hint={t('JPG, PNG ou WebP — 4 Mo maximum.')}
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
                                <Label htmlFor="published_at">{t('Date de publication')}</Label>
                                <Input id="published_at" type="datetime-local" className="mt-2" value={data.published_at} onChange={(e) => setData('published_at', e.target.value)} />
                                <p className="mt-1.5 text-xs text-ink-400">{t('Vide = brouillon, invisible pour les employés.')}</p>
                            </div>
                            <SwitchField
                                checked={data.is_visible}
                                onChange={(value) => setData('is_visible', value)}
                                label={t('Affichage sur le portail')}
                                description={t("Inactif, la publication n'apparaît nulle part et son lien direct renvoie une erreur. La date de publication est conservée.")}
                            />

                            <label className="flex items-center gap-2.5 text-sm text-ink-700 dark:text-ink-200">
                                <input
                                    type="checkbox"
                                    checked={data.is_featured}
                                    onChange={(e) => setData('is_featured', e.target.checked)}
                                    className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500/30 dark:border-white/20 dark:bg-white/5"
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
