<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    modelValue: string;
    options: string[];
    placeholder?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [string] }>();

const open = ref(false);
const local = ref(props.modelValue);

watch(
    () => props.modelValue,
    (v) => {
        local.value = v;
    },
);

const filtered = computed(() => {
    const q = local.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => o.toLowerCase().includes(q));
});

function select(opt: string) {
    local.value = opt;
    emit('update:modelValue', opt);
    open.value = false;
}

function onInput() {
    emit('update:modelValue', local.value);
    open.value = true;
}

function clear() {
    local.value = '';
    emit('update:modelValue', '');
}

function onFocus() {
    open.value = true;
}

function onBlur() {
    setTimeout(() => {
        open.value = false;
    }, 150);
}
</script>

<template>
    <div class="relative w-full">
        <input
            v-model="local"
            type="text"
            :placeholder="placeholder"
            autocomplete="off"
            class="h-8 w-full rounded-md border border-gray-300 px-2.5 pr-7 text-sm transition-all duration-200 focus:border-[#b51927] focus:ring-1 focus:ring-[#b51927] focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
        />
        <button
            v-if="local"
            type="button"
            tabindex="-1"
            class="absolute top-1/2 right-1.5 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
            @mousedown.prevent="clear"
        >
            <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor">
                <path
                    d="M7.41 6l3.3-3.29a1 1 0 00-1.42-1.42L6 4.59 2.71 1.3A1 1 0 001.3 2.71L4.59 6 1.3 9.29a1 1 0 001.41 1.42L6 7.41l3.29 3.3a1 1 0 001.42-1.42z"
                />
            </svg>
        </button>
        <Transition
            enter-active-class="transition-all duration-150 ease-out"
            enter-from-class="-translate-y-1 scale-95 opacity-0"
            leave-active-class="transition-all duration-100 ease-in"
            leave-to-class="-translate-y-1 scale-95 opacity-0"
        >
            <ul
                v-if="open && filtered.length > 0"
                class="absolute right-0 left-0 z-50 mt-0.5 max-h-44 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-md dark:border-gray-700 dark:bg-gray-800"
            >
                <li
                    v-for="opt in filtered"
                    :key="opt"
                    class="cursor-pointer px-2.5 py-1.5 text-sm text-gray-700 hover:bg-[#b51927]/10 dark:text-gray-200 dark:hover:bg-gray-700"
                    @mousedown.prevent="select(opt)"
                >
                    {{ opt }}
                </li>
            </ul>
        </Transition>
    </div>
</template>
