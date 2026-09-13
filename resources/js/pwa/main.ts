import { createApp } from 'vue';
import App from './App.vue';
import { vigilarActualizaciones } from './lib/actualizaciones';
import { router } from './router';
import '../../css/app.css';

createApp(App).use(router).mount('#pwa-app');

if ('serviceWorker' in navigator) {
    // Vacío salvo que la app vaya detrás de un proxy en subpath.
    const base = import.meta.env.VITE_PWA_BASE_PATH ?? '';

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register(`${base}/pwa/sw.js`, { scope: `${base}/pwa/` })
            .then((registro) => vigilarActualizaciones(registro))
            .catch(() => {
                /* el SW es opcional; la app funciona sin él */
            });
    });
}
