<script setup lang="ts">
import Chart from 'chart.js/auto';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Courbe } from '@/types/tableau';

/*
 * Courbe du PAR90 sur tout l'historique. Valeurs, textes et niveaux viennent
 * du serveur ; les couleurs sont lues dans les jetons du thème du conteneur,
 * donc la courbe suit le thème (et reste claire dans la zone d'impression).
 */
const props = defineProps<{ courbe: Courbe; compacte?: boolean }>();

const conteneur = ref<HTMLElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
let graphique: Chart | null = null;
let observateur: MutationObserver | null = null;
const impression = window.matchMedia('print');
const redimensionner = () => graphique?.resize();

function jeton(nom: string): string {
    return conteneur.value
        ? getComputedStyle(conteneur.value).getPropertyValue(nom).trim()
        : '';
}

function dessiner(): void {
    graphique?.destroy();
    if (!canvas.value) {
        return;
    }

    const {
        etiquettes,
        valeurs,
        infobulles,
        limite,
        libelleLimite,
        indexMois,
        couleurs,
    } = props.courbe;
    const t = {
        encre: jeton('--encre'),
        encre2: jeton('--encre-2'),
        filet: jeton('--filet'),
        feuille: jeton('--feuille'),
        prusse: jeton('--prusse'),
        alerte: jeton('--niveau-alerte'),
        vigilance: jeton('--niveau-vigilance'),
    };
    const teinte = {
        alerte: t.alerte,
        vigilance: t.vigilance,
        neutre: t.encre,
    };
    const estMois = (i: number) => i === indexMois;
    const taille = props.compacte ? 9 : 11;

    graphique = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: etiquettes,
            datasets: [
                {
                    label: 'PAR90',
                    data: valeurs,
                    borderColor: t.encre2,
                    borderWidth: 1.5,
                    tension: 0,
                    pointBackgroundColor: couleurs.map((c) => teinte[c]),
                    pointRadius: valeurs.map((_, i) =>
                        estMois(i) ? 6 : props.compacte ? 2.5 : 3.5,
                    ),
                    pointBorderColor: valeurs.map((_, i) =>
                        estMois(i) ? t.prusse : t.feuille,
                    ),
                    pointBorderWidth: valeurs.map((_, i) =>
                        estMois(i) ? 3 : 1,
                    ),
                    pointHoverRadius: 6,
                },
                {
                    label: libelleLimite,
                    data: valeurs.map(() => limite),
                    borderColor: t.encre2,
                    borderWidth: 1,
                    borderDash: [5, 4],
                    pointRadius: 0,
                    pointHitRadius: 0,
                },
            ],
        },
        options: {
            animation: false,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            layout: { padding: { top: 6, right: 8, left: 4 } },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: t.encre,
                    titleColor: t.feuille,
                    bodyColor: t.feuille,
                    displayColors: false,
                    filter: (item) => item.datasetIndex === 0,
                    callbacks: {
                        title: () => '',
                        label: (item) => infobulles[item.dataIndex] ?? '',
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: t.encre2,
                        font: { size: taille, family: "'IBM Plex Sans'" },
                        maxRotation: 0,
                        autoSkip: true,
                        autoSkipPadding: 10,
                    },
                    grid: { display: false },
                    border: { color: t.filet },
                },
                y: {
                    ticks: {
                        color: t.encre2,
                        font: { size: taille, family: "'IBM Plex Sans'" },
                        callback: (v) => `${String(v).replace('.', ',')} %`,
                    },
                    grid: { color: t.filet },
                    border: { display: false },
                },
            },
        },
    });
}

onMounted(() => {
    dessiner();
    impression.addEventListener('change', redimensionner);
    // Le thème change par la classe « dark » de <html> : on redessine.
    observateur = new MutationObserver(dessiner);
    observateur.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
});
watch(() => props.courbe, dessiner);
onBeforeUnmount(() => {
    impression.removeEventListener('change', redimensionner);
    observateur?.disconnect();
    graphique?.destroy();
});
</script>

<template>
    <div ref="conteneur" class="flex h-full w-full flex-col">
        <div class="relative min-h-0 flex-1">
            <canvas
                ref="canvas"
                role="img"
                :aria-label="`Courbe du PAR90 sur tout l'historique, avec la ${courbe.libelleLimite.toLowerCase()}`"
            />
        </div>
        <ul
            class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-encre-2"
            :class="compacte ? 'text-[8pt]' : 'text-xs'"
        >
            <li class="flex items-center gap-1.5">
                <span class="inline-block h-px w-5 bg-encre-2" />PAR90
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="inline-block w-5 border-t border-dashed border-encre-2"
                />{{ courbe.libelleLimite }}
            </li>
            <li
                v-for="l in courbe.legende"
                :key="l.libelle"
                class="flex items-center gap-1.5"
            >
                <span
                    class="point inline-block size-2.5 rounded-full"
                    :class="{
                        'bg-alerte': l.couleur === 'alerte',
                        'bg-vigilance': l.couleur === 'vigilance',
                        'bg-encre': l.couleur === 'neutre',
                    }"
                />{{ l.libelle }}
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="inline-block size-3 rounded-full border-[3px] border-prusse"
                />Mois affiché
            </li>
        </ul>
    </div>
</template>

<style scoped>
.point {
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}
</style>
