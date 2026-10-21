<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    FileSpreadsheet,
    KeyRound,
    Palette,
    ScrollText,
    SlidersHorizontal,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import BarreSuperieure from '@/components/tableau/BarreSuperieure.vue';
import { Toaster } from '@/components/ui/sonner';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { imports, journal, parametres } from '@/routes/admin';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/*
 * Back-office : barre supérieure du produit, navigation latérale des six
 * sections, contenu sur une feuille. Une section n'apparaît que si le rôle y
 * donne accès ; le serveur refuse de toute façon (403).
 */
defineProps<{ breadcrumbs?: unknown }>();

const page = usePage();
const institution = computed(() => (page.props.institution as string) ?? '');
const droits = computed(
    () =>
        (
            page.props.auth as {
                droits?: { importer: boolean; role: string | null };
            }
        ).droits,
);

const sections = computed<NavItem[]>(() => [
    ...(droits.value?.importer
        ? [{ title: 'Imports', href: imports(), icon: FileSpreadsheet }]
        : []),
    { title: 'Journal', href: journal(), icon: ScrollText },
    {
        title: 'Paramètres du calcul',
        href: parametres(),
        icon: SlidersHorizontal,
    },
    { title: 'Profil', href: editProfile(), icon: UserRound },
    { title: 'Mot de passe', href: editSecurity(), icon: KeyRound },
    { title: 'Apparence', href: editAppearance(), icon: Palette },
]);

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex h-dvh min-h-[640px] flex-col bg-fond text-encre">
        <BarreSuperieure
            :institution="institution"
            titre="Administration"
            :retour="{ libelle: 'Tableau de bord', url: toUrl(dashboard()) }"
        />

        <div
            class="mx-auto flex min-h-0 w-full max-w-[1400px] flex-1 gap-4 p-4"
        >
            <aside class="w-56 shrink-0">
                <nav
                    class="flex flex-col gap-0.5"
                    aria-label="Sections de l'administration"
                >
                    <Link
                        v-for="item in sections"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-prusse"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-feuille text-encre shadow-[inset_2px_0_0] shadow-prusse'
                                : 'text-encre-2 hover:bg-feuille/60 hover:text-encre'
                        "
                        :aria-current="
                            isCurrentOrParentUrl(item.href) ? 'page' : undefined
                        "
                    >
                        <component
                            :is="item.icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                        {{ item.title }}
                    </Link>
                </nav>
                <p
                    v-if="droits?.role"
                    class="mt-4 px-3 text-[12px] text-encre-2"
                >
                    Rôle : {{ droits.role }}
                </p>
            </aside>

            <main
                class="min-h-0 flex-1 overflow-y-auto rounded-[10px] bg-feuille px-8 py-6"
            >
                <div class="max-w-4xl space-y-8">
                    <slot />
                </div>
            </main>
        </div>
        <Toaster />
    </div>
</template>
