<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { journal as routeJournal } from '@/routes/admin';

/** Section Journal, en lecture seule : qui, quand, quelle action, sur quel mois. */

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Journal', href: routeJournal() }],
    },
});

defineProps<{
    entrees: {
        id: number;
        date: string | null;
        qui: string;
        action: string;
        mois: string;
        detail: string;
    }[];
    pagination: {
        page: number;
        pages: number;
        precedente: string | null;
        suivante: string | null;
    };
}>();
</script>

<template>
    <Head title="Journal" />

    <div class="space-y-4">
        <Heading
            variant="small"
            title="Journal des actions"
            description="Imports, refus, annulations et décisions, du plus récent au plus ancien."
        />

        <p v-if="!entrees.length" class="text-sm text-muted-foreground">
            Aucune action enregistrée.
        </p>
        <table v-else class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-muted-foreground">
                    <th class="py-1 font-medium">Quand</th>
                    <th class="py-1 font-medium">Qui</th>
                    <th class="py-1 font-medium">Action</th>
                    <th class="py-1 font-medium">Mois</th>
                    <th class="py-1 font-medium">Détail</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="e in entrees" :key="e.id" class="border-b align-top">
                    <td class="py-1 pr-2 whitespace-nowrap">{{ e.date }}</td>
                    <td class="py-1 pr-2">{{ e.qui }}</td>
                    <td class="py-1 pr-2 whitespace-nowrap">{{ e.action }}</td>
                    <td class="py-1 pr-2">{{ e.mois }}</td>
                    <td class="py-1 text-muted-foreground">{{ e.detail }}</td>
                </tr>
            </tbody>
        </table>

        <div
            v-if="pagination.pages > 1"
            class="flex items-center gap-3 text-sm"
        >
            <Button
                v-if="pagination.precedente"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="pagination.precedente">Plus récentes</Link>
            </Button>
            <span>Page {{ pagination.page }} sur {{ pagination.pages }}</span>
            <Button
                v-if="pagination.suivante"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="pagination.suivante">Plus anciennes</Link>
            </Button>
        </div>
    </div>
</template>
