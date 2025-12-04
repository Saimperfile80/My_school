<?php
// /api/classes/delete_class.php
// API pour supprimer une classe existante (DELETE)

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode DELETE
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul DELETE est accepté.']);
    exit;
}

// 3. Récupération des données DELETE (souvent l'ID via le corps)
$input = json_decode(file_get_contents('php://input'), true);
$id_classe = $input['id_classe'] ?? '';

// 4. Validation des données
if (empty($id_classe)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'L\'identifiant de la classe est requis pour la suppression.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête de suppression
    $stmt = $pdo->prepare("
        DELETE FROM CLASSE
        WHERE id_classe = :id
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([':id' => $id_classe]);
    
    // 7. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode([
            'success' => true, 
            'message' => "Classe ID $id_classe supprimée avec succès."
        ]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => "Aucune classe trouvée avec l'ID $id_classe."]);
    }

} catch (PDOException $e) {
    // Si la suppression échoue à cause de contraintes de clé étrangère (FK)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => "Impossible de supprimer la classe ID $id_classe. Elle est référencée par des élèves ou d'autres enregistrements (Contrainte FK)."]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression de la classe : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>