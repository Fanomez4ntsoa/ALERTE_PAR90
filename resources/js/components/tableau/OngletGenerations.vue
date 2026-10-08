<script setup lang="ts">
import type { Tableau } from '@/types/tableau';

/** Générations récentes : PAR30 par mois d'octroi et âge, sans seuil ni couleur. */
defineProps<{ tableau: Tableau }>();
</script>

<template>
    <section
        class="flex h-full w-full flex-col rounded-[10px] bg-feuille px-6 py-4"
        aria-labelledby="titre-generations"
    >
        <h2 id="titre-generations" class="text-[15px] font-semibold">
            Générations récentes
            <span class="ml-2 font-normal text-encre-2"
                >PAR30 en %, par mois d'octroi et âge en mois</span
            >
        </h2>
        <table class="chiffres mt-3 w-full text-[14px]">
            <thead>
                <tr class="border-b border-filet text-[13px] text-encre-2">
                    <th class="py-1.5 pr-3 text-left font-medium">Octroi</th>
                    <th
                        v-for="a in tableau.generations.ages"
                        :key="a"
                        class="py-1.5 text-right font-medium"
                    >
                        {{ a }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="ligne in tableau.generations.lignes"
                    :key="ligne.mois"
                    class="border-b border-filet last:border-0"
                >
                    <th
                        class="py-[5px] pr-3 text-left font-medium whitespace-nowrap text-encre-2"
                    >
                        {{ ligne.mois }}
                    </th>
                    <td
                        v-for="(cellule, age) in ligne.cellules"
                        :key="age"
                        class="py-[5px] pl-2 text-right"
                    >
                        {{ cellule ?? '' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
