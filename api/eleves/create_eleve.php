<?php
// /api/eleves/create_eleve.php
// API pour ajouter un nouvel élève

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? '';
$prenom = $input['prenom'] ?? '';
$date_naissance = $input['date_naissance'] ?? ''; // Format YYYY-MM-DD
$id_classe = $input['id_classe'] ?? '';

// Validation des données
if (empty($nom) || empty($postnom) || empty($prenom) || empty($date_naissance) || empty($id_classe)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nom, Postnom, Prénom, Date de naissance et ID de la classe sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Vérification de l'existence de la classe (FK check)
    $stmtClasse = $pdo->prepare("SELECT id_classe FROM CLASSE WHERE id_classe = :id_classe");
    $stmtClasse->execute([':id_classe' => $id_classe]);
    if (!$stmtClasse->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Classe ID $id_classe non trouvée. Impossible d'affecter l'élève."]);
        exit;
    }

    // 2. Génération du matricule de l'élève (E001, E002...)
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM ELEVE");
    $nextId = $stmtCount->fetchColumn() + 1;
    $matricule_eleve = 'E' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

    $date_inscription = date('Y-m-d H:i:s');
    
    // 3. Insertion
    $stmt = $pdo->prepare("
        INSERT INTO ELEVE 
        (matricule_eleve, id_classe, nom, postnom, prenom, date_naissance, date_inscription, statut_actif)
        VALUES 
        (:mat, :classe, :nom, :postnom, :prenom, :dob, :doi, 1)
    ");
    
    $stmt->execute([
        ':mat' => $matricule_eleve,
        ':classe' => $id_classe,
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':dob' => $date_naissance,
        ':doi' => $date_inscription
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Élève créé avec succès.',
        'matricule' => $matricule_eleve
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de l\'élève : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>