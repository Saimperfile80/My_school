<?php
// /api/grades/update_cote.php
// API pour modifier une note (cote) existante (PUT)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cote = $input['id_cote'] ?? '';
$cote_obtenue = $input['cote_obtenue'] ?? null; 
$periode_evaluation = $input['periode_evaluation'] ?? '';

// Validation des données
if (empty($id_cote) || empty($periode_evaluation) || !is_numeric($cote_obtenue) || $cote_obtenue < 0 || $cote_obtenue > 100) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID de la cote, Période et Note (entre 0 et 100) sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Préparation de la requête de mise à jour
    $stmt = $pdo->prepare("
        UPDATE COTE
        SET cote_obtenue = :cote, 
            periode_evaluation = :periode
        WHERE id_cote = :id_cote
    ");
    
    $stmt->execute([
        ':cote' => $cote_obtenue,
        ':periode' => $periode_evaluation,
        ':id_cote' => $id_cote
    ]);
    
    $pdo->commit();
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Note ID $id_cote mise à jour avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucune note trouvée avec l'ID $id_cote ou données identiques."]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    // Le changement de période pourrait violer la contrainte UNIQUE si une note existe déjà pour cette nouvelle période/cours/élève
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Le changement de période entraîne un conflit : une note existe déjà pour cette nouvelle période pour le même élève et cours.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la note : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>