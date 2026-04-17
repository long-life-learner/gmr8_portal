<?php
// ============================================================
// tutorial_detail.php — Baca Tutorial Warga
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT t.*, p.nama AS penulis FROM tutorial t LEFT JOIN pengurus p ON t.created_by = p.id WHERE t.id = ?");
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t) {
    header('Location: ' . SITE_URL . '/tutorial/');
    exit;
}

$pageTitle = htmlspecialchars($t['judul']);
require_once 'includes/header.php';

function getEmbedYouTubeUrl($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match);
    $videoId = $match[1] ?? false;
    return $videoId ? "https://www.youtube.com/embed/{$videoId}" : false;
}

$embedUrl = getEmbedYouTubeUrl($t['youtube_url']);
?>
<style>
.tutorial-content {
    line-height: 1.8;
    color: var(--text-dark);
    font-size: 16px;
}
.tutorial-content h1, .tutorial-content h2, .tutorial-content h3 {
    margin-top: 1.5em;
    margin-bottom: 0.5em;
    color: var(--green-800);
}
.tutorial-content p {
    margin-bottom: 1em;
}
.tutorial-content ul, .tutorial-content ol {
    margin-left: 1.5em;
    margin-bottom: 1em;
}
.tutorial-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    margin: 1em 0;
}
.video-responsive {
    position: relative;
    padding-bottom: 56.25%; /* 16:9 */
    height: 0;
    overflow: hidden;
    max-width: 100%;
    border-radius: 12px;
    margin-bottom: 2em;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.video-responsive iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}
</style>

<div class="container" style="max-width:900px;">
    <div class="card" style="padding:40px;">
        <div class="breadcrumb" style="margin-top: -15px; margin-bottom: 25px; border-bottom: 1px solid var(--green-50); padding-bottom: 12px; display: flex; align-items: center; gap: 8px; font-size: 13px;">
            <a href="<?= SITE_URL ?>/" style="color: var(--green-700); font-weight: 700;">
                <i class="fa-solid fa-house" style="margin-right: 4px;"></i> Beranda
            </a> 
            <span style="color: var(--green-200); opacity: 0.8;">›</span> 
            <a href="<?= SITE_URL ?>/tutorial/" style="color: var(--green-700); font-weight: 700;">Tutorial</a> 
            <span style="color: var(--green-200); opacity: 0.8;">›</span> 
            <span style="color: var(--text-mid); font-weight: 500;">Baca Tutorial</span>
        </div>

        <h1 style="font-size:28px; line-height:1.3; color:var(--text-dark); margin-bottom:12px;">
            <?= htmlspecialchars($t['judul']) ?>
        </h1>
        
        <div style="font-size:14px; color:var(--text-light); margin-bottom:30px; display:flex; gap:16px; align-items:center; border-bottom:1px solid var(--gray-border); padding-bottom:20px;">
            <div style="display:flex; align-items:center; gap:6px;">
                <div style="width:30px; height:30px; background:var(--green-200); color:var(--green-800); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:12px;">
                    <?= strtoupper(substr($t['penulis'] ?? 'P', 0, 1)) ?>
                </div>
                <span><?= htmlspecialchars($t['penulis'] ?? 'Pengurus RT') ?></span>
            </div>
            <span><i class="fa-regular fa-calendar" style="margin-right:4px;"></i> <?= date('d M Y, H:i', strtotime($t['created_at'])) ?></span>
        </div>

        <?php if ($embedUrl): ?>
            <div class="video-responsive">
                <iframe src="<?= $embedUrl ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
        <?php elseif ($t['foto']): ?>
            <div style="margin-bottom:30px;">
                <img src="<?= SITE_URL ?>/assets/uploads/tutorial/<?= htmlspecialchars($t['foto']) ?>" alt="Banner" style="max-width:100%; border-radius:12px; box-shadow:0 4px 15px rgba(0,0,0,0.05);">
            </div>
        <?php endif; ?>

        <div class="tutorial-content">
            <?= $t['konten'] // Raw konten karena input dari Summernote sudah format HTML ?>
        </div>
        
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
