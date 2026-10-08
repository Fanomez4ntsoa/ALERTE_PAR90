<script setup lang="ts">
import BlocRegistre from '@/components/BlocRegistre.vue';
import type { Tableau } from '@/types/tableau';

/** Registre (bloc 6) : seule zone qui défile, à l'intérieur de son cadre. */
defineProps<{ tableau: Tableau }>();
</script>

<template>
    <BlocRegistre :registre="tableau.registre" :mois="tableau.mois.libelle" />
</template>
