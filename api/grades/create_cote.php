<?php
// /api/grades/create_cote.php
// API pour enregistrer une nouvelle note (cote) pour un élève

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_eleve = $input['matricule_eleve'] ?? '';
$id_cours = $input['id_cours'] ?? '';
$id_affectation = $input['id_affectation'] ?? '';
$cote_obtenue = $input['cote_obtenue'] ?? null; // C'est la note sur 100
$periode_evaluation = $input['periode_evaluation'] ?? ''; // Ex: '1er Trimestre', 'Examen Final'
// 💡 NOUVEAU: Matricule du professeur qui tente de saisir la note (simule l'utilisateur connecté)
$matricule_professeur = $input['matricule_professeur'] ?? ''; 

// Validation des données
if (empty($matricule_eleve) || empty($id_cours) || empty($id_affectation) || empty($periode_evaluation) || !is_numeric($cote_obtenue) || $cote_obtenue < 0 || $cote_obtenue > 100 || empty($matricule_professeur)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Matricule élève, ID Cours, ID Affectation, Période, Note et Matricule Professeur sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 🔒 ÉTAPE DE SÉCURITÉ : VÉRIFIER SI CE PROFESSEUR EST AFFECTÉ À CE COURS/AFFECTATION
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM AFFECTATION_COURS 
        WHERE id_affectation = :id_affectation 
        AND matricule_pers = :matricule_prof
    ");
    
    $stmtCheck->execute([
        ':id_affectation' => $id_affectation,
        ':matricule_prof' => $matricule_professeur
    ]);

    if ($stmtCheck->fetchColumn() == 0) {
        http_response_code(403);
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Accès refusé. Le personnel spécifié n\'est pas affecté à cette tâche.']);
        exit;
    }
    
    // La suite de l'insertion reste inchangée...
    $date_enregistrement = date('Y-m-d H:i:s');
    
    // 1. Insertion de la note
    $stmt = $pdo->prepare("
        INSERT INTO COTE 
        (matricule_eleve, id_cours, id_affectation, cote_obtenue, periode_evaluation, date_enregistrement)
        VALUES 
        (:mat, :id_cours, :id_affect, :cote, :periode, :date_enreg)
    ");
    
    $stmt->execute([
        ':mat' => $matricule_eleve,
        ':id_cours' => $id_cours,
        ':id_affect' => $id_affectation,
        ':cote' => $cote_obtenue,
        ':periode' => $periode_evaluation,
        ':date_enreg' => $date_enregistrement
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Note enregistrée avec succès.',
        'id_cote' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    // Gestion de la contrainte UNIQUE...
    // [Reste du code de gestion des erreurs... ]
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Cet élève a déjà une note pour le Cours ID $id_cours durant la période $periode_evaluation. Utilisez la modification."]);
    } else if (strpos($e->getMessage(), 'FOREIGN KEY constraint failed') !== false) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Erreur de clé étrangère: Élève, Cours ou Affectation non trouvée."]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de la note : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>