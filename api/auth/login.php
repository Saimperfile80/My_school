<?php
// /api/auth/login.php
// Script de gestion de l'authentification (Connexion)

// ===========================
// 🌐 CORS et Config
// ===========================
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

// Remonte de deux niveaux pour atteindre config.php
$configPath = dirname(__DIR__, 2) . '/config.php'; 
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Fichier config.php introuvable"]);
    exit();
}
require_once($configPath);


// ===========================
// 📌 LOGIQUE DE CONNEXION
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lire le JSON envoyé par le frontend
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    $matricule_input = $data['matricule'] ?? '';
    $password_input = $data['password'] ?? '';

    if (empty($matricule_input) || empty($password_input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Veuillez fournir le matricule et le mot de passe.']);
        exit();
    }

    try {
        $pdo = getDbConnection();

        // 🚨 CORRECTION : Utilisation de T1.matricule au lieu de T1.matricule_pers
        $sql = "
        SELECT 
            T1.matricule, 
            T1.nom, 
            T1.postnom, 
            T1.prenom, 
            T1.password_hash, 
            T1.id_role,  -- <--- AJOUT CRITIQUE POUR Layout.jsx
            T2.nom_role
        FROM 
        PERSONNEL T1
    JOIN 
        ROLE T2 ON T1.id_role = T2.id_role
    WHERE 
        T1.matricule = :matricule_input
    ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':matricule_input' => $matricule_input]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Vérification du mot de passe
            if (password_verify($password_input, $user['password_hash'])) {
                // Succès de la connexion
                
                // Préparation des données de session (sans le hachage du mot de passe)
                unset($user['password_hash']);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Connexion réussie!',
                    'user' => $user
                ]);
            } else {
                // Mot de passe incorrect
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Matricule ou mot de passe incorrect.']);
            }
        } else {
            // Matricule non trouvé
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Matricule ou mot de passe incorrect.']);
        }

    } catch (Exception $e) {
        // Erreur de connexion DB ou autre
        http_response_code(500); 
        echo json_encode(['success' => false, 'message' => 'Erreur de connexion à l\'API: ' . $e->getMessage()]);
    }
} else {
    // Si la méthode n'est pas POST
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}
?>