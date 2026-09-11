import { createRouter, createWebHistory } from 'vue-router';
import { corriendoInstalada } from './lib/dispositivo';
import { useAuth } from './stores/auth';
import ContinuarPage from './pages/ContinuarPage.vue';
import HomePage from './pages/HomePage.vue';
import InstalarPage from './pages/InstalarPage.vue';
import LoginPage from './pages/LoginPage.vue';
import NuevaRutaPage from './pages/NuevaRutaPage.vue';

// Vacío salvo que la app vaya detrás de un proxy en subpath (ver config/pwa.php).
const BASE = import.meta.env.VITE_PWA_BASE_PATH ?? '';

export const router = createRouter({
    history: createWebHistory(`${BASE}/pwa`),
    routes: [
        // La raíz ("/pwa") es el `start_url` del manifest: lo primero que ve
        // un navegador normal es la página de instalar, no el login. Una vez
        // instalada (o si ya venía autenticado), el guard de abajo la salta.
        {
            path: '/',
            name: 'instalar',
            component: InstalarPage,
            meta: { publica: true },
        },
        {
            path: '/login',
            name: 'login',
            component: LoginPage,
            meta: { publica: true },
        },
        { path: '/inicio', name: 'home', component: HomePage },
        { path: '/nueva-ruta', name: 'nueva-ruta', component: NuevaRutaPage },
        { path: '/continuar', name: 'continuar', component: ContinuarPage },
        { path: '/:resto(.*)*', redirect: '/inicio' },
    ],
});

router.beforeEach((to) => {
    const { autenticado } = useAuth();

    // La página de instalar solo tiene sentido en una pestaña normal, sin
    // instalar todavía y sin sesión — a cualquier otro caso (ya instalada, o
    // ya logueado) se lo manda directo al flujo normal.
    if (to.name === 'instalar' && (corriendoInstalada() || autenticado.value)) {
        return { name: 'home' };
    }
    if (to.meta.publica !== true && !autenticado.value) {
        return { name: 'login' };
    }
    if (to.name === 'login' && autenticado.value) {
        return { name: 'home' };
    }

    return true;
});
