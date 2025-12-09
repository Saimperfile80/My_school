<?php
// api/eleves/create_eleve.php

header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once('../../config.php'); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $matricule_eleve = $data['matricule_eleve'] ?? '';
    $nom = $data['nom'] ?? '';
    $postnom = $data['postnom'] ?? '';
    $prenom = $data['prenom'] ?? '';
    $date_naissance = $data['date_naissance'] ?? ''; // Format AAAA-MM-JJ
    $nom_classe = $data['nom_classe'] ?? '';
    $option_classe = $data['option_classe'] ?? '';
    $promotion_annee = $data['promotion_annee'] ?? date('Y'); // Année en cours par défaut

    // Validation des champs requis
    if (empty($matricule_eleve) || empty($nom) || empty($prenom) || empty($date_naissance) || empty($nom_classe) || empty($option_classe)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires pour l\'inscription.']);
        exit();
    }
    
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        // 1. Trouver l'ID de la CLASSE (ou la créer si elle n'existe pas)
        $stmtClasse = $pdo->prepare("SELECT id_classe FROM CLASSE WHERE nom_classe = :nom_classe AND option_classe = :option_classe AND promotion_annee = :promotion_annee");
        $stmtClasse->execute([':nom_classe' => $nom_classe, ':option_classe' => $option_classe, ':promotion_annee' => $promotion_annee]);
        $id_classe = $stmtClasse->fetchColumn();

        if (!$id_classe) {
            // Créer la classe si elle n'existe pas (utile pour les nouvelles années scolaires)
            $pdo->exec("INSERT INTO CLASSE (nom_classe, option_classe, promotion_annee) VALUES ('$nom_classe', '$option_classe', '$promotion_annee')");
            $id_classe = $pdo->lastInsertId();
        }

        // 2. Insérer l'élève
        $sql = "
            INSERT INTO ELEVE 
            (matricule_eleve, id_classe, nom, postnom, prenom, date_naissance, date_inscription, statut_actif) 
            VALUES (:matricule_eleve, :id_classe, :nom, :postnom, :prenom, :date_naissance, datetime('now'), 1)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':matricule_eleve' => $matricule_eleve,
            ':id_classe' => $id_classe,
            ':nom' => $nom,
            ':postnom' => $postnom,
            ':prenom' => $prenom,
            ':date_naissance' => $date_naissance
        ]);
$pdo->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => "L'élève $nom $prenom a été inscrit avec succès."]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $message = "Erreur de base de données. ";
    
    // 🚨 Logique pour détecter et gérer le problème de matricule (cas le plus fréquent)
    if (strpos($e->getMessage(), 'UNIQUE constraint failed: ELEVE.matricule_eleve') !== false) {
         $message = "Ce matricule d'élève existe déjà. Veuillez en choisir un autre.";
    } 
    // 🚨 Logique pour les autres contraintes (ex: la classe existe déjà)
    else if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
        $message = "Erreur de contrainte : La classe ou l'option spécifiée est déjà enregistrée. Détails: " . $e->getMessage();
    }
    else {
         $message .= "Détail : " . $e->getMessage();
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