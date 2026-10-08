<script setup lang="ts">
import type { Tableau } from '@/types/tableau';

/*
 * Compréhension (bloc 5) : les huit indicateurs à gauche, les générations
 * récentes à droite. Aucun seuil, aucune couleur d'alerte.
 */
defineProps<{ tableau: Tableau }>();

const libelleEvolution: Record<string, string> = {
    hausse: 'en hausse',
    baisse: 'en baisse',
    stable: 'stable',
};
</script>

<template>
    <div class="grid h-full min-h-0 grid-cols-12 gap-4">
        <section
            class="col-span-5 flex min-h-0 flex-col rounded-[10px] bg-feuille px-6 py-4"
            aria-labelledby="titre-indicateurs"
        >
            <h2 id="titre-indicateurs" class="text-[15px] font-semibold">
                Indicateurs
                <span class="ml-2 font-normal text-encre-2"
                    >Où regarder pendant la revue ?</span
                >
            </h2>
            <table class="chiffres mt-3 w-full text-[14px]">
                <thead>
                    <tr
                        class="border-b border-filet text-left text-[12px] text-encre-2"
                    >
                        <th class="py-2 font-medium">Indicateur</th>
                        <th class="py-2 text-right font-medium">
                            {{ tableau.mois.libelle }}
                        </th>
                        <th class="py-2 text-right font-medium">
                            {{ tableau.comprehension.moisPrecedent }}
                        </th>
                        <th class="py-2 pl-4 font-medium">Sens</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="i in tableau.comprehension.indicateurs"
                        :key="i.libelle"
                        class="border-b border-filet last:border-0"
                    >
                        <td class="py-[9px] pr-3 leading-snug">
                            {{ i.libelle }}
                        </td>
                        <td
                            class="py-[9px] text-right font-semibold whitespace-nowrap"
                        >
                            {{ i.valeur }}
                        </td>
                        <td
                            class="py-[9px] pl-3 text-right whitespace-nowrap text-encre-2"
                        >
                            {{ i.precedente }}
                        </td>
                        <td
                            class="py-[9px] pl-4 whitespace-nowrap text-encre-2"
                        >
                            <span aria-hidden="true">{{ i.symbole }}</span>
                            <span class="ml-1 text-[12px]">{{
                                i.evolution ? libelleEvolution[i.evolution] : ''
                            }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            class="col-span-7 flex min-h-0 flex-col rounded-[10px] bg-feuille px-6 py-4"
            aria-labelledby="titre-generations"
        >
            <h2 id="titre-generations" class="text-[15px] font-semibold">
                Générations récentes
                <span class="ml-2 font-normal text-encre-2"
                    >PAR30 en %, par mois d'octroi et âge en mois</span
                >
            </h2>
            <table class="chiffres mt-3 w-full text-[13px]">
                <thead>
                    <tr class="border-b border-filet text-[12px] text-encre-2">
                        <th class="py-2 pr-2 text-left font-medium">Octroi</th>
                        <th
                            v-for="a in tableau.generations.ages"
                            :key="a"
                            class="py-2 text-right font-medium"
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
                            class="py-[6px] pr-2 text-left font-medium whitespace-nowrap text-encre-2"
                        >
                            {{ ligne.mois }}
                        </th>
                        <td
                            v-for="(cellule, age) in ligne.cellules"
                            :key="age"
                            class="py-[6px] pl-1.5 text-right"
                        >
                            {{ cellule ?? '' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
