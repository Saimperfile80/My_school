<?php
// /api/reports/get_class_grades.php
// API pour récupérer le tableau de notes d'une classe pour un cours et une période spécifiques.

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

$id_classe = $_GET['id_classe'] ?? '';
$id_cours = $_GET['id_cours'] ?? '';
$periode = $_GET['periode'] ?? '';

if (empty($id_classe) || empty($id_cours) || empty($periode)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'L\'ID de la classe, l\'ID du cours et la période d\'évaluation sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Informations de base (Classe et Cours)
    $stmtInfo = $pdo->prepare("
        SELECT T1.nom_classe, T1.option_classe, T2.nom_cours, T2.coefficient
        FROM CLASSE T1, COURS T2
        WHERE T1.id_classe = :id_classe AND T2.id_cours = :id_cours
    ");
    $stmtInfo->execute([':id_classe' => $id_classe, ':id_cours' => $id_cours]);
    $info = $stmtInfo->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Classe ou Cours non trouvé(s).']);
        exit;
    }

    // 2. Récupérer tous les élèves de cette classe et leurs notes pour ce cours/cette période (s'ils en ont)
    $stmt = $pdo->prepare("
        SELECT 
            T1.matricule_eleve, T1.nom, T1.postnom, T1.prenom,
            T2.cote_obtenue,
            CASE WHEN T2.cote_obtenue IS NOT NULL THEN 'Noté' ELSE 'Absence' END AS statut_note
        FROM 
            ELEVE T1
        LEFT JOIN 
            COTE T2 ON T1.matricule_eleve = T2.matricule_eleve 
                   AND T2.id_cours = :id_cours 
                   AND T2.periode_evaluation = :periode
        WHERE 
            T1.id_classe = :id_classe AND T1.statut_actif = 1
        ORDER BY 
            T1.nom
    ");
    
    $stmt->execute([
        ':id_classe' => $id_classe,
        ':id_cours' => $id_cours,
        ':periode' => $periode
    ]);
    
    $releve = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($releve) . ' élèves trouvés pour la classe.',
        'classe_info' => $info,
        'periode_evaluation' => $periode,
        'releve_notes' => $releve
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des notes de classe : ' . $e->getMessage()]);
}
?>