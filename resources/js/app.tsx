import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import EnCours from '@/components/en-cours';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'La Majestueuse';

/*
 * Une action reste là où elle a été déclenchée.
 *
 * Inertia remonte en haut de page après chaque visite, ce qui est juste pour
 * une navigation mais pas pour une action : cocher une case au bas d'un
 * tableau ne doit pas renvoyer l'écran à son sommet. On préserve donc le
 * défilement dès que la requête n'est pas un GET, sans toucher aux liens.
 */
router.on('before', (evenement) => {
    const visite = evenement.detail.visit;

    if (visite.method !== 'get') {
        visite.preserveScroll = true;
    }
});

// Resolveur maison : le helper de laravel-vite-plugin expose un type trop
// large pour Inertia 3, qui attend un module resolu.
const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const page = pages[`./pages/${name}.tsx`];

        if (!page) {
            throw new Error(`Page introuvable : ${name}`);
        }

        // Inertia 3 attend le composant lui-meme, pas le module.
        return page().then((module) => module.default);
    },
    setup({ el, App, props }) {
        // L'indicateur d'attente vit DANS l'application : il a besoin du
        // contexte Inertia (page courante, traductions).
        createRoot(el).render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => (
                    <>
                        <EnCours />
                        <Component key={key} {...pageProps} />
                    </>
                )}
            </App>,
        );
    },
    // L'attente est signalée par notre propre indicateur (components/en-cours),
    // plus visible que la fine barre par défaut.
    progress: false,
});
