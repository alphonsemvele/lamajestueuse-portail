import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import EnCours from '@/components/en-cours';
import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'La Majestueuse';

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
