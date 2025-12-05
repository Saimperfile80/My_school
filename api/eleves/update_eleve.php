<?php
// /api/eleves/update_eleve.php
// API pour modifier un élève existant (PUT)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_eleve = $input['matricule_eleve'] ?? '';
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? '';
$prenom = $input['prenom'] ?? '';
$date_naissance = $input['date_naissance'] ?? '';
$id_classe = $input['id_classe'] ?? '';
$statut_actif = $input['statut_actif'] ?? 1; // 1 par défaut (actif)

// Validation des données
if (empty($matricule_eleve) || empty($nom) || empty($postnom) || empty($prenom) || empty($date_naissance) || empty($id_classe)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule, Nom, Postnom, Prénom, Date de naissance et ID de la classe sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Vérification de l'existence de la nouvelle classe (FK check)
    $stmtClasse = $pdo->prepare("SELECT id_classe FROM CLASSE WHERE id_classe = :id_classe");
    $stmtClasse->execute([':id_classe' => $id_classe]);
    if (!$stmtClasse->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Classe ID $id_classe non trouvée. Impossible de changer la classe de l'élève."]);
        exit;
    }

    // 2. Préparation de la requête de mise à jour
    $stmt = $pdo->prepare("
        UPDATE ELEVE
        SET nom = :nom, 
            postnom = :postnom, 
            prenom = :prenom, 
            date_naissance = :dob, 
            id_classe = :classe,
            statut_actif = :statut
        WHERE matricule_eleve = :mat
    ");
    
    $stmt->execute([
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':dob' => $date_naissance,
        ':classe' => $id_classe,
        ':statut' => $statut_actif,
        ':mat' => $matricule_eleve
    ]);
    
    $pdo->commit();
    
    // 3. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Élève matricule $matricule_eleve mis à jour avec succès."
        ]);
    } else {
        // Cela inclut le cas où le matricule n'existe pas ou aucune donnée n'a changé
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun élève trouvé avec le matricule $matricule_eleve ou données identiques."]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour de l\'élève : ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>