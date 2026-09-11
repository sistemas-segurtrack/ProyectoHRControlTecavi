<script setup lang="ts">
import { recursoUrl } from '@/lib/utils';
</script>

<template>
    <div
        class="mx-auto flex h-13 w-48 max-w-full shrink-0 items-center justify-center overflow-hidden rounded-lg px-1 group-data-[collapsible=icon]:size-8 group-data-[collapsible=icon]:rounded-md group-data-[collapsible=icon]:px-0 dark:bg-white dark:p-1"
    >
        <img
            :src="recursoUrl('logo-segurtrack.png')"
            alt="Segurtrack"
            class="h-12 w-auto max-w-full object-contain group-data-[collapsible=icon]:h-20 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:max-w-none group-data-[collapsible=icon]:object-cover group-data-[collapsible=icon]:object-top"
            draggable="false"
            @contextmenu.prevent
        />
    </div>
</template>
