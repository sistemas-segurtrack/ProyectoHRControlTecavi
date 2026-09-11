<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        type?: string;
        inputmode?: 'text' | 'numeric' | 'decimal' | 'tel';
        placeholder?: string;
        required?: boolean;
    }>(),
    { type: 'text' },
);

const model = defineModel<string>({ required: true });
</script>

<template>
    <label class="flex flex-col gap-1">
        <span class="text-xs font-bold tracking-wide text-gray-500 uppercase">
            {{ label }}
        </span>
        <input
            v-model="model"
            :type="type"
            :inputmode="inputmode"
            :placeholder="placeholder"
            :required="required"
            class="h-12 w-full rounded-xl border border-gray-300 bg-white px-3.5 text-base text-gray-900 focus:border-[#b51927] focus:ring-2 focus:ring-[#b51927]/25 focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
        />
    </label>
</template>
