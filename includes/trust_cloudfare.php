<?php
/**
 * Middleware Cloudflare pour app PHP “flat”.
 * - Fixe REMOTE_ADDR depuis CF-Connecting-IP
 * - Force HTTPS si CF dit que la requête initiale est en HTTPS
 * - Fournit helpers is_https(), client_ip(), build_default_ws_url()
 *
 * À inclure le plus tôt possible (dans config.php juste après session_start()).
 */

if (!function_exists('applyCloudflareProxyFixes')) {
    function applyCloudflareProxyFixes(): void
    {
        // 1) IP réelle du client
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Prend la première IP si plusieurs
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $_SERVER['REMOTE_ADDR'] = trim($parts[0]);
        }

        // 2) Schéma HTTPS derrière Cloudflare
        // CF-Visitor: {"scheme":"https"} quand le client est venu en HTTPS
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $cf = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (!empty($cf['scheme'])) {
                $_SERVER['HTTP_X_FORWARDED_PROTO'] = $cf['scheme'];
                $_SERVER['HTTP_X_FORWARDED_PORT']  = ($cf['scheme'] === 'https') ? 443 : 80;
                if ($cf['scheme'] === 'https') {
                    $_SERVER['HTTPS'] = 'on';
                }
            }
        }

        // Sécurité : si un proxy met directement X-Forwarded-Proto
        if (empty($_SERVER['HTTPS']) && !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $_SERVER['HTTPS'] = 'on';
            if (empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
                $_SERVER['HTTP_X_FORWARDED_PORT'] = 443;
            }
        }
    }
}

if (!function_exists('is_https')) {
    function is_https(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            return true;
        }
        return false;
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($parts[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Construit une URL WebSocket par défaut :
 *  - wss://ton-domaine/ws si HTTPS (via reverse proxy /ws)
 *  - ws://ton-domaine/ws sinon
 * Tu peux l’utiliser si tu proxifies ton serveur WS (8802) derrière /ws.
 */
if (!function_exists('build_default_ws_url')) {
    function build_default_ws_url(string $path = '/ws'): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = is_https() ? 'wss' : 'ws';
        // S'assure que le path commence par /
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.$path;
        }
        return $scheme . '://' . $host . $path;
    }
}
