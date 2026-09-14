<script lang="ts">
let _filterTimer: ReturnType<typeof setTimeout> | null = null;
</script>

<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    FileSpreadsheet,
    FileText,
    Filter,
    Loader2,
    Paperclip,
    RefreshCw,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AccesoRutasModal from '@/components/AccesoRutasModal.vue';
import ComboFilter from '@/components/ComboFilter.vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import MetricaCard from '@/components/MetricaCard.vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import rutasRoutes from '@/routes/modulos/rutas';

// ─── Types ────────────────────────────────────────────────────────────────────

type Diferencia = { texto: string; signo: 'pos' | 'neg' | 'cero' } | null;

type Documento = {
    tipo: string | null;
    documento: string | null;
    producto: string | null;
    cantidad: string | null;
    envase: string | null;
    peso_neto: string | null;
    peso_bruto: string | null;
    imagen: string | null;
    /** De la parada (`detalleruta.observacion`) donde se registró — hasta 500 caracteres. */
    observacion: string | null;
};

/**
 * Un tramo = un par de paradas consecutivas (la N y la N+1) dentro de una
 * hoja. El resumen de `Hoja` solo trae la primera y la última parada de
 * TODA la hoja; esto es el itinerario completo para el modal (ver
 * `ConsultaHojasRuta::tramosPorRuta()`).
 */
type Tramo = {
    tramo: number;
    conductor: string | null;
    copiloto: string | null;
    placa: string | null;
    carreta: string | null;
    precintos: string | null;
    geocerca: string | null;
    coordenada: string | null;
    geocerca_final: string | null;
    coordenada_final: string | null;
    km_inicial: string | null;
    km_final: string | null;
    sis_inicial: string | null;
    cond_inicial: string | null;
    dif_inicial: Diferencia;
    sis_final: string | null;
    cond_final: string | null;
    dif_final: Diferencia;
    /** De la parada de inicio del tramo (`detalleruta.observacion`) — hasta 500 caracteres. */
    observacion: string | null;
    estado: string;
    estado_label: string;
};

type Hoja = {
    /** El `idruta` — una fila por hoja de ruta completa, no por tramo. */
    id: string;
    hoja: string | null;
    placa: string | null;
    carreta: string | null;
    conductor: string | null;
    copiloto: string | null;
    precintos: string | null;
    geocerca: string | null;
    coordenada: string | null;
    geocerca_final: string | null;
    coordenada_final: string | null;
    observacion: string | null;
    km_inicial: string | null;
    km_final: string | null;
    fh_inicio: string | null;
    fh_final: string | null;
    cond_inicial: string | null;
    sis_inicial: string | null;
    dif_inicial: Diferencia;
    cond_final: string | null;
    sis_final: string | null;
    dif_final: Diferencia;
    documentos: Documento[];
    /** Itinerario por tramo (paradas N y N+1 emparejadas) — ver `Tramo`. */
    tramos: Tramo[];
    estado: string;
    estado_label: string;
};

type PaginaMeta = {
    actual: number;
    total: number;
    porPagina: number;
    totalRegistros: number;
};

type Filtros = {
    conductor: string;
    placa: string;
    id: string;
    desde: string;
    hasta: string;
    estado: string;
    geocerca: string;
};

type EstadoOpcion = { value: string; label: string };
type Unidad = { vehiculo: string | null; ruta: string | null };

type Metricas = {
    total: number;
    enRuta: number;
    finalizadas: number;
    hoy: number;
    totalUnidades?: Unidad[];
    enRutaUnidades?: Unidad[];
    finalizadasUnidades?: Unidad[];
    hoyUnidades?: Unidad[];
};

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
    rutas: Hoja[];
    paginaMeta: PaginaMeta;
    filtros: Filtros;
    opciones: {
        placas: string[];
        conductores: string[];
        geocercas: string[];
        estados: EstadoOpcion[];
        porPagina: number[];
    };
    metricas: Metricas;
    /** Sin sesión (o sin el rol adecuado): la página se cubre con el
     *  formulario de acceso en vez de mostrar el listado real. */
    necesitaAcceso: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Rutas', href: rutasRoutes.index.url() }],
    },
});

// Exportar (Excel/PDF) también lo puede usar la cuenta compartida "tecavi"
// (rol "usuario"), no solo el admin.
const puedeExportar = computed(() => {
    const roles = usePage().props.auth.roles;

    return roles.includes('admin') || roles.includes('usuario');
});

// ─── Constantes de estilo ─────────────────────────────────────────────────────

const TH =
    'px-1.5 py-1.5 text-center text-xs font-semibold text-white uppercase';
const TD = 'px-1.5 py-1.5 text-center align-middle';

// Un ancho por cada una de las 12 columnas de la tabla (mismo orden que los <th>).
const ANCHOS_SKELETON = [
    '4rem',
    '4rem',
    '5rem',
    '5rem',
    '4rem',
    '3.5rem',
    '4rem',
    '3rem',
    '5rem',
    '3rem',
    '5rem',
    '4.5rem',
];

const DIF_CLASE: Record<string, string> = {
    pos: 'text-emerald-600 dark:text-emerald-400',
    neg: 'text-rose-600 dark:text-rose-400',
    cero: 'text-gray-500',
};

// ─── Estado reactivo ──────────────────────────────────────────────────────────

const cargando = ref(false);
const exportando = ref(false);
const mostrarFiltros = ref(
    Object.values(props.filtros).some((valor) => valor !== ''),
);
const seleccionada = ref<Hoja | null>(null);

const filtroConductor = ref(props.filtros.conductor);
const filtroPlaca = ref(props.filtros.placa);
const filtroId = ref(props.filtros.id);
const filtroDesde = ref(props.filtros.desde);
const filtroHasta = ref(props.filtros.hasta);
const filtroEstado = ref(props.filtros.estado);
const filtroGeocerca = ref(props.filtros.geocerca);

const filasPorPagina = ref(props.paginaMeta.porPagina);

// ─── Computed ─────────────────────────────────────────────────────────────────

const mostrandoSkeletonInicial = computed(
    () => cargando.value && props.rutas.length === 0,
);

const cantidadFiltrosActivos = computed(
    () =>
        [
            filtroConductor,
            filtroPlaca,
            filtroId,
            filtroDesde,
            filtroHasta,
            filtroEstado,
            filtroGeocerca,
        ].filter((filtro) => filtro.value !== '').length,
);
const filtrosActivos = computed(() => cantidadFiltrosActivos.value > 0);

const resumenFilas = computed(() => {
    const { totalRegistros, actual, porPagina } = props.paginaMeta;
    if (totalRegistros === 0) return '0 resultados';
    const ini = (actual - 1) * porPagina + 1;
    const fin = Math.min(actual * porPagina, totalRegistros);
    return `${ini}–${fin} de ${totalRegistros.toLocaleString()}`;
});

const paginasVisibles = computed((): (number | '...')[] => {
    const { actual, total } = props.paginaMeta;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    if (actual <= 4) return [1, 2, 3, 4, 5, '...', total];
    if (actual >= total - 3)
        return [1, '...', total - 4, total - 3, total - 2, total - 1, total];
    return [1, '...', actual - 1, actual, actual + 1, '...', total];
});

// ─── Navegación / recarga ─────────────────────────────────────────────────────

function query(pagina: number): Record<string, string | number> {
    return {
        conductor: filtroConductor.value,
        placa: filtroPlaca.value,
        id: filtroId.value,
        desde: filtroDesde.value,
        hasta: filtroHasta.value,
        estado: filtroEstado.value,
        geocerca: filtroGeocerca.value,
        porPagina: filasPorPagina.value,
        page: pagina,
    };
}

function recargar(pagina: number): void {
    cargando.value = true;
    seleccionada.value = null;
    router.get(rutasRoutes.index.url(), query(pagina), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['rutas', 'paginaMeta', 'filtros', 'metricas'],
        onFinish: () => {
            cargando.value = false;
        },
    });
}

function recargarDatos(): void {
    recargar(props.paginaMeta.actual);
}

function exportar(formato: 'excel' | 'pdf', hoja?: string | null): void {
    const params = hoja
        ? { hoja }
        : {
            conductor: filtroConductor.value,
            placa: filtroPlaca.value,
            id: filtroId.value,
            desde: filtroDesde.value,
            hasta: filtroHasta.value,
            estado: filtroEstado.value,
            geocerca: filtroGeocerca.value,
        };
    exportando.value = true;
    window.location.href = rutasRoutes.exportar[formato].url({ query: params });
    setTimeout(() => {
        exportando.value = false;
    }, 4000);
}

function limpiarFiltros(): void {
    [
        filtroConductor,
        filtroPlaca,
        filtroId,
        filtroDesde,
        filtroHasta,
        filtroEstado,
        filtroGeocerca,
    ].forEach((f) => {
        f.value = '';
    });
}

function truncar(texto: string | null, limite = 24): string {
    if (!texto) return '-';
    return texto.length > limite ? texto.slice(0, limite) + '…' : texto;
}

watch(
    [
        filtroConductor,
        filtroPlaca,
        filtroId,
        filtroDesde,
        filtroHasta,
        filtroEstado,
        filtroGeocerca,
        filasPorPagina,
    ],
    () => {
        if (_filterTimer !== null) clearTimeout(_filterTimer);
        _filterTimer = setTimeout(() => {
            _filterTimer = null;
            recargar(1);
        }, 350);
    },
);
</script>

<template>

    <Head title="Rutas" />

    <AccesoRutasModal v-if="props.necesitaAcceso" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-2 md:p-4">
        <div class="flex flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <!-- Cabecera -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-2.5">
                    <button type="button"
                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                        :aria-expanded="mostrarFiltros" @click="mostrarFiltros = !mostrarFiltros">
                        <Filter class="h-4 w-4" />
                        Filtros
                        <span v-if="cantidadFiltrosActivos"
                            class="rounded-full bg-[#b51927] px-1.5 text-[10px] leading-4 font-bold text-white">
                            {{ cantidadFiltrosActivos }}
                        </span>
                        <ChevronDown class="h-4 w-4 transition-transform" :class="mostrarFiltros ? 'rotate-180' : ''" />
                    </button>
                    <button v-if="cantidadFiltrosActivos" type="button"
                        class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="limpiarFiltros">
                        Limpiar filtros
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <DropdownMenu v-if="puedeExportar">
                        <DropdownMenuTrigger
                            class="inline-flex items-center gap-1.5 rounded-lg border border-green-300 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700 transition hover:bg-green-100 disabled:opacity-50 dark:border-green-700 dark:bg-green-900/30 dark:text-green-300 dark:hover:bg-green-900/50"
                            :disabled="exportando">
                            <Loader2 v-if="exportando" class="h-3.5 w-3.5 animate-spin text-[#b51927]" />
                            <FileSpreadsheet v-else class="h-3.5 w-3.5" />
                            {{ exportando ? 'Generando...' : 'Exportar' }}
                            <ChevronDown class="h-3.5 w-3.5" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem @click="exportar('excel')">
                                <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                                Excel (XLSX) (detallado)
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="exportar('pdf')">
                                <FileText class="h-4 w-4 text-[#b51927]" />
                                PDF (detallado)
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <button type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                        :disabled="cargando" @click="recargarDatos">
                        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': cargando }" />
                        Actualizar
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <Transition enter-active-class="transition duration-200 ease-out"
                leave-active-class="transition duration-150 ease-in" enter-from-class="-translate-y-1 opacity-0"
                leave-to-class="-translate-y-1 opacity-0">
                <div v-show="mostrarFiltros"
                    class="border-b border-gray-200 bg-white px-3 py-3 sm:px-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Conductor</label>
                            <ComboFilter v-model="filtroConductor" :options="opciones.conductores"
                                placeholder="Nombre" />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Placa</label>
                            <ComboFilter v-model="filtroPlaca" :options="opciones.placas" placeholder="Placa" />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">ID Hoja de Ruta</label>
                            <input v-model.trim="filtroId" type="text" placeholder="T000001"
                                class="h-8 w-full rounded-md border border-gray-300 px-2.5 text-sm transition-all duration-200 focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Estado</label>
                            <select v-model="filtroEstado"
                                class="h-8 w-full rounded-md border border-gray-300 px-2.5 text-sm transition-all duration-200 focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                <option value="">Todos</option>
                                <option v-for="opt in opciones.estados" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Geocerca</label>
                            <ComboFilter v-model="filtroGeocerca" :options="opciones.geocercas"
                                placeholder="Geocerca" />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Desde</label>
                            <input v-model="filtroDesde" type="date"
                                class="h-8 w-full rounded-md border border-gray-300 px-2.5 text-sm transition-all duration-200 focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label class="text-xs font-semibold text-gray-400 uppercase">Hasta</label>
                            <input v-model="filtroHasta" type="date"
                                class="h-8 w-full rounded-md border border-gray-300 px-2.5 text-sm transition-all duration-200 focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200" />
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Métricas -->
            <div
                class="grid grid-cols-2 gap-2 border-b border-gray-200 p-2 sm:gap-3 sm:p-3 md:grid-cols-4 dark:border-gray-700">
                <MetricaCard label="Hojas de Ruta" :valor="metricas.total" tono="slate" variante="solido"
                    :unidades="metricas.totalUnidades ?? []" alinear="izquierda" :cargando="mostrandoSkeletonInicial" />
                <MetricaCard label="En Ruta" :valor="metricas.enRuta" tono="blue" variante="solido"
                    :unidades="metricas.enRutaUnidades ?? []" alinear="derecha-izquierda"
                    :cargando="mostrandoSkeletonInicial" />
                <MetricaCard label="Finalizadas" :valor="metricas.finalizadas" tono="emerald" variante="solido"
                    :unidades="metricas.finalizadasUnidades ?? []" alinear="izquierda-derecha"
                    :cargando="mostrandoSkeletonInicial" />
                <MetricaCard label="Registradas Hoy" :valor="metricas.hoy" tono="amber" variante="solido"
                    :unidades="metricas.hoyUnidades ?? []" label-pequeno alinear="derecha"
                    :cargando="mostrandoSkeletonInicial" />
            </div>

            <!-- Tabla -->
            <div class="relative flex-1 overflow-x-auto bg-white dark:bg-gray-900">
                <div v-if="cargando && !mostrandoSkeletonInicial"
                    class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 dark:bg-gray-900/70">
                    <Loader2 class="h-8 w-8 animate-spin text-[#b51927]" />
                </div>

                <table class="w-full table-auto border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-[#b51927] bg-[#b51927]">
                            <th :class="TH">ID Hoja de Ruta</th>
                            <th :class="TH">Placa</th>
                            <th :class="TH">Conductor</th>
                            <th :class="TH">Copiloto</th>
                            <th :class="TH">Carreta</th>
                            <th :class="TH">Precintos</th>
                            <th :class="TH">Geocerca</th>
                            <th :class="TH">Km Inicial</th>
                            <th :class="TH">Fecha Hora Inicio</th>
                            <th :class="TH">Km Final</th>
                            <th :class="TH">Fecha Hora Final</th>
                            <th :class="TH">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="mostrandoSkeletonInicial">
                            <tr v-for="n in 8" :key="`skeleton-row-${n}`"
                                class="border-b border-gray-100 dark:border-gray-800">
                                <td v-for="(ancho, i) in ANCHOS_SKELETON" :key="i" class="px-1.5 py-1.5">
                                    <div class="mx-auto h-5 animate-pulse rounded-md bg-gray-200 dark:bg-gray-700"
                                        :style="{ width: ancho }" />
                                </td>
                            </tr>
                        </template>

                        <tr v-if="rutas.length === 0 && !cargando">
                            <td colspan="12" class="px-4 py-12 text-center">
                                <p class="text-sm font-medium text-gray-500">
                                    Sin resultados
                                </p>
                                <p v-if="filtrosActivos" class="mt-1 text-xs text-gray-400">
                                    No hay hojas de ruta que coincidan con los
                                    filtros aplicados.
                                </p>
                            </td>
                        </tr>

                        <tr v-for="item in rutas" :key="item.id"
                            class="cursor-pointer border-b border-gray-100 bg-white transition-colors hover:bg-[#b51927]/5 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800/50"
                            @click="seleccionada = item">
                            <td :class="[
                                TD,
                                'font-mono font-semibold text-[#b51927]',
                            ]">
                                {{ item.hoja ?? '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'font-semibold tracking-wide text-gray-900 dark:text-gray-100',
                            ]">
                                {{ item.placa?.trim() || '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-700 dark:text-gray-300',
                            ]">
                                <span :title="item.conductor ?? undefined">{{
                                    truncar(item.conductor)
                                    }}</span>
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                <span :title="item.copiloto ?? undefined">{{
                                    truncar(item.copiloto)
                                    }}</span>
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.carreta?.trim() || '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.precintos?.trim() || '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                <span :title="item.geocerca ?? undefined">{{
                                    truncar(item.geocerca, 18)
                                    }}</span>
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.km_inicial ?? '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'whitespace-nowrap text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.fh_inicio ?? '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.km_final ?? '-' }}
                            </td>
                            <td :class="[
                                TD,
                                'whitespace-nowrap text-gray-600 dark:text-gray-400',
                            ]">
                                {{ item.fh_final ?? '-' }}
                            </td>
                            <td :class="TD">
                                <EstadoBadge :estado="item.estado" :label="item.estado_label" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div
                class="grid grid-cols-2 items-center gap-x-2 gap-y-1.5 border-t border-gray-200 bg-gray-50 px-4 py-2.5 sm:grid-cols-3 sm:gap-2 dark:border-gray-700 dark:bg-gray-800">
                <div class="order-2 flex items-center gap-2 sm:order-1">
                    <label class="text-sm text-gray-500">Filas:</label>
                    <select v-model.number="filasPorPagina"
                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        <option v-for="n in opciones.porPagina" :key="n" :value="n">
                            {{ n }}
                        </option>
                    </select>
                </div>
                <div class="order-1 col-span-2 flex items-center justify-center gap-1 sm:order-2 sm:col-span-1">
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual <= 1 || cargando" title="Primera página" @click="recargar(1)">
                        <ChevronsLeft class="h-4 w-4" />
                    </button>
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual <= 1 || cargando" title="Página anterior"
                        @click="recargar(paginaMeta.actual - 1)">
                        <ChevronLeft class="h-4 w-4" />
                    </button>

                    <template v-for="(p, i) in paginasVisibles" :key="p === '...' ? `e${i}` : p">
                        <span v-if="p === '...'" class="px-1 text-sm text-gray-400">…</span>
                        <button v-else
                            class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md border px-1.5 text-sm transition"
                            :class="p === paginaMeta.actual
                                    ? 'border-[#b51927] bg-[#b51927] font-semibold text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                                " :disabled="cargando" @click="recargar(p as number)">
                            {{ p }}
                        </button>
                    </template>

                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual >= paginaMeta.total || cargando
                            " title="Página siguiente" @click="recargar(paginaMeta.actual + 1)">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual >= paginaMeta.total || cargando
                            " title="Última página" @click="recargar(paginaMeta.total)">
                        <ChevronsRight class="h-4 w-4" />
                    </button>
                </div>
                <p class="order-3 text-right text-sm text-gray-500">
                    {{ resumenFilas }}
                </p>
            </div>
        </div>
    </div>

    <!-- Modal detalle de la hoja de ruta -->
    <Dialog :open="seleccionada !== null" @update:open="(v: boolean) => !v && (seleccionada = null)">
        <DialogContent class="max-h-[88vh] w-[min(96vw,1400px)] max-w-[96vw] overflow-y-auto sm:max-w-[1400px]">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-3">
                    <span class="font-mono text-[#b51927]">
                        {{ seleccionada?.hoja }}
                    </span>
                    <EstadoBadge v-if="seleccionada" :estado="seleccionada.estado" :label="seleccionada.estado_label" />
                </DialogTitle>
            </DialogHeader>

            <!-- Exportar esta hoja de ruta (al costado de la "X") -->
            <DropdownMenu v-if="puedeExportar">
                <DropdownMenuTrigger
                    class="absolute top-3.5 right-12 inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    :disabled="exportando" title="Exportar esta hoja de ruta">
                    <Loader2 v-if="exportando" class="h-3.5 w-3.5 animate-spin text-[#b51927]" />
                    <FileSpreadsheet v-else class="h-3.5 w-3.5" />
                    <ChevronDown class="h-3 w-3" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuItem @click="exportar('excel', seleccionada?.hoja)">
                        <FileSpreadsheet class="h-4 w-4 text-emerald-600" />
                        Excel (XLSX)
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="exportar('pdf', seleccionada?.hoja)">
                        <FileText class="h-4 w-4 text-[#b51927]" />
                        PDF (A4)
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div v-if="seleccionada" class="flex min-w-0 flex-col gap-4">
                <!-- Detalle de la hoja de ruta: una fila por TRAMO (par de
                     paradas N/N+1) -- una hoja con varios tramos se ve
                     completa acá, no solo el resumen inicial/final. -->
                <div class="min-w-0 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full table-auto text-xs whitespace-nowrap">
                        <thead>
                            <tr
                                class="bg-gray-50 text-left text-[10px] font-semibold text-gray-500 uppercase dark:bg-gray-800">
                                <th
                                    class="sticky left-0 z-20 bg-gray-50 px-2.5 py-2 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)] dark:bg-gray-800">
                                    Conductor
                                </th>
                                <th class="px-2.5 py-2">Copiloto</th>
                                <th class="px-2.5 py-2">Placa</th>
                                <th class="px-2.5 py-2">Carreta</th>
                                <th class="px-2.5 py-2">Precintos</th>
                                <th class="px-2.5 py-2">Geocerca Inicial</th>
                                <th class="px-2.5 py-2">Coordenada Inicial</th>
                                <th class="px-2.5 py-2">
                                    Fecha Inicial Sistema
                                </th>
                                <th class="px-2.5 py-2">
                                    Fecha Inicial Conductor
                                </th>
                                <th class="px-2.5 py-2">Km Inicial</th>
                                <th class="px-2.5 py-2">Diferencia Inicial</th>
                                <th class="px-2.5 py-2">Geocerca Final</th>
                                <th class="px-2.5 py-2">Coordenada Final</th>
                                <th class="px-2.5 py-2">Fecha Final Sistema</th>
                                <th class="px-2.5 py-2">
                                    Fecha Final Conductor
                                </th>
                                <th class="px-2.5 py-2">Km Final</th>
                                <th class="px-2.5 py-2">Diferencia Final</th>
                                <th class="px-2.5 py-2">Observación</th>
                                <th class="px-2.5 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in seleccionada.tramos" :key="t.tramo"
                                class="text-gray-700 dark:text-gray-200 [&>td]:px-2.5 [&>td]:py-2">
                                <td
                                    class="sticky left-0 z-20 bg-white font-semibold shadow-[2px_0_5px_-2px_rgba(0,0,0,0.15)] dark:bg-gray-900">
                                    {{ t.conductor ?? '-' }}
                                </td>
                                <td>{{ t.copiloto ?? '-' }}</td>
                                <td>{{ t.placa ?? '-' }}</td>
                                <td>{{ t.carreta ?? '-' }}</td>
                                <td>{{ t.precintos ?? '-' }}</td>
                                <td>{{ t.geocerca ?? '-' }}</td>
                                <td class="font-mono">
                                    {{ t.coordenada ?? '-' }}
                                </td>
                                <td>{{ t.sis_inicial ?? '-' }}</td>
                                <td>{{ t.cond_inicial ?? '-' }}</td>
                                <td>{{ t.km_inicial ?? '-' }}</td>
                                <td class="font-semibold" :class="t.dif_inicial
                                        ? DIF_CLASE[t.dif_inicial.signo]
                                        : 'text-gray-400'
                                    ">
                                    {{ t.dif_inicial?.texto ?? '-' }}
                                </td>
                                <td>
                                    {{ t.geocerca_final ?? '-' }}
                                </td>
                                <td class="font-mono">
                                    {{ t.coordenada_final ?? '-' }}
                                </td>
                                <td>{{ t.sis_final ?? '-' }}</td>
                                <td>{{ t.cond_final ?? '-' }}</td>
                                <td>{{ t.km_final ?? '-' }}</td>
                                <td class="font-semibold" :class="t.dif_final
                                        ? DIF_CLASE[t.dif_final.signo]
                                        : 'text-gray-400'
                                    ">
                                    {{ t.dif_final?.texto ?? '-' }}
                                </td>
                                <td>
                                    <span :title="t.observacion ?? undefined">
                                        {{ truncar(t.observacion, 100) }}
                                    </span>
                                </td>
                                <td>
                                    <EstadoBadge :estado="t.estado" :label="t.estado_label" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="-mt-2 text-[11px] text-gray-400">
                    Desliza la tabla horizontalmente para ver todas las
                    columnas.
                </p>

                <!-- Documentos adjuntos -->
                <div class="min-w-0">
                    <p
                        class="mb-1.5 flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                        <Paperclip class="h-3.5 w-3.5" />
                        Documentos adjuntos
                        <span class="text-gray-400">
                            ({{ seleccionada.documentos.length }})
                        </span>
                    </p>
                    <div v-if="seleccionada.documentos.length > 0"
                        class="min-w-0 overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full table-auto text-xs">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] text-gray-500 uppercase dark:bg-gray-800">
                                    <th class="px-2 py-1.5 text-left">Tipo</th>
                                    <th class="px-2 py-1.5 text-left">
                                        Documento
                                    </th>
                                    <th class="px-2 py-1.5 text-left">
                                        Producto
                                    </th>
                                    <th class="px-2 py-1.5 text-center">
                                        Cant.
                                    </th>
                                    <th class="px-2 py-1.5 text-center">
                                        P. Neto
                                    </th>
                                    <th class="px-2 py-1.5 text-center">
                                        P. Bruto
                                    </th>
                                    <th class="px-2 py-1.5 text-left">
                                        Observación
                                    </th>
                                    <th class="px-2 py-1.5 text-center">
                                        Imagen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="(doc, i) in seleccionada.documentos" :key="i">
                                    <td class="px-2 py-1.5">
                                        {{ doc.tipo ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{ doc.documento ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{ doc.producto ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        {{ doc.cantidad ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        {{ doc.peso_neto ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        {{ doc.peso_bruto ?? '-' }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <span :title="doc.observacion ?? undefined">
                                            {{ truncar(doc.observacion, 100) }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <a v-if="doc.imagen" :href="doc.imagen" target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-semibold text-[#b51927] underline underline-offset-2 hover:no-underline">
                                            Ver imagen
                                        </a>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else
                        class="rounded-lg border border-dashed border-gray-200 py-3 text-center text-xs text-gray-400 dark:border-gray-700">
                        Sin documentos adjuntos.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
