<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { recursoUrl } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div
            class="flex h-10 w-28 shrink-0 items-center justify-center rounded-lg px-2 dark:bg-white"
        >
            <img
                :src="recursoUrl('logo_tecavi.svg')"
                alt="Tecavi"
                class="h-full w-auto max-w-full object-contain"
                draggable="false"
                @contextmenu.prevent
            />
        </div>
    </header>
</template>
