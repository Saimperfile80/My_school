<?php
// /api/payments/create_paiement.php
// API pour enregistrer un nouveau paiement

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_eleve = $input['matricule_eleve'] ?? '';
$id_frais = $input['id_frais'] ?? '';
$montant_paye = $input['montant_paye'] ?? null;
$id_comptable = $input['id_comptable'] ?? '';
$reference_recu = $input['reference_recu'] ?? uniqid('RCPT-'); // Génère une référence unique si non fournie

// Validation des données
if (empty($matricule_eleve) || empty($id_frais) || empty($id_comptable) || !is_numeric($montant_paye) || $montant_paye <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Matricule élève, ID Frais, Montant payé (> 0) et ID Comptable sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    
    // 1. Vérifications de base (existence du Comptable et de l'Élève/Frais via FK)
    
    // 2. Insertion du Paiement
    $date_enregistrement = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        INSERT INTO PAIEMENT 
        (matricule_eleve, id_frais, montant_paye, date_enregistrement, id_comptable, reference_recu)
        VALUES 
        (:mat_eleve, :id_frais, :montant, :date_enreg, :id_comptable, :ref_recu)
    ");
    
    $stmt->execute([
        ':mat_eleve' => $matricule_eleve,
        ':id_frais' => $id_frais,
        ':montant' => $montant_paye,
        ':date_enreg' => $date_enregistrement,
        ':id_comptable' => $id_comptable,
        ':ref_recu' => $reference_recu
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Paiement enregistré avec succès.',
        'id_paiement' => $pdo->lastInsertId(),
        'reference_recu' => $reference_recu
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cette référence de reçu existe déjà ou une contrainte (FK) a été violée.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du paiement : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>