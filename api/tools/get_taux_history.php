<?php
// /api/tools/get_taux_history.php
// Retourne l'historique complet des taux de change.

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
        SELECT id_taux, devise_source, devise_cible, taux_valeur, date_creation, statut_actif
        FROM TAUX_DE_CHANGE
        ORDER BY date_creation DESC
    ");
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'historique' => $history]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>