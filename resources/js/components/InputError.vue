<script setup lang="ts">
import { CircleAlert } from '@lucide/vue';

/** Erreur de saisie : texte foncé en gras et icône ; le rouge reste réservé aux niveaux. */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <p
        v-show="message"
        class="flex items-start gap-1.5 text-[13px] leading-snug font-semibold text-encre"
        role="alert"
    >
        <CircleAlert class="mt-px size-4 shrink-0" aria-hidden="true" />
        <span>{{ message }}</span>
    </p>
</template>
