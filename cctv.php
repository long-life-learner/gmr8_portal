<?php
// ============================================================
// cctv.php — Pantau CCTV Lokasi & Perumahan
// ============================================================
require_once 'includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$pageTitle = 'Monitor CCTV';
require_once 'includes/header.php';

$title = "Pantau CCTV";
$subTitle = "Pantau situasi lalu lintas utama dan keamanan perumahan secara real-time.";

$cctvs = $pdo->query("SELECT * FROM cctv WHERE status = 'Aktif' ORDER BY id ASC")->fetchAll();
?>

<section class="hero" style="padding-bottom:2rem;">
    <div class="hero-content">
        <div class="hero-badge">🎥 Monitoring Real-time</div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($subTitle) ?></p>
    </div>
</section>

<div class="container" style="max-width: 1000px;">
    <div class="section">
        
        <?php if (empty($cctvs)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-video-slash"></i>
                <p>Belum ada koneksi CCTV yang tersedia saat ini.</p>
            </div>
        <?php else: ?>
            <div class="cctv-grid">
                <?php foreach ($cctvs as $c): 
                    $streamUrl = $c['url_m3u8'];
                    // Jika URL eksternal (mengandung http), maka gunakan proxy untuk bypass CORS
                    if (strpos($streamUrl, 'http') === 0) {
                        
                        $streamUrl = SITE_URL . "/api/proxy_cctv.php?url=" . urlencode($streamUrl);
                    }
                ?>
                    <div class="cctv-card">
                        <div class="cctv-player-container">
                            <video id="video-<?= $c['id'] ?>" class="hls-player" data-src="<?= $streamUrl ?>" controls playsinline muted></video>
                        </div>
                        <div class="cctv-info">
                            <div class="cctv-lokasi">
                                <i class="fa-solid fa-location-dot" style="margin-right:6px; color:var(--green-500)"></i>
                                <?= htmlspecialchars($c['lokasi']) ?>
                                <span style="font-size:10px; color:#999; font-weight:400; display:block;"><?= $c['tipe'] ?></span>
                            </div>
                            <div class="live-indicator">
                                <div class="live-dot"></div>
                                LIVE
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const players = document.querySelectorAll('.hls-player');
    
    players.forEach(video => {
        const source = video.getAttribute('data-src');
        
        if (Hls.isSupported()) {
            const hls = new Hls({
                // Adjust for ATCS streams which might be a bit unstable
                manifestLoadingMaxRetry: 4,
                levelLoadingMaxRetry: 4
            });
            hls.loadSource(source);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function() {
                // Try to play - note: might be blocked by autoplay rules if not muted
                // We serve it muted by default to ensure it plays
                video.play().catch(e => console.log('Autoplay blocked:', e));
            });
            
            hls.on(Hls.Events.ERROR, function (event, data) {
                if (data.fatal) {
                    switch (data.type) {
                        case Hls.ErrorTypes.NETWORK_ERROR:
                            console.log('Fatal network error encountered, try to recover');
                            hls.startLoad();
                            break;
                        case Hls.ErrorTypes.MEDIA_ERROR:
                            console.log('Fatal media error encountered, try to recover');
                            hls.recoverMediaError();
                            break;
                        default:
                            hls.destroy();
                            break;
                    }
                }
            });
        } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Native support (Safari)
            video.src = source;
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
