<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    FlaskConical,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import EstadoBadge from '@/components/EstadoBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import gestion from '@/routes/modulos/rutas/gestion';
import rutasRoutes from '@/routes/modulos/rutas';

type Detalle = {
    id: number;
    orden: number | null;
    contacto_idcontacto: number;
    geocerca: string | null;
    coordenada: string | null;
    kilometraje: string | null;
    fhRegistro: string | null;
    fhIndicado: string | null;
    observacion: string | null;
    estado: string;
    estado_label: string;
};

type HojaRuta = {
    idruta: string;
    placa: string | null;
    piloto: string | null;
    copiloto: string | null;
    precintos: string | null;
    carreta: string | null;
    estado: string | null;
    detalles: Detalle[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Paginador = {
    data: HojaRuta[];
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
};

type Contacto = {
    idcontacto: number;
    nombre: string | null;
    geocerca: string | null;
};

const props = defineProps<{
    rutas: Paginador;
    filtros: { buscar: string };
    contactos: Contacto[];
    proximoCodigo: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Rutas', href: rutasRoutes.index.url() },
            { title: 'Pruebas', href: rutasRoutes.gestion.url() },
        ],
    },
});

const INPUT =
    'h-9 w-full rounded-lg border border-gray-300 px-2.5 text-sm focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200';

// ─── Búsqueda ─────────────────────────────────────────────────────────────────
const buscar = ref(props.filtros.buscar);
let debounce: ReturnType<typeof setTimeout> | null = null;
watch(buscar, (valor) => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            rutasRoutes.gestion.url(),
            { buscar: valor },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});

// ─── Expandir ─────────────────────────────────────────────────────────────────
const expandida = ref<string | null>(null);
function toggle(idruta: string): void {
    expandida.value = expandida.value === idruta ? null : idruta;
    ordenForm.reset();
    ordenForm.clearErrors();
}

// ─── Modal crear / editar hoja de ruta ────────────────────────────────────────
const dialogAbierto = ref(false);
const editando = ref<string | null>(null);

const rutaForm = useForm({
    placa: '',
    piloto: '',
    copiloto: '',
    precintos: '',
    carreta: '',
});

function abrirCrear(): void {
    editando.value = null;
    rutaForm.defaults({
        placa: '',
        piloto: '',
        copiloto: '',
        precintos: '',
        carreta: '',
    });
    rutaForm.reset();
    rutaForm.clearErrors();
    dialogAbierto.value = true;
}

function abrirEditar(ruta: HojaRuta): void {
    editando.value = ruta.idruta;
    rutaForm.defaults({
        placa: ruta.placa ?? '',
        piloto: ruta.piloto ?? '',
        copiloto: ruta.copiloto ?? '',
        precintos: ruta.precintos ?? '',
        carreta: ruta.carreta ?? '',
    });
    rutaForm.reset();
    rutaForm.clearErrors();
    dialogAbierto.value = true;
}

function guardarRuta(): void {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            dialogAbierto.value = false;
        },
    };
    if (editando.value === null) {
        rutaForm.post(gestion.store.url(), opts);
    } else {
        rutaForm.put(gestion.update.url(editando.value), opts);
    }
}

function eliminarRuta(ruta: HojaRuta): void {
    if (
        !confirm(
            `¿Eliminar la hoja de ruta ${ruta.idruta} y sus ${ruta.detalles.length} orden(es)?`,
        )
    ) {
        return;
    }
    router.delete(gestion.destroy.url(ruta.idruta), { preserveScroll: true });
}

// ─── Registrar orden ──────────────────────────────────────────────────────────
const ordenForm = useForm({
    contacto_idcontacto: '' as number | '',
    kilometraje: '',
    geocerca: '',
    coordenada: '',
    fhRegistro: '',
    fhIndicado: '',
    observacion: '',
});

function registrarOrden(idruta: string): void {
    ordenForm
        .transform((data) => ({
            ...data,
            contacto_idcontacto: data.contacto_idcontacto || null,
        }))
        .post(gestion.detalles.store.url(idruta), {
            preserveScroll: true,
            onSuccess: () => {
                ordenForm.reset();
            },
        });
}

function eliminarOrden(id: number): void {
    router.delete(gestion.detalles.destroy.url(id), { preserveScroll: true });
}

function nombreContacto(id: number): string {
    return props.contactos.find((c) => c.idcontacto === id)?.nombre ?? `#${id}`;
}
</script>

<template>
    <Head title="Rutas · Pruebas" />

    <div class="flex min-h-full flex-col gap-4 p-4">
        <!-- Hero -->
        <div class="flex items-center gap-3">
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#b51927] shadow-md shadow-[#b51927]/25"
            >
                <FlaskConical class="h-5 w-5 text-white" />
            </div>
            <div>
                <h1
                    class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100"
                >
                    Gestión de pruebas — Rutas
                </h1>
                <p class="text-xs text-gray-400">
                    Crea hojas de ruta y registra órdenes para probar la
                    transición EN RUTA → FINALIZADO.
                </p>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Input
                v-model="buscar"
                type="search"
                placeholder="Buscar por código, placa o conductor"
                class="h-9 max-w-sm"
            />
            <Button
                class="gap-1.5 bg-[#b51927] hover:bg-[#9a1521]"
                @click="abrirCrear"
            >
                <Plus class="h-4 w-4" />
                Nueva hoja de ruta
                <span
                    class="ml-1 rounded bg-white/20 px-1.5 py-0.5 font-mono text-[11px]"
                >
                    {{ proximoCodigo }}
                </span>
            </Button>
        </div>

        <!-- Lista -->
        <div class="flex flex-col gap-2">
            <div
                v-for="ruta in rutas.data"
                :key="ruta.idruta"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
            >
                <!-- Cabecera de la hoja -->
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
                >
                    <button
                        type="button"
                        class="flex items-center gap-2"
                        @click="toggle(ruta.idruta)"
                    >
                        <component
                            :is="
                                expandida === ruta.idruta
                                    ? ChevronUp
                                    : ChevronDown
                            "
                            class="h-4 w-4 text-gray-400"
                        />
                        <span
                            class="font-mono text-sm font-bold text-[#b51927]"
                        >
                            {{ ruta.idruta }}
                        </span>
                    </button>
                    <span
                        class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                    >
                        {{ ruta.placa ?? '—' }}
                    </span>
                    <span class="text-sm text-gray-500">
                        {{ ruta.piloto ?? '—' }}
                        <template v-if="ruta.copiloto">
                            / {{ ruta.copiloto }}
                        </template>
                    </span>
                    <span
                        class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ ruta.detalles.length }} orden(es)
                    </span>
                    <div class="ml-auto flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click="abrirEditar(ruta)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-red-600 hover:text-red-700"
                            @click="eliminarRuta(ruta)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Órdenes -->
                <div
                    v-if="expandida === ruta.idruta"
                    class="border-t border-gray-100 bg-gray-50/60 px-4 py-3 dark:border-gray-800 dark:bg-gray-800/40"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full table-auto text-xs">
                            <thead>
                                <tr class="text-gray-400 uppercase">
                                    <th class="px-2 py-1 text-left">
                                        N° Detalle
                                    </th>
                                    <th class="px-2 py-1 text-center">Orden</th>
                                    <th class="px-2 py-1 text-left">
                                        Contacto
                                    </th>
                                    <th class="px-2 py-1 text-left">
                                        Geocerca
                                    </th>
                                    <th class="px-2 py-1 text-center">Km</th>
                                    <th class="px-2 py-1 text-center">
                                        F.H. Registro
                                    </th>
                                    <th class="px-2 py-1 text-center">
                                        F.H. Indicada
                                    </th>
                                    <th class="px-2 py-1 text-center">
                                        Estado
                                    </th>
                                    <th class="px-2 py-1"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="d in ruta.detalles"
                                    :key="d.id"
                                    class="border-t border-gray-200 dark:border-gray-700"
                                >
                                    <td
                                        class="px-2 py-1.5 font-mono text-gray-500"
                                    >
                                        {{ d.id }}
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-center font-bold"
                                    >
                                        {{ d.orden }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{
                                            nombreContacto(
                                                d.contacto_idcontacto,
                                            )
                                        }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        {{ d.geocerca ?? '—' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        {{ d.kilometraje ?? '—' }}
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-center whitespace-nowrap"
                                    >
                                        {{ d.fhRegistro ?? '—' }}
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-center whitespace-nowrap"
                                    >
                                        {{ d.fhIndicado ?? '—' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        <EstadoBadge
                                            :estado="d.estado"
                                            :label="d.estado_label"
                                        />
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-700"
                                            @click="eliminarOrden(d.id)"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="ruta.detalles.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-2 py-4 text-center text-gray-400"
                                    >
                                        Sin órdenes. Registra el primero abajo.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Form registrar orden -->
                    <form
                        class="mt-3 grid grid-cols-2 gap-2 rounded-lg border border-gray-200 bg-white p-3 sm:grid-cols-3 lg:grid-cols-4 dark:border-gray-700 dark:bg-gray-900"
                        @submit.prevent="registrarOrden(ruta.idruta)"
                    >
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >Contacto *</label
                            >
                            <select
                                v-model="ordenForm.contacto_idcontacto"
                                :class="INPUT"
                            >
                                <option value="">Seleccione…</option>
                                <option
                                    v-for="c in contactos"
                                    :key="c.idcontacto"
                                    :value="c.idcontacto"
                                >
                                    {{ c.nombre }}
                                </option>
                            </select>
                            <InputError
                                :message="ordenForm.errors.contacto_idcontacto"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >Kilometraje</label
                            >
                            <input
                                v-model="ordenForm.kilometraje"
                                type="text"
                                inputmode="numeric"
                                :class="INPUT"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >Geocerca</label
                            >
                            <input
                                v-model="ordenForm.geocerca"
                                type="text"
                                :class="INPUT"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >Coordenada</label
                            >
                            <input
                                v-model="ordenForm.coordenada"
                                type="text"
                                placeholder="-12.04, -77.03"
                                :class="INPUT"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >F.H. Registro</label
                            >
                            <input
                                v-model="ordenForm.fhRegistro"
                                type="datetime-local"
                                :class="INPUT"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >F.H. Indicada</label
                            >
                            <input
                                v-model="ordenForm.fhIndicado"
                                type="datetime-local"
                                :class="INPUT"
                            />
                        </div>
                        <div class="flex flex-col gap-0.5 sm:col-span-2">
                            <label
                                class="text-[11px] font-bold text-gray-400 uppercase"
                                >Observación</label
                            >
                            <input
                                v-model="ordenForm.observacion"
                                type="text"
                                :class="INPUT"
                            />
                        </div>
                        <div
                            class="col-span-2 flex items-end sm:col-span-3 lg:col-span-4"
                        >
                            <Button
                                type="submit"
                                class="ml-auto gap-1.5 bg-[#b51927] hover:bg-[#9a1521]"
                                :disabled="ordenForm.processing"
                            >
                                <Plus class="h-4 w-4" />
                                Registrar orden {{ ruta.detalles.length + 1 }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>

            <p
                v-if="rutas.data.length === 0"
                class="rounded-xl border border-dashed border-gray-200 py-12 text-center text-sm text-gray-400 dark:border-gray-700"
            >
                No hay hojas de ruta. Crea la primera con el botón de arriba.
            </p>
        </div>

        <!-- Paginación -->
        <div
            v-if="rutas.links.length > 3"
            class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500"
        >
            <span
                >{{ rutas.from ?? 0 }}–{{ rutas.to ?? 0 }} de
                {{ rutas.total }}</span
            >
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="link in rutas.links"
                    :key="link.label"
                    type="button"
                    :disabled="!link.url"
                    class="min-w-8 rounded-md border px-2 py-1 transition disabled:opacity-40"
                    :class="
                        link.active
                            ? 'border-[#b51927] bg-[#b51927] text-white'
                            : 'border-gray-300 bg-white hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-900 dark:hover:bg-gray-700'
                    "
                    @click="
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    "
                >
                    {{
                        link.label
                            .replace(/<[^>]*>/g, '')
                            .replace(/&laquo;/g, '«')
                            .replace(/&raquo;/g, '»')
                            .trim()
                    }}
                </button>
            </div>
        </div>
    </div>

    <!-- Modal hoja de ruta -->
    <Dialog v-model:open="dialogAbierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editando === null
                            ? `Nueva hoja de ruta · ${proximoCodigo}`
                            : `Editar ${editando}`
                    }}
                </DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="guardarRuta">
                <div class="grid gap-1.5">
                    <Label for="placa">Placa *</Label>
                    <Input id="placa" v-model="rutaForm.placa" required />
                    <InputError :message="rutaForm.errors.placa" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="piloto">Conductor *</Label>
                    <Input id="piloto" v-model="rutaForm.piloto" required />
                    <InputError :message="rutaForm.errors.piloto" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="copiloto">Copiloto</Label>
                    <Input id="copiloto" v-model="rutaForm.copiloto" />
                    <InputError :message="rutaForm.errors.copiloto" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="precintos">Precintos</Label>
                        <Input id="precintos" v-model="rutaForm.precintos" />
                        <InputError :message="rutaForm.errors.precintos" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="carreta">Carreta</Label>
                        <Input id="carreta" v-model="rutaForm.carreta" />
                        <InputError :message="rutaForm.errors.carreta" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="dialogAbierto = false"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        class="bg-[#b51927] hover:bg-[#9a1521]"
                        :disabled="rutaForm.processing"
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
