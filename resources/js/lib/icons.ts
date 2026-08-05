/*
 * Iconos de Font Awesome Free 7 (https://fontawesome.com), artwork bajo
 * CC BY 4.0. Se importan del paquete oficial @fortawesome/free-solid-svg-icons
 * y se dibujan con `components/FaIcon.vue` en vez del componente oficial,
 * porque su runtime pesaba mas que los propios iconos.
 */
import {
    faArrowRightFromBracket,
    faBan,
    faBoxArchive,
    faBullseye,
    faChartColumn,
    faCheck,
    faChevronLeft,
    faChevronRight,
    faClipboardCheck,
    faCopy,
    faDownload,
    faFilter,
    faGear,
    faKey,
    faListUl,
    faPlus,
    faRepeat,
    faRotateLeft,
    faStar,
    faStop,
    faStopwatch,
    faTrash,
    faXmark,
} from '@fortawesome/free-solid-svg-icons';

/**
 * The chrome icons, keyed by the short name templates use. Only what is listed
 * here reaches the bundle: the package is tree-shaken and each definition is
 * about a hundred bytes of path data.
 *
 * Category icons are deliberately not here — those are emoji, because they are
 * user data and have to render anywhere, including places the app does not
 * control, like the Rainmeter skin.
 */
export const icons = {
    'archive': faBoxArchive,
    'ban': faBan,
    'budget': faBullseye,
    'cancel': faXmark,
    'check': faCheck,
    'copy': faCopy,
    'download': faDownload,
    'filter': faFilter,
    'history': faListUl,
    'key': faKey,
    'logout': faArrowRightFromBracket,
    'plus': faPlus,
    'prev': faChevronLeft,
    'next': faChevronRight,
    'restore': faRotateLeft,
    'review': faClipboardCheck,
    'settings': faGear,
    'star': faStar,
    'stop': faStop,
    'template': faRepeat,
    'today': faStopwatch,
    'trash': faTrash,
    'week': faChartColumn,
} as const;

export type IconName = keyof typeof icons;
