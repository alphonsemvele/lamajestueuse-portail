import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import Icon from '@/components/icon';
import Partage from '@/components/partage';
import InfoLayout from '@/layouts/info-layout';
import { routes } from '@/lib/utils';
import type { Article, PieceJointe, Rubrique } from '@/types';

interface Props {
    article: Article;
    rubriques: Rubrique[];
    aLire: Article[];
    meta: { url: string };
}

/**
 * Les images de la galerie, en grand.
 *
 * Une visionneuse plutôt qu'un nouvel onglet : on reste dans l'article, et
 * les flèches du clavier passent d'une image à l'autre.
 */
function Visionneuse({
    images,
    index,
    onFermer,
    onDeplacer,
}: {
    images: PieceJointe[];
    index: number;
    onFermer: () => void;
    onDeplacer: (pas: number) => void;
}) {
    const auClavier = useCallback(
        (evenement: KeyboardEvent) => {
            if (evenement.key === 'Escape') onFermer();
            if (evenement.key === 'ArrowRight') onDeplacer(1);
            if (evenement.key === 'ArrowLeft') onDeplacer(-1);
        },
        [onFermer, onDeplacer],
    );

    useEffect(() => {
        window.addEventListener('keydown', auClavier);

        return () => window.removeEventListener('keydown', auClavier);
    }, [auClavier]);

    const image = images[index];

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={image.legende ?? image.nom}
            className="fixed inset-0 z-50 flex flex-col items-center justify-center bg-black/90 p-4"
            onClick={onFermer}
        >
            <button
                type="button"
                onClick={onFermer}
                className="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                aria-label="Fermer"
            >
                <Icon name="close" className="h-5 w-5" />
            </button>

            {images.length > 1 && (
                <>
                    <button
                        type="button"
                        onClick={(evenement) => {
                            evenement.stopPropagation();
                            onDeplacer(-1);
                        }}
                        className="absolute left-3 rounded-full bg-white/10 p-2.5 text-white transition hover:bg-white/20"
                        aria-label="Image précédente"
                    >
                        <Icon name="chevron-right" className="h-5 w-5 rotate-180" />
                    </button>
                    <button
                        type="button"
                        onClick={(evenement) => {
                            evenement.stopPropagation();
                            onDeplacer(1);
                        }}
                        className="absolute right-3 rounded-full bg-white/10 p-2.5 text-white transition hover:bg-white/20"
                        aria-label="Image suivante"
                    >
                        <Icon name="chevron-right" className="h-5 w-5" />
                    </button>
                </>
            )}

            <img
                src={image.url}
                alt={image.legende ?? ''}
                className="max-h-[80vh] max-w-full rounded-lg object-contain"
                onClick={(evenement) => evenement.stopPropagation()}
            />

            <p className="mt-3 max-w-2xl text-center text-sm text-white/70">
                {image.legende ?? image.nom}
                {images.length > 1 && <span className="ml-2 text-white/40">{index + 1} / {images.length}</span>}
            </p>
        </div>
    );
}

export default function ArticlePublic({ article, rubriques, aLire, meta }: Props) {
    const [ouverte, setOuverte] = useState<number | null>(null);
    const images = article.images ?? [];
    const documents = article.documents ?? [];

    const deplacer = (pas: number) =>
        setOuverte((actuelle) => (actuelle === null ? null : (actuelle + pas + images.length) % images.length));

    return (
        <InfoLayout titre={article.titre} rubriques={rubriques} active={article.rubrique?.slug ?? null}>
            <nav className="mb-6 flex items-center gap-1.5 text-sm text-ink-500 dark:text-ink-400">
                <Link href={routes.info.index} className="transition hover:text-brand-600">
                    Info
                </Link>
                {article.rubrique && (
                    <>
                        <Icon name="chevron-right" className="h-3.5 w-3.5" />
                        <Link
                            href={`${routes.info.index}?rubrique=${article.rubrique.slug}`}
                            className="transition hover:text-brand-600"
                        >
                            {article.rubrique.nom}
                        </Link>
                    </>
                )}
            </nav>

            <div className="grid gap-10 lg:grid-cols-[minmax(0,1fr)_17rem]">
                <article className="min-w-0">
                    <header className="mb-7">
                        {article.rubrique && (
                            <span
                                className="inline-block rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white"
                                style={{ backgroundColor: article.rubrique.couleur }}
                            >
                                {article.rubrique.nom}
                            </span>
                        )}

                        <h1 className="mt-4 text-3xl font-semibold leading-tight tracking-tight text-ink-900 dark:text-white sm:text-4xl">
                            {article.titre}
                        </h1>

                        <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-ink-500 dark:text-ink-400">
                            <span>{article.publieLeLibelle}</span>
                            {article.auteur && <span>Par {article.auteur}</span>}
                            <span>{article.lecture} min de lecture</span>
                            <span className="inline-flex items-center gap-1.5">
                                <Icon name="eye" className="h-4 w-4" />
                                {article.vues} lecture{article.vues > 1 ? 's' : ''}
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <Icon name="share" className="h-4 w-4" />
                                {article.partages} partage{article.partages > 1 ? 's' : ''}
                            </span>
                        </div>
                    </header>

                    {article.imageUrl && (
                        <figure className="mb-8">
                            <img
                                src={article.imageUrl}
                                alt=""
                                className="w-full rounded-2xl object-cover"
                            />
                            {article.legende && (
                                <figcaption className="mt-2 text-xs text-ink-500 dark:text-ink-400">{article.legende}</figcaption>
                            )}
                        </figure>
                    )}

                    {article.chapeau && (
                        <p className="mb-7 border-l-4 border-brand-500 pl-4 text-lg leading-relaxed text-ink-700 dark:text-ink-200">
                            {article.chapeau}
                        </p>
                    )}

                    <div className="space-y-5 text-[16.5px] leading-[1.75] text-ink-700 dark:text-ink-300">
                        {(article.corps ?? '')
                            .split(/\n{2,}/)
                            .map((paragraphe) => paragraphe.trim())
                            .filter((paragraphe) => paragraphe !== '')
                            .map((paragraphe, rang) => (
                                <p key={rang}>{paragraphe}</p>
                            ))}
                    </div>

                    {images.length > 0 && (
                        <section className="mt-10">
                            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">
                                En images
                            </h2>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {images.map((image, rang) => (
                                    <button
                                        key={image.id}
                                        type="button"
                                        onClick={() => setOuverte(rang)}
                                        className="group overflow-hidden rounded-xl bg-ink-100 dark:bg-ink-800"
                                    >
                                        <img
                                            src={image.url}
                                            alt={image.legende ?? ''}
                                            loading="lazy"
                                            className="aspect-square w-full object-cover transition duration-500 group-hover:scale-105"
                                        />
                                    </button>
                                ))}
                            </div>
                        </section>
                    )}

                    {documents.length > 0 && (
                        <section className="mt-10">
                            <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">
                                Documents joints
                            </h2>
                            <ul className="divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white dark:divide-white/10 dark:border-white/10 dark:bg-ink-900">
                                {documents.map((document) => (
                                    <li key={document.id}>
                                        <a
                                            href={document.url}
                                            target="_blank"
                                            rel="noopener"
                                            className="flex items-center gap-3 px-4 py-3 transition hover:bg-ink-50 dark:hover:bg-white/5"
                                        >
                                            <span className="shrink-0 rounded-lg bg-brand-50 px-2 py-1 text-[11px] font-bold text-brand-700 dark:bg-brand-500/15 dark:text-brand-300">
                                                {document.extension}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium text-ink-800 dark:text-ink-100">
                                                    {document.legende ?? document.nom}
                                                </span>
                                                <span className="text-xs text-ink-400">{document.poids}</span>
                                            </span>
                                            <Icon name="download" className="h-4 w-4 shrink-0 text-ink-400" />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <div className="mt-10 rounded-2xl border border-ink-100 bg-white p-5 dark:border-white/10 dark:bg-ink-900">
                        <Partage
                            slug={article.slug}
                            titre={article.titre}
                            url={meta.url}
                            compteurs={article.partagesDetail}
                        />
                        <p className="mt-3 break-all text-xs text-ink-400">{meta.url}</p>
                    </div>
                </article>

                {/* Le fil latéral : ce qu'on lit ensuite. */}
                <aside className="lg:sticky lg:top-28 lg:self-start">
                    <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-ink-500 dark:text-ink-400">
                        À lire ensuite
                    </h2>

                    {aLire.length === 0 ? (
                        <p className="text-sm text-ink-400">Rien d'autre pour le moment.</p>
                    ) : (
                        <ul className="space-y-3">
                            {aLire.map((suite) => (
                                <li key={suite.id}>
                                    <Link
                                        href={routes.info.article(suite.slug)}
                                        className="flex gap-3 rounded-xl border border-ink-100 bg-white p-3 transition hover:border-brand-300 dark:border-white/10 dark:bg-ink-900"
                                    >
                                        <span className="h-16 w-16 shrink-0 overflow-hidden rounded-lg bg-ink-100 dark:bg-ink-800">
                                            {suite.imageUrl ? (
                                                <img src={suite.imageUrl} alt="" loading="lazy" className="h-full w-full object-cover" />
                                            ) : (
                                                <span className="flex h-full items-center justify-center text-ink-300">
                                                    <Icon name="newspaper" className="h-5 w-5" />
                                                </span>
                                            )}
                                        </span>
                                        <span className="min-w-0">
                                            <span className="line-clamp-2 text-sm font-medium text-ink-800 dark:text-ink-100">
                                                {suite.titre}
                                            </span>
                                            <span className="mt-1 block text-xs text-ink-400">{suite.publieLeLibelle}</span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </aside>
            </div>

            {ouverte !== null && images[ouverte] && (
                <Visionneuse images={images} index={ouverte} onFermer={() => setOuverte(null)} onDeplacer={deplacer} />
            )}
        </InfoLayout>
    );
}
