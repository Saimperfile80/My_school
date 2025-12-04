<?php
// /api/classes/update_class.php
// API pour modifier une classe existante (PUT)

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

// 3. Récupération des données PUT (à partir du corps de la requête)
$input = json_decode(file_get_contents('php://input'), true);
$id_classe = $input['id_classe'] ?? '';
$nom_classe = $input['nom_classe'] ?? '';
$option_classe = $input['option_classe'] ?? '';
$promotion_annee = $input['promotion_annee'] ?? '';

// 4. Validation des données
if (empty($id_classe) || empty($nom_classe) || empty($option_classe) || empty($promotion_annee)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'L\'identifiant de la classe, le nom, l\'option et l\'année de promotion sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête de mise à jour
    $stmt = $pdo->prepare("
        UPDATE CLASSE
        SET nom_classe = :nom, option_classe = :option, promotion_annee = :annee
        WHERE id_classe = :id
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([
        ':nom' => $nom_classe,
        ':option' => $option_classe,
        ':annee' => $promotion_annee,
        ':id' => $id_classe
    ]);
    
    // 7. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode([
            'success' => true, 
            'message' => "Classe ID $id_classe mise à jour avec succès."
        ]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => "Aucune classe trouvée avec l'ID $id_classe ou données identiques."]);
    }

} catch (PDOException $e) {
    // Gestion des erreurs de contrainte UNIQUE
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Cette combinaison Nom/Option/Année existe déjà pour une autre classe.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la classe : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>