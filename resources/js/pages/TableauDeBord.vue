<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarreSuperieure from '@/components/tableau/BarreSuperieure.vue';
import Impression from '@/components/tableau/Impression.vue';
import OngletComprehension from '@/components/tableau/OngletComprehension.vue';
import OngletGenerations from '@/components/tableau/OngletGenerations.vue';
import OngletRegistre from '@/components/tableau/OngletRegistre.vue';
import OngletSynthese from '@/components/tableau/OngletSynthese.vue';
import type { Navigation, Onglet, Tableau } from '@/types/tableau';

/*
 * Tableau de bord d'une clôture (sections 6, 8 et 9), en quatre onglets.
 * Toutes les valeurs arrivent calculées et mises en forme par le serveur :
 * la page ne compare rien et ne calcule rien, elle affiche.
 */
const props = defineProps<{
    institution: string;
    onglet: Onglet['cle'];
    onglets: Onglet[];
    navigation: Navigation | null;
    tableau: Tableau | null;
}>();

const contenus = {
    synthese: OngletSynthese,
    comprehension: OngletComprehension,
    generations: OngletGenerations,
    registre: OngletRegistre,
};

const titre = computed(() => {
    const libelle = props.onglets.find((o) => o.cle === props.onglet)?.libelle;

    return props.tableau
        ? `${libelle}, ${props.tableau.mois.libelle}`
        : 'Tableau de bord';
});
</script>

<template>
    <Head :title="titre" />

    <div
        class="ecran flex h-dvh min-h-[640px] flex-col overflow-hidden bg-fond text-encre"
    >
        <BarreSuperieure
            :institution="institution"
            :onglet="onglet"
            :onglets="onglets"
            :navigation="navigation"
            :mois-courant="tableau?.mois.valeur ?? null"
        />

        <main v-if="!tableau" class="grid flex-1 place-items-center p-8">
            <div class="max-w-md text-center">
                <p class="text-lg font-semibold">Aucune clôture importée.</p>
                <p class="mt-2 text-sm text-encre-2">
                    L'analyste charge le fichier modèle depuis Administration,
                    section Imports.
                </p>
            </div>
        </main>

        <main v-else class="mx-auto min-h-0 w-full max-w-[1760px] flex-1 p-4">
            <Transition name="fondu" mode="out-in">
                <component
                    :is="contenus[onglet]"
                    :key="`${onglet}-${tableau.mois.valeur}`"
                    :tableau="tableau"
                />
            </Transition>
        </main>
    </div>

    <Impression v-if="tableau" :tableau="tableau" :institution="institution" />
</template>

<style>
.fondu-enter-active {
    transition: opacity 150ms ease-out;
}

.fondu-enter-from {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .fondu-enter-active {
        transition: none;
    }
}

@media print {
    .ecran {
        display: none !important;
    }
}
</style>
