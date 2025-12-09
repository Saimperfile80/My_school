<?php
// /api/grades/save_grade.php
// API pour enregistrer une nouvelle note (Méthode POST)

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

$configPath = dirname(__DIR__, 2) . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Fichier config.php introuvable"]);
    exit();
}
require_once($configPath);


// ===========================
// 📌 VALIDATION DE LA REQUÊTE
// ===========================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée. Utilise POST.']);
    exit();
}

// Lire le JSON envoyé
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validation minimale des champs
if (empty($data['matricule_eleve']) || empty($data['matricule_prof']) || empty($data['nom_cours']) || !isset($data['note_obtenue']) || !isset($data['note_max'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données de note incomplètes.']);
    exit();
}

$matricule_eleve = $data['matricule_eleve'];
$matricule_prof = $data['matricule_prof'];
$nom_cours = $data['nom_cours'];
$type_evaluation = $data['type_evaluation'] ?? 'Interrogation';
$note_obtenue = (float)$data['note_obtenue'];
$note_max = (int)$data['note_max'];
$commentaire = $data['commentaire'] ?? '';
$date_evaluation = date('Y-m-d H:i:s'); // Date et heure actuelles

try {
    $pdo = getDbConnection();

    // 1. Récupérer l'ID du Cours à partir de son nom
    $stmtCours = $pdo->prepare("SELECT id_cours FROM COURS WHERE nom_cours = :coursNom");
    $stmtCours->execute([':coursNom' => $nom_cours]);
    $cours = $stmtCours->fetch(PDO::FETCH_ASSOC);

    if (!$cours) {
        throw new Exception("Cours non trouvé : " . $nom_cours);
    }
    $idCours = $cours['id_cours'];

    // 2. Insérer la nouvelle note
    $sql = "
        INSERT INTO NOTES 
        (matricule_eleve, id_cours, matricule_pers, type_evaluation, note_obtenue, note_max, date_evaluation, commentaire) 
        VALUES (:eleve, :cours, :prof, :type, :note, :max, :date, :commentaire)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':eleve' => $matricule_eleve,
        ':cours' => $idCours,
        ':prof' => $matricule_prof,
        ':type' => $type_evaluation,
        ':note' => $note_obtenue,
        ':max' => $note_max,
        ':date' => $date_evaluation,
        ':commentaire' => $commentaire
    ]);

    // 3. Succès
    http_response_code(201); // 201 Created
    echo json_encode([
        'success' => true, 
        'message' => 'Note enregistrée avec succès.',
        'note_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    http_response_code(500); 
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données : ' . $e->getMessage()]);
}
?>