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
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { imports, journal, parametres } from '@/routes/admin';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

/** Back-office : une section n'apparaît que si le rôle y donne accès. */
const page = usePage();
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
    <div class="px-4 py-6">
        <Heading
            title="Administration"
            :description="
                droits?.role
                    ? `Gestion de l'outil. Rôle : ${droits.role}.`
                    : 'Gestion de l\'outil.'
            "
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-56">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Administration"
                >
                    <Button
                        v-for="item in sections"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-4xl">
                <section class="space-y-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
