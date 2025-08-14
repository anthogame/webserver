<?php
require_once 'config/config.php';
require_once 'config/database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$pdo_auth  = Database::getAuthConnection();
$pdo_world = Database::getWorldConnection();

$azuriomId = $_SESSION['user_id'];

// Comptes jeu liés au compte web
$stmt = $pdo_auth->prepare("SELECT Id, Login, LastConnection FROM accounts WHERE AzuriomId = ?");
$stmt->execute([$azuriomId]);
$gameAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ajout statut connecté
foreach ($gameAccounts as &$acc) {
    $stmtOnline = $pdo_world->prepare("SELECT ConnectedCharacter FROM accounts WHERE Id = ?");
    $stmtOnline->execute([$acc['Id']]);
    $connectedCharId = $stmtOnline->fetchColumn();
    $acc['Online'] = (!empty($connectedCharId) && $connectedCharId > 0);
}
unset($acc);

include 'includes/header.php';
?>

<style>
  html, body { height: 100%; }
  body { display: flex; flex-direction: column; }
  main.index-main { flex: 1; }
</style>

<main class="index-main">
  <div class="container fade-in">
    <div class="card" style="margin-top: 1rem;">
      <h1 style="margin-bottom: .25rem;">Mes comptes Dofus</h1>
      <p style="color: var(--text-secondary);">Bienvenue, <?= htmlspecialchars($_SESSION['username']) ?> !</p>
    </div>

    <?php if (empty($gameAccounts)): ?>
      <div class="card">
        <p>Aucun compte Dofus lié à votre profil.</p>
        <p style="color: var(--text-secondary); font-size:.95rem;">Liez un compte jeu depuis votre espace web.</p>
      </div>
    <?php else: ?>
      <div class="characters-grid">
        <?php foreach ($gameAccounts as $acc): ?>
          <div class="character-card">
            <div class="character-header">
              <div class="character-avatar">
                <i class="fas fa-user"></i>
              </div>
              <div class="character-info">
                <h3><?= htmlspecialchars($acc['Login']) ?></h3>
                <small style="color:var(--text-secondary);">
                  Dernière connexion :
                  <?= !empty($acc['LastConnection']) ? htmlspecialchars($acc['LastConnection']) : 'Jamais' ?>
                </small>
              </div>
            </div>

            <div class="character-stats">
              <div class="stat-item">
                <strong>ID</strong><br><?= (int)$acc['Id'] ?>
              </div>
              <div class="stat-item">
                <strong>Statut</strong><br>
                <?php if ($acc['Online']): ?>
                    <span class="status-badge status-online">En ligne</span>
                <?php else: ?>
                    <span class="status-badge status-offline">Déconnecté</span>
                <?php endif; ?>
              </div>
            </div>

            <a class="btn" href="pages/characters.php?account_id=<?= (int)$acc['Id'] ?>">
              Voir les personnages
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include 'includes/footer.php'; ?>
