<?php
// /api/courses/create_course.php
// API pour ajouter un nouveau cours/matière

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nom_cours = $input['nom_cours'] ?? '';
$coefficient = $input['coefficient'] ?? 1;

// Validation des données
if (empty($nom_cours) || !is_numeric($coefficient) || $coefficient < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le nom du cours et un coefficient valide (> 0) sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 5. Préparation de la requête d'insertion (NOM DE TABLE CORRIGÉ : COURS)
    $stmt = $pdo->prepare("
        INSERT INTO COURS (nom_cours, coefficient)
        VALUES (:nom, :coeff)
    ");
    
    // 6. Exécution de la requête
    $stmt->execute([
        ':nom' => $nom_cours,
        ':coeff' => $coefficient
    ]);
// ...
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Cours créé avec succès.',
        'id_cours' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    // Si le nom du cours existe déjà (UNIQUE constraint)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un cours avec ce nom existe déjà.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du cours : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>