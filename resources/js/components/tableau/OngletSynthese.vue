<script setup lang="ts">
import CourbePar90 from '@/components/CourbePar90.vue';
import { aplatNiveau } from '@/types/tableau';
import type { Tableau } from '@/types/tableau';

/*
 * Synthèse : blocs 1 à 4 sur un écran. Le niveau domine (colonne de verdict),
 * le statut 5 % est en contour juste en dessous, distinct du niveau ; à droite,
 * une seule feuille découpée selon les questions du mémoire.
 */
defineProps<{ tableau: Tableau }>();
</script>

<template>
    <div class="grid h-full min-h-0 grid-cols-12 gap-4">
        <!-- Bloc 1 : faut-il ouvrir une revue ? -->
        <section
            class="col-span-4 flex min-h-0 flex-col gap-3"
            aria-labelledby="titre-verdict"
        >
            <div
                class="niveau flex min-h-0 flex-1 flex-col rounded-[10px] px-7 py-6"
                :class="aplatNiveau[tableau.bandeau.couleur]"
            >
                <h2 id="titre-verdict" class="text-sm font-medium opacity-85">
                    Niveau d'alerte de {{ tableau.mois.libelle }}
                </h2>
                <p
                    class="mt-1 text-[clamp(4.5rem,11vh,8.5rem)] leading-[0.95] font-bold tracking-[-0.03em]"
                >
                    {{ tableau.bandeau.libelle }}
                </p>
                <p
                    class="mt-5 text-[clamp(19px,2.6vh,28px)] leading-snug font-medium"
                >
                    {{ tableau.bandeau.motif }}
                </p>

                <div class="mt-auto border-t border-current/25 pt-4">
                    <p class="text-sm font-medium opacity-85">
                        Action attendue
                    </p>
                    <p class="mt-1 text-[15px] leading-snug">
                        {{ tableau.bandeau.action }}
                    </p>
                    <p
                        v-if="tableau.bandeau.mention"
                        class="mt-3 text-[13px] opacity-80"
                    >
                        Niveau établi en {{ tableau.bandeau.mention }}
                    </p>
                </div>
            </div>

            <div
                class="rounded-[10px] border-2 border-dashed border-encre-2/50 bg-feuille px-5 py-3"
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
        </section>

        <div class="col-span-8 flex min-h-0 flex-col rounded-[10px] bg-feuille">
            <!-- Bloc 2 : où en est le risque ? -->
            <section
                class="flex min-h-0 flex-1 flex-col px-6 pt-4 pb-3"
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

            <div class="grid grid-cols-2 border-t border-filet">
                <!-- Bloc 3 : trajectoire -->
                <section
                    class="border-r border-filet px-6 py-4"
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
                    <p class="chiffres mt-3 flex items-baseline gap-3">
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
                </section>

                <!-- Bloc 4 : signal avancé -->
                <section class="px-6 py-4" aria-labelledby="titre-signal">
                    <h2 id="titre-signal" class="text-[15px] font-semibold">
                        Signal avancé
                    </h2>
                    <p class="text-[13px] text-encre-2">
                        Une hausse se prépare-t-elle ?
                    </p>
                    <p
                        v-if="!tableau.signal.disponible"
                        class="mt-3 text-[15px] font-medium"
                    >
                        {{ tableau.signal.message }}
                        <span
                            class="chiffres block text-[13px] font-normal text-encre-2"
                            >{{ tableau.signal.libelleBonds }}</span
                        >
                    </p>
                    <div v-else class="mt-3 flex items-center gap-6">
                        <div class="flex items-center gap-3">
                            <span
                                class="niveau inline-block size-7 rounded-full border-2"
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
                            tableau.signal.disponible && tableau.signal.message
                        "
                        class="mt-2 text-[13px] text-encre-2"
                    >
                        {{ tableau.signal.message }}
                    </p>
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
