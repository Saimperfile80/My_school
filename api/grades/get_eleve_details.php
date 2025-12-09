<?php
// /api/grades/get_eleve_details.php
// API pour récupérer le parcours (notes, absences, discipline) d'un élève pour un cours donné

// ===========================
// 🌐 CORS et Config
// ===========================
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json");

// Chargement de la configuration et des fonctions DB
$configPath = dirname(__DIR__, 2) . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Fichier config.php introuvable"]);
    exit();
}
require_once($configPath);


// ===========================
// 📌 VALIDATION DES PARAMÈTRES
// ===========================
$eleveMatricule = $_GET['eleve_id'] ?? null;
$profMatricule = $_GET['prof_id'] ?? null;
// Nous utilisons le nom du cours pour le moment, mais l'ID du cours est préférable
$coursNom = $_GET['cours_nom'] ?? null; 

if (!$eleveMatricule || !$profMatricule || !$coursNom) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Paramètres obligatoires (eleve_id, prof_id, cours_nom) manquants.']);
    exit();
}

try {
    $pdo = getDbConnection();

    // 1. Récupérer l'ID du Cours à partir de son nom
    $stmtCours = $pdo->prepare("SELECT id_cours FROM COURS WHERE nom_cours = :coursNom");
    $stmtCours->execute([':coursNom' => $coursNom]);
    $cours = $stmtCours->fetch(PDO::FETCH_ASSOC);

    if (!$cours) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Cours non trouvé.']);
        exit();
    }
    $idCours = $cours['id_cours'];

    // 2. Récupérer les notes de cet élève pour ce cours donné par ce professeur
    // NOTE: Ceci suppose l'existence d'une table NOTES qui contient id_cours, matricule_eleve, et une référence à l'année/période.
    $stmtNotes = $pdo->prepare("
        SELECT 
            type_evaluation, note_obtenue, date_evaluation, commentaire
        FROM 
            NOTES 
        WHERE 
            matricule_eleve = :eleveMatricule AND id_cours = :idCours 
        ORDER BY date_evaluation DESC
    ");
    $stmtNotes->execute([':eleveMatricule' => $eleveMatricule, ':idCours' => $idCours]);
    $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupérer l'historique d'absence et de discipline (PLACEHOLDER)
    // Vous devez créer ces tables (ABSENCE, DISCIPLINE) dans votre schéma.
    $absences = []; // Simulé
    $discipline = []; // Simulé

    // 4. Succès et retour JSON
    http_response_code(200);
    echo json_encode([
        'success' => true, 
        'message' => 'Détails de l\'élève chargés avec succès.',
        'data' => [
            'notes' => $notes,
            'absences' => $absences,
            'discipline' => $discipline,
            'prof_id_check' => $profMatricule // Utile pour le débogage
        ]
    ]);
    $stmtNotes = $pdo->prepare("
        SELECT 
            type_evaluation, note_obtenue, date_evaluation, commentaire
        FROM 
            NOTES  -- 🚨 C'est ici que l'erreur se produit si NOTES n'existe pas.
        WHERE 
            matricule_eleve = :eleveMatricule AND id_cours = :idCours 
        ORDER BY date_evaluation DESC
    ");

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur API : ' . $e->getMessage()]);
}
?>