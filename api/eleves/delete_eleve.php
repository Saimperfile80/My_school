<?php
// /api/eleves/delete_eleve.php
// API pour supprimer logiquement un élève (DELETE)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_eleve = $input['matricule_eleve'] ?? '';

if (empty($matricule_eleve)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule de l\'élève est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Suppression Logique: Mettre statut_actif à 0
    $stmt = $pdo->prepare("
        UPDATE ELEVE
        SET statut_actif = 0
        WHERE matricule_eleve = :mat
    ");
    
    $stmt->execute([':mat' => $matricule_eleve]);
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Élève matricule $matricule_eleve marqué comme inactif (suppression logique) avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun élève trouvé avec le matricule $matricule_eleve."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>