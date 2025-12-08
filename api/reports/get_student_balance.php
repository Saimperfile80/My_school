<?php
// /api/reports/get_student_balance.php
// API pour calculer le solde (dû ou payé en trop) d'un élève.
// Solde = (Total Paiements) - (Total Frais Requis)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

$matricule_eleve = $_GET['matricule'] ?? '';

if (empty($matricule_eleve)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule de l\'élève est requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    
    // 1. Récupérer les informations de l'élève (Classe ID)
    $stmtEleve = $pdo->prepare("
        SELECT id_classe, nom, postnom, prenom 
        FROM ELEVE 
        WHERE matricule_eleve = :mat AND statut_actif = 1
    ");
    $stmtEleve->execute([':mat' => $matricule_eleve]);
    $eleve = $stmtEleve->fetch(PDO::FETCH_ASSOC);

    if (!$eleve) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Élève actif non trouvé avec le matricule $matricule_eleve."]);
        exit;
    }
    
    $id_classe = $eleve['id_classe'];

    // 2. Calculer le total des frais requis (on suppose que tous les frais actifs s'appliquent)
    $stmtFrais = $pdo->prepare("
        SELECT SUM(montant_standard) AS total_frais_requis 
        FROM FRAIS 
        WHERE statut_actif = 1
    ");
    $stmtFrais->execute();
    $total_frais_requis = (float) $stmtFrais->fetchColumn();

    // 3. Calculer le total des paiements effectués par cet élève
    $stmtPaiements = $pdo->prepare("
        SELECT SUM(montant_paye) AS total_paiements 
        FROM PAIEMENT 
        WHERE matricule_eleve = :mat
    ");
    $stmtPaiements->execute([':mat' => $matricule_eleve]);
    $total_paiements = (float) $stmtPaiements->fetchColumn();
    
    // 4. Calcul du solde
    // Solde > 0 : Excédent (payé en trop)
    // Solde < 0 : Dette (montant dû)
    // Solde = 0 : Solde équilibré
    $solde = $total_paiements - $total_frais_requis;
    $est_a_jour = ($solde >= 0);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Calcul du solde effectué.',
        'data' => [
            'matricule' => $matricule_eleve,
            'nom_complet' => $eleve['nom'] . ' ' . $eleve['postnom'] . ' ' . $eleve['prenom'],
            'total_frais_requis' => $total_frais_requis,
            'total_paiements' => $total_paiements,
            'solde' => $solde,
            'statut' => $solde < 0 ? 'Dette' : ($solde > 0 ? 'Excédent' : 'À Jour'),
            'montant_du' => max(0, -$solde), // Renvoie 0 si Solde >= 0
            'est_a_jour' => $est_a_jour
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors du calcul du solde : ' . $e->getMessage()]);
}
?>