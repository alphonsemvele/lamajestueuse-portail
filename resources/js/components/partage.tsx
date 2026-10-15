import { router } from '@inertiajs/react';
import { useState } from 'react';
import Icon from '@/components/icon';
import { cn, routes } from '@/lib/utils';

interface Props {
    slug: string;
    titre: string;
    /** L'adresse publique de l'article : c'est elle que lisent les réseaux. */
    url: string;
    compteurs?: { whatsapp: number; facebook: number; lien: number };
    className?: string;
}

/**
 * Partager un article.
 *
 * Le bouton ouvre le réseau dans un autre onglet et prévient le portail, qui
 * tient le compte. L'aperçu (image, titre, chapeau) vient des métadonnées
 * posées dans l'en-tête HTML de la page publique : WhatsApp et Facebook les
 * lisent eux-mêmes en suivant le lien.
 */
export default function Partage({ slug, titre, url, compteurs, className }: Props) {
    const [copie, setCopie] = useState(false);

    const compter = (reseau: 'whatsapp' | 'facebook' | 'lien') =>
        router.post(
            routes.info.partage(slug),
            { reseau },
            { preserveScroll: true, preserveState: true, showProgress: false },
        );

    const ouvrir = (reseau: 'whatsapp' | 'facebook', lien: string) => {
        compter(reseau);
        window.open(lien, '_blank', 'noopener,noreferrer');
    };

    const copier = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setCopie(true);
            setTimeout(() => setCopie(false), 2500);
            compter('lien');
        } catch {
            // Presse-papiers refusé (connexion non sécurisée, navigateur ancien) :
            // l'adresse reste lisible et sélectionnable sous les boutons.
            setCopie(false);
        }
    };

    const bouton = 'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition';

    return (
        <div className={cn('flex flex-wrap items-center gap-2', className)}>
            <span className="mr-1 text-sm text-ink-500 dark:text-ink-400">Partager</span>

            <button
                type="button"
                onClick={() => ouvrir('whatsapp', `https://wa.me/?text=${encodeURIComponent(`${titre}\n${url}`)}`)}
                className={cn(bouton, 'bg-[#25D366] text-white hover:bg-[#1da851]')}
            >
                <Icon name="whatsapp" className="h-4 w-4" />
                WhatsApp
                {compteurs && compteurs.whatsapp > 0 && (
                    <span className="rounded-md bg-white/20 px-1.5 text-xs tabular-nums">{compteurs.whatsapp}</span>
                )}
            </button>

            <button
                type="button"
                onClick={() => ouvrir('facebook', `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`)}
                className={cn(bouton, 'bg-[#1877F2] text-white hover:bg-[#1162c9]')}
            >
                <Icon name="facebook" className="h-4 w-4" />
                Facebook
                {compteurs && compteurs.facebook > 0 && (
                    <span className="rounded-md bg-white/20 px-1.5 text-xs tabular-nums">{compteurs.facebook}</span>
                )}
            </button>

            <button
                type="button"
                onClick={copier}
                className={cn(
                    bouton,
                    'border border-ink-200 text-ink-700 hover:bg-ink-100 dark:border-white/10 dark:text-ink-200 dark:hover:bg-white/5',
                )}
            >
                <Icon name={copie ? 'check' : 'copy'} className="h-4 w-4" />
                {copie ? 'Lien copié' : 'Copier le lien'}
                {compteurs && compteurs.lien > 0 && (
                    <span className="rounded-md bg-ink-100 px-1.5 text-xs tabular-nums dark:bg-white/10">{compteurs.lien}</span>
                )}
            </button>
        </div>
    );
}
