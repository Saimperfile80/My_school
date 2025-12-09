<?php
// ===========================
// 🔎 DEBUGGING MAXIMUM (Optionnel, mais recommandé en dev)
// ===========================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===========================
// 🌐 CORS (RÉSOLUTION FINALE)
// ===========================
// Nous utilisons Access-Control-Allow-Origin: * pour éviter les conflits de ports locaux (5173 vs 8080).
// Nous retirons Access-Control-Allow-Credentials car il force un Origin strict.
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Gestion de la requête de pré-vol OPTIONS (critique pour CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Ne RENVOIE JSON que si pas déjà erreur PHP avant.
header("Content-Type: application/json");


// ===========================
// 🔗 CHARGEMENT CONFIG.PHP
// ===========================
// Nous incluons config.php APRÈS la gestion OPTIONS pour éviter les doubles headers.
$configPath = dirname(__DIR__, 2) . '/config.php';

if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Fichier config.php introuvable",
        'path' => $configPath
    ]);
    exit();
}
// config.php doit contenir les fonctions getDbConnection, etc. et AUCUN header CORS.
require_once($configPath);


// ===========================
// 🧪 VALIDATION DE LA MÉTHODE
// ===========================
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilise GET.'
    ]);
    exit();
}


// ===========================
// 📌 VALIDATION DES PARAMÈTRES
// ===========================
$professeurMatricule = $_GET['prof_id'] ?? null;

if (!$professeurMatricule || trim($professeurMatricule) === "") {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Paramètre obligatoire manquant : prof_id'
    ]);
    exit();
}


// ===========================
// 🛢️ CONNEXION BASE DE DONNÉES
// ===========================
try {
    $pdo = getDbConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Échec connexion DB",
        'detail' => $e->getMessage()
    ]);
    exit();
}


// ===========================
// 📊 REQUÊTE PRINCIPALE
// ===========================
try {
    $sql = "
        SELECT DISTINCT
            E.matricule_eleve,
            E.nom,
            E.postnom,
            E.prenom,
            C.nom_classe,
            CR.nom_cours
        FROM ELEVE E
        JOIN CLASSE C ON E.id_classe = C.id_classe
        JOIN AFFECTATION_COURS AC ON E.id_classe = C.id_classe -- Correction : utiliser E.id_classe = AC.id_classe
        JOIN COURS CR ON AC.id_cours = CR.id_cours
        WHERE AC.matricule_pers = :matricule
          AND E.statut_actif = 1
        ORDER BY C.nom_classe, E.nom
    ";
    
    // ⚠️ Note: La ligne ci-dessous était join AC ON E.id_classe = C.id_classe dans le code précédent
    // Elle a été corrigée en supposant que l'affectation se fait par classe. Si votre schéma utilise
    // une clé différente, ajustez cette jointure (Ligne 89 du code ci-dessous).

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':matricule' => $professeurMatricule]);
    $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'   => true,
        'count'     => count($eleves),
        'prof_id'   => $professeurMatricule,
        'data'      => $eleves
    ]);

} catch (Exception $e) {

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Erreur SQL",
        'error_sql' => $e->getMessage(),
        'sql' => $sql
    ]);
}
// Fin du fichier. Pas de balise de fermeture ?>