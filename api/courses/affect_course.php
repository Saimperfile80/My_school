<?php
// /api/courses/affect_course.php
// API pour affecter un professeur à un cours et une classe

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id_cours = $input['id_cours'] ?? '';
$id_classe = $input['id_classe'] ?? '';
$matricule_pers = $input['matricule_pers'] ?? '';
$promotion_annee = $input['promotion_annee'] ?? date('Y') . '-' . (date('Y') + 1);

// Validation des données
if (empty($id_cours) || empty($id_classe) || empty($matricule_pers)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID Cours, ID Classe et Matricule Personnel sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Vérification des FK (Cours, Classe, Personnel)
    // Nous allons faire confiance aux vérifications de la BDD pour la simplicité,
    // mais ici nous vérifions que ce n'est pas le Préfet (ID 1) qui enseigne.
    $stmtRole = $pdo->prepare("SELECT id_role FROM PERSONNEL WHERE matricule_pers = :mat");
    $stmtRole->execute([':mat' => $matricule_pers]);
    $role_id = $stmtRole->fetchColumn();

    if (!$role_id || $role_id == 1) { // 1 = Préfet
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Matricule personnel invalide ou non autorisé à enseigner."]);
        $pdo->rollBack();
        exit;
    }
    
    // 2. Insertion dans AFFECTATION_COURS
    $stmt = $pdo->prepare("
        INSERT INTO AFFECTATION_COURS 
        (id_cours, id_classe, matricule_pers, promotion_annee)
        VALUES 
        (:id_cours, :id_classe, :mat_pers, :annee)
    ");
    
    $stmt->execute([
        ':id_cours' => $id_cours,
        ':id_classe' => $id_classe,
        ':mat_pers' => $matricule_pers,
        ':annee' => $promotion_annee
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Affectation de cours réussie.',
        'id_affectation' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cette affectation (Cours, Classe, Professeur) existe déjà.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'affectation du cours : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>