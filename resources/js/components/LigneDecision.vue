<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { DecisionAffichee } from '@/types/registre';

/** Une décision du registre : affichage, et au besoin compléter ou annuler. */
const props = defineProps<{ decision: DecisionAffichee }>();

const ouvert = ref<'completer' | 'corriger' | 'annuler' | null>(null);
const complement = useForm({ resultat: '' });
const correction = useForm({ resultat: props.decision.resultat ?? '' });
const annulation = useForm({ motif: '' });

const styleEtat: Record<DecisionAffichee['etat'], string> = {
    ouverte: 'border-neutral-400 text-neutral-700',
    completee: 'border-neutral-700 bg-neutral-800 text-white',
    annulee: 'border-neutral-300 text-neutral-500 line-through',
};

function completer(): void {
    if (props.decision.urlCompleter) {
        complement.patch(props.decision.urlCompleter, {
            preserveScroll: true,
            onSuccess: () => (ouvert.value = null),
        });
    }
}

function corriger(): void {
    if (props.decision.urlCorriger) {
        correction.patch(props.decision.urlCorriger, {
            preserveScroll: true,
            onSuccess: () => (ouvert.value = null),
        });
    }
}

function annuler(): void {
    if (props.decision.urlAnnuler) {
        annulation.patch(props.decision.urlAnnuler, {
            preserveScroll: true,
            onSuccess: () => (ouvert.value = null),
        });
    }
}
</script>

<template>
    <li
        class="rounded-md border border-neutral-200 px-3 py-2 text-sm"
        :class="{ 'bg-neutral-50': decision.etat === 'annulee' }"
    >
        <div class="flex items-start gap-2">
            <p
                class="flex-1 font-medium"
                :class="{ 'text-neutral-500': decision.etat === 'annulee' }"
            >
                {{ decision.action }}
            </p>
            <span
                class="rounded border px-1.5 py-0.5 text-xs whitespace-nowrap"
                :class="styleEtat[decision.etat]"
                >{{ decision.libelleEtat }}</span
            >
        </div>
        <p class="text-xs text-neutral-600">
            Responsable : {{ decision.responsable }} · échéance
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
        <p class="mt-1 text-[11px] text-neutral-500">
            {{ decision.saisie }}
            <template v-if="decision.complement">
                · {{ decision.complement }}</template
            >
            <template v-if="decision.annulation">
                · {{ decision.annulation }}</template
            >
        </p>

        <div
            v-if="
                decision.urlCompleter ||
                decision.urlCorriger ||
                decision.urlAnnuler
            "
            class="mt-1 flex gap-3 text-xs print:hidden"
        >
            <button
                v-if="decision.urlCompleter"
                type="button"
                class="underline hover:text-neutral-900"
                @click="ouvert = ouvert === 'completer' ? null : 'completer'"
            >
                Compléter
            </button>
            <button
                v-if="decision.urlCorriger"
                type="button"
                class="underline hover:text-neutral-900"
                @click="ouvert = ouvert === 'corriger' ? null : 'corriger'"
            >
                Corriger le résultat
            </button>
            <button
                v-if="decision.urlAnnuler"
                type="button"
                class="underline hover:text-neutral-900"
                @click="ouvert = ouvert === 'annuler' ? null : 'annuler'"
            >
                Annuler
            </button>
        </div>

        <form
            v-if="ouvert === 'completer'"
            class="mt-2 flex flex-col gap-1 print:hidden"
            @submit.prevent="completer"
        >
            <label :for="`resultat-${decision.id}`" class="text-xs font-medium"
                >Résultat</label
            >
            <textarea
                :id="`resultat-${decision.id}`"
                v-model="complement.resultat"
                rows="2"
                class="rounded border border-neutral-300 px-2 py-1 text-sm"
            />
            <p v-if="complement.errors.resultat" class="text-xs text-red-700">
                {{ complement.errors.resultat }}
            </p>
            <button
                type="submit"
                :disabled="complement.processing"
                class="self-start rounded bg-neutral-800 px-3 py-1 text-xs text-white disabled:opacity-50"
            >
                Enregistrer le résultat
            </button>
        </form>

        <form
            v-if="ouvert === 'corriger'"
            class="mt-2 flex flex-col gap-1 print:hidden"
            @submit.prevent="corriger"
        >
            <label
                :for="`correction-${decision.id}`"
                class="text-xs font-medium"
                >Résultat corrigé (l'ancien reste dans le journal)</label
            >
            <textarea
                :id="`correction-${decision.id}`"
                v-model="correction.resultat"
                rows="2"
                class="rounded border border-neutral-300 px-2 py-1 text-sm"
            />
            <p v-if="correction.errors.resultat" class="text-xs text-red-700">
                {{ correction.errors.resultat }}
            </p>
            <button
                type="submit"
                :disabled="correction.processing"
                class="self-start rounded bg-neutral-800 px-3 py-1 text-xs text-white disabled:opacity-50"
            >
                Enregistrer la correction
            </button>
        </form>

        <form
            v-if="ouvert === 'annuler'"
            class="mt-2 flex flex-col gap-1 print:hidden"
            @submit.prevent="annuler"
        >
            <label :for="`motif-${decision.id}`" class="text-xs font-medium"
                >Motif d'annulation (obligatoire)</label
            >
            <textarea
                :id="`motif-${decision.id}`"
                v-model="annulation.motif"
                rows="2"
                class="rounded border border-neutral-300 px-2 py-1 text-sm"
            />
            <p v-if="annulation.errors.motif" class="text-xs text-red-700">
                {{ annulation.errors.motif }}
            </p>
            <button
                type="submit"
                :disabled="annulation.processing"
                class="self-start rounded border border-neutral-500 px-3 py-1 text-xs disabled:opacity-50"
            >
                Annuler la décision
            </button>
        </form>
    </li>
</template>
