<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@lucide/vue';

/*
 * Récapitulatif de la vérification à trois mois (section 9) : une ligne par
 * clôture et les totaux par résultat. Valeurs préparées par le serveur.
 */

type Ligne = {
    mois: string;
    url: string;
    niveau: string;
    couleur: 'vert' | 'jaune' | 'rouge' | 'gris';
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

defineProps<{
    institution: string;
    lignes: Ligne[];
    totaux: Total[];
    urlTableau: string;
}>();

const fondNiveau: Record<string, string> = {
    vert: 'bg-emerald-700 text-white',
    jaune: 'bg-amber-300 text-neutral-950',
    rouge: 'bg-red-700 text-white',
    gris: 'bg-neutral-500 text-white',
};
</script>

<template>
    <Head title="Récapitulatif de la vérification" />

    <div class="min-h-screen bg-neutral-50 text-neutral-900 print:bg-white">
        <header
            class="flex items-center gap-6 border-b border-neutral-200 bg-white px-5 py-1.5 print:border-0 print:px-0"
        >
            <div class="mr-auto leading-tight">
                <p class="text-base font-semibold">
                    Vérification à trois mois : récapitulatif
                </p>
                <p class="text-xs text-neutral-600">{{ institution }}</p>
            </div>
            <Link
                :href="urlTableau"
                class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm hover:bg-neutral-100 print:hidden"
            >
                <ChevronLeft class="size-4" aria-hidden="true" /> Tableau de
                bord
            </Link>
        </header>

        <main class="flex flex-col gap-3 px-5 py-3 print:px-0">
            <section
                class="rounded-lg border border-neutral-200 bg-white px-4 py-2"
                aria-labelledby="titre-totaux"
            >
                <h2 id="titre-totaux" class="mb-2 text-sm font-semibold">
                    Totaux par résultat
                </h2>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="t in totaux"
                        :key="t.code"
                        class="min-w-36 rounded-md border px-3 py-1.5"
                        :class="
                            t.estResultat
                                ? 'border-neutral-300'
                                : 'border-dashed border-neutral-300 text-neutral-600'
                        "
                    >
                        <p class="text-2xl font-bold tabular-nums">
                            {{ t.nombre }}
                        </p>
                        <p class="text-xs">{{ t.libelle }}</p>
                    </li>
                </ul>
                <p class="mt-2 text-xs text-neutral-500">
                    « Sans objet » (mois Non évalué) et « En attente » (mois t+3
                    pas encore importé) ne sont pas des résultats et ne comptent
                    dans aucun total de résultats.
                </p>
            </section>

            <section
                class="rounded-lg border border-neutral-200 bg-white px-4 py-2"
                aria-labelledby="titre-mois"
            >
                <h2 id="titre-mois" class="mb-1 text-sm font-semibold">
                    Toutes les clôtures
                </h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-neutral-200 text-left text-xs text-neutral-500"
                        >
                            <th class="py-1 font-medium">Mois</th>
                            <th class="py-1 font-medium">Niveau</th>
                            <th class="py-1 font-medium">Statut</th>
                            <th class="py-1 text-right font-medium">
                                Hausse réelle
                            </th>
                            <th class="py-1 pl-4 font-medium">Résultat</th>
                            <th class="py-1 text-right font-medium">
                                Décisions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="l in lignes"
                            :key="l.mois"
                            class="border-b border-neutral-100 last:border-0"
                        >
                            <td class="py-0.5">
                                <Link
                                    :href="l.url"
                                    class="hover:underline print:no-underline"
                                    >{{ l.mois }}</Link
                                >
                            </td>
                            <td class="py-0.5">
                                <span
                                    class="niveau rounded px-2 py-0.5 text-xs font-semibold"
                                    :class="fondNiveau[l.couleur]"
                                    >{{ l.niveau }}</span
                                >
                            </td>
                            <td class="py-0.5">{{ l.statut }}</td>
                            <td class="py-0.5 text-right tabular-nums">
                                {{ l.hausseReelle }}
                            </td>
                            <td class="py-0.5 pl-4">{{ l.resultat }}</td>
                            <td class="py-0.5 text-right tabular-nums">
                                {{ l.decisions }}
                                <span
                                    v-if="l.decisionsAnnulees"
                                    class="text-xs text-neutral-500"
                                    >{{ l.decisionsAnnulees }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
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
