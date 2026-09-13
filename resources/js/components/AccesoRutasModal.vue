<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, LogIn } from '@lucide/vue';
import { onMounted } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Label } from '@/components/ui/label';
import { recursoUrl } from '@/lib/utils';
import rutas from '@/routes/modulos/rutas';

// Solo pide la contraseña -- la cuenta (tecavi@segurtrack.com) va fija en el
// servidor (ver RutasAccesoController). Mismo patrón que el reporte de
// clientes de mavitours, sin el campo de RUC porque acá hay una sola cuenta.
const form = useForm({ password: '' });

onMounted(() => {
    document.getElementById('acceso-rutas-password')?.focus();
});

function entrar(): void {
    form.post(rutas.acceso.url(), {
        preserveScroll: true,
        onError: () => {
            form.reset('password');
            document.getElementById('acceso-rutas-password')?.focus();
        },
    });
}
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="acceso-rutas-titulo"
    >
        <div
            class="w-full max-w-sm overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-2xl dark:border-sidebar-border"
        >
            <div class="flex flex-col items-center gap-3 px-8 pt-8">
                <img
                    :src="recursoUrl('logo-segurtrack.png')"
                    alt="Segurtrack"
                    class="h-9 w-auto object-contain dark:hue-rotate-180 dark:invert dark:saturate-150"
                    draggable="false"
                    @contextmenu.prevent
                />
                <div class="text-center">
                    <h1
                        id="acceso-rutas-titulo"
                        class="text-base font-semibold"
                    >
                        Hojas de ruta
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Ingresa la contraseña para ver el reporte
                    </p>
                </div>
            </div>

            <form
                class="flex flex-col gap-4 px-8 pt-6 pb-8"
                @submit.prevent="entrar"
            >
                <div class="grid gap-1.5">
                    <Label for="acceso-rutas-password">Contraseña</Label>
                    <PasswordInput
                        id="acceso-rutas-password"
                        v-model="form.password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <button
                    type="submit"
                    class="mt-1 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-[#b51927] text-sm font-medium text-white transition-colors hover:bg-[#8f141f] focus-visible:ring-2 focus-visible:ring-[#b51927] focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                    />
                    <LogIn v-else class="h-4 w-4" />
                    {{ form.processing ? 'Verificando…' : 'Ingresar' }}
                </button>
            </form>
        </div>
    </div>
</template>
