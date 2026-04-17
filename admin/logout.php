<?php
// ============================================================
// admin/logout.php
// ============================================================
require_once '../includes/auth.php';
session_destroy();
header('Location: ../login/?msg=logout');
exit;
