<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import CamposMultiples from '@/components/CamposMultiples.vue';
import ComboFilter from '@/components/ComboFilter.vue';
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
import contactosRoutes from '@/routes/modulos/contactos';

type Contacto = {
    id: number;
    geocerca: string | null;
    nombre: string | null;
    correo: string[] | null;
    telefonos: string[] | null;
};

type PaginaMeta = {
    actual: number;
    total: number;
    porPagina: number;
    totalRegistros: number;
};

const props = defineProps<{
    contactos: Contacto[];
    paginaMeta: PaginaMeta;
    filtros: { buscar: string };
    opciones: {
        porPagina: number[];
        geocercas: string[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Contactos', href: contactosRoutes.index.url() },
        ],
    },
});

const TH =
    'px-3 py-2 text-left text-xs font-semibold tracking-wide text-white uppercase';
const TD = 'px-3 py-2 whitespace-nowrap text-gray-700 dark:text-gray-200';

const cargando = ref(false);
const buscar = ref(props.filtros.buscar);
const filasPorPagina = ref(props.paginaMeta.porPagina);

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

function query(pagina: number): Record<string, string | number> {
    return {
        buscar: buscar.value,
        porPagina: filasPorPagina.value,
        page: pagina,
    };
}

function recargar(pagina: number): void {
    cargando.value = true;
    router.get(contactosRoutes.index.url(), query(pagina), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['contactos', 'paginaMeta', 'filtros'],
        onFinish: () => {
            cargando.value = false;
        },
    });
}

let debounce: ReturnType<typeof setTimeout> | null = null;
watch([buscar, filasPorPagina], () => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => {
        debounce = null;
        recargar(1);
    }, 350);
});

const dialogAbierto = ref(false);
const editando = ref<number | null>(null);

const form = useForm({
    geocerca: '',
    nombre: '',
    correo: [] as string[],
    telefonos: [] as string[],
});

function abrirCrear(): void {
    editando.value = null;
    form.clearErrors();
    form.defaults({ geocerca: '', nombre: '', correo: [], telefonos: [] });
    form.reset();
    dialogAbierto.value = true;
}

function abrirEditar(contacto: Contacto): void {
    editando.value = contacto.id;
    form.clearErrors();
    form.defaults({
        geocerca: contacto.geocerca ?? '',
        nombre: contacto.nombre ?? '',
        correo: [...(contacto.correo ?? [])],
        telefonos: [...(contacto.telefonos ?? [])],
    });
    form.reset();
    dialogAbierto.value = true;
}

/** Errores de validación por item (`correo.0`, `telefonos.1`, …). */
function erroresLista(campo: 'correo' | 'telefonos'): string[] {
    const errores = form.errors as Record<string, string>;
    return Object.keys(errores)
        .filter((clave) => clave.startsWith(`${campo}.`))
        .map((clave) => errores[clave]);
}

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            dialogAbierto.value = false;
        },
    };

    if (editando.value === null) {
        form.post(contactosRoutes.store.url(), opciones);
    } else {
        form.put(contactosRoutes.update.url(editando.value), opciones);
    }
}

const confirmandoBorrado = reactive<{ contacto: Contacto | null }>({
    contacto: null,
});

function eliminar(): void {
    if (!confirmandoBorrado.contacto) {
        return;
    }
    router.delete(contactosRoutes.destroy.url(confirmandoBorrado.contacto.id), {
        preserveScroll: true,
        onFinish: () => {
            confirmandoBorrado.contacto = null;
        },
    });
}
</script>

<template>
    <Head title="Contactos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-2 md:p-4">
        <div
            class="flex flex-1 flex-col overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-800"
            >
                <Input
                    v-model="buscar"
                    type="search"
                    placeholder="Buscar por nombre, geocerca, correo o teléfono"
                    class="h-9 max-w-sm"
                />
                <Button
                    class="gap-1.5 bg-[#b51927] hover:bg-[#9a1521]"
                    @click="abrirCrear"
                >
                    <Plus class="h-4 w-4" />
                    Nuevo contacto
                </Button>
            </div>

            <div class="flex-1 overflow-x-auto bg-white dark:bg-gray-900">
                <table class="w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr class="bg-[#b51927]">
                            <th :class="TH">Nombre</th>
                            <th :class="TH">Geocerca</th>
                            <th :class="TH">Correo</th>
                            <th :class="TH">Teléfonos</th>
                            <th :class="[TH, 'text-right']">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="contacto in contactos"
                            :key="contacto.id"
                            class="border-b border-gray-100 odd:bg-white even:bg-gray-50/60 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-800/40"
                        >
                            <td :class="[TD, 'font-medium']">
                                {{ contacto.nombre ?? '—' }}
                            </td>
                            <td :class="TD">{{ contacto.geocerca ?? '—' }}</td>
                            <td :class="[TD, 'whitespace-normal']">
                                <div
                                    v-if="contacto.correo?.length"
                                    class="flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="c in contacto.correo"
                                        :key="c"
                                        class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800"
                                    >
                                        {{ c }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td :class="[TD, 'whitespace-normal']">
                                <div
                                    v-if="contacto.telefonos?.length"
                                    class="flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="t in contacto.telefonos"
                                        :key="t"
                                        class="rounded bg-gray-100 px-1.5 py-0.5 text-xs dark:bg-gray-800"
                                    >
                                        {{ t }}
                                    </span>
                                </div>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td :class="[TD, 'text-right']">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        @click="abrirEditar(contacto)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-red-600 hover:text-red-700"
                                        @click="
                                            confirmandoBorrado.contacto =
                                                contacto
                                        "
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="contactos.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-10 text-center text-sm text-gray-400"
                            >
                                No hay contactos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación (mismo estilo que Rutas) -->
            <div
                class="grid grid-cols-2 items-center gap-x-2 gap-y-1.5 border-t border-gray-200 bg-gray-50 px-4 py-2.5 sm:grid-cols-3 sm:gap-2 dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="order-2 flex items-center gap-2 sm:order-1">
                    <label class="text-sm text-gray-500">Filas:</label>
                    <select
                        v-model.number="filasPorPagina"
                        class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >
                        <option
                            v-for="n in opciones.porPagina"
                            :key="n"
                            :value="n"
                        >
                            {{ n }}
                        </option>
                    </select>
                </div>
                <div
                    class="order-1 col-span-2 flex items-center justify-center gap-1 sm:order-2 sm:col-span-1"
                >
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual <= 1 || cargando"
                        title="Primera página"
                        @click="recargar(1)"
                    >
                        <ChevronsLeft class="h-4 w-4" />
                    </button>
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="paginaMeta.actual <= 1 || cargando"
                        title="Página anterior"
                        @click="recargar(paginaMeta.actual - 1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>

                    <template
                        v-for="(p, i) in paginasVisibles"
                        :key="p === '...' ? `e${i}` : p"
                    >
                        <span
                            v-if="p === '...'"
                            class="px-1 text-sm text-gray-400"
                            >…</span
                        >
                        <button
                            v-else
                            class="inline-flex h-7 min-w-[28px] items-center justify-center rounded-md border px-1.5 text-sm transition"
                            :class="
                                p === paginaMeta.actual
                                    ? 'border-[#b51927] bg-[#b51927] font-semibold text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                            "
                            :disabled="cargando"
                            @click="recargar(p as number)"
                        >
                            {{ p }}
                        </button>
                    </template>

                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="
                            paginaMeta.actual >= paginaMeta.total || cargando
                        "
                        title="Página siguiente"
                        @click="recargar(paginaMeta.actual + 1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                    <button
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white p-1.5 transition hover:bg-gray-100 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                        :disabled="
                            paginaMeta.actual >= paginaMeta.total || cargando
                        "
                        title="Última página"
                        @click="recargar(paginaMeta.total)"
                    >
                        <ChevronsRight class="h-4 w-4" />
                    </button>
                </div>
                <p class="order-3 text-right text-sm text-gray-500">
                    {{ resumenFilas }}
                </p>
            </div>
        </div>
    </div>

    <!-- Modal crear / editar -->
    <Dialog v-model:open="dialogAbierto">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>
                    {{
                        editando === null ? 'Nuevo contacto' : 'Editar contacto'
                    }}
                </DialogTitle>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="guardar">
                <div class="grid gap-1.5">
                    <Label for="nombre">Nombre</Label>
                    <Input id="nombre" v-model="form.nombre" required />
                    <InputError :message="form.errors.nombre" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Geocerca</Label>
                    <ComboFilter
                        v-model="form.geocerca"
                        :options="opciones.geocercas"
                        placeholder="Selecciona o escribe una geocerca"
                    />
                    <InputError :message="form.errors.geocerca" />
                </div>
                <div class="grid gap-1.5">
                    <CamposMultiples
                        v-model="form.correo"
                        label="Correos"
                        type="email"
                        placeholder="correo@ejemplo.com"
                        add-label="Agregar correo"
                    />
                    <InputError :message="form.errors.correo" />
                    <InputError
                        v-for="(msg, key) in erroresLista('correo')"
                        :key="key"
                        :message="msg"
                    />
                </div>
                <div class="grid gap-1.5">
                    <CamposMultiples
                        v-model="form.telefonos"
                        label="Teléfonos"
                        type="tel"
                        placeholder="999888777"
                        add-label="Agregar teléfono"
                    />
                    <InputError :message="form.errors.telefonos" />
                    <InputError
                        v-for="(msg, key) in erroresLista('telefonos')"
                        :key="key"
                        :message="msg"
                    />
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
                        :disabled="form.processing"
                    >
                        Guardar
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Confirmación de borrado -->
    <Dialog
        :open="confirmandoBorrado.contacto !== null"
        @update:open="
            (v: boolean) => !v && (confirmandoBorrado.contacto = null)
        "
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Eliminar contacto</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                ¿Seguro que deseas eliminar a
                <strong>{{ confirmandoBorrado.contacto?.nombre }}</strong
                >? Esta acción no se puede deshacer.
            </p>
            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    @click="confirmandoBorrado.contacto = null"
                >
                    Cancelar
                </Button>
                <Button type="button" variant="destructive" @click="eliminar">
                    Eliminar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
