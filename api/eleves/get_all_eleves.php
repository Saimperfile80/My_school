<?php
// /api/eleves/get_all_eleves.php
// API pour récupérer la liste de tous les élèves

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Requête avec JOIN pour obtenir le nom de la classe
    $stmt = $pdo->prepare("
        SELECT 
            T1.matricule_eleve, T1.nom, T1.postnom, T1.prenom, 
            T1.date_naissance, T1.date_inscription, T1.statut_actif, 
            T2.nom_classe, T2.option_classe, T2.promotion_annee
        FROM 
            ELEVE T1
        JOIN 
            CLASSE T2 ON T1.id_classe = T2.id_classe
        WHERE
            T1.statut_actif = 1  /* Afficher seulement les élèves actifs */
        ORDER BY 
            T2.nom_classe, T1.nom
    ");
    
    $stmt->execute();
    
    // 2. Récupération de tous les résultats
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($eleves) . ' élèves actifs trouvés.',
        'data' => $eleves
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des élèves : ' . $e->getMessage()]);
}
?>