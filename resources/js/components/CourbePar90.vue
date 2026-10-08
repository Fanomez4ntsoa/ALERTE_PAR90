<script setup lang="ts">
import Chart from 'chart.js/auto';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** Courbe du PAR90 sur tout l'historique ; données et textes préparés par le serveur. */
type Courbe = {
    etiquettes: string[];
    valeurs: number[];
    infobulles: string[];
    limite: number;
    libelleLimite: string;
    indexMois: number | null;
    couleurs: string[];
    legende: { libelle: string; couleur: string }[];
};

const props = defineProps<{ courbe: Courbe }>();

const canvas = ref<HTMLCanvasElement | null>(null);
let graphique: Chart | null = null;
const impression = window.matchMedia('print');
const redimensionner = () => graphique?.resize();

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
    const estMois = (i: number) => i === indexMois;

    graphique = new Chart(canvas.value, {
        type: 'line',
        data: {
            labels: etiquettes,
            datasets: [
                {
                    label: 'PAR90',
                    data: valeurs,
                    borderColor: '#64748b',
                    borderWidth: 1.5,
                    tension: 0,
                    // Couleur de chaque point : niveau de son mois, fournie par le serveur.
                    pointBackgroundColor: couleurs,
                    pointRadius: valeurs.map((_, i) => (estMois(i) ? 7 : 3.5)),
                    pointBorderColor: valeurs.map((_, i) =>
                        estMois(i) ? '#2563eb' : couleurs[i],
                    ),
                    pointBorderWidth: valeurs.map((_, i) =>
                        estMois(i) ? 3 : 0,
                    ),
                },
                {
                    label: libelleLimite,
                    data: valeurs.map(() => limite),
                    borderColor: '#64748b',
                    borderWidth: 1.5,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHitRadius: 0,
                },
            ],
        },
        options: {
            animation: false,
            maintainAspectRatio: false,
            devicePixelRatio: 2,
            plugins: {
                legend: { display: false },
                tooltip: {
                    filter: (item) => item.datasetIndex === 0,
                    callbacks: {
                        label: (item) => infobulles[item.dataIndex] ?? '',
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 0,
                        autoSkip: true,
                        autoSkipPadding: 8,
                    },
                    grid: { display: false },
                },
                y: {
                    ticks: {
                        font: { size: 10 },
                        callback: (v) => `${String(v).replace('.', ',')} %`,
                    },
                },
            },
        },
    });
}

onMounted(() => {
    dessiner();
    impression.addEventListener('change', redimensionner);
});
watch(() => props.courbe, dessiner);
onBeforeUnmount(() => {
    impression.removeEventListener('change', redimensionner);
    graphique?.destroy();
});
</script>

<template>
    <div class="flex h-full w-full flex-col">
        <div class="relative min-h-0 flex-1">
            <canvas
                ref="canvas"
                role="img"
                aria-label="Courbe du PAR90 sur tout l'historique, avec la limite"
            />
        </div>
        <ul
            class="mt-1 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[11px] text-neutral-600"
        >
            <li class="flex items-center gap-1.5">
                <span class="inline-block h-0.5 w-5 bg-slate-500" />PAR90
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="inline-block w-5 border-t-2 border-dashed border-slate-500"
                />{{ courbe.libelleLimite }}
            </li>
            <li
                v-for="l in courbe.legende"
                :key="l.libelle"
                class="flex items-center gap-1.5"
            >
                <span
                    class="point inline-block size-2.5 rounded-full"
                    :style="{ backgroundColor: l.couleur }"
                />{{ l.libelle }}
            </li>
            <li class="flex items-center gap-1.5">
                <span
                    class="inline-block size-3 rounded-full border-[3px] border-blue-600 bg-white"
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
