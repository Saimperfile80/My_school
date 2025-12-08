<?php
// /api/payments/get_eleve_payments.php
// API pour récupérer tous les paiements effectués par un élève donné

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

    // 1. Préparation de la requête avec JOIN pour obtenir le motif du frais et le nom du comptable
    $stmt = $pdo->prepare("
        SELECT 
            T1.id_paiement, T1.montant_paye, T1.date_enregistrement, T1.reference_recu,
            T2.motif AS motif_frais,
            T3.nom || ' ' || T3.prenom AS nom_comptable
        FROM 
            PAIEMENT T1
        JOIN 
            FRAIS T2 ON T1.id_frais = T2.id_frais
        JOIN
            PERSONNEL T3 ON T1.id_comptable = T3.matricule_pers
        WHERE 
            T1.matricule_eleve = :mat_eleve
        ORDER BY 
            T1.date_enregistrement DESC
    ");
    
    $stmt->execute([':mat_eleve' => $matricule_eleve]);
    
    // 2. Récupération de tous les résultats
    $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    if (empty($paiements)) {
        http_response_code(404);
        echo json_encode([
            'success' => true, 
            'message' => "Aucun paiement trouvé pour l'élève $matricule_eleve."
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => count($paiements) . ' paiement(s) trouvé(s) pour l\'élève ' . $matricule_eleve . '.',
            'data' => $paiements
        ]);
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des paiements : ' . $e->getMessage()]);
}
?>