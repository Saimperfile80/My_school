<?php
// /api/personnel/delete_personnel.php
// API pour supprimer un membre du personnel (DELETE)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_pers = $input['matricule_pers'] ?? '';

if (empty($matricule_pers)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule du personnel est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // ⚠️ Mesure de sécurité: Interdire la suppression du Préfet (P001)
    if ($matricule_pers === 'P001') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'La suppression du compte Préfet initial (P001) est interdite.']);
        exit;
    }
    
    // 1. Préparation de la requête de suppression physique
    $stmt = $pdo->prepare("
        DELETE FROM PERSONNEL
        WHERE matricule_pers = :mat
    ");
    
    $stmt->execute([':mat' => $matricule_pers]);
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Personnel matricule $matricule_pers supprimé avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun personnel trouvé avec le matricule $matricule_pers."]);
    }

} catch (PDOException $e) {
    // Gestion du blocage par Clé Étrangère (si P002 était lié à d'autres tables)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Impossible de supprimer le personnel $matricule_pers. Il est lié à des paiements ou des cours (Contrainte FK)."]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du personnel : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>