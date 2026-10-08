<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Pages d'authentification : le produit à gauche, le formulaire sur une feuille. */
defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const institution = computed(
    () => page.props.institution as string | undefined,
);
</script>

<template>
    <div
        class="grid min-h-svh bg-fond text-encre lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]"
    >
        <div class="flex flex-col justify-between px-8 py-10 lg:px-16 lg:py-14">
            <div>
                <p
                    class="text-[2.75rem] leading-none font-bold tracking-[-0.03em]"
                >
                    Alerte PAR90
                </p>
                <p v-if="institution" class="mt-3 text-lg text-encre-2">
                    {{ institution }}
                </p>
            </div>
            <p class="mt-10 max-w-sm text-[15px] leading-relaxed text-encre-2">
                Suivi mensuel du risque de portefeuille : niveau d'alerte,
                signal avancé et registre des décisions de la direction.
            </p>
        </div>

        <div class="flex items-center justify-center px-6 py-10 lg:px-12">
            <div
                class="w-full max-w-sm rounded-[10px] border border-filet bg-feuille px-8 py-9"
            >
                <h1 class="text-2xl font-semibold tracking-[-0.01em]">
                    {{ title }}
                </h1>
                <p v-if="description" class="mt-1.5 text-sm text-encre-2">
                    {{ description }}
                </p>
                <div class="mt-7">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
