<?php
// config.php - Version Finale et stable pour l'initialisation BDD

// Définir la constante DB_PATH. __DIR__ est le chemin absolu du dossier my_school/
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
    
    // ⚠️ CORRECTION : Découpage du schéma en commandes individuelles pour garantir l'exécution par SQLite.
    $sql_statements = [
        // 1. Table ROLE 
        "CREATE TABLE IF NOT EXISTS ROLE (id_role INTEGER PRIMARY KEY AUTOINCREMENT, nom_role TEXT UNIQUE NOT NULL);",
        "INSERT INTO ROLE (nom_role) VALUES ('Préfet'), ('Directeur'), ('Comptable'), ('Professeur'), ('Administrateur Système');",

        // 2. Table PERSONNEL 
        "CREATE TABLE IF NOT EXISTS PERSONNEL (
            matricule_pers TEXT PRIMARY KEY UNIQUE NOT NULL, 
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
            nom_classe TEXT UNIQUE NOT NULL,
            option_classe TEXT NOT NULL,
            promotion_annee TEXT NOT NULL 
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
            FOREIGN KEY (id_comptable) REFERENCES PERSONNEL(matricule_pers) ON DELETE RESTRICT
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
            FOREIGN KEY (matricule_pers) REFERENCES PERSONNEL(matricule_pers) ON DELETE RESTRICT
        );",
        
        // 9. Table COTE
        "CREATE TABLE IF NOT EXISTS COTE (
            id_cote INTEGER PRIMARY KEY AUTOINCREMENT,
            matricule_eleve TEXT NOT NULL,
            id_cours INTEGER NOT NULL,
            id_affectation INTEGER NOT NULL,
            cote_obtenue REAL NOT NULL,
            periode_evaluation TEXT NOT NULL,
            date_enregistrement DATETIME NOT NULL,
            UNIQUE(matricule_eleve, id_cours, periode_evaluation), 
            FOREIGN KEY (matricule_eleve) REFERENCES ELEVE(matricule_eleve) ON DELETE RESTRICT,
            FOREIGN KEY (id_cours) REFERENCES COURS(id_cours) ON DELETE RESTRICT,
            FOREIGN KEY (id_affectation) REFERENCES AFFECTATION_COURS(id_affectation) ON DELETE RESTRICT
        );"
    ];
    
    foreach ($sql_statements as $sql) {
        // Exécute chaque commande SQL individuellement
        $pdo->exec($sql);
    }
}

/**
 * Fonction utilitaire pour vérifier si un préfet existe dans la table PERSONNEL.
 * @param PDO $pdo La connexion à la base de données.
 * @return bool Vrai si aucun préfet n'est trouvé (première exécution), Faux sinon.
 */
function isFirstRun(PDO $pdo): bool {
    // Compter les utilisateurs dont le rôle est 'Préfet'
    $stmt = $pdo->prepare("SELECT COUNT(T1.matricule_pers) FROM PERSONNEL T1 JOIN ROLE T2 ON T1.id_role = T2.id_role WHERE T2.nom_role = 'Préfet'");
    $stmt->execute();
    
    // Si le compte est 0, c'est la première exécution.
    return $stmt->fetchColumn() == 0;
}
?>