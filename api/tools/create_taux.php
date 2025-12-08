<?php
// /api/tools/create_taux.php
// API pour enregistrer un nouveau taux de change et désactiver l'ancien.

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$taux_valeur = $input['taux_valeur'] ?? null;
// Valeurs par défaut pour simplifier le front-end
$devise_source = $input['devise_source'] ?? 'USD';
$devise_cible = $input['devise_cible'] ?? 'FC';

// 1. Validation des données
if (!is_numeric($taux_valeur) || $taux_valeur <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La valeur du taux est invalide ou manquante.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();
    $date_enregistrement = date('Y-m-d H:i:s');

    // 2. Désactiver tous les taux précédents (mettre statut_actif à 0)
    $stmtDesactivate = $pdo->prepare("
        UPDATE TAUX_DE_CHANGE 
        SET statut_actif = 0
    ");
    $stmtDesactivate->execute();

    // 3. Insérer le nouveau taux et le marquer comme actif (statut_actif = 1)
    $stmtInsert = $pdo->prepare("
        INSERT INTO TAUX_DE_CHANGE (devise_source, devise_cible, taux_valeur, date_creation, statut_actif) 
        VALUES (:source, :cible, :taux, :date_creation, 1)
    ");
    
    $stmtInsert->execute([
        ':source' => $devise_source,
        ':cible' => $devise_cible,
        ':taux' => $taux_valeur,
        ':date_creation' => $date_enregistrement
    ]);
    
    $pdo->commit();
    
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Nouveau taux de change (1 ' . $devise_source . ' = ' . $taux_valeur . ' ' . $devise_cible . ') activé avec succès.',
        'taux_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement du taux : ' . $e->getMessage()]);
}
?>