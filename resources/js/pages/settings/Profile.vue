<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Cuenta',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Cuenta" />

    <h1 class="sr-only">Cuenta</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Información de la cuenta"
            description="Datos asociados a tu usuario"
        />

        <div class="divide-y rounded-xl border">
            <div
                class="grid gap-1 p-4 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center"
            >
                <span class="text-muted-foreground text-sm font-medium"
                    >Nombre</span
                >
                <span class="min-w-0 truncate text-sm">{{ user.name }}</span>
            </div>
            <div
                class="grid gap-1 p-4 sm:grid-cols-[7rem_minmax(0,1fr)] sm:items-center"
            >
                <span class="text-muted-foreground text-sm font-medium"
                    >Correo</span
                >
                <span class="min-w-0 truncate text-sm">{{ user.email }}</span>
            </div>
        </div>

        <div
            class="flex gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
        >
            <LockKeyhole class="mt-0.5 size-4 shrink-0" />
            <div>
                <p class="text-sm font-medium">Cuenta de solo lectura</p>
                <p class="mt-1 text-xs leading-relaxed">
                    El nombre y el correo son administrados por el sistema. Esta
                    cuenta no puede modificarse ni eliminarse desde el panel.
                </p>
            </div>
        </div>
    </div>
</template>
