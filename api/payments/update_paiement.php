<?php
// /api/payments/update_paiement.php
// API pour modifier un paiement existant (PUT)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_paiement = $input['id_paiement'] ?? '';
$montant_paye = $input['montant_paye'] ?? null; 
$matricule_eleve = $input['matricule_eleve'] ?? ''; // Permet de s'assurer de l'identité
$id_frais = $input['id_frais'] ?? '';             // Permet de s'assurer du frais

// Validation des données
if (empty($id_paiement) || empty($matricule_eleve) || empty($id_frais) || !is_numeric($montant_paye) || $montant_paye <= 0) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'ID Paiement, Matricule Élève, ID Frais et Montant payé (> 0) sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Préparation de la requête de mise à jour. Nous ne modifions pas l'id_comptable pour des raisons d'audit.
    $stmt = $pdo->prepare("
        UPDATE PAIEMENT
        SET montant_paye = :montant,
            matricule_eleve = :mat_eleve,
            id_frais = :id_frais
        WHERE id_paiement = :id_paiement
    ");
    
    $stmt->execute([
        ':montant' => $montant_paye,
        ':mat_eleve' => $matricule_eleve,
        ':id_frais' => $id_frais,
        ':id_paiement' => $id_paiement
    ]);
    
    $pdo->commit();
    
    // 2. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Paiement ID $id_paiement mis à jour avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun paiement trouvé avec l'ID $id_paiement ou données identiques."]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du paiement : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>