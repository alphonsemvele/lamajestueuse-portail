import type { CSSProperties } from 'react';

/**
 * Le cadrage d'une photo : où regarder, et de combien agrandir.
 *
 * `x` et `y` donnent en pourcentage le point de la photo qui doit se
 * retrouver au centre du cadre ; `zoom` dit de combien l'agrandir. Rien
 * n'est appliqué au fichier : il reste tel qu'il a été déposé, et le
 * cadrage se refait autant de fois qu'on veut.
 */
export interface Cadrage {
    x: number;
    y: number;
    zoom: number;
}

export const CADRAGE_NEUTRE: Cadrage = { x: 50, y: 50, zoom: 1 };

/** L'agrandissement au-delà duquel la photo se délite à l'impression. */
export const ZOOM_MAX = 3;

/** Lit ce que le serveur envoie : un cadrage, ou rien. */
export function lireCadrage(valeur: unknown): Cadrage {
    if (!valeur || typeof valeur !== 'object') {
        return CADRAGE_NEUTRE;
    }

    const brut = valeur as Partial<Record<keyof Cadrage, unknown>>;
    const nombre = (v: unknown, defaut: number) => (typeof v === 'number' && Number.isFinite(v) ? v : defaut);

    return {
        x: Math.min(100, Math.max(0, nombre(brut.x, 50))),
        y: Math.min(100, Math.max(0, nombre(brut.y, 50))),
        zoom: Math.min(ZOOM_MAX, Math.max(1, nombre(brut.zoom, 1))),
    };
}

/**
 * Le style à poser sur l'image elle-même, dans un conteneur qui masque ce
 * qui déborde. L'origine de l'agrandissement suit le point visé : sans
 * cela, zoomer ramènerait le cadrage au centre.
 */
export function styleCadrage(cadrage?: Cadrage | null): CSSProperties {
    const { x, y, zoom } = cadrage ?? CADRAGE_NEUTRE;

    return {
        objectFit: 'cover',
        objectPosition: `${x}% ${y}%`,
        transform: zoom > 1 ? `scale(${zoom})` : undefined,
        transformOrigin: `${x}% ${y}%`,
    };
}

/** Un cadrage qui ne dit rien de plus que l'affichage par défaut. */
export function estNeutre(cadrage?: Cadrage | null): boolean {
    const { x, y, zoom } = cadrage ?? CADRAGE_NEUTRE;

    return x === 50 && y === 50 && zoom === 1;
}

/** Ce qui part au serveur : rien quand le cadrage ne dit rien. */
export function pourEnvoi(cadrage?: Cadrage | null): string | null {
    return estNeutre(cadrage) ? null : JSON.stringify(cadrage);
}
