<?php
class Database {
    private static $auth_connection = null;
    private static $world_connection = null;
    
    public static function getAuthConnection() {
        if (self::$auth_connection === null) {
            try {
                self::$auth_connection = new PDO(
                    "mysql:host=" . DB_AUTH_HOST . ";dbname=" . DB_AUTH_NAME . ";charset=utf8",
                    DB_AUTH_USER,
                    DB_AUTH_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("Erreur de connexion à la base auth: " . $e->getMessage());
            }
        }
        return self::$auth_connection;
    }
    
    public static function getWorldConnection() {
        if (self::$world_connection === null) {
            try {
                self::$world_connection = new PDO(
                    "mysql:host=" . DB_WORLD_HOST . ";dbname=" . DB_WORLD_NAME . ";charset=utf8",
                    DB_WORLD_USER,
                    DB_WORLD_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("Erreur de connexion à la base world: " . $e->getMessage());
            }
        }
        return self::$world_connection;
    }
}

// Classes modèles
class User {
    private $db_auth;
    private $db_world;
    
    public function __construct() {
        $this->db_auth = Database::getAuthConnection();
        $this->db_world = Database::getWorldConnection();
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->db_auth->prepare("SELECT * FROM accounts WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            return true;
        }
        return false;
    }
    
    public function getCharacters($account_id) {
        $stmt = $this->db_world->prepare("
            SELECT c.*, p.name as player_name, p.level, p.experience, p.online
            FROM characters c 
            JOIN players p ON c.id = p.character_id 
            WHERE c.account_id = ?
        ");
        $stmt->execute([$account_id]);
        return $stmt->fetchAll();
    }
    
    public function getCharacterJobs($character_id) {
        $stmt = $this->db_world->prepare("
            SELECT j.*, jt.name as job_name 
            FROM character_jobs j
            JOIN job_templates jt ON j.job_id = jt.id
            WHERE j.character_id = ?
        ");
        $stmt->execute([$character_id]);
        return $stmt->fetchAll();
    }
}

class GameActions {
    private $db_world;
    
    public function __construct() {
        $this->db_world = Database::getWorldConnection();
    }
    
    public function simulateHarvest($character_id, $resource_id, $quantity) {
        // Vérifier si le personnage est déconnecté
        if ($this->isCharacterOnline($character_id)) {
            return ['success' => false, 'message' => 'Le personnage doit être déconnecté'];
        }
        
        // Vérifier le niveau du métier
        $job_level = $this->getJobLevel($character_id, $resource_id);
        if ($job_level < $this->getRequiredLevel($resource_id)) {
            return ['success' => false, 'message' => 'Niveau de métier insuffisant'];
        }
        
        // Simuler la récolte
        $this->addItemToInventory($character_id, $resource_id, $quantity);
        $this->addExperience($character_id, $resource_id, $quantity * 10);
        
        return ['success' => true, 'message' => "Récolte de $quantity ressources effectuée"];
    }
    
    public function simulateCombat($character_id, $monster_id) {
        if ($this->isCharacterOnline($character_id)) {
            return ['success' => false, 'message' => 'Le personnage doit être déconnecté'];
        }
        
        // Logique de combat simulé
        $character_stats = $this->getCharacterStats($character_id);
        $monster_stats = $this->getMonsterStats($monster_id);
        
        $victory = $this->calculateCombatResult($character_stats, $monster_stats);
        
        if ($victory) {
            $exp_gain = $monster_stats['experience_reward'];
            $this->addExperience($character_id, 'combat', $exp_gain);
            return ['success' => true, 'message' => "Combat gagné! +$exp_gain XP"];
        } else {
            return ['success' => false, 'message' => 'Combat perdu'];
        }
    }
    
    private function isCharacterOnline($character_id) {
        $stmt = $this->db_world->prepare("SELECT online FROM players WHERE character_id = ?");
        $stmt->execute([$character_id]);
        $result = $stmt->fetch();
        return $result && $result['online'] == 1;
    }
    
    // Autres méthodes privées...
}
?>