<?php
// /api/look_image.php
// Usage: /api/look_image.php?look=<hexstring>

$lookHex = isset($_GET['look']) ? preg_replace('/[^0-9a-f]/i', '', $_GET['look']) : '';
if (empty($lookHex)) {
    http_response_code(400);
    exit('Missing look parameter.');
}

$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$cacheFile = $cacheDir . '/full_' . md5($lookHex) . '.png';

// Si déjà en cache
if (file_exists($cacheFile)) {
    header('Content-Type: image/png');
    readfile($cacheFile);
    exit;
}

// URL Ankama (full 150x220)
$url = "https://static.ankama.com/dofus/renderer/look/{$lookHex}/full/1/150_220-10.png";

// Téléchargement
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$imageData = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $imageData !== false) {
    file_put_contents($cacheFile, $imageData);
    header('Content-Type: image/png');
    echo $imageData;
    exit;
}

http_response_code($httpCode ?: 500);
exit('Error fetching image.');
