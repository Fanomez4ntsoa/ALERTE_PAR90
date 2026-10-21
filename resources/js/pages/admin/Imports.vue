<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CircleAlert, FileSpreadsheet, Undo2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { imports as routeImports } from '@/routes/admin';
import { annuler, store } from '@/routes/admin/imports';
import { aplatNiveau } from '@/types/tableau';
import type { TeinteNiveau } from '@/types/tableau';

/** Section Imports (analyste) : envoi, compte rendu, historique, annulation. */

type LigneImport = {
    id: number;
    date: string;
    utilisateur: string;
    fichier: string;
    mois: string;
    mode: string;
    avertissements: number;
    etat: string;
    actif: boolean;
};

type CompteRendu = {
    statut: 'importe' | 'inchange' | 'refuse';
    fichier: string;
    mode?: string | null;
    mois?: { mois: string; niveau: string; couleur: TeinteNiveau }[];
    generations?: number;
    ecartees?: number;
    avertissements?: string[];
    erreurs?: string[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Imports', href: routeImports() }],
    },
});

defineProps<{
    imports: LigneImport[];
    dernier: { id: number; description: string } | null;
}>();

const page = usePage();
const compteRendu = () => page.flash.compteRendu as CompteRendu | undefined;
const annulation = () =>
    page.flash.annulation as
        | { statut: 'annule' | 'refuse'; message: string }
        | undefined;

const formulaire = useForm<{ fichier: File | null }>({ fichier: null });
const confirmation = ref(false);

function choisir(evenement: Event): void {
    formulaire.fichier =
        (evenement.target as HTMLInputElement).files?.[0] ?? null;
}

function envoyer(): void {
    formulaire.post(store().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => formulaire.reset(),
    });
}

function annulerDernier(): void {
    router.post(
        annuler().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => (confirmation.value = false),
        },
    );
}
</script>

<template>
    <Head title="Imports" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Importer un fichier"
            description="Fichier modèle Excel (.xlsx). Seuls les mois absents de la base sont ajoutés ; chaque mois est calculé puis figé."
        />

        <form class="flex flex-wrap items-end gap-3" @submit.prevent="envoyer">
            <div class="grid gap-2">
                <span class="text-sm font-medium">Fichier</span>
                <!-- Libellé en français, quelle que soit la langue du navigateur. -->
                <label
                    for="fichier"
                    class="flex h-9 max-w-md cursor-pointer items-center gap-3 rounded-md border border-filet bg-feuille pr-3 text-sm focus-within:outline-2 focus-within:outline-prusse"
                >
                    <span
                        class="flex h-full items-center gap-2 border-r border-filet px-3 font-medium"
                    >
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Choisir un fichier
                    </span>
                    <span class="truncate text-encre-2">{{
                        formulaire.fichier?.name ?? 'Aucun fichier choisi'
                    }}</span>
                    <input
                        id="fichier"
                        type="file"
                        accept=".xlsx"
                        class="sr-only"
                        @change="choisir"
                    />
                </label>
                <InputError :message="formulaire.errors.fichier" />
            </div>
            <Button
                type="submit"
                :disabled="!formulaire.fichier || formulaire.processing"
                >Importer</Button
            >
        </form>

        <!-- Compte rendu du dernier envoi -->
        <Alert v-if="compteRendu()?.statut === 'refuse'">
            <CircleAlert aria-hidden="true" />
            <AlertTitle class="font-semibold"
                >Fichier refusé : {{ compteRendu()?.fichier }}. Aucune ligne
                créée.</AlertTitle
            >
            <AlertDescription>
                <ul class="list-disc pl-4">
                    <li v-for="(e, i) in compteRendu()?.erreurs" :key="i">
                        {{ e }}
                    </li>
                </ul>
            </AlertDescription>
        </Alert>

        <Alert v-else-if="compteRendu()?.statut === 'inchange'">
            <AlertTitle
                >{{ compteRendu()?.fichier }} : aucun mois ni aucune génération
                à ajouter.</AlertTitle
            >
            <AlertDescription>La base est inchangée.</AlertDescription>
        </Alert>

        <Alert v-else-if="compteRendu()?.statut === 'importe'">
            <AlertTitle
                >{{ compteRendu()?.fichier }} importé ({{
                    compteRendu()?.mode
                }}).</AlertTitle
            >
            <AlertDescription class="space-y-2">
                <p v-if="compteRendu()?.mois?.length">
                    Mois ajoutés :
                    <span
                        v-for="m in compteRendu()?.mois"
                        :key="m.mois"
                        class="mr-2 inline-block"
                        >{{ m.mois }} :
                        <span
                            class="niveau rounded px-1.5 py-px text-[12px] font-semibold"
                            :class="aplatNiveau[m.couleur]"
                            >{{ m.niveau }}</span
                        ></span
                    >
                </p>
                <p>
                    {{ compteRendu()?.generations }} cellule(s) de générations
                    ajoutée(s), {{ compteRendu()?.ecartees }} écartée(s) au-delà
                    du dernier mois.
                </p>
                <div v-if="compteRendu()?.avertissements?.length">
                    <p class="font-medium">Avertissements :</p>
                    <ul class="list-disc pl-4">
                        <li
                            v-for="(a, i) in compteRendu()?.avertissements"
                            :key="i"
                        >
                            {{ a }}
                        </li>
                    </ul>
                </div>
            </AlertDescription>
        </Alert>

        <Alert v-if="annulation()">
            <CircleAlert
                v-if="annulation()?.statut === 'refuse'"
                aria-hidden="true"
            />
            <AlertTitle class="font-semibold">{{
                annulation()?.statut === 'refuse'
                    ? 'Annulation refusée'
                    : 'Import annulé'
            }}</AlertTitle>
            <AlertDescription>{{ annulation()?.message }}</AlertDescription>
        </Alert>

        <Heading
            variant="small"
            title="Historique des imports"
            description="Du plus récent au plus ancien."
        />

        <p v-if="!imports.length" class="text-sm text-muted-foreground">
            Aucun import.
        </p>
        <table v-else class="w-full text-sm">
            <thead>
                <tr class="border-b text-left text-xs text-muted-foreground">
                    <th class="py-1 font-medium">Date</th>
                    <th class="py-1 font-medium">Utilisateur</th>
                    <th class="py-1 font-medium">Fichier</th>
                    <th class="py-1 font-medium">Mois concernés</th>
                    <th class="py-1 font-medium">État</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="i in imports" :key="i.id" class="border-b align-top">
                    <td class="py-1 pr-2 whitespace-nowrap">{{ i.date }}</td>
                    <td class="py-1 pr-2">{{ i.utilisateur }}</td>
                    <td class="py-1 pr-2">
                        {{ i.fichier }}
                        <span class="block text-xs text-muted-foreground"
                            >{{ i.mode }},
                            {{ i.avertissements }} avertissement(s)</span
                        >
                    </td>
                    <td class="py-1 pr-2">{{ i.mois }}</td>
                    <td class="py-1">
                        <Badge :variant="i.actif ? 'secondary' : 'outline'">{{
                            i.etat
                        }}</Badge>
                    </td>
                </tr>
            </tbody>
        </table>

        <div v-if="dernier" class="space-y-2">
            <Heading
                variant="small"
                title="Annuler le dernier import"
                description="Ses mois et ses générations sont retirés, ainsi que les vérifications qui en dépendent. Refusé si une décision est inscrite sur l'un de ses mois."
            />
            <Dialog v-model:open="confirmation">
                <DialogTrigger as-child>
                    <Button variant="outline">
                        <Undo2 class="size-4" aria-hidden="true" />
                        Annuler le dernier import
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Annuler cet import ?</DialogTitle>
                        <DialogDescription>{{
                            dernier.description
                        }}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="ghost">Garder cet import</Button>
                        </DialogClose>
                        <Button variant="outline" @click="annulerDernier">
                            <Undo2 class="size-4" aria-hidden="true" />
                            Annuler cet import
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>

<style scoped>
.niveau {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
