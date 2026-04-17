<?php
// ============================================================
// tutorial.php — Portal Tutorial Warga
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Tutorial & Panduan Warga';
require_once 'includes/header.php';

$tutorials = $pdo->query("SELECT t.*, p.nama AS penulis FROM tutorial t LEFT JOIN pengurus p ON t.created_by = p.id ORDER BY t.created_at DESC")->fetchAll();

function getYouTubeId($url) {
    preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $url, $match);
    return $match[1] ?? false;
}
?>

<section class="hero" style="padding-bottom:2rem;">
    <div class="hero-content">
        <div class="hero-badge">📖 Edukasi Digital</div>
        <h1>Tutorial & Panduan Warga</h1>
        <p>Temukan informasi dan langkah-langkah kepengurusan yang Anda butuhkan (seperti Pindah KTP, Akte, dll) dengan mudah.</p>
    </div>
</section>

<div class="container">
    <div class="section">
        
        <?php if (empty($tutorials)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-folder-open"></i>
                <p>Belum ada tutorial atau panduan yang dipublikasikan oleh pengurus.</p>
            </div>
        <?php else: ?>
            <div class="org-grid" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));">
                <?php foreach ($tutorials as $t): 
                    $ytId = getYouTubeId($t['youtube_url']);
                    $hasMedia = $t['foto'] || $ytId;
                ?>
                <div class="card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; transition: transform 0.3s;" onmouseover="this.style.transform='translateY(-6px)'" onmouseout="this.style.transform='none'">
                    
                    <?php if ($t['foto']): ?>
                        <div style="height:180px; width:100%; background:url('<?= SITE_URL ?>/assets/uploads/tutorial/<?= htmlspecialchars($t['foto']) ?>') center/cover;"></div>
                    <?php elseif ($ytId): ?>
                        <div style="height:180px; width:100%; background:url('https://img.youtube.com/vi/<?= $ytId ?>/hqdefault.jpg') center/cover; position:relative;">
                            <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:rgba(0,0,0,0.6); color:white; border-radius:50%; width:40px; height:40px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="height:120px; width:100%; background:linear-gradient(135deg, var(--green-100), var(--green-200)); display:flex; align-items:center; justify-content:center;">
                            <i class="fa-solid fa-book-open" style="font-size:32px; color:var(--green-600)"></i>
                        </div>
                    <?php endif; ?>

                    <div style="padding:20px; flex-grow:1; display:flex; flex-direction:column;">
                        <h3 style="font-size:18px; color:var(--text-dark); margin-bottom:10px; line-height:1.4;">
                            <?= htmlspecialchars($t['judul']) ?>
                        </h3>
                        
                        <div style="font-size:13px; color:var(--text-light); margin-bottom:16px; display:flex; justify-content:space-between;">
                            <span>✍️ <?= htmlspecialchars($t['penulis'] ?? 'Pengurus RT') ?></span>
                            <span>📅 <?= date('d M Y', strtotime($t['created_at'])) ?></span>
                        </div>
                        
                        <p style="font-size:14px; color:var(--text-mid); line-height:1.6; margin-bottom:20px; flex-grow:1;">
                            <?= mb_strimwidth(strip_tags($t['konten']), 0, 100, "...") ?>
                        </p>
                        
                        <a href="<?= SITE_URL ?>/tutorial_detail/?id=<?= $t['id'] ?>" class="btn" style="text-align:center; display:block; text-decoration:none; background:var(--green-50); color:var(--green-700); border:1px solid var(--green-200);">
                            Baca Selengkapnya →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
