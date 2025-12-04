<?php
// /api/auth/login.php

// 🚨 CORRECTION DU CHEMIN D'INCLUSION
require_once(dirname(__DIR__, 2) . '/config.php');
header('Content-Type: application/json');

// Récupérer les données POST
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';

// Vérification de la méthode POST et des données
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide ou données manquantes.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // 1. Vérification si c'est la première exécution (devrait être FAUX après le setup réussi)
    if (isFirstRun($pdo)) {
        http_response_code(403); 
        echo json_encode(['success' => false, 'message' => 'Configuration requise: Le compte Préfet initial doit être créé.']);
        exit;
    }

    // 2. Recherche de l'utilisateur et de son rôle
    $stmt = $pdo->prepare("
        SELECT 
            T1.matricule_pers, T1.password_hash, T2.nom_role 
        FROM 
            PERSONNEL T1 
        JOIN 
            ROLE T2 ON T1.id_role = T2.id_role 
        WHERE 
            T1.email = :email
    ");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Connexion réussie
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => [
                'matricule' => $user['matricule_pers'],
                'role' => $user['nom_role'] // Rôle pour la redirection
            ]
        ]);
    } else {
        // Échec de connexion
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur Serveur lors de la connexion : ' . $e->getMessage()]);
}
?>