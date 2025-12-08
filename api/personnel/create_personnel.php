<?php
// ... [Vérification de la méthode POST, require_once, headers] ...

// ... [Début du code existant] ...
$input = json_decode(file_get_contents('php://input'), true);
$nom = $input['nom'] ?? '';
$postnom = $input['postnom'] ?? '';
$prenom = $input['prenom'] ?? '';
$email = $input['email'] ?? '';
$id_role = $input['id_role'] ?? '';
$salaire_base = $input['salaire_base'] ?? null;
$matricule_admin = $input['matricule_admin'] ?? '';
// 💡 NOUVEAU : Champ pour le mot de passe initial
$password = $input['password'] ?? ''; 

// Validation des données de base
if (empty($nom) || empty($postnom) || empty($prenom) || empty($id_role) || !is_numeric($salaire_base) || $salaire_base <= 0 || empty($matricule_admin) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tous les champs de base, y compris le Mot de Passe et le Matricule de l\'administrateur, sont requis.']);
    exit;
}

try {
    // ... [Vérification de sécurité Admin (Rôle ID 1)] ...
    // Le code de vérification du Rôle ID 1 reste inchangé ici
    $pdo = getDbConnection();
    // ... [Code de vérification du Rôle ID 1] ...

    // Hachage du mot de passe
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ... [Génération du Matricule (P00X)] ...

    // 1. Insertion du Personnel
    $stmt = $pdo->prepare("
        INSERT INTO PERSONNEL 
        (matricule_pers, nom, postnom, prenom, email, id_role, salaire_base, date_embauche, hashed_password)
        VALUES 
        (:mat, :nom, :postnom, :prenom, :email, :id_role, :salaire, :date_emb, :hashed_pwd)
    ");
    
    $stmt->execute([
        ':mat' => $matricule,
        ':nom' => $nom,
        ':postnom' => $postnom,
        ':prenom' => $prenom,
        ':email' => $email,
        ':id_role' => $id_role,
        ':salaire' => $salaire_base,
        ':date_emb' => date('Y-m-d H:i:s'),
        ':hashed_pwd' => $hashed_password // Nouveau champ
    ]);
    
    $pdo->commit();
    
    // Succès
    http_response_code(201);
    echo json_encode([
        'success' => true, 
        'message' => 'Personnel créé avec succès.',
        'matricule' => $matricule
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    // Gestion de l'erreur d'email unique
    if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false && strpos($e->getMessage(), 'email') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Un personnel avec cet email existe déjà.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du personnel : ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur inattendue : ' . $e->getMessage()]);
}
?>