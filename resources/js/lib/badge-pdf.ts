import { toPng } from 'html-to-image';
import { jsPDF } from 'jspdf';

import { CARTE_MM } from '@/pages/modules/badges/carte';

/**
 * Les badges en PDF, au format de la carte.
 *
 * La carte est dessinée une seule fois, en React : plutôt que de la refaire
 * côté serveur — deux dessins qui finiraient par diverger — on photographie
 * celle qui est à l'écran et on la pose, telle quelle, sur une page au format
 * de la carte. Le PDF sort donc exactement comme l'aperçu validé au guichet.
 */

/** Le format carte, en millimètres, tel que la carte le définit. */
export const CARTE = { largeur: CARTE_MM.largeur, hauteur: CARTE_MM.hauteur };

/**
 * Finesse de la capture. Une carte mesure environ 212 × 329 points à l'écran ;
 * à 4, l'image fait plus de 800 × 1300 pixels, soit environ 380 points par
 * pouce — ce que demande une imprimerie.
 */
const FINESSE = 4;

export interface FaceAImprimer {
    /** L'élément qui porte la carte, tel qu'il est affiché. */
    element: HTMLElement;
}

/** Photographie une face et la rend en image, fond blanc compris. */
async function capturer(element: HTMLElement): Promise<string> {
    return toPng(element, {
        pixelRatio: FINESSE,
        cacheBust: true,
        backgroundColor: '#ffffff',
        // Les ombres et l'anneau de l'écran n'ont rien à faire sur un tirage.
        style: { boxShadow: 'none' },
    });
}

/**
 * Assemble les faces en un PDF : une face par page, dans l'ordre reçu.
 *
 * Pour un badge recto-verso, on passe les deux faces à la suite ; la
 * reprographie imprime alors la page 1 au recto et la page 2 au verso.
 */
export async function pdfDesFaces(elements: HTMLElement[]): Promise<jsPDF> {
    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'mm',
        format: [CARTE.largeur, CARTE.hauteur],
        compress: true,
    });

    for (const [rang, element] of elements.entries()) {
        if (rang > 0) {
            pdf.addPage([CARTE.largeur, CARTE.hauteur], 'portrait');
        }

        const image = await capturer(element);
        pdf.addImage(image, 'PNG', 0, 0, CARTE.largeur, CARTE.hauteur, undefined, 'FAST');
    }

    return pdf;
}

/** Le même PDF, enregistré sous le nom donné. */
export async function telechargerFaces(elements: HTMLElement[], nomFichier: string): Promise<void> {
    const pdf = await pdfDesFaces(elements);
    pdf.save(nomFichier.endsWith('.pdf') ? nomFichier : `${nomFichier}.pdf`);
}

/** Un nom de fichier sans accent ni espace, pour ne fâcher aucun système. */
export function nomDeFichier(...morceaux: (string | null | undefined)[]): string {
    return (
        morceaux
            .filter(Boolean)
            .join('-')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-zA-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .toLowerCase() || 'badge'
    );
}
