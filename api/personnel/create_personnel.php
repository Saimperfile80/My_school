<?php
// /api/personnel/create_personnel.php
// API pour ajouter un nouveau membre du personnel

require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? '';
$prenom = $input['prenom'] ?? '';
$email = $input['email'] ?? '';
$telephone = $input['telephone'] ?? '';
$id_role = $input['id_role'] ?? ''; // ID du rôle (2=Directeur, 3=Comptable, 4=Professeur)
$salaire_base = $input['salaire_base'] ?? 0.0;
$password = $input['password'] ?? 'MotDePasseParDefaut!'; // Mot de passe par défaut si non fourni

// Validation des données
if (empty($nom) || empty($postnom) || empty($prenom) || empty($email) || empty($id_role) || !is_numeric($salaire_base)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nom, Postnom, Prénom, Email, Rôle et Salaire de base sont requis.']);
    exit;
}

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // 1. Vérification de l'existence du rôle
    $stmtRole = $pdo->prepare("SELECT nom_role FROM ROLE WHERE id_role = :id_role");
    $stmtRole->execute([':id_role' => $id_role]);
    if (!$stmtRole->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Rôle ID $id_role non trouvé."]);
        exit;
    }
    
    // 2. Détermination du nouveau matricule (basé sur le rôle Professeur T4)
    // Pour simplifier, on trouve le dernier matricule de PERSONNEL et on incrémente.
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM PERSONNEL");
    $nextId = $stmtCount->fetchColumn() + 1;
    $matricule_pers = 'P' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

    // 3. Hachage du mot de passe
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $date_creation = date('Y-m-d H:i:s');
    
    // 4. Insertion
    $stmt = $pdo->prepare("
        INSERT INTO PERSONNEL 
        (matricule_pers, id_role, nom, postnom, prenom, email, telephone, password_hash, salaire_base, date_creation)
        VALUES 
        (:mat, :role, :nom, :postnom, :prenom, :email, :tel, :hash, :salaire, :date_crea)
    ");
    
    $stmt->execute([
        ':mat' => $matricule_pers,
        ':role' => $id_role,
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':email' => $email,
        ':tel' => $telephone,
        ':hash' => $password_hash,
        ':salaire' => $salaire_base,
        ':date_crea' => $date_creation
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Personnel créé avec succès.',
        'matricule' => $matricule_pers
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    // Gestion des erreurs de contrainte UNIQUE (email ou telephone)
    if ($e->getCode() == '23000' || strpos($e->getMessage(), 'SQLSTATE[23000]') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'L\'Email ou le Numéro de téléphone est déjà utilisé.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du personnel : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>