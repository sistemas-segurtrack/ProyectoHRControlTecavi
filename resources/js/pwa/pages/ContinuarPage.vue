<script setup lang="ts">
import { ChevronLeft, Paperclip } from '@lucide/vue';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import { useRouter } from 'vue-router';
import AdjuntarDocumento from '../components/AdjuntarDocumento.vue';
import CampoCombo from '../components/CampoCombo.vue';
import CampoTexto from '../components/CampoTexto.vue';
import EstadoUbicacion from '../components/EstadoUbicacion.vue';
import { api, ApiError, fechaHoraLocal, uuid } from '../lib/api';
import { useUbicacion } from '../lib/dispositivo';
import {
    armarCuerpo,
    faltanteAntesDeEnviar,
    textoONulo,
} from '../lib/formularioAvance';
import { errorKilometraje, kilometrajeAnterior } from '../lib/kilometraje';
import { encolarContinuar } from '../lib/sincronizar';
import { rutaFinalizada, useAuth, type Ruta } from '../stores/auth';

const router = useRouter();
const { state, setRutaActiva } = useAuth();
const { ubicacion } = useUbicacion();

onMounted(() => {
    if (!state.rutaActiva) {
        router.replace({ name: 'home' });
    }
});

const ruta = computed(() => state.rutaActiva);

const form = ref({
    geocerca: '',
    fhRegistro: fechaHoraLocal(),
    kilometraje: '',
    observacion: '',
});
const adjunto = useTemplateRef('adjunto');
const cargando = ref(false);
const error = ref('');

// El km debe superar al de la parada anterior (también sin señal: las
// paradas encoladas ya están en `ruta.ordenes`).
const kmAnterior = computed(() =>
    kilometrajeAnterior(ruta.value?.ordenes ?? []),
);
const errorKm = computed(() =>
    errorKilometraje(form.value.kilometraje, kmAnterior.value),
);

// Parada impar = inicio de un tramo, parada par = su fin. Si este avance
// ABRE un tramo nuevo, un documento que finaliza toda la hoja (RECIBO
// COMBUSTIBLE) no debe ofrecerse todavía.
const abreTramo = computed(() => {
    const ultimoOrden = ruta.value?.ordenes.at(-1)?.orden ?? 0;
    return (ultimoOrden + 1) % 2 === 1;
});

/**
 * El servidor solo marca `FI` la parada que un avance POSTERIOR ya dejó
 * atrás (`DetalleRutaObserver`) — la última parada registrada siempre llega
 * como `ER` ("en ruta"), aunque sea PAR y ya haya cerrado su propio tramo.
 * Acá se corrige esa última parada para la vista: una parada PAR siempre
 * cerró su tramo (aunque la hoja completa siga sin finalizar), así que se
 * muestra FINALIZADO; solo una IMPAR sigue de verdad "en ruta" (tramo
 * abierto, esperando su cierre).
 */
function estadoAvance(o: { estado: string | null; orden: number | null }): {
    finalizado: boolean;
    label: string;
} {
    const finalizado = o.estado === 'FI' || (o.orden ?? 0) % 2 === 0;
    return finalizado
        ? { finalizado: true, label: 'FINALIZADO' }
        : { finalizado: false, label: 'EN RUTA' };
}

function limpiar(): void {
    form.value = {
        geocerca: '',
        fhRegistro: fechaHoraLocal(),
        kilometraje: '',
        observacion: '',
    };
    adjunto.value?.reset();
}

async function registrar(): Promise<void> {
    if (!ruta.value) return;
    const faltante = faltanteAntesDeEnviar({
        adjunto: adjunto.value,
        kilometraje: form.value.kilometraje,
        geocerca: form.value.geocerca,
    });
    if (faltante) {
        error.value = faltante;
        return;
    }
    // El aviso de km ya está visible junto al campo.
    if (errorKm.value) return;
    error.value = '';
    cargando.value = true;

    const campos = {
        geocerca: textoONulo(form.value.geocerca),
        // Coordenada automática de la ubicación del dispositivo.
        coordenada: ubicacion.coordenada,
        fhRegistro: form.value.fhRegistro || null,
        kilometraje: textoONulo(form.value.kilometraje),
        observacion: textoONulo(form.value.observacion),
    };
    const cuerpo = armarCuerpo(campos, adjunto.value);
    try {
        const res = await api<{ data: Ruta }>(
            `/rutas/${ruta.value.idruta}/ordenes`,
            { method: 'POST', idempotencyKey: uuid(), body: cuerpo },
        );
        // Si el documento adjunto finaliza la hoja de ruta, ya no queda "en
        // curso" — Home debe ofrecer "Nueva Ruta" y no "Continuar".
        setRutaActiva(rutaFinalizada(res.data) ? null : res.data);
        limpiar();
        router.replace({ name: 'home' });
    } catch (e) {
        if (e instanceof ApiError && e.status === 0) {
            // Sin señal: se registra localmente y se manda cuando vuelva.
            const local = await encolarContinuar(cuerpo, ruta.value, {
                ...campos,
                fhRegistro: form.value.fhRegistro,
                finaliza: adjunto.value?.finalizara ?? false,
            });
            setRutaActiva(rutaFinalizada(local) ? null : local);
            limpiar();
            router.replace({ name: 'home' });
            return;
        }
        error.value =
            e instanceof ApiError ? e.message : 'No se pudo registrar.';
    } finally {
        cargando.value = false;
    }
}
</script>

<template>
    <div v-if="ruta" class="flex flex-1 flex-col gap-5 p-6">
        <header class="flex items-center gap-3">
            <button
                class="rounded-lg p-1 text-gray-400"
                aria-label="Volver"
                @click="router.back()"
            >
                <ChevronLeft class="h-6 w-6" />
            </button>
            <div>
                <h1 class="text-lg font-bold">Continuar ruta</h1>
                <p class="font-mono text-xs text-gray-400">{{ ruta.idruta }}</p>
            </div>
        </header>

        <!-- Órdenes registradas -->
        <div class="flex flex-col gap-2">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">
                Avances ({{ ruta.ordenes.length }})
            </p>
            <ul class="flex flex-col gap-1.5">
                <li
                    v-for="o in ruta.ordenes"
                    :key="o.id"
                    class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                >
                    <span class="font-semibold">{{ o.orden }}</span>
                    <span class="text-gray-500">{{ o.geocerca ?? '—' }}</span>
                    <Paperclip
                        v-if="o.documentos.length"
                        class="h-3.5 w-3.5 text-gray-400"
                    />

                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                        :class="
                            estadoAvance(o).finalizado
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-300'
                                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'
                        "
                    >
                        {{ estadoAvance(o).label }}
                    </span>
                </li>
            </ul>
        </div>

        <form class="flex flex-1 flex-col gap-4" @submit.prevent="registrar">
            <p class="text-xs font-bold tracking-wide text-gray-500 uppercase">
                Registrar avance
            </p>

            <CampoCombo
                v-model="form.geocerca"
                label="Lugar"
                :options="state.catalogos.geocercas"
                placeholder="Dónde estás"
                required
            />

            <CampoTexto
                v-model="form.fhRegistro"
                label="Fecha y hora"
                type="datetime-local"
                required
            />
            <div class="flex flex-col gap-1">
                <CampoTexto
                    v-model="form.kilometraje"
                    label="Kilometraje"
                    inputmode="numeric"
                    :placeholder="
                        kmAnterior === null
                            ? 'Km del odómetro'
                            : `Mayor a ${kmAnterior} km`
                    "
                    required
                />
                <p
                    v-if="errorKm"
                    class="text-xs text-rose-600 dark:text-rose-400"
                >
                    {{ errorKm }}
                </p>
            </div>
            <CampoTexto
                v-model="form.observacion"
                label="Observación"
                placeholder="Opcional"
            />

            <AdjuntarDocumento
                ref="adjunto"
                :ocultar-finalizadores="abreTramo"
            />

            <EstadoUbicacion />

            <p
                v-if="error"
                class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
            >
                {{ error }}
            </p>

            <button
                type="submit"
                :disabled="cargando || !!errorKm"
                class="mt-auto h-14 w-full rounded-xl bg-[#b51927] text-lg font-bold text-white transition active:scale-[.98] disabled:opacity-60"
            >
                {{ cargando ? 'Registrando…' : 'Registrar avance' }}
            </button>
        </form>
    </div>
</template>
