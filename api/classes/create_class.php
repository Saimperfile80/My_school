<?php
// /api/classes/create_class.php
// API pour ajouter une nouvelle classe

// 1. Inclusion des fichiers de configuration et fonctions
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// FORCER l'affichage des erreurs pour le développement
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Vérification de la méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

// 3. Récupération des données POST
$input = json_decode(file_get_contents('php://input'), true);
$nom_classe = $input['nom_classe'] ?? '';
$option_classe = $input['option_classe'] ?? '';
$promotion_annee = $input['promotion_annee'] ?? '';

// 4. Validation des données
if (empty($nom_classe) || empty($option_classe) || empty($promotion_annee)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Le nom de la classe, l\'option et l\'année de promotion sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête d'insertion
    $stmt = $pdo->prepare("
        INSERT INTO CLASSE (nom_classe, option_classe, promotion_annee)
        VALUES (:nom_classe, :option_classe, :promotion_annee)
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([
        ':nom_classe' => $nom_classe,
        ':option_classe' => $option_classe,
        ':promotion_annee' => $promotion_annee
    ]);
    
    // Succès
    http_response_code(201); // Created
    echo json_encode([
        'success' => true, 
        'message' => 'Classe créée avec succès.',
        'id_classe' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    // Si le nom de la classe existe déjà (UNIQUE constraint)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Une classe avec ce nom ou cette combinaison existe déjà.']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création de la classe : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>