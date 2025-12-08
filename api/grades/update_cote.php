<?php
// /api/grades/update_cote.php
// API pour modifier une note existante (cote) pour un élève

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

// Accepter POST ou PUT pour la modification (selon la convention Front-end)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST/PUT est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cote = $input['id_cote'] ?? ''; // ID unique de la note à mettre à jour
$cote_obtenue = $input['cote_obtenue'] ?? null; // Nouvelle note sur 100
// Matricule du professeur qui tente de saisir la note (tiré de la session Front-end)
$matricule_professeur = $input['matricule_professeur'] ?? ''; 

// 1. Validation des données
if (empty($id_cote) || !is_numeric($id_cote) || !is_numeric($cote_obtenue) || $cote_obtenue < 0 || $cote_obtenue > 100 || empty($matricule_professeur)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de la note, Note (entre 0 et 100) et Matricule Professeur sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 2. Récupérer l'ID Affectation lié à cette note
    $stmtFindAffectation = $pdo->prepare("
        SELECT id_affectation 
        FROM COTE 
        WHERE id_cote = :id_cote
    ");
    $stmtFindAffectation->execute([':id_cote' => $id_cote]);
    $result = $stmtFindAffectation->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        http_response_code(404);
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Note ID non trouvée dans la base de données.']);
        exit;
    }
    $id_affectation_cible = $result['id_affectation'];

    // 3. 🔒 VÉRIFICATION DE SÉCURITÉ : Le professeur est-il affecté à cette tâche ?
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM AFFECTATION_COURS 
        WHERE id_affectation = :id_affectation AND matricule_pers = :matricule_prof
    ");
    
    $stmtCheck->execute([
        ':id_affectation' => $id_affectation_cible,
        ':matricule_prof' => $matricule_professeur
    ]);

    if ($stmtCheck->fetchColumn() == 0) {
        http_response_code(403);
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Vous n\'êtes pas le professeur affecté à ce cours.']);
        exit;
    }
    
    // 4. Exécution de la Mise à Jour
    $stmt = $pdo->prepare("
        UPDATE COTE 
        SET cote_obtenue = :cote, date_enregistrement = :date_enreg
        WHERE id_cote = :id_cote
    ");
    
    $stmt->execute([
        ':cote' => $cote_obtenue,
        ':date_enreg' => date('Y-m-d H:i:s'),
        ':id_cote' => $id_cote
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Note ID ' . $id_cote . ' mise à jour avec succès.',
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de la note : ' . $e->getMessage()]);
}
?>