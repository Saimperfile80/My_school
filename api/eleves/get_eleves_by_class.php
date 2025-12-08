<?php
// /api/eleves/get_eleves_by_class.php
// API pour récupérer la liste des élèves actifs d'une classe spécifique

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

$id_classe = $_GET['id_classe'] ?? '';

if (empty($id_classe) || !is_numeric($id_classe)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'L\'ID de la classe est requis et doit être numérique.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Récupération du nom de la classe pour le titre du rapport
    $stmtClasse = $pdo->prepare("SELECT nom_classe, option_classe FROM CLASSE WHERE id_classe = :id_classe");
    $stmtClasse->execute([':id_classe' => $id_classe]);
    $classe_info = $stmtClasse->fetch(PDO::FETCH_ASSOC);

    if (!$classe_info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Classe ID $id_classe non trouvée."]);
        exit;
    }
    
    // 1. Récupération des élèves actifs dans cette classe
    $stmt = $pdo->prepare("
        SELECT 
            matricule_eleve, nom, postnom, prenom, date_naissance, date_inscription
        FROM 
            ELEVE
        WHERE 
            id_classe = :id_classe AND statut_actif = 1
        ORDER BY 
            nom, prenom
    ");
    
    $stmt->execute([':id_classe' => $id_classe]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($eleves) . ' élèves actifs trouvés pour cette classe.',
        'classe' => $classe_info,
        'data' => $eleves
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des élèves : ' . $e->getMessage()]);
}
?>