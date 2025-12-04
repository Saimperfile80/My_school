<?php
// /api/fees/create_fee.php
// API pour ajouter un nouveau type de frais

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

// 3. Récupération des données POST
$input = json_decode(file_get_contents('php://input'), true);
$motif = $input['motif'] ?? '';
$montant_standard = $input['montant_standard'] ?? 0.0;

// 4. Validation des données
if (empty($motif) || !is_numeric($montant_standard) || $montant_standard <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Le motif et un montant standard valide (> 0) sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête d'insertion
    $stmt = $pdo->prepare("
        INSERT INTO FRAIS (motif, montant_standard, statut_actif)
        VALUES (:motif, :montant, 1)
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([
        ':motif' => $motif,
        ':montant' => $montant_standard
    ]);
    
    // Succès
    http_response_code(201); // Created
    echo json_encode([
        'success' => true, 
        'message' => 'Frais créé avec succès.',
        'id_frais' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    // Si le motif de frais existe déjà (UNIQUE constraint)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Un type de frais avec ce motif existe déjà.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du frais : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>