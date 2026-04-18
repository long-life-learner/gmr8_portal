<?php
/**
 * api/proxy_cctv.php - absolute path version
 */

// Permissive CORS at the very top
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Expose-Headers: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

error_reporting(0);
ini_set('display_errors', 0);
while (ob_get_level()) { ob_end_clean(); }

if (!isset($_GET['url'])) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

$url = $_GET['url'];
if (empty($url)) exit;

$parsedUrl = parse_url($url);
$path = $parsedUrl['path'] ?? '';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
// Important: Specific Dishub referer
curl_setopt($ch, CURLOPT_REFERER, 'https://cctv-dishub.tangerangkab.go.id/');

$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    header("HTTP/1.1 502 Bad Gateway");
    exit;
}

// Map content types
if ($ext === 'm3u8' || strpos($contentType, 'mpegurl') !== false) {
    header("Content-Type: application/vnd.apple.mpegurl");
    
    $baseUrl = dirname($finalUrl);
    $lines = explode("\n", $response);
    $newLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if ($line[0] !== '#') {
            $fullUrl = $line;
            if (strpos($line, 'http') !== 0) {
                $fullUrl = ($line[0] === '/') ? $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $line : $baseUrl . '/' . $line;
            }
            // Use ABSOLUTE path to avoid relative resolution confusion
            $newLines[] = "/api/proxy_cctv.php?url=" . urlencode($fullUrl);
        } else {
            if (preg_match('/URI=["\'](.*?)["\']/', $line, $matches) || preg_match('/URI=([^,]+)/', $line, $matches)) {
                $origUri = trim($matches[1], '"\' ');
                if (!empty($origUri)) {
                    $fullUri = (strpos($origUri, 'http') !== 0) ? $baseUrl . '/' . $origUri : $origUri;
                    $line = str_replace($origUri, "/api/proxy_cctv.php?url=" . urlencode($fullUri), $line);
                }
            }
            $newLines[] = $line;
        }
    }
    echo implode("\n", $newLines);
} else {
    if ($ext === 'ts') {
        header("Content-Type: video/mp2t");
    } elseif ($contentType) {
        header("Content-Type: $contentType");
    }
    echo $response;
}
