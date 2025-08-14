<?php
/**
 * -----------------------------------------------------------
 *  CONFIG GLOBALE DU SITE
 *  - Middleware Cloudflare (optionnel mais recommandé)
 *  - Constantes DB / API / WS
 *  - Helpers (redirect, sanitize, auth distante)
 * -----------------------------------------------------------
 */

session_start();

/**
 * === Cloudflare middleware (à inclure le plus tôt possible) ===
 * Place un fichier includes/trust_cloudflare.php (fourni précédemment).
 * Si le fichier n’existe pas, on continue sans casser le site.
 */
$__cf_mw = dirname(__DIR__) . '/includes/trust_cloudflare.php';
if (file_exists($__cf_mw)) {
    require_once $__cf_mw;
    if (function_exists('applyCloudflareProxyFixes')) {
        applyCloudflareProxyFixes(); // fixe REMOTE_ADDR / HTTPS derrière CF
    }
}

/**
 * Fallbacks si le middleware n’est pas présent
 */
if (!function_exists('is_https')) {
    function is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
        return false;
    }
}
if (!function_exists('build_default_ws_url')) {
    /**
     * Construit une URL WebSocket par défaut :
     *  - wss://host/ws si HTTPS (reverse proxy /ws)
     *  - ws://host/ws sinon
     */
    function build_default_ws_url(string $path = '/ws'): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = is_https() ? 'wss' : 'ws';
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.$path;
        }
        return $scheme . '://' . $host . $path;
    }
}

/* =======================
   Infos site
   ======================= */
define('SITE_NAME',    'WebServerDofus');
define('SITE_URL',     'http://localhost');
define('SITE_VERSION', '1.0.0');

/* =======================
   Base de données
   ======================= */
define('DB_AUTH_HOST', 'localhost');
define('DB_AUTH_NAME', 'rushu_auth');
define('DB_AUTH_USER', 'root');
define('DB_AUTH_PASS', '');

define('DB_WORLD_HOST', 'localhost');
define('DB_WORLD_NAME', 'rushu_world');
define('DB_WORLD_USER', 'root');
define('DB_WORLD_PASS', '');

/* =======================
   API / WebSocket
   ======================= */
define('API_KEY',      'your_secure_api_key_here');
define('API_VERSION',  'v1');

// OPTION A (accès direct, sans reverse-proxy) :
// -> décommente la ligne suivante et commente le bloc dynamique plus bas
// define('WS_URL', 'ws://84.32.41.50:8802'); // IP:port de ton serveur WS jeu

// OPTION B (recommandée derrière Cloudflare / reverse-proxy) :
// -> construit automatiquement ws(s)://<host>/ws
if (!defined('WS_URL')) {
    define('WS_URL', build_default_ws_url('/ws'));
}

// Clé qui doit correspondre à GameWsApiKey côté serveur C#
define('WS_API_KEY', 'abcd');

/* =======================
   Divers
   ======================= */
date_default_timezone_set('Europe/Paris');

/* =======================
   Helpers généraux
   ======================= */
function redirect(string $url): void
{
    header("Location: $url");
    exit();
}

function sanitize(?string $data): string
{
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

/* ===========================================================
   Auth distante (exemple) via ton API Azuriom (si tu conserves ce flux)
   =========================================================== */
function verifyAccessToken(string $access_token)
{
    $url  = 'https://rushu.xyz/web/api/auth/verify';
    $data = ['access_token' => $access_token];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    }
    return false;
}

function logoutUser(string $access_token): bool
{
    $url  = 'https://rushu.xyz/web/api/auth/logout';
    $data = ['access_token' => $access_token];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code >= 200 && $http_code < 300;
}

/**
 * Vérifie la session et met à jour des infos basiques.
 * Si le token est invalide, détruit la session.
 */
function isLoggedIn(): bool
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_token'])) {
        return false;
    }

    $user_data = verifyAccessToken($_SESSION['access_token']);
    if (!$user_data) {
        session_unset();
        session_destroy();
        return false;
    }

    $_SESSION['username'] = $user_data['username'] ?? ($_SESSION['username'] ?? 'User');
    $_SESSION['money']    = $user_data['money']    ?? ($_SESSION['money'] ?? 0);
    $_SESSION['role']     = $user_data['role']     ?? ($_SESSION['role'] ?? 'user');

    return true;
}
