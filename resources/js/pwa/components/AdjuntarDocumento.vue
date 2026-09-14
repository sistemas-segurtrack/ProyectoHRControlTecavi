<script setup lang="ts">
import { Aperture, Camera, RotateCcw, X, ZoomIn } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useAuth } from '../stores/auth';
import CampoTexto from './CampoTexto.vue';

const props = withDefaults(
    defineProps<{
        /** Al ABRIR un tramo (parada impar: 1, 3, 5…) un documento que
         *  finaliza toda la hoja (p. ej. RECIBO COMBUSTIBLE) no tiene
         *  sentido todavía — recién se está empezando ese tramo. Se excluye
         *  ese tipo del combo; al CERRARLO (parada par) sí se ofrecen todos. */
        ocultarFinalizadores?: boolean;
    }>(),
    { ocultarFinalizadores: false },
);

const { state } = useAuth();

const activo = ref(false);
const campos = ref(camposVacios());

// Foto del documento.
const video = useTemplateRef<HTMLVideoElement>('video');
const camaraContenedor = useTemplateRef<HTMLDivElement>('camaraContenedor');
const stream = ref<MediaStream | null>(null);
const camaraAbierta = ref(false);
const camaraError = ref('');
const archivo = ref<File | null>(null);
const previa = ref<string | null>(null);
const soportaCamara =
    typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

// Zoom de la cámara. Algunos Android (Chrome) exponen un zoom real del
// sensor por `MediaTrackCapabilities.zoom` — todavía no es parte del DOM
// estándar de TypeScript, de ahí las interfaces propias de abajo — y se usa
// ese cuando está disponible. Si no, se simula recortando y agrandando el
// centro del cuadro (mismo truco que el "zoom digital" de cualquier cámara
// de celular): la vista en vivo se agranda con CSS y la foto capturada
// recorta esa misma región para que coincida con lo que se ve en pantalla.
interface CapacidadesConZoom extends MediaTrackCapabilities {
    zoom?: { min: number; max: number; step: number };
}
interface RestriccionConZoom extends MediaTrackConstraintSet {
    zoom?: number;
}

const zoom = ref(1);
const zoomMax = ref(1);
const zoomNativo = ref(false);
let pistaVideo: MediaStreamTrack | null = null;

function resetZoom(): void {
    zoom.value = 1;
    zoomMax.value = 1;
    zoomNativo.value = false;
    pistaVideo = null;
}

async function aplicarZoomNativo(valor: number): Promise<void> {
    if (!pistaVideo) return;
    try {
        const restriccion: RestriccionConZoom = { zoom: valor };
        await pistaVideo.applyConstraints({ advanced: [restriccion] });
    } catch {
        /* si el navegador rechaza el valor, la vista simplemente no cambia */
    }
}

watch(zoom, (valor) => {
    if (zoomNativo.value) void aplicarZoomNativo(valor);
});

function camposVacios() {
    return {
        tipo_documento_id: '',
        documento: '',
        cantidad: '',
        producto: '',
        envase: '',
        peso_neto: '',
        peso_bruto: '',
    };
}

/** Opciones del combo: todo el catálogo, salvo los que finalizan la hoja
 *  (`condiciona_fin`) cuando este avance ABRE un tramo. */
const tiposDisponibles = computed(() =>
    props.ocultarFinalizadores
        ? state.catalogos.tipos_documento.filter((t) => !t.condiciona_fin)
        : state.catalogos.tipos_documento,
);

/** Tipo "GUIA" del catálogo: es el que queda preseleccionado por defecto. */
const tipoPorDefecto = computed(() =>
    tiposDisponibles.value.find(
        (t) => (t.nombre ?? '').trim().toUpperCase() === 'GUIA',
    ),
);

function aplicarDefecto(): void {
    if (campos.value.tipo_documento_id === '' && tipoPorDefecto.value) {
        campos.value.tipo_documento_id = String(tipoPorDefecto.value.id);
    }
}
watch(tipoPorDefecto, aplicarDefecto, { immediate: true });

// Si el tipo elegido deja de estar disponible (p. ej. al entrar a un tramo
// que abre), se limpia la selección en vez de dejar un id "fantasma" que
// ya no aparece en el combo.
watch(tiposDisponibles, (lista) => {
    if (
        campos.value.tipo_documento_id !== '' &&
        !lista.some((t) => String(t.id) === campos.value.tipo_documento_id)
    ) {
        campos.value.tipo_documento_id = '';
        aplicarDefecto();
    }
});

const tipoElegido = computed(() =>
    tiposDisponibles.value.find(
        (t) => String(t.id) === campos.value.tipo_documento_id,
    ),
);

/** El formulario está completo (o el switch está apagado): con "Adjuntar"
 *  activo, el tipo, el código del documento y la foto son obligatorios. */
const listo = computed(
    () =>
        !activo.value ||
        (campos.value.tipo_documento_id !== '' &&
            campos.value.documento.trim() !== '' &&
            archivo.value !== null),
);

/** Al registrar, este documento finalizará la hoja de ruta. */
const finalizara = computed(
    () => activo.value && tipoElegido.value?.condiciona_fin === true,
);

function cerrarCamara(): void {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
    camaraAbierta.value = false;
    resetZoom();
}

async function abrirCamara(): Promise<void> {
    camaraError.value = '';
    if (!soportaCamara) return;
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        camaraAbierta.value = true;

        pistaVideo = stream.value.getVideoTracks()[0] ?? null;
        const capacidades = pistaVideo?.getCapabilities?.() as
            | CapacidadesConZoom
            | undefined;
        if (capacidades?.zoom) {
            zoomNativo.value = true;
            zoomMax.value = capacidades.zoom.max;
            zoom.value = capacidades.zoom.min;
        } else {
            zoomNativo.value = false;
            zoomMax.value = 3;
            zoom.value = 1;
        }

        // el <video> aparece con v-if; esperar al siguiente tick
        await new Promise((r) => setTimeout(r, 0));
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play().catch(() => undefined);
        }
        // La cámara suele quedar más abajo del campo que se estaba llenando
        // (varios campos antes) — se centra en pantalla para que el
        // conductor no tenga que buscarla haciendo scroll a mano.
        camaraContenedor.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
    } catch {
        camaraError.value =
            'No se pudo abrir la cámara. Revisa los permisos del navegador.';
        cerrarCamara();
    }
}

function ponerFoto(blob: Blob | null): void {
    if (!blob) return;
    if (previa.value) URL.revokeObjectURL(previa.value);
    archivo.value = new File([blob], `documento-${Date.now()}.jpg`, {
        type: 'image/jpeg',
    });
    previa.value = URL.createObjectURL(archivo.value);
}

function capturar(): void {
    const v = video.value;
    if (!v) return;
    const canvas = document.createElement('canvas');
    canvas.width = v.videoWidth;
    canvas.height = v.videoHeight;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    if (!zoomNativo.value && zoom.value > 1) {
        // Zoom simulado (CSS en la vista previa): recorta el centro del
        // cuadro en la misma proporción y lo agranda al tamaño del canvas,
        // para que la foto coincida con lo que el conductor vio en pantalla.
        const anchoRecorte = v.videoWidth / zoom.value;
        const altoRecorte = v.videoHeight / zoom.value;
        const x = (v.videoWidth - anchoRecorte) / 2;
        const y = (v.videoHeight - altoRecorte) / 2;
        ctx.drawImage(
            v,
            x,
            y,
            anchoRecorte,
            altoRecorte,
            0,
            0,
            canvas.width,
            canvas.height,
        );
    } else {
        // Sin zoom, o con zoom NATIVO del sensor (el cuadro ya viene
        // acercado de fábrica — no hay que recortar de nuevo).
        ctx.drawImage(v, 0, 0, canvas.width, canvas.height);
    }

    canvas.toBlob(
        (blob) => {
            ponerFoto(blob);
            cerrarCamara();
        },
        'image/jpeg',
        0.85,
    );
}

/** Fallback (sin getUserMedia): usa la cámara del sistema vía input file. */
function onArchivo(e: Event): void {
    const f = (e.target as HTMLInputElement).files?.[0] ?? null;
    if (previa.value) URL.revokeObjectURL(previa.value);
    archivo.value = f;
    previa.value = f ? URL.createObjectURL(f) : null;
}

function quitarFoto(): void {
    if (previa.value) URL.revokeObjectURL(previa.value);
    archivo.value = null;
    previa.value = null;
}

/** Añade los campos del documento a un FormData (no hace nada si el switch está apagado). */
function anexar(fd: FormData): void {
    if (!activo.value || !campos.value.tipo_documento_id) return;
    fd.append('adjuntar', '1');
    fd.append('documento[tipo_documento_id]', campos.value.tipo_documento_id);
    for (const clave of [
        'documento',
        'cantidad',
        'producto',
        'envase',
        'peso_neto',
        'peso_bruto',
    ] as const) {
        const valor = campos.value[clave].trim();
        if (valor !== '') fd.append(`documento[${clave}]`, valor);
    }
    if (archivo.value) fd.append('documento[imagen]', archivo.value);
}

function reset(): void {
    activo.value = false;
    campos.value = camposVacios();
    aplicarDefecto();
    cerrarCamara();
    quitarFoto();
    camaraError.value = '';
}

onBeforeUnmount(cerrarCamara);

defineExpose({ activo, listo, finalizara, anexar, reset });
</script>

<template>
    <div class="flex flex-col gap-4">
        <label class="flex items-center justify-between py-1">
            <span
                class="text-xs font-bold tracking-wide text-gray-500 uppercase"
            >
                Adjuntar
            </span>
            <button
                type="button"
                role="switch"
                :aria-checked="activo"
                class="relative h-7 w-12 rounded-full transition"
                :class="
                    activo ? 'bg-[#b51927]' : 'bg-gray-300 dark:bg-gray-600'
                "
                @click="activo = !activo"
            >
                <span
                    class="absolute top-1 left-1 h-5 w-5 rounded-full bg-white transition"
                    :class="activo ? 'translate-x-5' : ''"
                />
            </button>
        </label>

        <div
            v-if="activo"
            class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
        >
            <label class="flex flex-col gap-1">
                <span
                    class="text-xs font-bold tracking-wide text-gray-500 uppercase"
                >
                    Tipo de documento
                </span>
                <select
                    v-model="campos.tipo_documento_id"
                    class="h-12 w-full rounded-xl border border-gray-300 bg-white px-3.5 text-base text-gray-900 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                >
                    <option value="">Selecciona…</option>
                    <option
                        v-for="t in tiposDisponibles"
                        :key="t.id"
                        :value="String(t.id)"
                    >
                        {{ t.nombre }}
                    </option>
                </select>
            </label>

            <p
                v-if="finalizara"
                class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-300"
            >
                Este documento finaliza la hoja de ruta al registrarse.
            </p>

            <CampoTexto
                v-model="campos.documento"
                label="Código de documento *"
                placeholder="N.º / código"
                required
            />
            <CampoTexto
                v-model="campos.cantidad"
                label="Cantidad"
                inputmode="numeric"
                placeholder="Opcional"
            />
            <CampoTexto
                v-model="campos.producto"
                label="Producto"
                placeholder="Opcional"
            />
            <CampoTexto
                v-model="campos.envase"
                label="Envase"
                inputmode="numeric"
                placeholder="Opcional"
            />
            <CampoTexto
                v-model="campos.peso_neto"
                label="Peso neto"
                inputmode="decimal"
                placeholder="Opcional"
            />
            <CampoTexto
                v-model="campos.peso_bruto"
                label="Peso bruto"
                inputmode="decimal"
                placeholder="Opcional"
            />

            <!-- Foto del documento -->
            <div class="flex flex-col gap-2">
                <span
                    class="text-xs font-bold tracking-wide text-gray-500 uppercase"
                >
                    Foto del documento *
                </span>

                <!-- Cámara en vivo -->
                <div
                    v-if="camaraAbierta"
                    ref="camaraContenedor"
                    class="flex flex-col gap-2"
                >
                    <div
                        class="overflow-hidden rounded-lg border border-gray-300 bg-black dark:border-gray-600"
                    >
                        <video
                            ref="video"
                            autoplay
                            playsinline
                            muted
                            class="w-full"
                            :style="
                                !zoomNativo
                                    ? { transform: `scale(${zoom})` }
                                    : undefined
                            "
                        />
                    </div>

                    <!-- Zoom: nativo del sensor si el navegador lo soporta
                         (Android/Chrome), si no un recorte digital del
                         centro (ver capturar()). -->
                    <div class="flex items-center gap-2 px-0.5">
                        <ZoomIn class="h-4 w-4 shrink-0 text-gray-400" />
                        <input
                            v-model.number="zoom"
                            type="range"
                            min="1"
                            :max="zoomMax"
                            step="0.1"
                            class="h-1.5 flex-1 accent-[#b51927]"
                        />
                        <span
                            class="w-9 shrink-0 text-right text-xs font-semibold text-gray-500 dark:text-gray-400"
                        >
                            {{ zoom.toFixed(1) }}x
                        </span>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="inline-flex h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-[#b51927] text-base font-bold text-white active:scale-[.98]"
                            @click="capturar"
                        >
                            <Aperture class="h-5 w-5" />
                            Capturar
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-1.5 rounded-xl border border-gray-300 px-4 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300"
                            @click="cerrarCamara"
                        >
                            <X class="h-4 w-4" />
                            Cancelar
                        </button>
                    </div>
                </div>

                <!-- Foto tomada -->
                <div v-else-if="previa" class="flex flex-col gap-2">
                    <img
                        :src="previa"
                        alt="Foto del documento"
                        class="max-h-56 w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700"
                    />
                    <button
                        v-if="soportaCamara"
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-[#b51927] text-sm font-semibold text-[#b51927]"
                        @click="abrirCamara"
                    >
                        <RotateCcw class="h-4 w-4" />
                        Repetir foto
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-gray-300 text-sm font-semibold text-gray-600 dark:border-gray-600 dark:text-gray-300"
                        @click="quitarFoto"
                    >
                        <X class="h-4 w-4" />
                        Quitar foto
                    </button>
                </div>

                <!-- Sin foto todavía -->
                <template v-else>
                    <button
                        v-if="soportaCamara"
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-[#b51927] text-base font-bold text-[#b51927] active:scale-[.98]"
                        @click="abrirCamara"
                    >
                        <Camera class="h-5 w-5" />
                        Tomar foto
                    </button>
                    <input
                        v-else
                        type="file"
                        accept="image/*"
                        capture="environment"
                        class="text-sm"
                        @change="onArchivo"
                    />
                </template>

                <p v-if="camaraError" class="text-xs text-rose-600">
                    {{ camaraError }}
                </p>
            </div>
        </div>
    </div>
</template>
