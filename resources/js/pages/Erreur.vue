<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

/** Page d'erreur (403, 404, 419, 429, 500, 503), textes préparés par le serveur. */
defineProps<{
    statut: number;
    titre: string;
    message: string;
    institution: string;
    urlTableau: string;
}>();
</script>

<template>
    <Head :title="titre" />

    <div
        class="grid min-h-svh bg-fond text-encre lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]"
    >
        <div class="flex flex-col px-8 py-10 lg:px-16 lg:py-14">
            <p class="text-[2.75rem] leading-none font-bold tracking-[-0.03em]">
                Alerte PAR90
            </p>
            <p class="mt-3 text-lg text-encre-2">{{ institution }}</p>
        </div>

        <div class="flex items-center justify-center px-6 py-10 lg:px-12">
            <div
                class="w-full max-w-md rounded-[10px] border border-filet bg-feuille px-8 py-9"
            >
                <p class="chiffres text-sm font-medium text-encre-2">
                    Erreur {{ statut }}
                </p>
                <h1 class="mt-1 text-2xl font-semibold tracking-[-0.01em]">
                    {{ titre }}
                </h1>
                <p class="mt-3 text-[15px] leading-relaxed text-encre-2">
                    {{ message }}
                </p>
                <Link
                    :href="urlTableau"
                    class="mt-7 inline-flex rounded-md bg-prusse px-4 py-2 text-sm font-medium text-feuille focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-prusse"
                    >Retour au tableau de bord</Link
                >
            </div>
        </div>
    </div>
</template>
