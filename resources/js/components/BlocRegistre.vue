<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import LigneDecision from '@/components/LigneDecision.vue';
import type { Registre } from '@/types/registre';
import { aplatNiveau } from '@/types/tableau';

/*
 * Bloc 6 : en haut, le signal émis trois mois plus tôt en bandeau compact ;
 * en dessous, les décisions du mois puis le formulaire d'ajout, juste sous
 * la liste. Seule la liste défile, et seulement quand elle déborde.
 */
const props = defineProps<{ registre: Registre; mois: string }>();

const ajout = useForm({ action: '', responsable: '', echeance: '' });

function ajouter(): void {
    if (!props.registre.urlAjout) {
        return;
    }
    ajout.post(props.registre.urlAjout, {
        preserveScroll: true,
        onSuccess: () => ajout.reset(),
    });
}

const champ =
    'w-full rounded-md border border-filet bg-feuille px-2.5 py-1.5 text-sm text-encre placeholder:text-encre-2/70 focus-visible:outline-2 focus-visible:outline-prusse';
</script>

<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <!-- Signal émis trois mois plus tôt -->
        <section
            class="shrink-0 rounded-[10px] bg-feuille px-6 py-4"
            aria-labelledby="titre-signal-passe"
        >
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                <h2 id="titre-signal-passe" class="text-[15px] font-semibold">
                    Signal émis en {{ registre.moisSignal }}
                    <span class="ml-2 font-normal text-encre-2"
                        >Avait-on raison ?</span
                    >
                </h2>
                <Link
                    v-if="registre.signal"
                    :href="registre.signal.url"
                    class="text-[13px] font-medium text-prusse underline-offset-2 hover:underline"
                    >Voir {{ registre.signal.mois }}</Link
                >
                <Link
                    :href="registre.urlRecapitulatif"
                    class="ml-auto text-[13px] font-medium text-prusse underline-offset-2 hover:underline"
                    >Récapitulatif de tous les mois</Link
                >
            </div>

            <p v-if="!registre.signal" class="mt-2 text-sm text-encre-2">
                Aucune clôture en {{ registre.moisSignal }}.
            </p>
            <template v-else>
                <dl class="chiffres mt-3 grid grid-cols-5 gap-x-6 text-sm">
                    <div>
                        <dt class="text-[12px] text-encre-2">Niveau d'alors</dt>
                        <dd class="mt-1">
                            <span
                                class="niveau rounded px-2 py-0.5 text-[13px] font-semibold"
                                :class="aplatNiveau[registre.signal.couleur]"
                                >{{ registre.signal.niveau }}</span
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-encre-2">
                            PAR90 en {{ registre.signal.mois }}
                        </dt>
                        <dd class="mt-1 text-[15px] font-medium">
                            {{ registre.signal.par90Alors }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-encre-2">
                            PAR90 en {{ mois }}
                        </dt>
                        <dd class="mt-1 text-[15px] font-medium">
                            {{ registre.signal.par90Aujourdhui }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-encre-2">Hausse réelle</dt>
                        <dd class="mt-1 text-[15px] font-semibold">
                            {{ registre.signal.hausseReelle }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12px] text-encre-2">Résultat</dt>
                        <dd class="mt-1 text-[15px] font-semibold">
                            {{ registre.signal.resultat }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3 border-t border-filet pt-2 text-[13px]">
                    <span class="text-encre-2"
                        >Décisions prises en {{ registre.signal.mois }} :</span
                    >
                    <span v-if="!registre.signal.decisions.length" class="ml-1"
                        >aucune.</span
                    >
                    <ul v-else class="mt-1 flex flex-col gap-0.5">
                        <li
                            v-for="d in registre.signal.decisions"
                            :key="d.id"
                            :class="{
                                'text-encre-2 line-through':
                                    d.etat === 'annulee',
                            }"
                        >
                            {{ d.action }}
                            <span class="text-encre-2"
                                >({{ d.libelleEtat.toLowerCase()
                                }}<template v-if="d.resultat"
                                    >, résultat : {{ d.resultat }}</template
                                >)</span
                            >
                        </li>
                    </ul>
                </div>
            </template>
        </section>

        <!-- Décisions du mois affiché -->
        <section
            class="flex min-h-0 flex-col rounded-[10px] bg-feuille px-6 py-4"
            aria-labelledby="titre-decisions"
        >
            <h2 id="titre-decisions" class="text-[15px] font-semibold">
                Décisions de {{ mois }}
                <span class="ml-2 font-normal text-encre-2"
                    >Qu'a-t-on décidé ?</span
                >
            </h2>

            <div class="mt-1 min-h-0 overflow-y-auto pr-2">
                <ul v-if="registre.decisions.length">
                    <LigneDecision
                        v-for="d in registre.decisions"
                        :key="d.id"
                        :decision="d"
                    />
                </ul>
                <p v-else class="py-2 text-sm text-encre-2">
                    Aucune décision inscrite pour ce mois.
                </p>
            </div>

            <form
                v-if="registre.urlAjout"
                class="mt-3 shrink-0 border-t border-filet pt-3 print:hidden"
                @submit.prevent="ajouter"
            >
                <p class="text-[13px] font-semibold">Inscrire une décision</p>
                <div
                    class="mt-2 grid grid-cols-[minmax(0,1fr)_14rem_9.5rem_auto] items-start gap-3"
                >
                    <div class="flex flex-col gap-1">
                        <label
                            for="decision-action"
                            class="text-[12px] text-encre-2"
                            >Action décidée</label
                        >
                        <input
                            id="decision-action"
                            v-model="ajout.action"
                            type="text"
                            :class="champ"
                        />
                        <InputError :message="ajout.errors.action" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            for="decision-responsable"
                            class="text-[12px] text-encre-2"
                            >Responsable</label
                        >
                        <input
                            id="decision-responsable"
                            v-model="ajout.responsable"
                            type="text"
                            :class="champ"
                        />
                        <InputError :message="ajout.errors.responsable" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            for="decision-echeance"
                            class="text-[12px] text-encre-2"
                            >Échéance</label
                        >
                        <input
                            id="decision-echeance"
                            v-model="ajout.echeance"
                            type="text"
                            inputmode="numeric"
                            placeholder="jj/mm/aaaa"
                            maxlength="10"
                            :class="champ"
                        />
                        <InputError :message="ajout.errors.echeance" />
                    </div>
                    <button
                        type="submit"
                        :disabled="ajout.processing"
                        class="mt-[22px] rounded-md bg-prusse px-4 py-1.5 text-sm font-medium text-feuille disabled:opacity-50"
                    >
                        Inscrire
                    </button>
                </div>
            </form>
        </section>
    </div>
</template>

<style scoped>
.niveau {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
