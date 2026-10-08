<script setup lang="ts">
import CourbePar90 from '@/components/CourbePar90.vue';
import { aplatNiveau } from '@/types/tableau';
import type { Tableau } from '@/types/tableau';

/*
 * Feuille imprimée : blocs 1 à 5 sur une page A4 paysage, toujours en thème
 * clair, quel que soit l'onglet affiché. Hors écran à l'affichage, pour que la
 * courbe soit déjà dessinée au moment d'imprimer.
 */
defineProps<{ tableau: Tableau; institution: string }>();
</script>

<template>
    <div
        class="impression theme-clair bg-feuille text-encre"
        aria-hidden="true"
    >
        <header
            class="flex items-baseline justify-between border-b border-filet pb-[2mm]"
        >
            <p class="text-[11pt] font-bold">
                Alerte PAR90
                <span class="ml-[2mm] font-normal text-encre-2">{{
                    institution
                }}</span>
            </p>
            <p class="text-[10pt] text-encre-2">
                Clôture de {{ tableau.mois.libelle }}
            </p>
        </header>

        <!-- Bloc 1 -->
        <div class="mt-[3mm] grid grid-cols-12 gap-[3mm]">
            <div
                class="niveau col-span-4 rounded-[2mm] px-[4mm] py-[3mm]"
                :class="aplatNiveau[tableau.bandeau.couleur]"
            >
                <p class="text-[8pt] opacity-85">
                    Niveau d'alerte de {{ tableau.mois.libelle }}
                </p>
                <p class="text-[22pt] leading-none font-bold">
                    {{ tableau.bandeau.libelle }}
                </p>
                <p class="mt-[1.5mm] text-[9pt] font-medium">
                    {{ tableau.bandeau.motif }}
                </p>
            </div>
            <div class="col-span-5 py-[1mm] text-[9pt]">
                <p class="text-[8pt] text-encre-2">Action attendue</p>
                <p class="font-medium">{{ tableau.bandeau.action }}</p>
                <p
                    v-if="tableau.bandeau.mention"
                    class="mt-[1.5mm] text-[8pt] text-encre-2"
                >
                    Niveau établi en {{ tableau.bandeau.mention }}
                </p>
            </div>
            <div
                class="col-span-3 rounded-[2mm] border border-dashed border-encre-2 px-[3mm] py-[2mm]"
            >
                <p class="text-[8pt] text-encre-2">
                    Statut par rapport à la
                    {{ tableau.risque.courbe.libelleLimite.toLowerCase() }}
                </p>
                <p class="text-[12pt] font-semibold">
                    {{ tableau.bandeau.statut.libelle }}
                </p>
                <p
                    v-if="tableau.bandeau.statut.texte"
                    class="text-[7.5pt] leading-snug text-encre-2"
                >
                    {{ tableau.bandeau.statut.texte }}
                </p>
            </div>
        </div>

        <!-- Blocs 2, 3 et 4 -->
        <div class="mt-[3mm] grid grid-cols-12 gap-[4mm]">
            <section class="col-span-7">
                <p class="chiffres flex items-baseline gap-[4mm] text-[9pt]">
                    <span class="font-semibold">Niveau de risque</span>
                    <span
                        >PAR90
                        <strong class="text-[13pt]">{{
                            tableau.risque.par90
                        }}</strong></span
                    >
                    <span
                        >en retard de plus de 90 jours
                        <strong>{{ tableau.risque.montant }}</strong></span
                    >
                </p>
                <div class="mt-[1mm] h-[62mm]">
                    <CourbePar90 :courbe="tableau.risque.courbe" compacte />
                </div>
            </section>
            <div class="chiffres col-span-5 flex flex-col gap-[3mm] text-[9pt]">
                <section class="border-b border-filet pb-[3mm]">
                    <p class="font-semibold">
                        Trajectoire
                        <span class="font-normal text-encre-2"
                            >Le PAR90 s'est-il dégradé sur trois mois ?</span
                        >
                    </p>
                    <p class="mt-[1mm]">
                        <strong class="text-[14pt]">{{
                            tableau.trajectoire.hausse
                        }}</strong>
                        <span class="ml-[2mm] text-encre-2"
                            >seuil de hausse
                            {{ tableau.trajectoire.seuil }}</span
                        >
                    </p>
                    <p class="text-encre-2">{{ tableau.trajectoire.texte }}</p>
                </section>
                <section>
                    <p class="font-semibold">
                        Signal avancé
                        <span class="font-normal text-encre-2"
                            >Une hausse se prépare-t-elle ?</span
                        >
                    </p>
                    <p v-if="!tableau.signal.disponible" class="mt-[1mm]">
                        {{ tableau.signal.message }}
                        ({{ tableau.signal.libelleBonds }})
                    </p>
                    <template v-else>
                        <p class="mt-[1mm] flex items-center gap-[2mm]">
                            <span
                                class="niveau inline-block size-[4mm] rounded-full border"
                                :class="
                                    tableau.signal.voyant
                                        ? 'border-vigilance bg-vigilance'
                                        : 'border-encre-2'
                                "
                            />
                            <strong class="text-[11pt]">{{
                                tableau.signal.libelleVoyant
                            }}</strong>
                        </p>
                        <p class="text-encre-2">
                            Bond du mois
                            <strong class="text-encre">{{
                                tableau.signal.bond
                            }}</strong
                            >, seuil du mois
                            <strong class="text-encre">{{
                                tableau.signal.seuil
                            }}</strong
                            >, {{ tableau.signal.nombreBonds }} bonds utilisés
                        </p>
                    </template>
                </section>
            </div>
        </div>

        <!-- Bloc 5 -->
        <div class="mt-[3mm] grid grid-cols-12 gap-[4mm]">
            <section class="col-span-5">
                <p class="text-[9pt] font-semibold">
                    Compréhension
                    <span class="font-normal text-encre-2"
                        >Où regarder pendant la revue ?</span
                    >
                </p>
                <table class="chiffres mt-[1mm] w-full text-[8pt]">
                    <thead>
                        <tr class="border-b border-filet text-encre-2">
                            <th class="py-[0.5mm] text-left font-medium">
                                Indicateur
                            </th>
                            <th class="text-right font-medium">
                                {{ tableau.mois.libelle }}
                            </th>
                            <th class="text-right font-medium">
                                {{ tableau.comprehension.moisPrecedent }}
                            </th>
                            <th class="pl-[2mm] text-left font-medium">Sens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="i in tableau.comprehension.indicateurs"
                            :key="i.libelle"
                            class="border-b border-filet"
                        >
                            <td class="py-[0.6mm] pr-[2mm]">{{ i.libelle }}</td>
                            <td
                                class="text-right font-semibold whitespace-nowrap"
                            >
                                {{ i.valeur }}
                            </td>
                            <td
                                class="text-right whitespace-nowrap text-encre-2"
                            >
                                {{ i.precedente }}
                            </td>
                            <td class="pl-[2mm]">{{ i.symbole }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
            <section class="col-span-7">
                <p class="text-[9pt] font-semibold">
                    Générations récentes
                    <span class="font-normal text-encre-2"
                        >PAR30 en %, par mois d'octroi et âge en mois</span
                    >
                </p>
                <table class="chiffres mt-[1mm] w-full text-[7.5pt]">
                    <thead>
                        <tr class="border-b border-filet text-encre-2">
                            <th class="text-left font-medium">Octroi</th>
                            <th
                                v-for="a in tableau.generations.ages"
                                :key="a"
                                class="text-right font-medium"
                            >
                                {{ a }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ligne in tableau.generations.lignes"
                            :key="ligne.mois"
                        >
                            <th
                                class="pr-[1.5mm] text-left font-medium whitespace-nowrap text-encre-2"
                            >
                                {{ ligne.mois }}
                            </th>
                            <td
                                v-for="(cellule, age) in ligne.cellules"
                                :key="age"
                                class="pl-[1mm] text-right"
                            >
                                {{ cellule ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</template>

<style>
/* Hors écran à l'affichage ; seule chose imprimée. */
.impression {
    position: fixed;
    top: 0;
    left: -10000px;
    width: 281mm;
    padding: 0;
    font-size: 9pt;
}

@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }

    .impression {
        position: static;
        left: auto;
    }

    .niveau {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
