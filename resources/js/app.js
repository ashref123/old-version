import './bootstrap';
import '../scss/config/default/app.scss';
import '@vueform/slider/themes/default.css';
import '../scss/mermaid.min.css';
import 'leaflet/dist/leaflet.css';

import { createApp, h } from 'vue';
import { createInertiaApp , router} from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import BootstrapVueNext from 'bootstrap-vue-next';
import vClickOutside from "click-outside-vue3";
import VueApexCharts from "vue3-apexcharts";
import VueFeather from 'vue-feather';
import VueTheMask from 'vue-the-mask';
import { ref, onMounted } from "vue";
import AOS from 'aos';
import 'aos/dist/aos.css';

import store from "./state/store";
import i18n, { initI18n } from './i18n';
import CookieConsent from './Components/CookieConsent.vue';

AOS.init({
    easing: 'ease-out-back',
    duration: 1000
});

router.on('error', () => {
    window.location.reload();
});


async function bootstrap() {

    const selectedLanguageCode = ref(i18n.global.locale);
    const currentLocale = localStorage.getItem('locale') || window.defaultLocale;
    selectedLanguageCode.value = currentLocale;
    localStorage.setItem('locale', currentLocale);
    
    const body = document.body;

    const normalizeTitle = (title) => {
        return title === 'Log in' ? 'Login' : title;
    };

    let portalTitle = window.__PORTAL_TITLE || (window.__INERTIA_PAGE_PATH === '/' ? 'Landing-Site' : 'Admin');

    const getPanelTitle = (pathname) => {
        const path = (pathname || window.location.pathname || window.__INERTIA_PAGE_PATH || '/').replace(/\/+$/, '') || '/';

        // `portalTitle` is supplied by Laravel from the authenticated role.
        // It keeps every menu page in a portal labelled consistently after login.
        if (portalTitle && portalTitle !== 'Admin') return portalTitle;

        if (
            path === '/' ||
            path.startsWith('/create-booking') ||
            path.startsWith('/user') ||
            path.startsWith('/driver') ||
            path.startsWith('/aboutus') ||
            path.startsWith('/contact') ||
            path.startsWith('/privacy') ||
            path.startsWith('/compliance') ||
            path.startsWith('/terms') ||
            path.startsWith('/dmv')
        ) {
            return 'Landing-Site';
        }

        if (
            path.startsWith('/dispatcher-pro') ||
            path.startsWith('/dispatcher') ||
            path.startsWith('/dispatch') ||
            path.startsWith('/login/dispatch') ||
            path.startsWith('/login/dispatcher') ||
            path.startsWith('/login/dispatcher-pro')
        ) {
            return 'Dispatcher';
        }

        if (
            path.startsWith('/owner-dashboard') ||
            path.startsWith('/individual-owner-dashboard') ||
            path.startsWith('/owner/')
        ) {
            return 'Owner';
        }

        if (path.startsWith('/franchiseowner-dashboard')) {
            return 'Franchise';
        }

        return 'Admin';
    };

    const getPageTitle = (pathname) => {
        const path = (pathname || window.location.pathname || '/').replace(/\/+$/, '') || '/';

        if (path === '/login' || path.startsWith('/login/')) return 'Login';
        if (path === '/create-booking') return 'Create Booking';
        if (path === '/dispatcher/bookride') return 'Book Ride';

        return '';
    };

    router.on('navigate', (event) => {
        portalTitle = event.detail.page.props.portalTitle || portalTitle;
    });

    // Fetch permissions before initializing the app
    // await store.dispatch('fetchPermissions');
    await initI18n(currentLocale);

    createInertiaApp({
        title: title => {
            const panelTitle = getPanelTitle();
            // Route titles take precedence where the URL has a clearer label
            // than a legacy component Head title (for example Create Booking).
            const resolvedTitle = getPageTitle() || normalizeTitle(title);

            return resolvedTitle ? `${resolvedTitle} | ${panelTitle}` : panelTitle;
        },
        resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
        setup({ el, App, props, plugin }) {
            return createApp({ render: () => h(App, props) })
                .use(plugin)
                .use(store)
                .use(i18n)
                .use(ZiggyVue)
                .use(BootstrapVueNext)
                .use(VueApexCharts)
                .use(VueTheMask)
                .use(vClickOutside)
                .component(VueFeather.type, VueFeather)
                .component('CookieConsent', CookieConsent)
                .mount(el);
                
        },
        progress: {
            color: '#4B5563',
        },
    });
}

bootstrap();
