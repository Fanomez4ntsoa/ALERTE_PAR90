<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BarreSuperieure from '@/components/tableau/BarreSuperieure.vue';
import { aplatNiveau } from '@/types/tableau';
import type { TeinteNiveau } from '@/types/tableau';

/*
 * Récapitulatif de la vérification à trois mois (section 9) : les totaux par
 * résultat, puis une ligne par clôture. Valeurs préparées par le serveur.
 */

type Ligne = {
    mois: string;
    url: string;
    niveau: string;
    couleur: TeinteNiveau;
    statut: string;
    hausseReelle: string;
    resultatCode: string;
    resultat: string;
    decisions: number;
    decisionsAnnulees: string | null;
};

type Total = {
    code: string;
    libelle: string;
    nombre: number;
    estResultat: boolean;
};

const props = defineProps<{
    institution: string;
    lignes: Ligne[];
    totaux: Total[];
    urlTableau: string;
}>();

const resultats = computed(() => props.totaux.filter((t) => t.estResultat));
const horsResultats = computed(() => props.totaux.filter((t) => !t.estResultat));
</script>

<template>
    <Head title="Récapitulatif de la vérification" />

    <div class="flex h-dvh min-h-[640px] flex-col overflow-hidden bg-fond text-encre">
        <BarreSuperieure
            :institution="institution"
            titre="Récapitulatif de la vérification à trois mois"
            :retour="{ libelle: 'Tableau de bord', url: urlTableau }"
        />

        <main class="mx-auto flex min-h-0 w-full max-w-[1760px] flex-1 flex-col gap-4 p-4">
            <section
                class="shrink-0 rounded-[10px] bg-feuille px-6 py-4"
                aria-labelledby="titre-totaux"
            >
                <h2 id="titre-totaux" class="text-[15px] font-semibold">
                    Totaux par résultat
                    <span class="ml-2 font-normal text-encre-2"
                        >Le signal a-t-il été suivi d'une hausse ?</span
                    >
                </h2>
                <dl class="chiffres mt-3 flex items-stretch">
                    <div
                        v-for="t in resultats"
                        :key="t.code"
                        class="flex-1 border-l border-filet px-4 first:border-l-0 first:pl-0"
                    >
                        <dd class="text-[1.75rem] leading-none font-semibold">
                            {{ t.nombre }}
                        </dd>
                        <dt class="mt-1.5 text-[13px] leading-snug text-encre-2">
                            {{ t.libelle }}
                        </dt>
                    </div>
                    <div
                        v-for="t in horsResultats"
                        :key="t.code"
                        class="w-32 border-l-2 border-dashed border-filet px-4"
                    >
                        <dd class="text-[1.75rem] leading-none font-medium text-encre-2">
                            {{ t.nombre }}
                        </dd>
                        <dt class="mt-1.5 text-[13px] leading-snug text-encre-2">
                            {{ t.libelle }}
                        </dt>
                    </div>
                </dl>
                <p class="mt-3 text-[12px] text-encre-2">
                    « Sans objet » (mois Non évalué) et « En attente » (mois
                    t+3 pas encore importé) ne sont pas des résultats et ne
                    comptent dans aucun total de résultats.
                </p>
            </section>

            <section
                class="flex min-h-0 flex-1 flex-col rounded-[10px] bg-feuille px-6 pt-4 pb-2"
                aria-labelledby="titre-mois"
            >
                <h2 id="titre-mois" class="text-[15px] font-semibold">
                    Toutes les clôtures
                </h2>
                <div class="mt-2 min-h-0 flex-1 overflow-y-auto pr-2">
                    <table class="chiffres w-full text-[14px]">
                        <thead class="sticky top-0 bg-feuille">
                            <tr class="border-b border-filet text-left text-[12px] text-encre-2">
                                <th class="py-2 font-medium">Mois</th>
                                <th class="py-2 font-medium">Niveau</th>
                                <th class="py-2 font-medium">Statut</th>
                                <th class="py-2 text-right font-medium">Hausse réelle</th>
                                <th class="py-2 pl-8 font-medium">Résultat</th>
                                <th class="py-2 text-right font-medium">Décisions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="l in lignes"
                                :key="l.mois"
                                class="border-b border-filet last:border-0"
                            >
                                <td class="py-1.5">
                                    <Link
                                        :href="l.url"
                                        class="font-medium text-prusse underline-offset-2 hover:underline"
                                        >{{ l.mois }}</Link
                                    >
                                </td>
                                <td class="py-1.5">
                                    <span
                                        class="niveau rounded px-2 py-0.5 text-[12px] font-semibold"
                                        :class="aplatNiveau[l.couleur]"
                                        >{{ l.niveau }}</span
                                    >
                                </td>
                                <td class="py-1.5">{{ l.statut }}</td>
                                <td class="py-1.5 text-right">{{ l.hausseReelle }}</td>
                                <td
                                    class="py-1.5 pl-8"
                                    :class="{ 'text-encre-2': l.resultatCode === 'SANS_OBJET' || l.resultatCode === 'EN_ATTENTE' }"
                                >
                                    {{ l.resultat }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ l.decisions }}
                                    <span
                                        v-if="l.decisionsAnnulees"
                                        class="text-[12px] text-encre-2"
                                        >{{ l.decisionsAnnulees }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
</template>

<style scoped>
.niveau {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
