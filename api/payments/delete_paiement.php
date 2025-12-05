<?php
// /api/payments/delete_paiement.php
// API pour supprimer un enregistrement de paiement (DELETE)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_paiement = $input['id_paiement'] ?? '';

if (empty($id_paiement)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'L\'identifiant du paiement est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Préparation de la requête de suppression physique
    $stmt = $pdo->prepare("
        DELETE FROM PAIEMENT
        WHERE id_paiement = :id_paiement
    ");
    
    $stmt->execute([':id_paiement' => $id_paiement]);
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Paiement ID $id_paiement supprimé avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun paiement trouvé avec l'ID $id_paiement."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>