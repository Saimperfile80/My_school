<?php
// /api/tools/get_active_rate.php
// Retourne le taux de change actuellement actif.

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul GET est accepté.']);
    exit;
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("
        SELECT id_taux, devise_source, devise_cible, taux_valeur, date_creation
        FROM TAUX_DE_CHANGE
        WHERE statut_actif = 1
        ORDER BY id_taux DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $taux = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($taux) {
        echo json_encode(['success' => true, 'taux_actif' => $taux]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aucun taux de change actif trouvé.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>