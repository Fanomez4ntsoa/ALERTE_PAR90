<script setup lang="ts">
import type { Tableau } from '@/types/tableau';

/** Compréhension : les huit indicateurs sans seuil (bloc 5), sans couleur d'alerte. */
defineProps<{ tableau: Tableau }>();

const libelleEvolution: Record<string, string> = {
    hausse: 'en hausse',
    baisse: 'en baisse',
    stable: 'stable',
};
</script>

<template>
    <section
        class="mx-auto flex h-full w-full max-w-4xl flex-col rounded-[10px] bg-feuille px-6 py-4"
        aria-labelledby="titre-comprehension"
    >
        <h2 id="titre-comprehension" class="text-[15px] font-semibold">
            Compréhension
            <span class="ml-2 font-normal text-encre-2"
                >Où regarder pendant la revue ?</span
            >
        </h2>
        <table class="chiffres mt-3 w-full text-[15px]">
            <thead>
                <tr
                    class="border-b border-filet text-left text-[13px] text-encre-2"
                >
                    <th class="py-2 font-medium">Indicateur</th>
                    <th class="py-2 text-right font-medium">
                        {{ tableau.mois.libelle }}
                    </th>
                    <th class="py-2 text-right font-medium">
                        {{ tableau.comprehension.moisPrecedent }}
                    </th>
                    <th class="py-2 pl-6 font-medium">Sens</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="i in tableau.comprehension.indicateurs"
                    :key="i.libelle"
                    class="border-b border-filet last:border-0"
                >
                    <td class="py-2.5 pr-4">{{ i.libelle }}</td>
                    <td
                        class="py-2.5 text-right font-semibold whitespace-nowrap"
                    >
                        {{ i.valeur }}
                    </td>
                    <td
                        class="py-2.5 text-right whitespace-nowrap text-encre-2"
                    >
                        {{ i.precedente }}
                    </td>
                    <td class="py-2.5 pl-6 whitespace-nowrap text-encre-2">
                        <span aria-hidden="true">{{ i.symbole }}</span>
                        <span class="ml-1.5 text-[13px]">{{
                            i.evolution ? libelleEvolution[i.evolution] : ''
                        }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
