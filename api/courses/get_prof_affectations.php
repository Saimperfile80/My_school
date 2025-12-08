<?php
// /api/courses/get_prof_affectations.php
// API pour récupérer la liste de tous les cours affectés à un professeur donné.

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

$matricule_prof = $_GET['matricule'] ?? '';

if (empty($matricule_prof)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule du professeur est requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Récupérer les affectations avec les noms des cours et classes associés
    $stmt = $pdo->prepare("
        SELECT 
            T1.id_affectation, 
            T1.id_cours, 
            T1.id_classe,
            T2.nom_cours,
            T3.nom_classe || ' (' || T3.option_classe || ')' AS nom_classe_complet
        FROM 
            AFFECTATION_COURS T1
        JOIN 
            COURS T2 ON T1.id_cours = T2.id_cours
        JOIN 
            CLASSE T3 ON T1.id_classe = T3.id_classe
        WHERE 
            T1.matricule_pers = :mat_prof
        ORDER BY 
            T3.nom_classe
    ");
    
    $stmt->execute([':mat_prof' => $matricule_prof]);
    $affectations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    if (empty($affectations)) {
        http_response_code(404);
        echo json_encode(['success' => true, 'message' => "Aucune affectation trouvée pour le professeur $matricule_prof."]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => count($affectations) . ' affectation(s) trouvée(s).',
            'data' => $affectations
        ]);
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des affectations : ' . $e->getMessage()]);
}
?>