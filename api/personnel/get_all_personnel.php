<?php
// /api/personnel/get_all_personnel.php
// API pour récupérer la liste de tous les membres du personnel

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Préparation de la requête avec JOIN pour obtenir le nom du rôle
    $stmt = $pdo->prepare("
        SELECT 
            T1.matricule_pers, T1.nom, T1.postnom, T1.prenom, T1.email, T1.telephone, 
            T1.salaire_base, T2.nom_role 
        FROM 
            PERSONNEL T1
        JOIN 
            ROLE T2 ON T1.id_role = T2.id_role
        ORDER BY 
            T1.matricule_pers
    ");
    
    $stmt->execute();
    
    // 2. Récupération de tous les résultats
    $personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($personnel) . ' membres du personnel trouvés.',
        'data' => $personnel
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération du personnel : ' . $e->getMessage()]);
}
?>