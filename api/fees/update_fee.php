<?php

// API pour modifier un type de frais existant (PUT)

require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode PUT
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

// 3. Récupération des données PUT
$input = json_decode(file_get_contents('php://input'), true);
$id_frais = $input['id_frais'] ?? '';
$motif = $input['motif'] ?? '';
$montant_standard = $input['montant_standard'] ?? 0.0;

// 4. Validation des données
if (empty($id_frais) || empty($motif) || !is_numeric($montant_standard) || $montant_standard <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'L\'identifiant du frais, le motif et un montant standard valide (> 0) sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête de mise à jour
    $stmt = $pdo->prepare("
        UPDATE FRAIS
        SET motif = :motif, montant_standard = :montant
        WHERE id_frais = :id
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([
        ':motif' => $motif,
        ':montant' => $montant_standard,
        ':id' => $id_frais
    ]);
    
    // 7. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); // OK
        echo json_encode([
            'success' => true, 
            'message' => "Frais ID $id_frais mis à jour avec succès."
        ]);
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => "Aucun frais trouvé avec l'ID $id_frais ou données identiques."]);
    }

} catch (PDOException $e) {
    // Gestion des erreurs de contrainte UNIQUE
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Ce motif de frais existe déjà pour un autre enregistrement.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du frais : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>