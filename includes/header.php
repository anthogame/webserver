<?php
require_once __DIR__ . '/../config/config.php';

// Détection simple du chemin
$prefix = (basename(dirname($_SERVER['SCRIPT_NAME'])) === 'pages') ? '../' : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>

    <!-- CSS -->
    <link rel="stylesheet" href="<?= $prefix ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- JS -->
    <script defer src="<?= $prefix ?>assets/js/main.js"></script>
</head>
<body data-theme="<?= $_SESSION['theme'] ?? 'dark' ?>">
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="<?= $prefix ?>index.php"><?= SITE_NAME ?></a>
            </div>

            <?php if (isLoggedIn()): ?>
                <ul class="nav-menu">
                    <li><a href="<?= $prefix ?>index.php"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="<?= $prefix ?>pages/characters.php"><i class="fas fa-users"></i> Personnages</a></li>
                    <li><a href="<?= $prefix ?>pages/actions.php"><i class="fas fa-cogs"></i> Actions</a></li>
                    <li><a href="<?= $prefix ?>pages/profile.php"><i class="fas fa-user"></i> Profil</a></li>
                </ul>

                <div class="nav-actions">
                    <button id="theme-toggle" class="theme-btn">
                        <i class="fas fa-moon"></i>
                    </button>
                    <div class="user-menu">
                        <span>Bonjour, <?= htmlspecialchars($_SESSION['username']) ?></span>
                        <a href="<?= $prefix ?>api/auth.php?action=logout" class="logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <main class="main-content">
