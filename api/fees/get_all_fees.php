<?php
// /api/fees/get_all_fees.php
// API pour récupérer la liste de tous les types de frais

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// 2. Vérification de la méthode GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 3. Préparation et exécution de la requête de sélection
    // On sélectionne uniquement les frais avec statut_actif = 1 (pour masquer les anciens/supprimés logiquement)
    $stmt = $pdo->prepare("
        SELECT id_frais, motif, montant_standard, statut_actif
        FROM FRAIS
        WHERE statut_actif = 1
        ORDER BY motif
    ");
    
    $stmt->execute();
    
    // 4. Récupération de tous les résultats sous forme de tableau associatif
    $fees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Succès
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => count($fees) . ' types de frais trouvés.',
        'data' => $fees
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des frais : ' . $e->getMessage()]);
}
?>