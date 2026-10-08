/** Données du bloc 6, préparées par le serveur (App\Tableau\TableauDeBord::registre). */
export type DecisionAffichee = {
    id: number;
    action: string;
    responsable: string;
    echeance: string;
    etat: 'ouverte' | 'completee' | 'annulee';
    libelleEtat: string;
    resultat: string | null;
    motifAnnulation: string | null;
    saisie: string;
    complement: string | null;
    annulation: string | null;
    urlCompleter: string | null;
    urlCorriger: string | null;
    urlAnnuler: string | null;
};

export type Registre = {
    decisions: DecisionAffichee[];
    urlAjout: string | null;
    moisSignal: string;
    signal: {
        mois: string;
        url: string;
        niveau: string;
        couleur: 'vert' | 'jaune' | 'rouge' | 'gris';
        par90Alors: string;
        par90Aujourdhui: string;
        hausseReelle: string;
        resultatCode: string;
        resultat: string;
        decisions: DecisionAffichee[];
    } | null;
    urlRecapitulatif: string;
};
