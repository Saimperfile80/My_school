<?php
// /api/grades/delete_cote.php
// API pour supprimer une note (cote) (DELETE)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cote = $input['id_cote'] ?? '';

if (empty($id_cote)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'L\'identifiant de la cote est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Préparation de la requête de suppression
    $stmt = $pdo->prepare("
        DELETE FROM COTE
        WHERE id_cote = :id_cote
    ");
    
    $stmt->execute([':id_cote' => $id_cote]);
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Note ID $id_cote supprimée avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucune note trouvée avec l'ID $id_cote."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>