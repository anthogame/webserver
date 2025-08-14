<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect($prefix . 'login.php');
}

$pdo_auth  = Database::getAuthConnection();
$pdo_world = Database::getWorldConnection();

$azuriomId = $_SESSION['user_id'];

/* 1) Comptes liés (AUTH) */
$stmt = $pdo_auth->prepare("SELECT Id, Login FROM accounts WHERE AzuriomId = ?");
$stmt->execute([$azuriomId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Sélection du compte (GET sécurisé) */
$selectedAccountId = null;
if (!empty($_GET['account_id'])) {
    $candidate = (int) $_GET['account_id'];
    foreach ($accounts as $acc) {
        if ((int)$acc['Id'] === $candidate) {
            $selectedAccountId = $candidate;
            break;
        }
    }
}
if ($selectedAccountId === null && !empty($accounts)) {
    $selectedAccountId = (int)$accounts[0]['Id'];
}

/* Helpers */
function getExistingColumns(PDO $pdo, string $dbName, string $table, array $wanted): array {
    if (empty($wanted)) return [];
    $in = str_repeat('?,', count($wanted)-1) . '?';
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME IN ($in)
    ");
    $params = array_merge([$dbName, $table], $wanted);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

if (!function_exists('getExperienceLevel')) {
    /**
     * Calcule le niveau en fonction d'une XP et d'un type (CharacterExp, JobExp, etc.)
     */
    function getExperienceLevel(string $type, int $experience): int {
        $allowed = ['CharacterExp','GuildExp','MountExp','AlignmentHonor','JobExp'];
        if (!in_array($type, $allowed, true)) return 1;
        try {
            $pdo = Database::getWorldConnection();
        } catch (PDOException $e) {
            return 1;
        }
        $stmt = $pdo->query("SELECT Level, `$type` AS ExpRequired FROM experiences ORDER BY Level DESC");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($experience >= (int)$row['ExpRequired']) {
                return (int)$row['Level'];
            }
        }
        return 1;
    }
}

/** Formattage d'affichage de niveau avec Oméga si > 200 */
function formatOmegaLevel(int $level): string {
    return ($level > 200) ? ('Oméga ' . ($level - 200)) : (string)$level;
}

/** Mappings */
$alignNames = [ 0=>'Neutre', 1=>'Bontarien', 2=>'Brakmarien', 3=>'Mercenaire' ];
$sexNames   = [ 0=>'Homme', 1=>'Femme' ];

$characters = [];
$accountLogin = null;
$accountIsOnline = false;
$connectedCharacterId = null;
$breedNames = []; // Id => ShortName

if ($selectedAccountId !== null) {
    foreach ($accounts as $acc) {
        if ((int)$acc['Id'] === $selectedAccountId) {
            $accountLogin = $acc['Login'];
            break;
        }
    }

    // Statut compte (WORLD)
    $stmt = $pdo_world->prepare("SELECT ConnectedCharacter FROM accounts WHERE Id = ?");
    $stmt->execute([$selectedAccountId]);
    $connectedCharacterId = $stmt->fetchColumn();
    $accountIsOnline = !empty($connectedCharacterId);

    // IDs persos via AUTH.worlds_characters (pour être fidèle à ta structure)
    $stmt = $pdo_auth->prepare("SELECT Id FROM worlds_characters WHERE AccountId = ?");
    $stmt->execute([$selectedAccountId]);
    $charIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($charIds)) {
        // Colonnes de base + bonus si elles existent
        $baseCols = [
            'Id','Name','AccountId','Experience','Breed','Sex','AlignmentSide','Honor','LastLookString','CreationDate',
            // Caractéristiques de base (confirmées par toi)
            'BaseHealth','AP','MP','Prospection','Strength','Intelligence','Chance','Agility','Wisdom'
        ];
        $bonusColsWanted = [
            'Grade','Honneur','Prestige','PrestigeRank','Alignement',
            'WinPvm','LosPvm','SuccessPoints',
            'PermanentAddedVitality','PermanentAddedChance','PermanentAddedAgility',
            'PermanentAddedStrength','PermanentAddedIntelligence','PermanentAddedWisdom'
        ];
        $existingBonus = getExistingColumns($pdo_world, DB_WORLD_NAME, 'characters', $bonusColsWanted);
        $selectCols = array_merge($baseCols, $existingBonus);

        $selectList = implode(', ', array_map(fn($c) => "c.`$c`", $selectCols));
        $in = implode(',', array_fill(0, count($charIds), '?'));

        $sql = "SELECT $selectList FROM characters c WHERE c.Id IN ($in) ORDER BY c.Id DESC";
        $stmt = $pdo_world->prepare($sql);
        $stmt->execute($charIds);
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Récup des noms de classes (breeds.ShortName) pour les Breed présents
        $breedIds = array_values(array_unique(array_map(fn($c) => (int)($c['Breed'] ?? 0), $characters)));
        if (!empty($breedIds)) {
            $inBreed = implode(',', array_fill(0, count($breedIds), '?'));
            $stmtB = $pdo_world->prepare("SELECT Id, ShortName FROM breeds WHERE Id IN ($inBreed)");
            $stmtB->execute($breedIds);
            $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsB as $r) {
                $breedNames[(int)$r['Id']] = $r['ShortName'] ?? ('Classe #' . (int)$r['Id']);
            }
        }

        // Pour chaque perso : statut + normalisation + métiers
        foreach ($characters as &$c) {
            // En ligne ?
            $c['Online'] = (!empty($connectedCharacterId) && (int)$connectedCharacterId === (int)$c['Id']) ? 1 : 0;

            // Normalisation champs
            $c['_Honor']    = $c['Honor']      ?? ($c['Honneur'] ?? '—');
            $c['_Prestige'] = $c['Prestige']   ?? ($c['PrestigeRank'] ?? '—');
            $c['_Align']    = $c['Alignement'] ?? ($c['AlignmentSide'] ?? 0);
            $c['_AlignName']= $alignNames[(int)$c['_Align']] ?? 'Inconnu';
            $c['_SexName']  = $sexNames[(int)($c['Sex'] ?? 0)] ?? 'Inconnu';
            $c['_Grade']    = $c['Grade']      ?? '—';
            $c['_WinPvm']   = $c['WinPvm']     ?? '—';
            $c['_LosPvm']   = $c['LosPvm']     ?? '—';
            $c['_Success']  = $c['SuccessPoints'] ?? '—';

            // Parchotage (0 si absent)
            $c['_Vit'] = (int)($c['PermanentAddedVitality']     ?? 0);
            $c['_Cha'] = (int)($c['PermanentAddedChance']       ?? 0);
            $c['_Agi'] = (int)($c['PermanentAddedAgility']      ?? 0);
            $c['_Str'] = (int)($c['PermanentAddedStrength']     ?? 0);
            $c['_Int'] = (int)($c['PermanentAddedIntelligence'] ?? 0);
            $c['_Wis'] = (int)($c['PermanentAddedWisdom']       ?? 0);

            // Base stats (déjà confirmées)
            $c['_BaseHealth']   = (int)($c['BaseHealth']   ?? 0);
            $c['_AP']           = (int)($c['AP']           ?? 0);
            $c['_MP']           = (int)($c['MP']           ?? 0);
            $c['_Prospection']  = (int)($c['Prospection']  ?? 0);
            $c['_Strength']     = (int)($c['Strength']     ?? 0);
            $c['_Intelligence'] = (int)($c['Intelligence'] ?? 0);
            $c['_Chance']       = (int)($c['Chance']       ?? 0);
            $c['_Agility']      = (int)($c['Agility']      ?? 0);
            $c['_Wisdom']       = (int)($c['Wisdom']       ?? 0);

            // Nom de classe
            $c['_BreedName'] = $breedNames[(int)($c['Breed'] ?? 0)] ?? ('Classe #' . (int)($c['Breed'] ?? 0));

            // Métiers du perso (characters_jobs + jobs_templates + langs.French)
            $stmtJobs = $pdo_world->prepare("
                SELECT 
                    cj.TemplateId, cj.Experience,
                    jt.IconId, jt.NameId,
                    l.French AS NameFR
                FROM characters_jobs cj
                JOIN jobs_templates jt ON jt.Id = cj.TemplateId
                JOIN langs l ON l.Id = jt.NameId
                WHERE cj.OwnerId = ?
            ");
            $stmtJobs->execute([$c['Id']]);
            $jobs = $stmtJobs->fetchAll(PDO::FETCH_ASSOC);

            foreach ($jobs as &$job) {
                $lvl = getExperienceLevel('JobExp', (int)$job['Experience']);
                // capé 200
                if ($lvl > 200) $lvl = 200;
                $job['Level']    = $lvl;
                $job['IconPath'] = $prefix . 'assets/images/job/' . (int)$job['IconId'] . '.png';
            }
            unset($job);

            $c['_Jobs'] = $jobs; // tableau métiers prêt pour JSON
        }
        unset($c);
    }
}
?>

<!-- Styles spécifiques (légers) -->
<style>
  .characters-header { display:flex; justify-content:space-between; align-items:center; gap:1rem; }
  .inline-form { display:flex; align-items:center; gap:.5rem; }
  .small-note { color: var(--text-secondary); font-size:.9rem; }
  .head-img { width:45px; height:45px; border-radius:50%; object-fit:cover; background: var(--bg-accent); }
  .actions-bar { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.5rem; }
  .account-badge { padding:.25rem .6rem; border-radius:999px; border:1px solid var(--border-color); }
  .stat-icon { width:18px; height:18px; vertical-align:-3px; margin-right:.25rem; }

  /* Modal */
  .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.6);
      display: none; align-items: center; justify-content: center; z-index: 2000;
  }
  .modal-content {
      background: var(--bg-secondary); border:1px solid var(--border-color);
      border-radius: .75rem; max-width: 940px; width: 95%; max-height: 90vh; overflow:auto;
      box-shadow: 0 20px 60px var(--shadow); padding: 1rem 1.2rem;
  }
  .modal-header { display:flex; justify-content:space-between; align-items:center; }
  .modal-header h3 { margin:0; color: var(--text-primary); }
  .modal-close { background:none; border:none; color: var(--text-secondary); font-size:1.4rem; cursor:pointer; }
  .modal-body { margin-top: .75rem; }
  .modal-image { text-align:center; margin-bottom: 1rem; }
  .modal-image img { max-width: 260px; height:auto; }
  .grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
  .stat-line { margin:.25rem 0; }
  .jobs-list { display:flex; flex-direction:column; gap:.4rem; }
  .job-item { display:flex; align-items:center; gap:.5rem; }
  .job-icon { width:24px; height:24px; }

  @media (max-width: 720px){ .grid-2{ grid-template-columns: 1fr; } }
</style>

<div class="container fade-in">
    <div class="card">
        <div class="characters-header">
            <div>
                <h1>Personnages</h1>
                <div class="small-note">
                    Compte sélectionné :
                    <?php if ($accountLogin): ?>
                        <span class="account-badge"><?= htmlspecialchars($accountLogin) ?> (#<?= (int)$selectedAccountId ?>)</span>
                    <?php else: ?>
                        <span class="account-badge">—</span>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <form class="inline-form" method="get">
                    <label for="account_id">Compte :</label>
                    <select id="account_id" name="account_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int)$acc['Id'] ?>" <?= ((int)$acc['Id'] === (int)$selectedAccountId) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['Login']) ?> (#<?= (int)$acc['Id'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ($selectedAccountId !== null): ?>
                    <?php if ($accountIsOnline): ?>
                        <span class="status-badge status-online">Compte en ligne</span>
                    <?php else: ?>
                        <span class="status-badge status-offline">Compte hors-ligne</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($selectedAccountId !== null && empty($characters)): ?>
        <div class="card"><p>Aucun personnage trouvé pour ce compte.</p></div>
    <?php elseif ($selectedAccountId === null): ?>
        <div class="card"><p>Aucun compte jeu lié.</p></div>
    <?php else: ?>
        <div class="characters-grid">
            <?php foreach ($characters as $char):
                $levelInt = getExperienceLevel('CharacterExp', (int)$char['Experience']);
                $levelTxt = formatOmegaLevel($levelInt);
                $lookHex  = bin2hex($char['LastLookString'] ?? '');
                $headUrl  = $prefix . 'api/head_image.php?look=' . urlencode($lookHex);

                // Payload sûr pour data-attribute (utilisé par le modal)
                $payload = [
                    'name'       => (string)$char['Name'],
                    'look'       => (string)$lookHex,
                    'level'      => (int)$levelInt, // le JS fera l'affichage Oméga
                    'experience' => (int)$char['Experience'],
                    'grade'      => (string)$char['_Grade'],
                    'honneur'    => (string)$char['_Honor'],
                    'prestige'   => (string)$char['_Prestige'],
                    'alignement' => (int)$char['_Align'],
                    'align_name' => (string)$char['_AlignName'],
                    'sex_name'   => (string)$char['_SexName'],
                    'breed_name' => (string)$char['_BreedName'],
                    'winpvm'     => (string)$char['_WinPvm'],
                    'lospvm'     => (string)$char['_LosPvm'],
                    'success'    => (string)$char['_Success'],
                    // Base + parchos
                    'base' => [
                        'health'   => (int)$char['_BaseHealth'],
                        'ap'       => (int)$char['_AP'],
                        'mp'       => (int)$char['_MP'],
                        'prospect' => (int)$char['_Prospection'],
                        'str'      => (int)$char['_Strength'],
                        'int'      => (int)$char['_Intelligence'],
                        'cha'      => (int)$char['_Chance'],
                        'agi'      => (int)$char['_Agility'],
                        'wis'      => (int)$char['_Wisdom'],
                    ],
                    'parcho' => [
                        'vit' => (int)$char['_Vit'],
                        'str' => (int)$char['_Str'],
                        'int' => (int)$char['_Int'],
                        'cha' => (int)$char['_Cha'],
                        'agi' => (int)$char['_Agi'],
                        'wis' => (int)$char['_Wis'],
                    ],
                    'jobs' => $char['_Jobs'], // avec level capé 200 + IconPath + NameFR
                ];
                $dataPayload = htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="character-card">
                <div class="character-header">
                    <!-- Clic tête -> modal -->
                    <button type="button" class="open-modal" data-payload="<?= $dataPayload ?>" style="border:none; padding:0; background:none; cursor:pointer;" title="Voir l'apparence complète">
                        <img class="head-img" src="<?= $headUrl ?>"
                             onerror="this.onerror=null; this.src='<?= $prefix ?>assets/images/heads/<?= (int)$char['Breed'] ?>_<?= (int)$char['Sex'] ?>.png';"
                             alt="Tête">
                    </button>

                    <div class="character-info" style="margin-left:.75rem;">
                        <h3><?= htmlspecialchars($char['Name']) ?></h3>
                        <small class="small-note">
                            Nv. <?= htmlspecialchars($levelTxt) ?> • <?= ($char['Online'] == 1) ? '🟢 En ligne' : '⚫ Hors-ligne' ?>
                        </small><br>
                        <small class="small-note">
                            <?= htmlspecialchars($char['_BreedName']) ?> • Sexe : <?= htmlspecialchars($char['_SexName']) ?> • Alignement : <?= htmlspecialchars($char['_AlignName']) ?>
                        </small>
                    </div>
                </div>

                <div class="character-stats">
                    <div class="stat-item"><strong>Classe</strong><br><?= htmlspecialchars($char['_BreedName']) ?></div>
                    <div class="stat-item"><strong>Align.</strong><br><?= htmlspecialchars($char['_AlignName']) ?></div>
                    <div class="stat-item"><strong>Honneur</strong><br><?= htmlspecialchars((string)$char['_Honor']) ?></div>
                    <div class="stat-item"><strong>ID</strong><br><?= (int)$char['Id'] ?></div>
                </div>

                <div class="actions-bar">
                    <button type="button" class="btn open-modal" data-payload="<?= $dataPayload ?>">Détails</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal global réutilisable -->
<div id="charModal" class="modal-overlay" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="charModalTitle">Détails du personnage</h3>
      <button class="modal-close" onclick="closeCharacterModal()" aria-label="Fermer">&times;</button>
    </div>
    <div class="modal-body">
      <div class="modal-image">
        <img id="charModalImg" src="" alt="Look">
      </div>

      <hr>

      <h4>Informations</h4>
      <div class="grid-2">
        <div>
          <p class="stat-line"><strong>Niveau :</strong> <span id="charLevel">—</span></p>
          <p class="stat-line"><strong>Expérience :</strong> <span id="charExp">—</span></p>
          <p class="stat-line"><strong>Grade :</strong> <span id="charGrade">—</span></p>
          <p class="stat-line"><strong>Honneur :</strong> <span id="charHonneur">—</span></p>
          <p class="stat-line"><strong>Prestige :</strong> <span id="charPrestige">—</span></p>
        </div>
        <div>
          <p class="stat-line"><strong>Classe :</strong> <span id="charBreed">—</span></p>
          <p class="stat-line"><strong>Alignement :</strong> <span id="charAlign">—</span></p>
          <p class="stat-line"><strong>Sexe :</strong> <span id="charSex">—</span></p>
          <p class="stat-line"><strong>Combat PVM gagné :</strong> <span id="charWinPvm">—</span></p>
          <p class="stat-line"><strong>Combat PVM perdu :</strong> <span id="charLosPvm">—</span></p>
          <p class="stat-line"><strong>Points de succès :</strong> <span id="charSuccess">—</span></p>
        </div>
      </div>

      <hr>

      <h4>Caractéristiques de base</h4>
      <div class="grid-2">
        <div>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_vitality.png" alt="Vit"> <strong>Vitalité :</strong> <span id="baseVit">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_pa.png" alt="PA"> <strong>PA :</strong> <span id="basePA">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_pm.png" alt="PM"> <strong>PM :</strong> <span id="basePM">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_prospection.png" alt="PP"> <strong>Prospection :</strong> <span id="basePP">—</span></p>
        </div>
        <div>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_strength.png" alt="Force"> <strong>Force :</strong> <span id="baseStr">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_intelligence.png" alt="Int"> <strong>Intelligence :</strong> <span id="baseInt">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_chance.png" alt="Chance"> <strong>Chance :</strong> <span id="baseCha">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_agility.png" alt="Agi"> <strong>Agilité :</strong> <span id="baseAgi">—</span></p>
          <p class="stat-line" title=""><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_wisdom.png" alt="Sagesse"> <strong>Sagesse :</strong> <span id="baseWis">—</span></p>
        </div>
      </div>

      <hr>

      <h4>Parchotage</h4>
      <div class="grid-2">
        <div>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_vitality.png" alt="Vit"> <strong>Vitalité :</strong> <span id="parchoVit">—</span></p>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_strength.png" alt="Force"> <strong>Force :</strong> <span id="parchoStr">—</span></p>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_intelligence.png" alt="Int"> <strong>Intelligence :</strong> <span id="parchoInt">—</span></p>
        </div>
        <div>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_chance.png" alt="Chance"> <strong>Chance :</strong> <span id="parchoCha">—</span></p>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_agility.png" alt="Agi"> <strong>Agilité :</strong> <span id="parchoAgi">—</span></p>
          <p class="stat-line"><img class="stat-icon" src="<?= $prefix ?>assets/images/stats/tx_wisdom.png" alt="Sagesse"> <strong>Sagesse :</strong> <span id="parchoWis">—</span></p>
        </div>
      </div>

      <hr>

      <h4>Métiers</h4>
      <div id="charJobs" class="jobs-list"></div>
    </div>
  </div>
</div>

<script>
// Attache les handlers sans inline JS fragile
document.querySelectorAll('.open-modal').forEach(function(btn){
    btn.addEventListener('click', function(){
        try {
            const data = JSON.parse(this.dataset.payload || '{}');
            openCharacterModal(data);
        } catch (e) {
            console.error('Payload JSON invalide', e);
        }
    });
});

function openCharacterModal(data){
    // Titre & image full
    document.getElementById('charModalTitle').textContent = data.name || 'Détails du personnage';
    const img = document.getElementById('charModalImg');
    img.src = '<?= $prefix ?>api/look_image.php?look=' + encodeURIComponent(data.look || '');
    img.alt = data.name || 'Look';

    // Informations
    const lvl = parseInt(data.level || 0, 10);
    document.getElementById('charLevel').textContent = (lvl > 200) ? ('Oméga ' + (lvl - 200)) : lvl;
    document.getElementById('charExp').textContent   = (data.experience ?? '—').toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    document.getElementById('charGrade').textContent = data.grade || '—';
    document.getElementById('charHonneur').textContent = data.honneur || '—';
    document.getElementById('charPrestige').textContent = data.prestige || '—';

    document.getElementById('charBreed').textContent = data.breed_name || '—';

    // Alignement + couleur
    const alignElem = document.getElementById('charAlign');
    const aVal = parseInt(data.alignement || 0, 10);
    let color = 'gray';
    if (aVal === 1) color = 'cornflowerblue';
    else if (aVal === 2) color = 'tomato';
    else if (aVal === 3) color = 'sandybrown';
    alignElem.textContent = data.align_name || 'Neutre';
    alignElem.style.color = color;

    document.getElementById('charSex').textContent    = data.sex_name || '—';
    document.getElementById('charWinPvm').textContent = data.winpvm || '—';
    document.getElementById('charLosPvm').textContent = data.lospvm || '—';
    document.getElementById('charSuccess').textContent= data.success || '—';

    // Caractéristiques de base (avec total base + parcho, et title indiquant le détail)
    const base = data.base || {};
    const par  = data.parcho || {};
    const withSum = (baseVal, parVal) => (parseInt(baseVal||0,10) + parseInt(parVal||0,10));

    const setBaseLine = (id, baseVal, parVal) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = withSum(baseVal, parVal);
        const parentP = el.closest('p');
        if (parentP) parentP.title = `Base : ${baseVal||0} | Parchotage : ${parVal||0}`;
    };

    // Vit = BaseHealth + parchotage (permanent vit)
    setBaseLine('baseVit', base.health, par.vit);
    setBaseLine('basePA', base.ap, 0);
    setBaseLine('basePM', base.mp, 0);
    setBaseLine('basePP', base.prospect, 0);
    setBaseLine('baseStr', base.str, par.str);
    setBaseLine('baseInt', base.int, par.int);
    setBaseLine('baseCha', base.cha, par.cha);
    setBaseLine('baseAgi', base.agi, par.agi);
    setBaseLine('baseWis', base.wis, par.wis);

    // Parchotage
    document.getElementById('parchoVit').textContent = par.vit ?? '—';
    document.getElementById('parchoStr').textContent = par.str ?? '—';
    document.getElementById('parchoInt').textContent = par.int ?? '—';
    document.getElementById('parchoCha').textContent = par.cha ?? '—';
    document.getElementById('parchoAgi').textContent = par.agi ?? '—';
    document.getElementById('parchoWis').textContent = par.wis ?? '—';

    // Métiers
    const jobsContainer = document.getElementById('charJobs');
    jobsContainer.innerHTML = '';
    if (Array.isArray(data.jobs) && data.jobs.length > 0) {
        data.jobs.forEach(job => {
            const lvl = Math.min(parseInt(job.Level || 1, 10), 200);
            const div = document.createElement('div');
            div.className = 'job-item';
            div.innerHTML = `
                <img src="${job.IconPath}" alt="${job.NameFR || ''}" class="job-icon">
                <span>${job.NameFR || '—'}</span>
                <small>(Niv. ${lvl})</small>
            `;
            jobsContainer.appendChild(div);
        });
    } else {
        jobsContainer.innerHTML = '<p>Aucun métier</p>';
    }

    // Affiche le modal
    const overlay = document.getElementById('charModal');
    overlay.style.display = 'flex';
    overlay.setAttribute('aria-hidden', 'false');
}

function closeCharacterModal(){
    const overlay = document.getElementById('charModal');
    overlay.style.display = 'none';
    overlay.setAttribute('aria-hidden', 'true');
}

// Fermer si clic hors contenu
document.getElementById('charModal').addEventListener('click', function(e){
    if(e.target === this){ closeCharacterModal(); }
});

// ESC pour fermer
document.addEventListener('keydown', function(e){
    if(e.key === 'Escape'){ closeCharacterModal(); }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
