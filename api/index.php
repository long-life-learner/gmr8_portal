<?php
// ============================================================
// api/index.php — API Index (Keamanan)
// Blokir akses langsung ke folder /api/
// ============================================================
header('HTTP/1.1 403 Forbidden');
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'error'   => 'Forbidden',
    'message' => 'Akses langsung tidak diizinkan.'
]);
exit;
