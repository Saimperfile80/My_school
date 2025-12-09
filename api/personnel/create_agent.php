<?php
// api/personnel/create_agent.php

header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Remonte pour atteindre config.php (api/personnel -> api -> MY_SCHOOL -> config.php)
require_once('../../config.php'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $nom = $data['nom'] ?? '';
    $postnom = $data['postnom'] ?? '';
    $prenom = $data['prenom'] ?? '';
    $email = $data['email'] ?? '';
    $telephone = $data['telephone'] ?? '';
    $matricule = $data['matricule'] ?? '';
    $password = $data['password'] ?? '';
    $nom_role = $data['nom_role'] ?? ''; // Ex: 'Professeur', 'Comptable'

    // Validation des champs requis
    if (empty($nom) || empty($prenom) || empty($email) || empty($matricule) || empty($password) || empty($nom_role)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires (Nom, Prénom, Email, Matricule, Mot de passe, Rôle).']);
        exit();
    }
    
    // Hachage du mot de passe
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // 1. Trouver l'ID du Rôle
        $stmtRole = $pdo->prepare("SELECT id_role FROM ROLE WHERE nom_role = :nom_role");
        $stmtRole->execute([':nom_role' => $nom_role]);
        $id_role = $stmtRole->fetchColumn();

        if (!$id_role) {
            throw new Exception("Rôle '$nom_role' inconnu.");
        }

        // 2. Insérer le nouvel agent dans PERSONNEL
        $sql = "
            INSERT INTO PERSONNEL 
            (matricule, id_role, nom, postnom, prenom, email, telephone, password_hash, date_creation) 
            VALUES (:matricule, :id_role, :nom, :postnom, :prenom, :email, :telephone, :password_hash, datetime('now'))
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':matricule' => $matricule,
            ':id_role' => $id_role,
            ':nom' => $nom,
            ':postnom' => $postnom,
            ':prenom' => $prenom,
            ':email' => $email,
            ':telephone' => $telephone,
            ':password_hash' => $password_hash
        ]);

        $pdo->commit();
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => "L'agent $nom $prenom a été créé avec succès."]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorCode = $e->getCode();
        $message = "Erreur de base de données.";
        
        // Gérer les erreurs de doublon
        if (strpos($e->getMessage(), 'UNIQUE constraint failed')) {
             $message = "Ce matricule ou cette adresse email existe déjà. Veuillez en choisir un autre.";
        } else {
             $message .= " Détail : " . $e->getMessage();
        }
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $message]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
}
?>