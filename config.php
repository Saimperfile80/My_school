    <?php

    header("Access-Control-Allow-Origin: *"); 
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    define('DB_PATH', __DIR__ . '/data/my_school.sqlite'); 

    /**
     * Tente d'établir une connexion PDO (PHP Data Objects) avec SQLite.
     * Si le fichier de base de données n'existe pas, il le crée et initialise.
     * @return PDO La connexion à la base de données.
     */
    function getDbConnection(): PDO {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            // Crée le dossier 'data' s'il n'existe pas
            mkdir($dbDir, 0777, true);
        }

        $isNewDb = !file_exists(DB_PATH);
        
        try {
            // Connexion à la base de données
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Activer les Clés Étrangères (CRITIQUE pour l'intégrité)
            $pdo->exec('PRAGMA foreign_keys = ON;');
            
            if ($isNewDb) {
                // Si le fichier est nouveau, on exécute le schéma SQL
                initializeDatabaseSchema($pdo);
                // 🚨 Insertion des données initiales après la création du schéma
                insertInitialTestData($pdo);
            }

            return $pdo;

        } catch (PDOException $e) {
            // En mode production Electron, loggez cette erreur plutôt que 'die'
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }

    /**
     * Exécute le script de création des tables.
     * @param PDO $pdo La connexion à la base de données.
     */
    function initializeDatabaseSchema(PDO $pdo) {
        
        $sql_statements = [
            // 1. Table ROLE 
            "CREATE TABLE IF NOT EXISTS ROLE (id_role INTEGER PRIMARY KEY AUTOINCREMENT, nom_role TEXT UNIQUE NOT NULL);",
            "INSERT OR IGNORE INTO ROLE (nom_role) VALUES ('Préfet'), ('Directeur'), ('Comptable'), ('Professeur'), ('Administrateur Système');",

            // 2. Table PERSONNEL
            "CREATE TABLE IF NOT EXISTS PERSONNEL (
                matricule TEXT PRIMARY KEY UNIQUE NOT NULL, 
                id_role INTEGER NOT NULL,
                nom TEXT NOT NULL,
                postnom TEXT NOT NULL,
                prenom TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                telephone TEXT UNIQUE,
                password_hash TEXT NOT NULL,
                salaire_base REAL NOT NULL DEFAULT 0.0,
                date_creation DATETIME NOT NULL,
                FOREIGN KEY (id_role) REFERENCES ROLE(id_role) ON DELETE RESTRICT
            );",
            
            // 3. Table CLASSE
            "CREATE TABLE IF NOT EXISTS CLASSE (
                id_classe INTEGER PRIMARY KEY AUTOINCREMENT,
                nom_classe TEXT NOT NULL,
                option_classe TEXT NOT NULL,
                promotion_annee TEXT NOT NULL,
                UNIQUE(nom_classe, option_classe, promotion_annee) -- 🚨 CORRECTION : Unicité sur les trois champs
            );",
            
            // 4. Table ELEVE
            "CREATE TABLE IF NOT EXISTS ELEVE (
                matricule_eleve TEXT PRIMARY KEY UNIQUE NOT NULL, 
                id_classe INTEGER NOT NULL,
                nom TEXT NOT NULL,
                postnom TEXT NOT NULL,
                prenom TEXT NOT NULL,
                date_naissance DATE NOT NULL,
                date_inscription DATETIME NOT NULL,
                statut_actif INTEGER NOT NULL DEFAULT 1, 
                FOREIGN KEY (id_classe) REFERENCES CLASSE(id_classe) ON DELETE RESTRICT
            );",
            
            // 5. Table FRAIS
            "CREATE TABLE IF NOT EXISTS FRAIS (
                id_frais INTEGER PRIMARY KEY AUTOINCREMENT,
                motif TEXT UNIQUE NOT NULL,
                montant_standard REAL NOT NULL,
                statut_actif INTEGER NOT NULL DEFAULT 1
            );",
            
            // 6. Table PAIEMENT
            "CREATE TABLE IF NOT EXISTS PAIEMENT (
                id_paiement INTEGER PRIMARY KEY AUTOINCREMENT,
                matricule_eleve TEXT NOT NULL,
                id_frais INTEGER NOT NULL,
                montant_paye REAL NOT NULL,
                date_enregistrement DATETIME NOT NULL,
                id_comptable TEXT NOT NULL, 
                reference_recu TEXT UNIQUE NOT NULL,
                FOREIGN KEY (matricule_eleve) REFERENCES ELEVE(matricule_eleve) ON DELETE RESTRICT,
                FOREIGN KEY (id_frais) REFERENCES FRAIS(id_frais) ON DELETE RESTRICT,
                FOREIGN KEY (id_comptable) REFERENCES PERSONNEL(matricule) ON DELETE RESTRICT
            );",
            
            // 7. Table COURS
            "CREATE TABLE IF NOT EXISTS COURS (
                id_cours INTEGER PRIMARY KEY AUTOINCREMENT,
                nom_cours TEXT UNIQUE NOT NULL,
                coefficient INTEGER NOT NULL DEFAULT 1
            );",
            
            // 8. Table AFFECTATION_COURS
            "CREATE TABLE IF NOT EXISTS AFFECTATION_COURS (
                id_affectation INTEGER PRIMARY KEY AUTOINCREMENT,
                id_cours INTEGER NOT NULL,
                id_classe INTEGER NOT NULL,
                matricule_pers TEXT NOT NULL, 
                promotion_annee TEXT NOT NULL,
                UNIQUE(id_cours, id_classe, matricule_pers, promotion_annee), 
                FOREIGN KEY (id_cours) REFERENCES COURS(id_cours) ON DELETE RESTRICT,
                FOREIGN KEY (id_classe) REFERENCES CLASSE(id_classe) ON DELETE RESTRICT,
                FOREIGN KEY (matricule_pers) REFERENCES PERSONNEL(matricule) ON DELETE RESTRICT
            );",
            
            // 9. Table NOTES
            "CREATE TABLE IF NOT EXISTS NOTES (
                id_note INTEGER PRIMARY KEY AUTOINCREMENT,
                matricule_eleve TEXT NOT NULL,
                id_cours INTEGER NOT NULL,
                matricule_pers TEXT NOT NULL, -- Professeur qui a donné la note
                type_evaluation TEXT NOT NULL, -- Ex: Interrogation, Examen, Participation
                note_obtenue REAL NOT NULL,
                note_max INTEGER NOT NULL DEFAULT 20,
                commentaire TEXT,
                date_evaluation DATETIME NOT NULL,
                
                FOREIGN KEY (matricule_eleve) REFERENCES ELEVE(matricule_eleve) ON DELETE RESTRICT,
                FOREIGN KEY (id_cours) REFERENCES COURS(id_cours) ON DELETE RESTRICT,
                FOREIGN KEY (matricule_pers) REFERENCES PERSONNEL(matricule) ON DELETE RESTRICT
            );"
        ];
        
        foreach ($sql_statements as $sql) {
            $pdo->exec($sql);
        }
    }

    /**
     * Insère les données initiales et de test nécessaires pour l'application.
     * @param PDO $pdo La connexion à la base de données.
     */
    // --- Dans config.php : Remplacez la fonction insertInitialTestData ---

    function insertInitialTestData(PDO $pdo) {
    try {
        // 1. Trouver TOUS les IDs des rôles au début
        // 🚨 CORRECTION CRITIQUE: On sélectionne nom_role en premier et id_role en second.
        // Cela garantit que PDO::FETCH_KEY_PAIR crée le tableau : ['Préfet' => 1, 'Professeur' => 4, ...]
        $stmtRoles = $pdo->prepare("SELECT nom_role, id_role FROM ROLE"); 
        $stmtRoles->execute();
        $roles = $stmtRoles->fetchAll(PDO::FETCH_KEY_PAIR); // Associe nom_role (Clé) => id_role (Valeur)
        
        $prefetRoleId = $roles['Préfet'] ?? null;
        $profRoleId = $roles['Professeur'] ?? null;
        $comptableRoleId = $roles['Comptable'] ?? null; 

        if (!$prefetRoleId || !$profRoleId || !$comptableRoleId) {
            // Cette ligne ne devrait plus s'afficher !
            error_log("Rôles (Préfet, Professeur ou Comptable) non trouvés. Vérifiez le schéma de la table ROLE.");
            return;
        }

        // 2. Insertion du Préfet A001 (Administrateur de Test)
        $adminPasswordHash = password_hash("admin123", PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT OR IGNORE INTO PERSONNEL 
            (matricule, id_role, nom, postnom, prenom, email, password_hash, date_creation) 
            VALUES ('A001', $prefetRoleId, 'Admin', 'Chef', 'Principal', 'prefet@myschool.com', '$adminPasswordHash', datetime('now'))
        ");
        
        // 3. Insertion du Comptable C001 (Inscription des élèves)
        $comptablePasswordHash = password_hash("compte123", PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT OR IGNORE INTO PERSONNEL 
            (matricule, id_role, nom, postnom, prenom, email, password_hash, date_creation) 
            VALUES ('C001', $comptableRoleId, 'Mme', 'Caisse', 'Julie', 'comptable@myschool.com', '$comptablePasswordHash', datetime('now'))
        ");

        // 4. Insertion du professeur P002 (pour les tests de saisie de note)
        $profPasswordHash = password_hash("password123", PASSWORD_DEFAULT);
        $pdo->exec("
            INSERT OR IGNORE INTO PERSONNEL 
            (matricule, id_role, nom, postnom, prenom, email, password_hash, date_creation) 
            VALUES ('P002', $profRoleId, 'Prof', 'Test', 'Maths', 'prof@school.com', '$profPasswordHash', datetime('now'))
        ");
        
        // 5. Insertion des données minimales pour les tests de saisie de notes
        $pdo->exec("INSERT OR IGNORE INTO COURS (nom_cours, coefficient) VALUES ('Mathématiques', 4)");
        $pdo->exec("INSERT OR IGNORE INTO COURS (nom_cours, coefficient) VALUES ('Biologie', 3)");
        $pdo->exec("INSERT OR IGNORE INTO CLASSE (nom_classe, option_classe, promotion_annee) VALUES ('5ème', 'Biologie', '2025')");
        
        // Récupérer les IDs nécessaires
        $idClasse = $pdo->query("SELECT id_classe FROM CLASSE WHERE nom_classe='5ème' AND option_classe='Biologie'")->fetchColumn();
        $idCours = $pdo->query("SELECT id_cours FROM COURS WHERE nom_cours='Mathématiques'")->fetchColumn();

        // Insérer l'affectation Professeur P002
        if ($idClasse && $idCours) {
            $pdo->exec("INSERT OR IGNORE INTO AFFECTATION_COURS (id_cours, id_classe, matricule_pers, promotion_annee) VALUES ($idCours, $idClasse, 'P002', '2025')");
        }
        
        // Insérer les élèves E002 et E003 (pour les tests de notes)
        if ($idClasse) {
            $pdo->exec("INSERT OR IGNORE INTO ELEVE (matricule_eleve, id_classe, nom, postnom, prenom, date_naissance, date_inscription) VALUES ('E002', $idClasse, 'Kabongo', 'Kazadi', 'Yves', '2005-01-01', datetime('now'))");
            $pdo->exec("INSERT OR IGNORE INTO ELEVE (matricule_eleve, id_classe, nom, postnom, prenom, date_naissance, date_inscription) VALUES ('E003', $idClasse, 'Tshibangu', 'Mutombo', 'Grace', '2005-01-01', datetime('now'))");
        }

    } catch (Exception $e) {
        error_log("Erreur lors de l'insertion des données de test : " . $e->getMessage());
    }
}

    /**
     * Fonction utilitaire pour vérifier si un préfet existe dans la table PERSONNEL.
     * @param PDO $pdo La connexion à la base de données.
     * @return bool Vrai si aucun préfet n'est trouvé (première exécution), Faux sinon.
     */
    function isFirstRun(PDO $pdo): bool {
        // Compter les utilisateurs dont le rôle est 'Préfet'
        $stmt = $pdo->prepare("SELECT COUNT(T1.matricule) FROM PERSONNEL T1 JOIN ROLE T2 ON T1.id_role = T2.id_role WHERE T2.nom_role = 'Préfet'");
        $stmt->execute();
        
        // Si le compte est 0, c'est la première exécution.
        return $stmt->fetchColumn() == 0;
    }
    ?>