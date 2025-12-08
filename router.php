<?php
// router.php

// Si l'URL demandée correspond à un fichier existant, servir ce fichier
if (file_exists($_SERVER["DOCUMENT_ROOT"] . $_SERVER["REQUEST_URI"])) {
    return false; // laisse le serveur gérer la requête
}

// Sinon, c'est que nous avons un problème de routing Front-end ou d'API manquante.
// Pour notre cas, nous laissons le serveur continuer pour voir si l'API est trouvée.

// En cas de doute, la fonction file_exists permet souvent de corriger
// les problèmes de résolution de chemins pour les serveurs de développement.
?>