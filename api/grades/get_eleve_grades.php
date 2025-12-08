<?php
// /api/grades/get_eleve_grades.php
// API pour récupérer toutes les notes (cotes) d'un élève donné

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

$matricule_eleve = $_GET['matricule'] ?? '';

if (empty($matricule_eleve)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule de l\'élève est requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Requête complexe avec de multiples JOINs pour obtenir tous les détails
    $stmt = $pdo->prepare("
        SELECT 
            T1.id_cote, T1.cote_obtenue, T1.periode_evaluation, T1.date_enregistrement,
            T2.nom_cours, T2.coefficient,
            T3.matricule_pers AS matricule_prof, T3.nom AS nom_prof, T3.prenom AS prenom_prof,
            T4.nom_classe, T4.option_classe, T4.promotion_annee
        FROM 
            COTE T1
        JOIN 
            COURS T2 ON T1.id_cours = T2.id_cours
        JOIN 
            AFFECTATION_COURS T5 ON T1.id_affectation = T5.id_affectation
        JOIN 
            PERSONNEL T3 ON T5.matricule_pers = T3.matricule_pers
        JOIN
            CLASSE T4 ON T5.id_classe = T4.id_classe
        WHERE 
            T1.matricule_eleve = :mat_eleve
        ORDER BY 
            T1.periode_evaluation, T2.nom_cours
    ");
    
    $stmt->execute([':mat_eleve' => $matricule_eleve]);
    
    // 2. Récupération de tous les résultats
    $cotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    if (empty($cotes)) {
        http_response_code(404);
        echo json_encode([
            'success' => true, 
            'message' => "Aucune note trouvée pour l'élève $matricule_eleve."
        ]);
    } else {
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => count($cotes) . ' note(s) trouvée(s) pour l\'élève ' . $matricule_eleve . '.',
            'data' => $cotes
        ]);
    }

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des notes : ' . $e->getMessage()]);
}
?>