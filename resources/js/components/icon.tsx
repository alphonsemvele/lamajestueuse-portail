import { cn } from '@/lib/utils';

/** Jeu d'icones du portail : traces SVG en 24x24, trait courant. */
const paths: Record<string, string> = {
    'academic': `<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.7 3 6 3s6-2 6-3v-5"/><path d="M22 10v6"/>`,
    'school': `<path d="M14 22v-4a2 2 0 1 0-4 0v4"/><path d="m2 9 10-7 10 7"/><path d="M4 10v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V10"/>`,
    'briefcase': `<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><path d="M2 13h20"/>`,
    'heart': `<path d="M19 14c1.5-1.5 3-3.3 3-5.5A5.5 5.5 0 0 0 12 5.7 5.5 5.5 0 0 0 2 8.5c0 2.2 1.5 4 3 5.5l7 7Z"/>`,
    'users': `<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>`,
    'document': `<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>`,
    'mail': `<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/>`,
    'globe': `<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20Z"/>`,
    'grid': `<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>`,
    'search': `<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>`,
    'bell': `<path d="M18 8a6 6 0 1 0-12 0c0 6-2 7-2 7h16s-2-1-2-7"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>`,
    'sun': `<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>`,
    'moon': `<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>`,
    'monitor': `<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>`,
    'user': `<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>`,
    'lock': `<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>`,
    'eye': `<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>`,
    'eye-off': `<path d="M10.6 5.2A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3 3.9"/><path d="M6.1 6.6A17 17 0 0 0 2 12s3.6 7 10 7a9.8 9.8 0 0 0 4.5-1.1"/><path d="m2 2 20 20"/>`,
    'arrow-right': `<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>`,
    'chevron-right': `<path d="m9 18 6-6-6-6"/>`,
    'chevron-down': `<path d="m6 9 6 6 6-6"/>`,
    'chevron-up': `<path d="m18 15-6-6-6 6"/>`,
    'external': `<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>`,
    'plus': `<path d="M12 5v14M5 12h14"/>`,
    'pencil': `<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>`,
    'trash': `<path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>`,
    'check': `<path d="m20 6-11 11-5-5"/>`,
    'clock': `<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>`,
    'login': `<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>`,
    'logout': `<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>`,
    'shield': `<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>`,
    'megaphone': `<path d="m3 11 15-6v14L3 13Z"/><path d="M3 11v2a2 2 0 0 0 2 2h1"/><path d="M7 15v4a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-3"/>`,
    'newspaper': `<path d="M4 4h13a1 1 0 0 1 1 1v14a2 2 0 0 0 2 2H5a2 2 0 0 1-2-2V5a1 1 0 0 1 1-1Z"/><path d="M7 8h7M7 12h7M7 16h4"/>`,
    'clipboard': `<rect x="8" y="3" width="8" height="4" rx="1"/><path d="M16 5h2a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h2"/><path d="M9 13h6M9 17h4"/>`,
    'layers': `<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 17 9 5 9-5"/>`,
    'settings': `<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>`,
    'link': `<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1.5 1.5"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7L12.5 19"/>`,
    'pin': `<path d="M12 17v5"/><path d="M9 2h6l-1 6 3 3v2H7v-2l3-3-1-6Z"/>`,
    'alert': `<path d="M12 9v4"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/>`,
    'key': `<circle cx="7.5" cy="15.5" r="4.5"/><path d="m10.7 12.3 8.3-8.3"/><path d="m16 7 2.5 2.5"/>`,
    'building': `<rect x="4" y="2" width="16" height="20" rx="1.5"/><path d="M9 6h1M14 6h1M9 10h1M14 10h1M9 14h1M14 14h1"/><path d="M10 22v-4h4v4"/>`,
    'image': `<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>`,
    'sliders': `<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h10M18 18h2"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="16" cy="18" r="2"/>`,
    'phone': `<path d="M15.5 21A12.5 12.5 0 0 1 3 8.5 2.5 2.5 0 0 1 5.5 6h1.8a1 1 0 0 1 1 .78l.7 3a1 1 0 0 1-.3.96l-1.3 1.2a10.5 10.5 0 0 0 4.66 4.66l1.2-1.3a1 1 0 0 1 .96-.3l3 .7a1 1 0 0 1 .78 1v1.8A2.5 2.5 0 0 1 15.5 21Z"/>`,
    'upload': `<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 9 5-5 5 5"/><path d="M12 4v12"/>`,
};

export default function Icon({ name, className }: { name: string; className?: string }) {
    return (
        <svg
            className={cn('h-5 w-5', className)}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.6}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            dangerouslySetInnerHTML={{ __html: paths[name] ?? paths.grid }}
        />
    );
}
