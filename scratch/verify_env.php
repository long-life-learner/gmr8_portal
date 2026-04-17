<?php
require_once 'includes/db.php';

echo "Database Host: " . DB_HOST . "\n";
echo "Database Name: " . DB_NAME . "\n";
echo "Site Name: " . SITE_NAME . "\n";
echo "Bank Nama: " . BANK_NAMA . "\n";

if (defined('DB_HOST') && DB_HOST === 'localhost') {
    echo "SUCCESS: Constants loaded correctly.\n";
} else {
    echo "FAILURE: Constants not loaded as expected.\n";
}
