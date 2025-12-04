<?php
// /api/fees/delete_fee.php
// API pour supprimer logiquement un type de frais (DELETE)

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

// 3. Récupération des données DELETE
$input = json_decode(file_get_contents('php://input'), true);
$id_frais = $input['id_frais'] ?? '';

// 4. Validation des données
if (empty($id_frais)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'L\'identifiant du frais est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Utilisation d'UPDATE pour la suppression logique
    $stmt = $pdo->prepare("
        UPDATE FRAIS
        SET statut_actif = 0 
        WHERE id_frais = :id
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([':id' => $id_frais]);
    
    // 7. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode([
            'success' => true, 
            'message' => "Frais ID $id_frais marqué comme inactif (suppression logique) avec succès."
        ]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => "Aucun frais trouvé avec l'ID $id_frais."]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>