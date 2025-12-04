<?php
// /api/classes/get_all_classes.php
// API pour récupérer la liste de toutes les classes

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// FORCER l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Vérification de la méthode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 3. Préparation et exécution de la requête de sélection
    $stmt = $pdo->prepare("
        SELECT id_classe, nom_classe, option_classe, promotion_annee
        FROM CLASSE
        ORDER BY nom_classe, promotion_annee
    ");
    
    $stmt->execute();
    
    // 4. Récupération de tous les résultats sous forme de tableau associatif
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($classes) . ' classes trouvées.',
        'data' => $classes
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des classes : ' . $e->getMessage()]);
}
?>