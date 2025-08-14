<?php

/**
 * Retourne le niveau correspondant à l'XP donnée pour un type spécifique.
 * @param string $type - Nom de la colonne XP dans la table (ex: "CharacterExp", "JobExp")
 * @param int $experience - Valeur d'expérience brute
 * @return int - Niveau calculé
 */
function getExperienceLevel($type, $experience)
{
    // Connexion à la BDD World
    try {
        $pdo_world = new PDO(
            "mysql:host=" . DB_WORLD_HOST . ";dbname=" . DB_WORLD_NAME . ";charset=utf8mb4",
            DB_WORLD_USER,
            DB_WORLD_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (PDOException $e) {
        die("Erreur connexion WORLD : " . $e->getMessage());
    }

    // Sécurité : vérifier que le type de colonne existe
    $allowedTypes = ['CharacterExp', 'GuildExp', 'MountExp', 'AlignmentHonor', 'JobExp'];
    if (!in_array($type, $allowedTypes)) {
        return 1; // Niveau minimal
    }

    // Récupérer les niveaux par ordre décroissant
    $stmt = $pdo_world->query("SELECT Level, `$type` AS ExpRequired FROM experiences ORDER BY Level DESC");
    $levels = $stmt->fetchAll();

    // Trouver le niveau correspondant
    foreach ($levels as $level) {
        if ($experience >= $level['ExpRequired']) {
            return (int) $level['Level'];
        }
    }

    return 1; // Par défaut
}
