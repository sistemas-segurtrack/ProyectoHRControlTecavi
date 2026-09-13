<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = withDefaults(
    defineProps<{
        label: string;
        type?: string;
        placeholder?: string;
        addLabel?: string;
        inputmode?: HTMLAttributes['inputmode'];
        maxlength?: number;
    }>(),
    { type: 'text', addLabel: 'Agregar' },
);

const model = defineModel<string[]>({ required: true });

function agregar(): void {
    model.value = [...model.value, ''];
}

function quitar(indice: number): void {
    model.value = model.value.filter((_, i) => i !== indice);
}

function actualizar(indice: number, valor: string): void {
    const copia = [...model.value];
    copia[indice] = valor;
    model.value = copia;
}
</script>

<template>
    <div class="grid gap-1.5">
        <Label>{{ props.label }}</Label>

        <div
            v-for="(item, i) in model"
            :key="i"
            class="flex items-center gap-2"
        >
            <Input
                :type="props.type"
                :placeholder="props.placeholder"
                :inputmode="props.inputmode"
                :maxlength="props.maxlength"
                :model-value="item"
                @update:model-value="actualizar(i, String($event))"
            />
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="h-9 w-9 shrink-0 text-gray-500 hover:text-red-600"
                :aria-label="`Quitar ${props.label}`"
                @click="quitar(i)"
            >
                <X class="h-4 w-4" />
            </Button>
        </div>

        <Button
            type="button"
            variant="outline"
            size="sm"
            class="w-fit gap-1.5"
            @click="agregar"
        >
            <Plus class="h-3.5 w-3.5" />
            {{ props.addLabel }}
        </Button>
    </div>
</template>
