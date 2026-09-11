<script setup lang="ts">
import { Aperture, Camera, RotateCcw, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useAuth } from '../stores/auth';
import CampoTexto from './CampoTexto.vue';

const { state } = useAuth();

const activo = ref(false);
const campos = ref(camposVacios());

// Foto del documento.
const video = useTemplateRef<HTMLVideoElement>('video');
const stream = ref<MediaStream | null>(null);
const camaraAbierta = ref(false);
const camaraError = ref('');
const archivo = ref<File | null>(null);
const previa = ref<string | null>(null);
const soportaCamara =
    typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;

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

/** Tipo "GUIA" del catálogo: es el que queda preseleccionado por defecto. */
const tipoPorDefecto = computed(() =>
    state.catalogos.tipos_documento.find(
        (t) => (t.nombre ?? '').trim().toUpperCase() === 'GUIA',
    ),
);

function aplicarDefecto(): void {
    if (campos.value.tipo_documento_id === '' && tipoPorDefecto.value) {
        campos.value.tipo_documento_id = String(tipoPorDefecto.value.id);
    }
}
watch(tipoPorDefecto, aplicarDefecto, { immediate: true });

const tipoElegido = computed(() =>
    state.catalogos.tipos_documento.find(
        (t) => String(t.id) === campos.value.tipo_documento_id,
    ),
);

/** El formulario está completo (o el switch está apagado). */
const listo = computed(
    () => !activo.value || campos.value.tipo_documento_id !== '',
);

/** Al registrar, este documento finalizará la hoja de ruta. */
const finalizara = computed(
    () => activo.value && tipoElegido.value?.condiciona_fin === true,
);

function cerrarCamara(): void {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
    camaraAbierta.value = false;
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
        // el <video> aparece con v-if; esperar al siguiente tick
        await new Promise((r) => setTimeout(r, 0));
        if (video.value) {
            video.value.srcObject = stream.value;
            await video.value.play().catch(() => undefined);
        }
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
    canvas.getContext('2d')?.drawImage(v, 0, 0, canvas.width, canvas.height);
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
                        v-for="t in state.catalogos.tipos_documento"
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
                label="Documento"
                placeholder="N.º / nombre (opcional)"
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
                    Foto del documento
                </span>

                <!-- Cámara en vivo -->
                <div v-if="camaraAbierta" class="flex flex-col gap-2">
                    <video
                        ref="video"
                        autoplay
                        playsinline
                        muted
                        class="w-full rounded-lg border border-gray-300 bg-black dark:border-gray-600"
                    />
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
