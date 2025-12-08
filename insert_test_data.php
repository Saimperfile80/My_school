<?php
// insert_test_data.php

// Inclure le fichier de configuration (connexion BDD)
require_once('config.php'); 

// Définir un mot de passe non haché pour la démo
$password_test = 'password123';
// ⚠️ NOTE : En production, vous hacheriez ce mot de passe (e.g., password_hash($password_test, PASSWORD_BCRYPT))

try {
    $pdo = getDbConnection();
    $pdo->beginTransaction(); // Début de la transaction

    echo "Tentative d'insertion du personnel de test...\n";

    // Insertion des Rôles (ID 1: Préfet, ID 3: Comptable, ID 4: Professeur)
    // Les IDs sont déterminés par l'insertion dans config.php

    $users_to_insert = [
        // PRÉFET (ID 1)
        [
            'matricule' => 'A001', 
            'role_id' => 1, 
            'nom' => 'KAMBALE', 
            'postnom' => 'Patrick', 
            'prenom' => 'Jean',
            'email' => 'prefet@myschool.com',
            'tel' => '0990000001',
            'password_hash' => $password_test // Simulation
        ],
        // COMPTABLE (ID 3)
        [
            'matricule' => 'C101', 
            'role_id' => 3, 
            'nom' => 'KAYEMBE', 
            'postnom' => 'Rachel', 
            'prenom' => 'Marie',
            'email' => 'comptable@myschool.com',
            'tel' => '0990000003',
            'password_hash' => $password_test
        ],
        // PROFESSEUR (ID 4)
        [
            'matricule' => 'P002', 
            'role_id' => 4, 
            'nom' => 'KANKONDE', 
            'postnom' => 'Patrick', 
            'prenom' => 'Daniel',
            'email' => 'professeur@myschool.com',
            'tel' => '0990000004',
            'password_hash' => $password_test
        ]
    ];

    $stmt = $pdo->prepare("
        INSERT INTO PERSONNEL 
        (matricule_pers, id_role, nom, postnom, prenom, email, telephone, password_hash, date_creation) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATETIME('now'))
    ");

    foreach ($users_to_insert as $user) {
        try {
            $stmt->execute(array_values($user));
            echo "  -> Utilisateur {$user['matricule']} ({$user['nom']} - Rôle {$user['role_id']}) inséré.\n";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Code pour 'UNIQUE constraint failed' (déjà inséré)
                echo "  -> Utilisateur {$user['matricule']} existe déjà. Saut.\n";
            } else {
                throw $e; // Renvoyer l'erreur si ce n'est pas un conflit d'unicité
            }
        }
    }

    $pdo->commit(); // Validation des changements
    echo "Insertion des données de test terminée avec succès.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Annulation en cas d'erreur
    }
    die("Erreur fatale lors de l'insertion des données : " . $e->getMessage() . "\n");
}
?>