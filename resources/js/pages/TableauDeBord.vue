<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import CourbePar90 from '@/components/CourbePar90.vue';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

/*
 * Tableau de bord d'une clôture (sections 6 et 8), blocs 1 à 5.
 * Toutes les valeurs arrivent calculées et mises en forme par le serveur :
 * cette page ne compare rien et ne calcule rien, elle affiche.
 */

type LienMois = { valeur: string; libelle: string; url: string };

type Indicateur = {
    libelle: string;
    valeur: string;
    precedente: string;
    evolution: 'hausse' | 'baisse' | 'stable' | null;
    symbole: string;
};

type Tableau = {
    mois: { valeur: string; libelle: string };
    bandeau: {
        niveau: string;
        libelle: string;
        couleur: 'vert' | 'jaune' | 'rouge' | 'gris';
        motif: string;
        action: string;
        statut: { code: string | null; libelle: string; texte: string | null };
        mention: string | null;
    };
    risque: {
        par90: string;
        montant: string;
        limite: string;
        courbe: {
            etiquettes: string[];
            valeurs: number[];
            infobulles: string[];
            limite: number;
            libelleLimite: string;
            indexMois: number | null;
            couleurs: string[];
            legende: { libelle: string; couleur: string }[];
        };
    };
    trajectoire: {
        hausse: string;
        seuil: string;
        atteint: boolean;
        texte: string;
        jauge: { hausse: number; seuil: number; zero: number } | null;
    };
    signal: {
        disponible: boolean;
        message: string | null;
        nombreBonds: number;
        libelleBonds?: string;
        bond?: string;
        seuil?: string;
        voyant?: boolean;
        libelleVoyant?: string;
    };
    comprehension: { moisPrecedent: string; indicateurs: Indicateur[] };
    generations: {
        ages: number[];
        lignes: { mois: string; cellules: (string | null)[] }[];
    };
};

const props = defineProps<{
    institution: string;
    navigation: {
        precedent: LienMois | null;
        suivant: LienMois | null;
        liste: LienMois[];
    } | null;
    tableau: Tableau | null;
}>();

const page = usePage();
const utilisateur = computed(
    () => page.props.auth?.user?.name as string | undefined,
);

const fondNiveau: Record<string, string> = {
    vert: 'bg-emerald-700 text-white',
    jaune: 'bg-amber-300 text-neutral-950',
    rouge: 'bg-red-700 text-white',
    gris: 'bg-neutral-500 text-white',
};

const libelleEvolution: Record<string, string> = {
    hausse: 'en hausse',
    baisse: 'en baisse',
    stable: 'stable',
};

function changerMois(evenement: Event): void {
    const valeur = (evenement.target as HTMLSelectElement).value;
    const lien = props.navigation?.liste.find((m) => m.valeur === valeur);
    if (lien) {
        router.visit(lien.url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head
        :title="
            tableau
                ? `Tableau de bord — ${tableau.mois.libelle}`
                : 'Tableau de bord'
        "
    />

    <div class="tableau min-h-screen bg-neutral-50 text-neutral-900">
        <!-- En-tête : titre, sélecteur de mois, compte -->
        <header
            class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-neutral-200 bg-white px-5 py-1.5 print:border-0 print:bg-transparent print:px-0 print:py-0"
        >
            <div class="mr-auto leading-tight">
                <p class="text-base font-semibold">Alerte précoce PAR90</p>
                <p class="text-xs text-neutral-600">{{ institution }}</p>
            </div>

            <nav
                v-if="navigation && tableau"
                class="flex items-center gap-1 print:hidden"
                aria-label="Choix du mois de clôture"
            >
                <Link
                    v-if="navigation.precedent"
                    :href="navigation.precedent.url"
                    preserve-scroll
                    class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm hover:bg-neutral-100"
                    :title="`Mois précédent : ${navigation.precedent.libelle}`"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" /> Précédent
                </Link>
                <span
                    v-else
                    class="inline-flex items-center rounded-md border border-neutral-200 px-2 py-1 text-sm text-neutral-400"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" /> Précédent
                </span>

                <label class="sr-only" for="choix-mois">Mois de clôture</label>
                <select
                    id="choix-mois"
                    :value="tableau.mois.valeur"
                    class="rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm font-medium"
                    @change="changerMois"
                >
                    <option
                        v-for="m in navigation.liste"
                        :key="m.valeur"
                        :value="m.valeur"
                    >
                        {{ m.libelle }}
                    </option>
                </select>

                <Link
                    v-if="navigation.suivant"
                    :href="navigation.suivant.url"
                    preserve-scroll
                    class="inline-flex items-center rounded-md border border-neutral-300 bg-white px-2 py-1 text-sm hover:bg-neutral-100"
                    :title="`Mois suivant : ${navigation.suivant.libelle}`"
                >
                    Suivant <ChevronRight class="size-4" aria-hidden="true" />
                </Link>
                <span
                    v-else
                    class="inline-flex items-center rounded-md border border-neutral-200 px-2 py-1 text-sm text-neutral-400"
                >
                    Suivant <ChevronRight class="size-4" aria-hidden="true" />
                </span>
            </nav>

            <div
                class="flex items-center gap-3 text-sm text-neutral-600 print:hidden"
            >
                <span v-if="utilisateur">{{ utilisateur }}</span>
                <Link
                    :href="editProfile()"
                    class="inline-flex items-center gap-1 hover:text-neutral-900"
                    title="Paramètres du compte"
                >
                    <Settings class="size-4" aria-hidden="true" /><span
                        class="sr-only"
                        >Paramètres</span
                    >
                </Link>
                <Link
                    :href="logout()"
                    as="button"
                    class="inline-flex items-center gap-1 hover:text-neutral-900"
                >
                    <LogOut class="size-4" aria-hidden="true" /> Déconnexion
                </Link>
            </div>
        </header>

        <main v-if="!tableau" class="px-5 py-10 text-center text-neutral-600">
            <p class="text-lg font-medium">Aucune clôture importée.</p>
            <p class="mt-1 text-sm">
                Chargez le fichier modèle avec
                <code>php artisan alerte:importer</code>.
            </p>
        </main>

        <main
            v-else
            class="grid grid-cols-12 gap-2 px-5 py-2 print:gap-2 print:px-0 print:py-2"
        >
            <!-- Bloc 1 : bandeau -->
            <section
                class="col-span-12 flex flex-wrap items-stretch gap-2"
                aria-labelledby="titre-bloc-1"
            >
                <h2 id="titre-bloc-1" class="sr-only">
                    Faut-il ouvrir une revue ?
                </h2>
                <div
                    class="niveau flex min-w-44 flex-col justify-center rounded-lg px-5 py-2"
                    :class="fondNiveau[tableau.bandeau.couleur]"
                >
                    <p
                        class="text-xs font-medium tracking-wide uppercase opacity-90"
                    >
                        Niveau — {{ tableau.mois.libelle }}
                    </p>
                    <p class="text-3xl leading-tight font-bold">
                        {{ tableau.bandeau.libelle }}
                    </p>
                </div>

                <div
                    class="flex min-w-72 flex-1 flex-col justify-center rounded-lg border border-neutral-200 bg-white px-4 py-1.5"
                >
                    <p class="text-base font-medium">
                        {{ tableau.bandeau.motif }}
                    </p>
                    <p class="mt-1 text-sm text-neutral-700">
                        <span class="font-semibold">Action attendue :</span>
                        {{ tableau.bandeau.action }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-500">
                        {{ institution
                        }}<span class="mx-1.5" aria-hidden="true">·</span
                        >clôture de
                        {{ tableau.mois.libelle }}
                        <span
                            v-if="tableau.bandeau.mention"
                            class="ml-1 rounded border border-neutral-300 px-1.5 py-0.5 text-neutral-600 italic"
                            >{{ tableau.bandeau.mention }}</span
                        >
                    </p>
                </div>

                <div
                    class="flex w-72 flex-col justify-center rounded-lg border-2 border-dashed border-neutral-400 bg-white px-4 py-1.5"
                >
                    <p
                        class="text-xs font-medium tracking-wide text-neutral-500 uppercase"
                    >
                        Statut / limite {{ tableau.risque.limite }}
                    </p>
                    <p class="text-lg font-semibold">
                        {{ tableau.bandeau.statut.libelle }}
                    </p>
                    <p
                        v-if="tableau.bandeau.statut.texte"
                        class="text-xs text-neutral-700"
                    >
                        {{ tableau.bandeau.statut.texte }}
                    </p>
                </div>
            </section>

            <!-- Bloc 2 : niveau de risque -->
            <section
                class="bloc col-span-12 lg:col-span-7 print:col-span-7"
                aria-labelledby="titre-bloc-2"
            >
                <h2 id="titre-bloc-2" class="titre-bloc">
                    2 · Niveau de risque <span>Où en est le risque ?</span>
                </h2>
                <div class="flex items-baseline gap-6">
                    <p>
                        <span class="text-2xl font-bold tabular-nums">{{
                            tableau.risque.par90
                        }}</span>
                        <span class="ml-1.5 text-sm text-neutral-600"
                            >PAR90</span
                        >
                    </p>
                    <p>
                        <span class="text-xl font-semibold tabular-nums">{{
                            tableau.risque.montant
                        }}</span>
                        <span class="ml-1.5 text-sm text-neutral-600"
                            >en retard de plus de 90 jours</span
                        >
                    </p>
                </div>
                <div class="mt-1 h-40 print:h-40">
                    <CourbePar90 :courbe="tableau.risque.courbe" />
                </div>
            </section>

            <div
                class="col-span-12 grid gap-2 lg:col-span-5 print:col-span-5 print:gap-2"
            >
                <!-- Bloc 3 : trajectoire -->
                <section class="bloc" aria-labelledby="titre-bloc-3">
                    <h2 id="titre-bloc-3" class="titre-bloc">
                        3 · Trajectoire
                        <span>Le PAR90 s'est-il dégradé sur trois mois ?</span>
                    </h2>
                    <div class="flex items-baseline gap-4">
                        <p>
                            <span class="text-2xl font-bold tabular-nums">{{
                                tableau.trajectoire.hausse
                            }}</span>
                            <span class="ml-1.5 text-sm text-neutral-600"
                                >sur trois mois</span
                            >
                        </p>
                        <p class="text-sm text-neutral-600">
                            seuil de hausse :
                            <span
                                class="font-semibold text-neutral-900 tabular-nums"
                                >{{ tableau.trajectoire.seuil }}</span
                            >
                        </p>
                    </div>
                    <div
                        v-if="tableau.trajectoire.jauge"
                        class="relative mt-2 mb-4 h-2 rounded-full bg-neutral-200"
                        aria-hidden="true"
                    >
                        <div
                            class="absolute top-0 right-0 h-2 rounded-r-full bg-neutral-300"
                            :style="{
                                left: `${tableau.trajectoire.jauge.seuil}%`,
                            }"
                        />
                        <div
                            class="absolute -top-1 h-4 w-px bg-neutral-500"
                            :style="{
                                left: `${tableau.trajectoire.jauge.zero}%`,
                            }"
                        />
                        <span
                            class="absolute top-3 -translate-x-1/2 text-[10px] text-neutral-500"
                            :style="{
                                left: `${tableau.trajectoire.jauge.zero}%`,
                            }"
                            >0</span
                        >
                        <div
                            class="absolute -top-1.5 h-5 w-0.5 bg-neutral-900"
                            :style="{
                                left: `${tableau.trajectoire.jauge.seuil}%`,
                            }"
                        />
                        <span
                            class="absolute top-3 -translate-x-1/2 text-[10px] font-semibold text-neutral-700"
                            :style="{
                                left: `${tableau.trajectoire.jauge.seuil}%`,
                            }"
                            >seuil</span
                        >
                        <div
                            class="absolute -top-1 size-4 -translate-x-1/2 rounded-full border-2 border-white shadow"
                            :class="
                                tableau.trajectoire.atteint
                                    ? 'bg-red-700'
                                    : 'bg-slate-700'
                            "
                            :style="{
                                left: `${tableau.trajectoire.jauge.hausse}%`,
                            }"
                        />
                    </div>
                    <p
                        class="text-sm"
                        :class="
                            tableau.trajectoire.atteint
                                ? 'font-semibold text-red-800'
                                : 'text-neutral-700'
                        "
                    >
                        {{ tableau.trajectoire.texte }}
                    </p>
                </section>

                <!-- Bloc 4 : signal avancé -->
                <section class="bloc" aria-labelledby="titre-bloc-4">
                    <h2 id="titre-bloc-4" class="titre-bloc">
                        4 · Signal avancé
                        <span>Une hausse se prépare-t-elle ?</span>
                    </h2>
                    <p
                        v-if="!tableau.signal.disponible"
                        class="text-sm font-medium text-neutral-700"
                    >
                        {{ tableau.signal.message }}
                        <span class="font-normal text-neutral-500"
                            >({{ tableau.signal.libelleBonds }})</span
                        >
                    </p>
                    <div v-else class="flex items-center gap-5">
                        <div class="flex items-center gap-2">
                            <span
                                class="voyant inline-block size-6 rounded-full border-2"
                                :class="
                                    tableau.signal.voyant
                                        ? 'border-amber-600 bg-amber-400 shadow-[0_0_10px_2px] shadow-amber-300'
                                        : 'border-neutral-400 bg-white'
                                "
                                aria-hidden="true"
                            />
                            <span class="text-base font-semibold">{{
                                tableau.signal.libelleVoyant
                            }}</span>
                        </div>
                        <dl class="grid grid-cols-[auto_auto] gap-x-3 text-sm">
                            <dt class="text-neutral-600">Bond du mois</dt>
                            <dd class="font-semibold tabular-nums">
                                {{ tableau.signal.bond }}
                            </dd>
                            <dt class="text-neutral-600">Seuil du mois</dt>
                            <dd class="font-semibold tabular-nums">
                                {{ tableau.signal.seuil }}
                            </dd>
                            <dt class="text-neutral-600">Bonds utilisés</dt>
                            <dd class="font-semibold tabular-nums">
                                {{ tableau.signal.nombreBonds }}
                            </dd>
                        </dl>
                        <p
                            v-if="tableau.signal.message"
                            class="text-xs text-neutral-600"
                        >
                            {{ tableau.signal.message }}
                        </p>
                    </div>
                </section>
            </div>

            <!-- Bloc 5 : compréhension (aucune couleur d'alerte) -->
            <section
                class="bloc col-span-12 lg:col-span-6 print:col-span-6"
                aria-labelledby="titre-bloc-5"
            >
                <h2 id="titre-bloc-5" class="titre-bloc">
                    5 · Compréhension
                    <span>Où regarder pendant la revue ?</span>
                </h2>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr
                            class="border-b border-neutral-200 text-left text-xs text-neutral-500"
                        >
                            <th class="py-0.5 font-medium">Indicateur</th>
                            <th class="py-0.5 text-right font-medium">
                                {{ tableau.mois.libelle }}
                            </th>
                            <th class="py-0.5 text-right font-medium">
                                {{ tableau.comprehension.moisPrecedent }}
                            </th>
                            <th class="py-0.5 pl-3 font-medium">Sens</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="i in tableau.comprehension.indicateurs"
                            :key="i.libelle"
                            class="border-b border-neutral-100 last:border-0"
                        >
                            <td class="py-0.5 pr-2">{{ i.libelle }}</td>
                            <td
                                class="py-0.5 text-right font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{ i.valeur }}
                            </td>
                            <td
                                class="py-0.5 text-right whitespace-nowrap text-neutral-600 tabular-nums"
                            >
                                {{ i.precedente }}
                            </td>
                            <td
                                class="py-0.5 pl-3 whitespace-nowrap text-neutral-700"
                            >
                                <span aria-hidden="true">{{ i.symbole }}</span>
                                <span class="ml-1 text-xs">{{
                                    i.evolution
                                        ? libelleEvolution[i.evolution]
                                        : ''
                                }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section
                class="bloc col-span-12 lg:col-span-6 print:col-span-6"
                aria-labelledby="titre-generations"
            >
                <h2 id="titre-generations" class="titre-bloc">
                    5 · Générations récentes
                    <span>PAR30 (%) par mois d'octroi et âge en mois</span>
                </h2>
                <table class="w-full text-[11px] leading-[18px] tabular-nums">
                    <thead>
                        <tr
                            class="border-b border-neutral-200 text-neutral-500"
                        >
                            <th class="py-0 pr-2 text-left font-medium">
                                Octroi
                            </th>
                            <th
                                v-for="a in tableau.generations.ages"
                                :key="a"
                                class="py-0 text-right font-medium"
                            >
                                {{ a }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ligne in tableau.generations.lignes"
                            :key="ligne.mois"
                            class="border-b border-neutral-100 last:border-0"
                        >
                            <th
                                class="py-0 pr-2 text-left font-medium whitespace-nowrap text-neutral-700"
                            >
                                {{ ligne.mois }}
                            </th>
                            <td
                                v-for="(cellule, age) in ligne.cellules"
                                :key="age"
                                class="py-0 pl-1 text-right"
                            >
                                {{ cellule ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </main>
    </div>
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

@media print {
    .tableau {
        background: white;
        font-size: 11px;
    }

    .niveau,
    .voyant {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>

<style>
@media print {
    @page {
        size: A4 landscape;
        margin: 8mm;
    }
}
</style>
