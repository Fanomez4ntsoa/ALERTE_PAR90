<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { parametres as routeParametres } from '@/routes/admin';

/** Section Paramètres du calcul : jeu en vigueur, en lecture seule. */

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Paramètres du calcul', href: routeParametres() },
        ],
    },
});

defineProps<{
    enregistre: boolean;
    dateEffet: string | null;
    institution: string;
    parametres: { libelle: string; valeur: string }[];
}>();
</script>

<template>
    <Head title="Paramètres du calcul" />

    <div class="space-y-4">
        <Heading
            variant="small"
            title="Paramètres du calcul"
            description="Jeu en vigueur, en lecture seule. Un changement ne recalcule pas les mois déjà enregistrés."
        />

        <dl class="grid max-w-xl grid-cols-[1fr_auto] gap-x-6 gap-y-2 text-sm">
            <template v-for="p in parametres" :key="p.libelle">
                <dt class="text-muted-foreground">{{ p.libelle }}</dt>
                <dd class="text-right font-medium tabular-nums">
                    {{ p.valeur }}
                </dd>
            </template>
            <dt class="text-muted-foreground">Institution</dt>
            <dd class="text-right font-medium">{{ institution }}</dd>
            <dt class="text-muted-foreground">Date d'effet</dt>
            <dd class="text-right font-medium">
                {{ dateEffet ?? 'au premier import' }}
            </dd>
        </dl>

        <p v-if="!enregistre" class="text-xs text-muted-foreground">
            Aucun jeu n'est encore enregistré : ces valeurs viennent de la
            configuration et seront enregistrées au premier import.
        </p>
    </div>
</template>
