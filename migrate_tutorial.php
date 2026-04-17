<?php
require_once __DIR__ . '/includes/db.php';
try {
    $sql = "CREATE TABLE IF NOT EXISTS tutorial (
        id INT AUTO_INCREMENT PRIMARY KEY,
        judul VARCHAR(255) NOT NULL,
        konten TEXT NOT NULL,
        foto VARCHAR(255),
        youtube_url VARCHAR(255),
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES pengurus(id) ON DELETE SET NULL
    ) ENGINE=InnoDB;";
    $pdo->exec($sql);
    echo "Tabel tutorial berhasil dibuat.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
