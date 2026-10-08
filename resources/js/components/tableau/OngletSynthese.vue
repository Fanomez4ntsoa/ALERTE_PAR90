<script setup lang="ts">
import CourbePar90 from '@/components/CourbePar90.vue';
import { aplatNiveau } from '@/types/tableau';
import type { Tableau } from '@/types/tableau';

/*
 * Synthèse : blocs 1 à 4 sur un écran. Bandeau sur toute la largeur (niveau
 * en grand sur son aplat, motif et action au centre, statut 5 % en cadre
 * pointillé à droite) ; en dessous, la courbe et, à côté, trajectoire et
 * signal avancé empilés.
 */
defineProps<{ tableau: Tableau }>();
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <!-- Bloc 1 : faut-il ouvrir une revue ? -->
        <section
            class="grid shrink-0 grid-cols-[minmax(17rem,26%)_1fr_minmax(16rem,24%)] overflow-hidden rounded-[10px] bg-feuille"
            aria-labelledby="titre-verdict"
        >
            <div
                class="niveau flex flex-col justify-center px-7 py-5"
                :class="aplatNiveau[tableau.bandeau.couleur]"
            >
                <h2 id="titre-verdict" class="text-sm font-medium opacity-85">
                    Niveau d'alerte de {{ tableau.mois.libelle }}
                </h2>
                <p
                    class="mt-1 text-[clamp(3.25rem,8vh,6rem)] leading-[0.95] font-bold tracking-[-0.03em]"
                >
                    {{ tableau.bandeau.libelle }}
                </p>
            </div>

            <div class="flex flex-col justify-center gap-2 px-7 py-5">
                <p
                    class="text-[clamp(17px,2.4vh,24px)] leading-snug font-semibold"
                >
                    {{ tableau.bandeau.motif }}
                </p>
                <p class="text-[15px] leading-snug">
                    <span class="text-encre-2">Action attendue :</span>
                    {{ tableau.bandeau.action }}
                </p>
                <p
                    v-if="tableau.bandeau.mention"
                    class="text-[13px] text-encre-2"
                >
                    Niveau établi en {{ tableau.bandeau.mention }}
                </p>
            </div>

            <div class="flex items-stretch py-4 pr-4">
                <div
                    class="w-full rounded-lg border-2 border-dashed border-encre-2/50 px-5 py-3"
                >
                    <p class="text-[13px] text-encre-2">
                        Statut par rapport à la
                        {{ tableau.risque.courbe.libelleLimite.toLowerCase() }}
                    </p>
                    <p class="mt-0.5 text-xl font-semibold">
                        {{ tableau.bandeau.statut.libelle }}
                    </p>
                    <p
                        v-if="tableau.bandeau.statut.texte"
                        class="mt-1 text-[13px] leading-snug text-encre-2"
                    >
                        {{ tableau.bandeau.statut.texte }}
                    </p>
                </div>
            </div>
        </section>

        <div class="grid min-h-0 flex-1 grid-cols-12 gap-4">
            <!-- Bloc 2 : où en est le risque ? -->
            <section
                class="col-span-8 flex min-h-0 flex-col rounded-[10px] bg-feuille px-6 pt-4 pb-3"
                aria-labelledby="titre-risque"
            >
                <div class="flex items-end justify-between gap-6">
                    <h2 id="titre-risque" class="text-[15px] font-semibold">
                        Niveau de risque
                        <span class="ml-2 font-normal text-encre-2"
                            >Où en est le risque ?</span
                        >
                    </h2>
                    <dl class="chiffres flex items-baseline gap-6">
                        <div class="flex items-baseline gap-2">
                            <dt class="text-[13px] text-encre-2">PAR90</dt>
                            <dd class="text-[2rem] leading-none font-semibold">
                                {{ tableau.risque.par90 }}
                            </dd>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <dt class="text-[13px] text-encre-2">
                                En retard de plus de 90 jours
                            </dt>
                            <dd class="text-xl leading-none font-semibold">
                                {{ tableau.risque.montant }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="mt-3 min-h-0 flex-1">
                    <CourbePar90 :courbe="tableau.risque.courbe" />
                </div>
            </section>

            <div class="col-span-4 flex min-h-0 flex-col gap-4">
                <!-- Bloc 3 : trajectoire -->
                <section
                    class="flex min-h-0 flex-1 flex-col rounded-[10px] bg-feuille px-6 py-4"
                    aria-labelledby="titre-trajectoire"
                >
                    <h2
                        id="titre-trajectoire"
                        class="text-[15px] font-semibold"
                    >
                        Trajectoire
                    </h2>
                    <p class="text-[13px] text-encre-2">
                        Le PAR90 s'est-il dégradé sur trois mois ?
                    </p>
                    <div class="flex flex-1 flex-col justify-center">
                        <p class="chiffres flex items-baseline gap-3">
                            <span
                                class="text-[1.75rem] leading-none font-semibold"
                                >{{ tableau.trajectoire.hausse }}</span
                            >
                            <span class="text-[13px] text-encre-2"
                                >seuil de hausse
                                <span class="font-semibold text-encre">{{
                                    tableau.trajectoire.seuil
                                }}</span></span
                            >
                        </p>
                        <div
                            v-if="tableau.trajectoire.jauge"
                            class="relative mt-4 mb-5 h-1.5 rounded-full bg-filet"
                            aria-hidden="true"
                        >
                            <div
                                class="absolute top-0 right-0 h-1.5 rounded-r-full bg-encre-2/30"
                                :style="{
                                    left: `${tableau.trajectoire.jauge.seuil}%`,
                                }"
                            />
                            <div
                                class="absolute -top-1 h-3.5 w-px bg-encre-2"
                                :style="{
                                    left: `${tableau.trajectoire.jauge.zero}%`,
                                }"
                            />
                            <span
                                class="chiffres absolute top-3 -translate-x-1/2 text-[11px] text-encre-2"
                                :style="{
                                    left: `${tableau.trajectoire.jauge.zero}%`,
                                }"
                                >0</span
                            >
                            <div
                                class="absolute -top-2 h-5 w-0.5 bg-encre"
                                :style="{
                                    left: `${tableau.trajectoire.jauge.seuil}%`,
                                }"
                            />
                            <span
                                class="absolute top-3 -translate-x-1/2 text-[11px] font-medium text-encre"
                                :style="{
                                    left: `${tableau.trajectoire.jauge.seuil}%`,
                                }"
                                >seuil</span
                            >
                            <div
                                class="niveau absolute -top-[5px] size-4 -translate-x-1/2 rounded-full border-2 border-feuille"
                                :class="
                                    tableau.trajectoire.atteint
                                        ? 'bg-alerte'
                                        : 'bg-encre'
                                "
                                :style="{
                                    left: `${tableau.trajectoire.jauge.hausse}%`,
                                }"
                            />
                        </div>
                        <p
                            class="text-[13px]"
                            :class="
                                tableau.trajectoire.atteint
                                    ? 'font-semibold text-alerte'
                                    : 'text-encre-2'
                            "
                        >
                            {{ tableau.trajectoire.texte }}
                        </p>
                    </div>
                </section>

                <!-- Bloc 4 : signal avancé -->
                <section
                    class="flex min-h-0 flex-1 flex-col rounded-[10px] bg-feuille px-6 py-4"
                    aria-labelledby="titre-signal"
                >
                    <h2 id="titre-signal" class="text-[15px] font-semibold">
                        Signal avancé
                    </h2>
                    <p class="text-[13px] text-encre-2">
                        Une hausse se prépare-t-elle ?
                    </p>
                    <div class="flex flex-1 flex-col justify-center">
                        <p
                            v-if="!tableau.signal.disponible"
                            class="text-[15px] font-medium"
                        >
                            {{ tableau.signal.message }}
                            <span
                                class="chiffres block text-[13px] font-normal text-encre-2"
                                >{{ tableau.signal.libelleBonds }}</span
                            >
                        </p>
                        <div
                            v-else
                            class="flex items-center justify-between gap-4"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="niveau inline-block size-7 shrink-0 rounded-full border-2"
                                    :class="
                                        tableau.signal.voyant
                                            ? 'border-vigilance bg-vigilance shadow-[0_0_0_4px] shadow-vigilance/25'
                                            : 'border-encre-2/50 bg-transparent'
                                    "
                                    aria-hidden="true"
                                />
                                <span class="text-xl font-semibold">{{
                                    tableau.signal.libelleVoyant
                                }}</span>
                            </div>
                            <dl
                                class="chiffres grid grid-cols-[auto_auto] gap-x-4 gap-y-0.5 text-[13px]"
                            >
                                <dt class="text-encre-2">Bond du mois</dt>
                                <dd class="text-right font-semibold">
                                    {{ tableau.signal.bond }}
                                </dd>
                                <dt class="text-encre-2">Seuil du mois</dt>
                                <dd class="text-right font-semibold">
                                    {{ tableau.signal.seuil }}
                                </dd>
                                <dt class="text-encre-2">Bonds utilisés</dt>
                                <dd class="text-right font-semibold">
                                    {{ tableau.signal.nombreBonds }}
                                </dd>
                            </dl>
                        </div>
                        <p
                            v-if="
                                tableau.signal.disponible &&
                                tableau.signal.message
                            "
                            class="mt-2 text-[13px] text-encre-2"
                        >
                            {{ tableau.signal.message }}
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
.niveau {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
