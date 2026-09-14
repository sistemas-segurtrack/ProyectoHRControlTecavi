<script setup lang="ts">
import { recursoUrl } from '@/lib/utils';
</script>

<template>
    <div
        class="mx-auto flex h-13 w-48 max-w-full shrink-0 items-center justify-center group-data-[collapsible=icon]:size-8"
    >
        <img
            :src="recursoUrl('logo-segurtrack.png')"
            alt="Segurtrack"
            class="h-13 w-auto max-w-full object-contain group-data-[collapsible=icon]:hidden dark:hue-rotate-180 dark:invert dark:saturate-150"
            draggable="false"
            @contextmenu.prevent
        />
        <img
            :src="recursoUrl('favicon.png')"
            alt="Segurtrack"
            class="hidden size-7 rounded-md object-contain group-data-[collapsible=icon]:block"
            draggable="false"
            @contextmenu.prevent
        />
    </div>
</template>
