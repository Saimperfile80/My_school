<?php
// /api/auth/login.php

// 1. Définition des En-têtes CORS (NOUVEAU)
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// 2. Interception de la requête OPTIONS (CORS pre-flight) (NOUVEAU)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); // Répond 200 OK pour autoriser le POST suivant
    exit;
}

// 3. Inclusion du fichier de configuration (Décalé)
require_once(dirname(__DIR__, 2) . '/config.php'); 
header('Content-Type: application/json');

// 4. Vérification de la méthode POST (Décalé)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Seul POST est accepté.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$matricule = $input['matricule'] ?? '';
// En production, le mot de passe ($input['password']) serait utilisé et haché

if (empty($matricule)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le matricule est requis.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Récupérer les informations de l'utilisateur (nom, rôle)
    // En production, une clause WHERE inclurait la vérification du mot de passe
    $stmt = $pdo->prepare("
        SELECT T1.matricule_pers, T1.nom, T1.prenom, T1.postnom, T1.id_role, T2.nom_role
        FROM PERSONNEL T1
        JOIN ROLE T2 ON T1.id_role = T2.id_role
        WHERE T1.matricule_pers = :mat
    ");
    
    $stmt->execute([':mat' => $matricule]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Matricule non trouvé.']);
        exit;
    }
    
    // Succès : Simuler le retour d'un Token/Session pour le Front-end
    unset($user['hashed_password']);

    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Connexion réussie.',
        'user' => [
            'matricule' => $user['matricule_pers'],
            'nom_complet' => $user['nom'] . ' ' . $user['prenom'],
            'role_id' => $user['id_role'],
            'role_nom' => $user['nom_role']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion : ' . $e->getMessage()]);
}
?>