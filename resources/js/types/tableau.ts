import type { Registre } from '@/types/registre';

/** Données d'une clôture, préparées par le serveur (App\Tableau\TableauDeBord). */

export type TeinteNiveau = 'vert' | 'jaune' | 'rouge' | 'gris';

export type LienMois = { valeur: string; libelle: string; url: string };

export type Onglet = {
    cle: 'synthese' | 'comprehension' | 'generations' | 'registre';
    libelle: string;
    url: string | null;
};

export type Navigation = {
    precedent: LienMois | null;
    suivant: LienMois | null;
    liste: LienMois[];
};

export type Courbe = {
    etiquettes: string[];
    valeurs: number[];
    infobulles: string[];
    limite: number;
    libelleLimite: string;
    indexMois: number | null;
    couleurs: ('alerte' | 'vigilance' | 'neutre')[];
    legende: { libelle: string; couleur: 'alerte' | 'vigilance' | 'neutre' }[];
};

export type Indicateur = {
    libelle: string;
    valeur: string;
    precedente: string;
    evolution: 'hausse' | 'baisse' | 'stable' | null;
    symbole: string;
};

export type Tableau = {
    mois: { valeur: string; libelle: string };
    bandeau: {
        niveau: string;
        libelle: string;
        couleur: TeinteNiveau;
        motif: string;
        action: string;
        statut: { code: string | null; libelle: string; texte: string | null };
        mention: string | null;
    };
    risque: {
        par90: string;
        montant: string;
        limite: string;
        courbe: Courbe;
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
    registre: Registre;
};

/** Classes d'aplat et de texte pour une teinte de niveau (jetons du thème). */
export const aplatNiveau: Record<TeinteNiveau, string> = {
    vert: 'bg-normal text-sur-niveau',
    jaune: 'bg-vigilance text-sur-vigilance',
    rouge: 'bg-alerte text-sur-niveau',
    gris: 'bg-non-evalue text-sur-niveau',
};
