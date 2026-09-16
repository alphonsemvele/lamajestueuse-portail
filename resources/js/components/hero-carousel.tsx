import { type PropsWithChildren, useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export interface Slide {
    image: string;
    label: string;
}

interface Props {
    slides: Slide[];
    /** Durée d'affichage d'une vue, en millisecondes. */
    interval?: number;
}

/**
 * Bandeau à défilement automatique.
 *
 * Les vues glissent horizontalement et la photo affichée dérive lentement
 * (effet Ken Burns) pour éviter l'image figée. Le défilement se met en pause
 * quand l'onglet passe en arrière-plan ou au survol, et s'efface entièrement
 * si le système demande à réduire les animations.
 */
export default function HeroCarousel({ slides, interval = 6500, children }: PropsWithChildren<Props>) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const [reduced, setReduced] = useState(false);
    const startedAt = useRef(Date.now());

    useEffect(() => {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        const sync = () => setReduced(query.matches);
        sync();
        query.addEventListener('change', sync);
        return () => query.removeEventListener('change', sync);
    }, []);

    useEffect(() => {
        const onVisibility = () => setPaused(document.hidden);
        document.addEventListener('visibilitychange', onVisibility);
        return () => document.removeEventListener('visibilitychange', onVisibility);
    }, []);

    useEffect(() => {
        if (reduced || paused || slides.length < 2) return;

        startedAt.current = Date.now();
        const timer = setInterval(() => setIndex((current) => (current + 1) % slides.length), interval);

        return () => clearInterval(timer);
    }, [reduced, paused, slides.length, interval, index]);

    const go = (next: number) => {
        setIndex(next);
        startedAt.current = Date.now();
    };

    return (
        <div
            className="relative isolate h-full overflow-hidden bg-ink-950"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            {/* Rail des vues : une translation par cran */}
            <div
                className="absolute inset-0 -z-20 flex transition-transform duration-[900ms] ease-[cubic-bezier(0.65,0,0.35,1)]"
                style={{ transform: `translateX(-${index * 100}%)` }}
            >
                {slides.map((slide, position) => (
                    <div key={slide.image} className="relative h-full w-full shrink-0 overflow-hidden">
                        <img
                            src={slide.image}
                            alt=""
                            loading={position === 0 ? 'eager' : 'lazy'}
                            className={cn(
                                'h-full w-full object-cover',
                                !reduced && 'motion-safe:animate-[heroDrift_18s_ease-in-out_infinite_alternate]',
                            )}
                        />
                    </div>
                ))}
            </div>

            {/* Voile en deux couches : sombre a gauche pour la lisibilite du texte,
                plus transparent a droite pour laisser respirer la photo. */}
            <div className="absolute inset-0 -z-10 bg-linear-to-r from-ink-950/92 via-ink-950/62 to-ink-950/28" />
            <div className="absolute inset-0 -z-10 bg-brand-950/25" />

            {children}

            {slides.length > 1 && (
                <div className="absolute inset-x-0 bottom-0 flex items-center justify-end gap-2 p-12 xl:px-16">
                    {slides.map((slide, position) => (
                        <button
                            key={slide.image}
                            type="button"
                            aria-label={slide.label}
                            aria-current={position === index}
                            onClick={() => go(position)}
                            className={cn(
                                'relative h-1.5 overflow-hidden rounded-full transition-all duration-300',
                                position === index ? 'w-10 bg-white/25' : 'w-3 bg-white/25 hover:bg-white/40',
                            )}
                        >
                            {position === index && (
                                <span
                                    key={`${index}-${paused}-${reduced}`}
                                    className="absolute inset-y-0 left-0 block rounded-full bg-brand-400"
                                    style={
                                        reduced || paused
                                            ? { width: '100%' }
                                            : { animation: `heroProgress ${interval}ms linear forwards` }
                                    }
                                />
                            )}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
