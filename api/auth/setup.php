<?php
// /api/auth/setup.php - Version Finale
// 🚨 Le chemin d'inclusion est désormais fiable.
require_once(dirname(__DIR__, 2) . '/config.php'); 

header('Content-Type: application/json');

// Réglages d'erreur (à affiner en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Récupération des données POST
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? $nom; // Ajouté le postnom ici par souci de complétude
$prenom = $input['prenom'] ?? '';

// Vérification de base des données
if (empty($email) || empty($password) || empty($nom) || empty($postnom) || empty($prenom)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les informations d\'identification (email, mot de passe, nom, postnom, prénom) sont requises.']);
    exit;
}


try {
    // getDbConnection() crée et initialise la BDD si elle n'existe pas.
    $pdo = getDbConnection();

    // 1. VÉRIFICATION CRITIQUE : S'assurer que le Préfet n'existe pas déjà
    if (!isFirstRun($pdo)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Un compte Préfet existe déjà. Veuillez vous connecter.']);
        exit;
    }
    
    // 2. Récupérer l'ID du rôle 'Préfet'
    $stmtRole = $pdo->prepare("SELECT id_role FROM ROLE WHERE nom_role = 'Préfet'");
    $stmtRole->execute();
    $id_prefet_role = $stmtRole->fetchColumn();

    if (!$id_prefet_role) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur de configuration : Le rôle Préfet est introuvable.']);
        exit;
    }

    // 3. Préparation des données
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $matricule_pers = 'P' . str_pad(1, 3, '0', STR_PAD_LEFT); // P001 pour le premier utilisateur
    $date_creation = date('Y-m-d H:i:s');
    
    // 4. Insertion du premier utilisateur (Préfet)
    $stmt = $pdo->prepare("
        INSERT INTO PERSONNEL 
        (matricule_pers, id_role, nom, postnom, prenom, email, telephone, password_hash, date_creation)
        VALUES 
        (:mat, :role, :nom, :postnom, :prenom, :email, 'N/A', :hash, :date_crea)
    ");
    
    $stmt->execute([
        ':mat' => $matricule_pers,
        ':role' => $id_prefet_role,
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':email' => $email,
        ':hash' => $password_hash,
        ':date_crea' => $date_creation
    ]);
    
    // Succès : Le Préfet est créé.
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Compte Préfet initial créé avec succès. Vous pouvez maintenant vous connecter.',
        'user' => [
            'matricule' => $matricule_pers,
            'role' => 'Préfet'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du compte : ' . $e->getMessage()]);
}

?>