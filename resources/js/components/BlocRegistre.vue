<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import LigneDecision from '@/components/LigneDecision.vue';
import type { Registre } from '@/types/registre';

/** Bloc 6 : registre des décisions et signal émis trois mois plus tôt (section 9). */
const props = defineProps<{ registre: Registre; mois: string }>();

const ajout = useForm({ action: '', responsable: '', echeance: '' });

const fondNiveau: Record<string, string> = {
    vert: 'bg-emerald-700 text-white',
    jaune: 'bg-amber-300 text-neutral-950',
    rouge: 'bg-red-700 text-white',
    gris: 'bg-neutral-500 text-white',
};

function ajouter(): void {
    ajout.post(props.registre.urlAjout, {
        preserveScroll: true,
        onSuccess: () => ajout.reset(),
    });
}
</script>

<template>
    <section
        class="bloc col-span-12 print:break-before-page"
        aria-labelledby="titre-bloc-6"
    >
        <div class="flex items-baseline">
            <h2 id="titre-bloc-6" class="titre-bloc mr-auto">
                6 · Registre
                <span>Qu'a-t-on décidé, et avait-on raison ?</span>
            </h2>
            <Link
                :href="registre.urlRecapitulatif"
                class="text-sm text-blue-700 underline print:hidden"
                >Tableau récapitulatif de tous les mois →</Link
            >
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- a. Décisions de la clôture affichée -->
            <div>
                <h3 class="mb-1 text-sm font-semibold">
                    Décisions de {{ mois }}
                </h3>
                <ul
                    v-if="registre.decisions.length"
                    class="flex flex-col gap-2"
                >
                    <LigneDecision
                        v-for="d in registre.decisions"
                        :key="d.id"
                        :decision="d"
                    />
                </ul>
                <p v-else class="text-sm text-neutral-500">
                    Aucune décision inscrite pour ce mois.
                </p>

                <form
                    class="mt-3 grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 rounded-md border border-dashed border-neutral-300 p-3 text-sm print:hidden"
                    @submit.prevent="ajouter"
                >
                    <p
                        class="col-span-2 text-xs font-semibold text-neutral-700"
                    >
                        Ajouter une décision
                    </p>
                    <div class="col-span-2 flex flex-col gap-0.5">
                        <label for="decision-action" class="text-xs"
                            >Action décidée</label
                        >
                        <textarea
                            id="decision-action"
                            v-model="ajout.action"
                            rows="2"
                            class="rounded border border-neutral-300 px-2 py-1"
                        />
                        <p
                            v-if="ajout.errors.action"
                            class="text-xs text-red-700"
                        >
                            {{ ajout.errors.action }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label for="decision-responsable" class="text-xs"
                            >Responsable</label
                        >
                        <input
                            id="decision-responsable"
                            v-model="ajout.responsable"
                            type="text"
                            class="rounded border border-neutral-300 px-2 py-1"
                        />
                        <p
                            v-if="ajout.errors.responsable"
                            class="text-xs text-red-700"
                        >
                            {{ ajout.errors.responsable }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <label for="decision-echeance" class="text-xs"
                            >Échéance (jj/mm/aaaa)</label
                        >
                        <input
                            id="decision-echeance"
                            v-model="ajout.echeance"
                            type="text"
                            inputmode="numeric"
                            placeholder="jj/mm/aaaa"
                            maxlength="10"
                            class="rounded border border-neutral-300 px-2 py-1"
                        />
                        <p
                            v-if="ajout.errors.echeance"
                            class="text-xs text-red-700"
                        >
                            {{ ajout.errors.echeance }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="ajout.processing"
                        class="col-span-2 mt-1 justify-self-start rounded bg-neutral-800 px-3 py-1.5 text-xs text-white disabled:opacity-50"
                    >
                        Inscrire la décision
                    </button>
                </form>
            </div>

            <!-- b. Signal émis trois mois plus tôt -->
            <div>
                <h3 class="mb-1 text-sm font-semibold">
                    Signal émis en {{ registre.moisSignal }}
                </h3>
                <p v-if="!registre.signal" class="text-sm text-neutral-500">
                    Aucune clôture en {{ registre.moisSignal }}.
                </p>
                <template v-else>
                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm"
                    >
                        <dt class="text-neutral-600">Niveau d'alors</dt>
                        <dd>
                            <span
                                class="niveau rounded px-2 py-0.5 text-xs font-semibold"
                                :class="fondNiveau[registre.signal.couleur]"
                                >{{ registre.signal.niveau }}</span
                            >
                            <Link
                                :href="registre.signal.url"
                                class="ml-2 text-xs text-blue-700 underline print:hidden"
                                >voir {{ registre.signal.mois }}</Link
                            >
                        </dd>
                        <dt class="text-neutral-600">
                            PAR90 en {{ registre.signal.mois }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ registre.signal.par90Alors }}
                        </dd>
                        <dt class="text-neutral-600">PAR90 en {{ mois }}</dt>
                        <dd class="tabular-nums">
                            {{ registre.signal.par90Aujourdhui }}
                        </dd>
                        <dt class="text-neutral-600">Hausse réelle</dt>
                        <dd class="font-semibold tabular-nums">
                            {{ registre.signal.hausseReelle }}
                        </dd>
                        <dt class="text-neutral-600">Résultat</dt>
                        <dd class="font-semibold">
                            {{ registre.signal.resultat }}
                        </dd>
                    </dl>
                    <h4
                        class="mt-2 mb-1 text-xs font-semibold text-neutral-700"
                    >
                        Décisions prises en {{ registre.signal.mois }}
                    </h4>
                    <ul
                        v-if="registre.signal.decisions.length"
                        class="flex flex-col gap-2"
                    >
                        <LigneDecision
                            v-for="d in registre.signal.decisions"
                            :key="d.id"
                            :decision="d"
                        />
                    </ul>
                    <p v-else class="text-sm text-neutral-500">
                        Aucune décision inscrite à l'époque.
                    </p>
                </template>
            </div>
        </div>
    </section>
</template>

<style scoped>
@reference '../../css/app.css';

.bloc {
    @apply rounded-lg border border-neutral-200 bg-white px-4 py-1.5 print:break-inside-avoid print:px-2 print:py-1;
}

.titre-bloc {
    @apply mb-1 text-sm font-semibold text-neutral-800;
}

.titre-bloc span {
    @apply ml-2 font-normal text-neutral-500;
}

.niveau {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
