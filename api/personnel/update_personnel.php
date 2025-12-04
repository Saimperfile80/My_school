<?php
// /api/personnel/update_personnel.php
// API pour modifier un membre du personnel existant (PUT)

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405); 
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul PUT est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule_pers = $input['matricule_pers'] ?? '';
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? '';
$prenom = $input['prenom'] ?? '';
$email = $input['email'] ?? '';
$telephone = $input['telephone'] ?? '';
$id_role = $input['id_role'] ?? '';
$salaire_base = $input['salaire_base'] ?? 0.0;
$password = $input['password'] ?? null; // Optionnel : ne le met à jour que s'il est fourni

// Validation des données
if (empty($matricule_pers) || empty($nom) || empty($postnom) || empty($prenom) || empty($email) || empty($id_role) || !is_numeric($salaire_base)) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Le matricule, Nom, Postnom, Prénom, Email, Rôle et Salaire de base sont requis pour la mise à jour.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Début de la construction de la requête
    $sql = "UPDATE PERSONNEL SET 
                nom = :nom, 
                postnom = :postnom, 
                prenom = :prenom, 
                email = :email, 
                telephone = :tel, 
                id_role = :role, 
                salaire_base = :salaire
            ";
    $params = [
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':email' => $email,
        ':tel' => $telephone,
        ':role' => $id_role,
        ':salaire' => $salaire_base,
        ':mat' => $matricule_pers
    ];

    // 2. Ajouter la mise à jour du mot de passe si un nouveau mot de passe est fourni
    if ($password !== null && !empty($password)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password_hash = :hash";
        $params[':hash'] = $password_hash;
    }
    
    // 3. Finalisation de la requête
    $sql .= " WHERE matricule_pers = :mat";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $pdo->commit();
    
    // 4. Vérification de l'impact
    if ($stmt->rowCount() > 0) {
        http_response_code(200); 
        echo json_encode([
            'success' => true, 
            'message' => "Personnel matricule $matricule_pers mis à jour avec succès."
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Aucun personnel trouvé avec le matricule $matricule_pers ou données identiques."]);
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    // Gestion des erreurs de contrainte UNIQUE (email ou telephone)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'L\'Email ou le Numéro de téléphone est déjà utilisé par un autre utilisateur.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du personnel : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>