import { createRouter, createWebHistory } from 'vue-router';
import { api } from './lib/api';
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
        // La raíz ("/pwa") es el `start_url` del manifest. En una pestaña
        // normal del navegador es la única pantalla disponible.
        { path: '/', name: 'instalar', component: InstalarPage },
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

/**
 * La app solo funciona instalada. El almacenamiento del navegador sobrevive a
 * la desinstalación, así que abrir el link en una pestaña normal con una
 * sesión guardada la cierra (y revoca el token). La cola offline (IndexedDB)
 * no se toca: sus envíos salen cuando ese conductor vuelva a entrar.
 */
function cerrarSesionDelNavegador(): void {
    const { cerrarSesion } = useAuth();

    // `api()` arma la cabecera con el token antes de su primer `await`, así
    // que el logout sale con el token aunque la sesión se borre enseguida.
    api('/logout', { method: 'POST' }).catch(() => {
        /* sin señal o ya revocado: igual se borra la sesión local */
    });
    cerrarSesion();
}

router.beforeEach((to) => {
    const { autenticado } = useAuth();

    if (!corriendoInstalada()) {
        if (autenticado.value) cerrarSesionDelNavegador();

        return to.name === 'instalar' ? true : { name: 'instalar' };
    }

    if (to.name === 'instalar') {
        return { name: autenticado.value ? 'home' : 'login' };
    }
    if (to.meta.publica !== true && !autenticado.value) {
        return { name: 'login' };
    }
    if (to.name === 'login' && autenticado.value) {
        return { name: 'home' };
    }

    return true;
});
