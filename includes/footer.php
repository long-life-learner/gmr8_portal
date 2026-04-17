<?php
// ============================================================
// Footer — Portal Warga RT 005 GMR 8
// ============================================================
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- Site Footer -->
<footer class="site-footer">
    <p>🌿 Portal Warga <strong>RT 005 RW 012 GMR 8</strong></p>
    <p style="margin-top:4px;">Grand Madani Residence 2 &bull; <?= date('Y') ?></p>
</footer>

<!-- Bottom Navigation (Mobile) -->
<nav class="bottom-nav" aria-label="Navigasi bawah">
    <a href="<?= SITE_URL ?>/" class="bottom-nav-item <?= $currentPage==='index'?'active':'' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Beranda</span>
    </a>
    <a href="<?= SITE_URL ?>/iuran/" class="bottom-nav-item <?= in_array($currentPage,['iuran','bayar','konfirmasi'])?'active':'' ?>">
        <i class="fa-solid fa-leaf"></i>
        <span>Bayar Iuran</span>
    </a>
    <a href="<?= SITE_URL ?>/monitoring/" class="bottom-nav-item <?= $currentPage==='monitoring'?'active':'' ?>">
        <i class="fa-solid fa-chart-line"></i>
        <span>Kas Warga</span>
    </a>
    <a href="javascript:void(0)" onclick="toggleBottomSheet(event)" id="menu-lainnya" class="bottom-nav-item <?= in_array($currentPage, ['struktur', 'tutorial', 'tutorial_detail']) ? 'active' : '' ?>">
        <i class="fa-solid fa-ellipsis"></i>
        <span>Lainnya</span>
    </a>
</nav>

<!-- Bottom Sheet (More Menu) -->
<div class="bottom-sheet-overlay" id="bottom-sheet-overlay" onclick="closeSheet()"></div>
<div class="bottom-sheet" id="bottom-sheet">
    <div class="bottom-sheet-header">
        <div class="bottom-sheet-title">Layanan Warga</div>
        <button class="bottom-sheet-close" onclick="closeSheet()" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <div class="bottom-sheet-grid">
        <a href="<?= SITE_URL ?>/struktur/" class="bottom-sheet-item">
            <i class="fa-solid fa-people-group"></i>
            <span>Struktur Pengurus</span>
        </a>
        <a href="<?= SITE_URL ?>/tutorial/" class="bottom-sheet-item">
            <i class="fa-solid fa-book-open"></i>
            <span>Tutorial & Panduan</span>
        </a>
    </div>
</div>

<!-- Ambient Sound Control -->
<div class="ambient-control" id="ambient-toggle" onclick="toggleAmbient()">
    <div class="ambient-icon">
        <i class="fa-solid fa-leaf"></i>
    </div>
    <div class="ambient-label">Suara Asri</div>
</div>

<!-- Hidden YouTube Player for Ambient -->
<div id="ambient-player" style="position: absolute; width: 0; height: 0; pointer-events: none; opacity: 0;"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= SITE_URL ?>/assets/js/app.js"></script>
<script>
// Mobile Menu Logic
const bottomSheet = document.getElementById('bottom-sheet');
const overlay = document.getElementById('bottom-sheet-overlay');

function toggleBottomSheet(e) {
    if (e) e.preventDefault();
    bottomSheet.classList.add('show');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSheet() {
    bottomSheet.classList.remove('show');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// ==========================================
// AMBIENT SOUND LOGIC (YouTube IFrame API)
// ==========================================
let player;
const ambientToggle = document.getElementById('ambient-toggle');
const YOUTUBE_ID = 'eKFTSSKCzWA'; 
const TARGET_VOLUME = 20;

// Force UI state immediately (Visual memory)
if (localStorage.getItem('ambient_on') === 'true') {
    ambientToggle.classList.add('active');
}

window.onYouTubeIframeAPIReady = function() {
    player = new YT.Player('ambient-player', {
        height: '0',
        width: '0',
        videoId: YOUTUBE_ID,
        playerVars: {
            'autoplay': 1,
            'loop': 1,
            'playlist': YOUTUBE_ID,
            'controls': 0,
            'showinfo': 0,
            'modestbranding': 1,
            'mute': 1, // Start muted to bypass autoplay policy
            'enablejsapi': 1
        },
        events: {
            'onReady': onPlayerInit,
            'onStateChange': function(event) {
                if (event.data === YT.PlayerState.ENDED) player.playVideo();
            }
        }
    });
};

function onPlayerInit(event) {
    if (localStorage.getItem('ambient_on') === 'true') {
        player.playVideo();
        setupUnlocker();
    }
}

function setupUnlocker() {
    const unlock = () => {
        if (player && localStorage.getItem('ambient_on') === 'true') {
            player.unMute();
            player.setVolume(TARGET_VOLUME);
            player.playVideo();
            console.log("Ambient Audio Unmuted & Playing");
            ['mousedown', 'touchstart', 'keydown', 'scroll'].forEach(evt => 
                window.removeEventListener(evt, unlock)
            );
        }
    };
    ['mousedown', 'touchstart', 'keydown', 'scroll'].forEach(evt => 
        window.addEventListener(evt, unlock)
    );
}

function toggleAmbient() {
    if (!player) return;
    
    const isActive = ambientToggle.classList.contains('active');
    if (isActive) {
        player.pauseVideo();
        player.mute();
        ambientToggle.classList.remove('active');
        localStorage.setItem('ambient_on', 'false');
    } else {
        localStorage.setItem('ambient_on', 'true');
        ambientToggle.classList.add('active');
        player.unMute();
        player.setVolume(TARGET_VOLUME);
        player.playVideo();
        setupUnlocker(); 
    }
}

// Load YouTube API
if (!window.YT) {
    const tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    const firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
}
</script>
<?= isset($extraScript) ? $extraScript : '' ?>
</body>
</html>
