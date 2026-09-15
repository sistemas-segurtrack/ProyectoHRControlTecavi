<script setup lang="ts">
import { Check, ChevronDown } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';

defineProps<{
    label: string;
    options: string[];
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}>();

const model = defineModel<string>({ required: true });
</script>

<template>
    <label class="flex flex-col gap-1">
        <span class="text-xs font-bold tracking-wide text-gray-500 uppercase">
            {{ label }}
        </span>

        <ComboboxRoot
            v-model="model"
            :required="required"
            :disabled="disabled"
            open-on-click
            open-on-focus
            class="relative"
        >
            <ComboboxAnchor
                class="flex h-12 w-full items-center gap-2 rounded-xl border border-gray-300 px-3.5 focus-within:border-[#b51927] focus-within:ring-2 focus-within:ring-[#b51927]/25 dark:border-gray-600"
                :class="
                    disabled
                        ? 'cursor-not-allowed bg-gray-100 opacity-70 dark:bg-gray-900'
                        : 'bg-white dark:bg-gray-800'
                "
            >
                <ComboboxInput
                    :placeholder="placeholder"
                    class="h-full w-full bg-transparent text-base text-gray-900 placeholder:text-gray-400 focus:outline-none disabled:cursor-not-allowed dark:text-gray-100"
                />
                <ComboboxTrigger class="shrink-0 text-gray-400">
                    <ChevronDown class="size-4" />
                </ComboboxTrigger>
            </ComboboxAnchor>

            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="4"
                    class="z-50 max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                >
                    <ComboboxViewport class="overflow-y-auto p-1">
                        <ComboboxEmpty
                            class="px-3 py-6 text-center text-sm text-gray-400"
                        >
                            Sin resultados.
                        </ComboboxEmpty>
                        <ComboboxItem
                            v-for="opt in options"
                            :key="opt"
                            :value="opt"
                            class="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-base text-gray-900 outline-none data-[highlighted]:bg-[#b51927]/10 dark:text-gray-100"
                        >
                            {{ opt }}
                            <ComboboxItemIndicator>
                                <Check class="size-4 shrink-0 text-[#b51927]" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </label>
</template>
