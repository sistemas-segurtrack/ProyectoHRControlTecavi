import { createRouter, createWebHistory } from 'vue-router';
import { useAuth } from './stores/auth';
import ContinuarPage from './pages/ContinuarPage.vue';
import HomePage from './pages/HomePage.vue';
import LoginPage from './pages/LoginPage.vue';
import NuevaRutaPage from './pages/NuevaRutaPage.vue';

// Vacío salvo que la app vaya detrás de un proxy en subpath (ver config/pwa.php).
const BASE = import.meta.env.VITE_PWA_BASE_PATH ?? '';

export const router = createRouter({
    history: createWebHistory(`${BASE}/pwa`),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginPage,
            meta: { publica: true },
        },
        { path: '/', name: 'home', component: HomePage },
        { path: '/nueva-ruta', name: 'nueva-ruta', component: NuevaRutaPage },
        { path: '/continuar', name: 'continuar', component: ContinuarPage },
        { path: '/:resto(.*)*', redirect: '/' },
    ],
});

router.beforeEach((to) => {
    const { autenticado } = useAuth();

    if (to.meta.publica !== true && !autenticado.value) {
        return { name: 'login' };
    }
    if (to.name === 'login' && autenticado.value) {
        return { name: 'home' };
    }

    return true;
});
