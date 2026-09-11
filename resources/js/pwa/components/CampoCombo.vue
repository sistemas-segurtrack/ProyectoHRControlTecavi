<script setup lang="ts">
import { useId } from 'vue';

defineProps<{
    label: string;
    options: string[];
    placeholder?: string;
    required?: boolean;
}>();

const model = defineModel<string>({ required: true });
const listId = useId();
</script>

<template>
    <label class="flex flex-col gap-1">
        <span class="text-xs font-bold tracking-wide text-gray-500 uppercase">
            {{ label }}
        </span>
        <input
            v-model="model"
            :list="listId"
            :placeholder="placeholder"
            :required="required"
            type="text"
            autocomplete="off"
            class="h-12 w-full rounded-xl border border-gray-300 bg-white px-3.5 text-base text-gray-900 focus:border-[#b51927] focus:ring-2 focus:ring-[#b51927]/25 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        />
        <datalist :id="listId">
            <option v-for="opt in options" :key="opt" :value="opt" />
        </datalist>
    </label>
</template>
