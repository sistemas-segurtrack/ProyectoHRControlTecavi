<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    LockKeyhole,
    Palette,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { edit as editSecurity } from '@/routes/security';
import type { User } from '@/types';

type SeccionConfiguracion = 'cuenta' | 'seguridad' | 'apariencia';

defineProps<{
    user: User;
}>();

const isOpen = defineModel<boolean>('open', { default: false });
const seccionActiva = ref<SeccionConfiguracion>('cuenta');

watch(isOpen, (abierto) => {
    if (abierto) {
        seccionActiva.value = 'cuenta';
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent
            class="max-h-[calc(100svh-2rem)] gap-0 overflow-hidden p-0 sm:max-w-3xl"
        >
            <DialogHeader class="border-b px-6 py-5 pr-12">
                <DialogTitle>Configuración</DialogTitle>
                <DialogDescription>
                    Consulta tu cuenta y administra las opciones disponibles.
                </DialogDescription>
            </DialogHeader>

            <div
                class="grid min-h-0 sm:min-h-96 sm:grid-cols-[12rem_minmax(0,1fr)]"
            >
                <nav
                    class="bg-muted/30 flex gap-1 overflow-x-auto border-b p-2 sm:flex-col sm:border-r sm:border-b-0 sm:p-3"
                    aria-label="Secciones de configuración"
                >
                    <button
                        type="button"
                        class="flex min-w-max items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:w-full"
                        :class="
                            seccionActiva === 'cuenta'
                                ? 'bg-[#b51927]/10 text-[#b51927] dark:bg-[#b51927]/20 dark:text-[#f06a76]'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="seccionActiva = 'cuenta'"
                    >
                        <UserRound class="size-4" />
                        Cuenta
                    </button>
                    <button
                        type="button"
                        class="flex min-w-max items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:w-full"
                        :class="
                            seccionActiva === 'seguridad'
                                ? 'bg-[#b51927]/10 text-[#b51927] dark:bg-[#b51927]/20 dark:text-[#f06a76]'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="seccionActiva = 'seguridad'"
                    >
                        <ShieldCheck class="size-4" />
                        Seguridad
                    </button>
                    <button
                        type="button"
                        class="flex min-w-max items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors sm:w-full"
                        :class="
                            seccionActiva === 'apariencia'
                                ? 'bg-[#b51927]/10 text-[#b51927] dark:bg-[#b51927]/20 dark:text-[#f06a76]'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        @click="seccionActiva = 'apariencia'"
                    >
                        <Palette class="size-4" />
                        Apariencia
                    </button>
                </nav>

                <div
                    class="min-h-80 overflow-y-auto p-5 sm:max-h-[calc(100svh-10rem)] sm:p-6"
                >
                    <section
                        v-if="seccionActiva === 'cuenta'"
                        class="space-y-5"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">Cuenta</h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Información asociada a tu usuario.
                            </p>
                        </div>

                        <div class="divide-y rounded-xl border">
                            <div
                                class="grid gap-1 p-4 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center"
                            >
                                <span
                                    class="text-muted-foreground text-sm font-medium"
                                    >Nombre</span
                                >
                                <span class="min-w-0 truncate text-sm">{{
                                    user.name
                                }}</span>
                            </div>
                            <div
                                class="grid gap-1 p-4 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center"
                            >
                                <span
                                    class="text-muted-foreground text-sm font-medium"
                                    >Correo</span
                                >
                                <span class="min-w-0 truncate text-sm">{{
                                    user.email
                                }}</span>
                            </div>
                        </div>

                        <div
                            class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            <LockKeyhole class="mt-0.5 size-4 shrink-0" />
                            <div>
                                <p class="text-sm font-medium">
                                    Cuenta de solo lectura
                                </p>
                                <p class="mt-1 text-xs leading-relaxed">
                                    El nombre y el correo son administrados por
                                    el sistema. Esta cuenta no puede modificarse
                                    ni eliminarse desde el panel.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        v-else-if="seccionActiva === 'seguridad'"
                        class="space-y-5"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">Seguridad</h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Protege tu cuenta con una segunda verificación.
                            </p>
                        </div>

                        <div class="rounded-xl border p-4">
                            <div class="flex items-start gap-3">
                                <div
                                    class="rounded-lg bg-[#b51927]/10 p-2 text-[#b51927] dark:bg-[#b51927]/20 dark:text-[#f06a76]"
                                >
                                    <ShieldCheck class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold">
                                        Autenticación de dos factores
                                    </h3>
                                    <p
                                        class="text-muted-foreground mt-1 text-sm leading-relaxed"
                                    >
                                        Activa o administra el código adicional
                                        solicitado al iniciar sesión.
                                    </p>
                                </div>
                            </div>

                            <Button class="mt-4" as-child>
                                <Link
                                    :href="editSecurity()"
                                    @click="isOpen = false"
                                >
                                    Activar o administrar 2FA
                                    <ChevronRight class="size-4" />
                                </Link>
                            </Button>
                        </div>
                    </section>

                    <section v-else class="space-y-5">
                        <div>
                            <h2 class="text-lg font-semibold">Apariencia</h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Elige cómo se muestra el panel en este
                                dispositivo.
                            </p>
                        </div>

                        <AppearanceTabs />

                        <p
                            class="text-muted-foreground text-xs leading-relaxed"
                        >
                            "Escritorio" utiliza automáticamente el modo claro u
                            oscuro configurado en tu sistema operativo.
                        </p>
                    </section>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
