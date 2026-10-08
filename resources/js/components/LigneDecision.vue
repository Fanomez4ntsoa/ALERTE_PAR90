<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import type { DecisionAffichee } from '@/types/registre';

/** Une décision du registre : lecture, et selon les droits compléter, corriger ou annuler. */
const props = defineProps<{ decision: DecisionAffichee }>();

const ouvert = ref<'completer' | 'corriger' | 'annuler' | null>(null);
const complement = useForm({ resultat: '' });
const correction = useForm({ resultat: props.decision.resultat ?? '' });
const annulation = useForm({ motif: '' });

const styleEtat: Record<DecisionAffichee['etat'], string> = {
    ouverte: 'border-encre-2/60 text-encre',
    completee: 'border-encre bg-encre text-feuille',
    annulee: 'border-filet text-encre-2 line-through',
};

const options = {
    preserveScroll: true,
    onSuccess: () => (ouvert.value = null),
};

function envoyer(): void {
    const d = props.decision;
    if (ouvert.value === 'completer' && d.urlCompleter) {
        complement.patch(d.urlCompleter, options);
    } else if (ouvert.value === 'corriger' && d.urlCorriger) {
        correction.patch(d.urlCorriger, options);
    } else if (ouvert.value === 'annuler' && d.urlAnnuler) {
        annulation.patch(d.urlAnnuler, options);
    }
}

const champ =
    'w-full rounded-md border border-filet bg-feuille px-2.5 py-1.5 text-sm text-encre focus-visible:outline-2 focus-visible:outline-prusse';
const lien =
    'text-[13px] font-medium text-prusse underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-prusse';
</script>

<template>
    <li
        class="border-b border-filet py-3 last:border-0"
        :class="{ 'text-encre-2': decision.etat === 'annulee' }"
    >
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-[15px] leading-snug font-medium">
                    {{ decision.action }}
                </p>
                <p class="chiffres mt-0.5 text-[13px] text-encre-2">
                    Responsable : {{ decision.responsable }}, échéance le
                    {{ decision.echeance }}
                </p>
                <p v-if="decision.resultat" class="mt-1 text-sm">
                    <span class="font-semibold">Résultat :</span>
                    {{ decision.resultat }}
                </p>
                <p v-if="decision.motifAnnulation" class="mt-1 text-sm">
                    <span class="font-semibold">Motif d'annulation :</span>
                    {{ decision.motifAnnulation }}
                </p>
                <p class="chiffres mt-1 text-[12px] text-encre-2">
                    {{ decision.saisie }}.
                    <template v-if="decision.complement">
                        {{ decision.complement }}.</template
                    >
                    <template v-if="decision.annulation">
                        {{ decision.annulation }}.</template
                    >
                </p>
            </div>
            <span
                class="shrink-0 rounded border px-2 py-0.5 text-[12px] font-medium"
                :class="styleEtat[decision.etat]"
                >{{ decision.libelleEtat }}</span
            >
        </div>

        <div
            v-if="
                decision.urlCompleter ||
                decision.urlCorriger ||
                decision.urlAnnuler
            "
            class="mt-1.5 flex gap-4 print:hidden"
        >
            <button
                v-if="decision.urlCompleter"
                type="button"
                :class="lien"
                @click="ouvert = ouvert === 'completer' ? null : 'completer'"
            >
                Compléter
            </button>
            <button
                v-if="decision.urlCorriger"
                type="button"
                :class="lien"
                @click="ouvert = ouvert === 'corriger' ? null : 'corriger'"
            >
                Corriger le résultat
            </button>
            <button
                v-if="decision.urlAnnuler"
                type="button"
                :class="lien"
                @click="ouvert = ouvert === 'annuler' ? null : 'annuler'"
            >
                Annuler la décision
            </button>
        </div>

        <form
            v-if="ouvert"
            class="mt-2 flex flex-col gap-1.5 print:hidden"
            @submit.prevent="envoyer"
        >
            <label
                :for="`saisie-${decision.id}`"
                class="text-[13px] font-medium"
                >{{
                    ouvert === 'completer'
                        ? 'Résultat'
                        : ouvert === 'corriger'
                          ? "Résultat corrigé (l'ancien reste dans le journal)"
                          : "Motif d'annulation (obligatoire)"
                }}</label
            >
            <textarea
                v-if="ouvert === 'completer'"
                :id="`saisie-${decision.id}`"
                v-model="complement.resultat"
                rows="2"
                :class="champ"
            />
            <textarea
                v-else-if="ouvert === 'corriger'"
                :id="`saisie-${decision.id}`"
                v-model="correction.resultat"
                rows="2"
                :class="champ"
            />
            <textarea
                v-else
                :id="`saisie-${decision.id}`"
                v-model="annulation.motif"
                rows="2"
                :class="champ"
            />
            <InputError
                :message="
                    ouvert === 'completer'
                        ? complement.errors.resultat
                        : ouvert === 'corriger'
                          ? correction.errors.resultat
                          : annulation.errors.motif
                "
            />
            <div class="flex gap-3">
                <button
                    type="submit"
                    class="rounded-md bg-prusse px-3 py-1.5 text-[13px] font-medium text-feuille disabled:opacity-50"
                    :disabled="
                        complement.processing ||
                        correction.processing ||
                        annulation.processing
                    "
                >
                    {{
                        ouvert === 'completer'
                            ? 'Enregistrer le résultat'
                            : ouvert === 'corriger'
                              ? 'Enregistrer la correction'
                              : "Confirmer l'annulation"
                    }}
                </button>
                <button type="button" :class="lien" @click="ouvert = null">
                    Fermer
                </button>
            </div>
        </form>
    </li>
</template>
