<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    LogOut,
    Settings,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import { admin, logout } from '@/routes';
import type { Navigation, Onglet } from '@/types/tableau';

/*
 * Barre supérieure du tableau de bord : produit et institution, onglets,
 * mois (précédent, liste, suivant) et menu utilisateur. Les adresses viennent
 * du serveur ; changer de mois garde l'onglet en cours.
 */
const props = withDefaults(
    defineProps<{
        institution: string;
        onglet?: Onglet['cle'] | null;
        onglets?: Onglet[];
        navigation?: Navigation | null;
        moisCourant?: string | null;
        /** Sans onglets : titre de la page, au centre. */
        titre?: string | null;
        /** Sans onglets : lien de retour, à droite. */
        retour?: { libelle: string; url: string } | null;
    }>(),
    {
        onglet: null,
        onglets: () => [],
        navigation: null,
        moisCourant: null,
        titre: null,
        retour: null,
    },
);

const page = usePage();
const utilisateur = computed(
    () => page.props.auth?.user as { name: string } | null,
);
const role = computed(
    () =>
        (page.props.auth as { droits?: { role: string | null } }).droits?.role,
);
const initiale = computed(() =>
    (utilisateur.value?.name ?? '?').charAt(0).toUpperCase(),
);

const { appearance, updateAppearance } = useAppearance();
const themes: { valeur: Appearance; libelle: string }[] = [
    { valeur: 'light', libelle: 'Clair' },
    { valeur: 'dark', libelle: 'Sombre' },
    { valeur: 'system', libelle: 'Comme le système' },
];

function changerMois(evenement: Event): void {
    const valeur = (evenement.target as HTMLSelectElement).value;
    const lien = props.navigation?.liste.find((m) => m.valeur === valeur);
    if (lien) {
        router.visit(lien.url, { preserveScroll: true });
    }
}

const bouton =
    'inline-flex size-8 items-center justify-center rounded-md text-encre-2 hover:bg-fond hover:text-encre focus-visible:outline-2 focus-visible:outline-prusse';
</script>

<template>
    <header
        class="grid h-12 shrink-0 grid-cols-[1fr_auto_1fr] items-center gap-6 border-b border-filet bg-feuille px-5"
    >
        <div class="flex min-w-0 items-baseline gap-3">
            <span class="text-[15px] font-bold tracking-[-0.01em]"
                >Alerte PAR90</span
            >
            <span
                class="truncate border-l border-filet pl-3 text-[13px] text-encre-2"
                >{{ institution }}</span
            >
        </div>

        <nav
            v-if="moisCourant"
            class="flex h-12 items-stretch gap-1"
            aria-label="Onglets du tableau de bord"
        >
            <Link
                v-for="o in onglets"
                :key="o.cle"
                :href="o.url ?? ''"
                preserve-scroll
                class="relative flex items-center px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-prusse"
                :class="
                    o.cle === onglet
                        ? 'text-encre after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-prusse'
                        : 'text-encre-2 hover:text-encre'
                "
                :aria-current="o.cle === onglet ? 'page' : undefined"
                >{{ o.libelle }}</Link
            >
        </nav>
        <p v-else-if="titre" class="text-sm font-semibold">{{ titre }}</p>
        <span v-else />

        <div class="flex items-center justify-end gap-3">
            <Link
                v-if="retour"
                :href="retour.url"
                class="inline-flex h-8 items-center gap-1 rounded-md px-2 text-sm font-medium text-encre-2 hover:bg-fond hover:text-encre focus-visible:outline-2 focus-visible:outline-prusse"
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
                {{ retour.libelle }}
            </Link>
            <div
                v-if="navigation && moisCourant"
                class="flex items-center gap-0.5"
                role="group"
                aria-label="Mois de clôture"
            >
                <Link
                    v-if="navigation.precedent"
                    :href="navigation.precedent.url"
                    preserve-scroll
                    :class="bouton"
                    :title="`Mois précédent : ${navigation.precedent.libelle}`"
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                    <span class="sr-only">Mois précédent</span>
                </Link>
                <span v-else :class="bouton" class="opacity-30">
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </span>

                <label class="sr-only" for="choix-mois">Mois de clôture</label>
                <div class="relative">
                    <select
                        id="choix-mois"
                        :value="moisCourant"
                        class="chiffres h-8 appearance-none rounded-md border border-filet bg-feuille py-0 pr-8 pl-3 text-sm font-semibold text-encre focus-visible:outline-2 focus-visible:outline-prusse"
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
                    <ChevronDown
                        class="pointer-events-none absolute top-2 right-2 size-4 text-encre-2"
                        aria-hidden="true"
                    />
                </div>

                <Link
                    v-if="navigation.suivant"
                    :href="navigation.suivant.url"
                    preserve-scroll
                    :class="bouton"
                    :title="`Mois suivant : ${navigation.suivant.libelle}`"
                >
                    <ChevronRight class="size-4" aria-hidden="true" />
                    <span class="sr-only">Mois suivant</span>
                </Link>
                <span v-else :class="bouton" class="opacity-30">
                    <ChevronRight class="size-4" aria-hidden="true" />
                </span>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="flex size-8 items-center justify-center rounded-full border border-filet text-sm font-semibold text-encre hover:bg-fond focus-visible:outline-2 focus-visible:outline-prusse"
                    :aria-label="`Menu de ${utilisateur?.name ?? 'l\'utilisateur'}`"
                >
                    {{ initiale }}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel class="font-normal">
                        <span class="block text-sm font-semibold">{{
                            utilisateur?.name
                        }}</span>
                        <span v-if="role" class="block text-xs text-encre-2">{{
                            role
                        }}</span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="admin()" class="w-full cursor-pointer">
                            <Settings class="mr-2 size-4" aria-hidden="true" />
                            Administration
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuLabel class="text-xs font-normal text-encre-2"
                        >Thème</DropdownMenuLabel
                    >
                    <DropdownMenuItem
                        v-for="t in themes"
                        :key="t.valeur"
                        class="cursor-pointer"
                        @select="updateAppearance(t.valeur)"
                    >
                        <Check
                            class="mr-2 size-4"
                            :class="appearance === t.valeur ? '' : 'invisible'"
                            aria-hidden="true"
                        />
                        {{ t.libelle }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link
                            :href="logout()"
                            as="button"
                            class="w-full cursor-pointer"
                            @click="router.flushAll()"
                        >
                            <LogOut class="mr-2 size-4" aria-hidden="true" />
                            Déconnexion
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
