// ============================================================
// Portal Warga RT 005 GMR 8 — Main JavaScript
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    // --- Mark active bottom nav item ---
    const currentPath = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.bottom-nav-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && currentPath === href.split('/').pop()) {
            item.classList.add('active');
        }
    });
    document.querySelectorAll('.desktop-nav a').forEach(item => {
        const href = item.getAttribute('href');
        if (href && currentPath === href.split('/').pop()) {
            item.classList.add('active');
        }
    });

    // --- Tab system ---
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const group = this.closest('.tabs-container') || document;
            const target = this.dataset.tab;
            group.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            group.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const content = group.querySelector('#' + target);
            if (content) content.classList.add('active');
        });
    });

    // --- File upload preview ---
    document.querySelectorAll('.upload-zone').forEach(zone => {
        const input = zone.querySelector('input[type=file]');
        const preview = zone.querySelector('.upload-preview');
        const previewImg = zone.querySelector('.upload-preview img');

        if (!input || !preview || !previewImg) return;

        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) {
                showToast('File harus berupa gambar ya!', 'error');
                return;
            }
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
                zone.querySelector('p').textContent = file.name;
                zone.style.borderColor = 'var(--green-500)';
                zone.style.background = 'var(--green-50)';
            };
            reader.readAsDataURL(file);
        });

        // Drag & drop
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    // --- Copy WhatsApp text ---
    const copyWaBtn = document.getElementById('copy-wa-btn');
    const waBox = document.getElementById('wa-preview');
    if (copyWaBtn && waBox) {
        copyWaBtn.addEventListener('click', function () {
            const text = waBox.textContent;
            navigator.clipboard.writeText(text).then(() => {
                showToast('✅ Teks siap di-paste ke WhatsApp!', 'success');
                this.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
                setTimeout(() => {
                    this.innerHTML = '<i class="fa-brands fa-whatsapp"></i> Copy Teks WA';
                }, 2500);
            }).catch(() => {
                // Fallback
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                showToast('✅ Teks tersalin!', 'success');
            });
        });
    }

    // --- Toast notification ---
    window.showToast = function (msg, type = 'success') {
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.cssText = `
            position:fixed; bottom:calc(var(--bottom-nav-h,70px) + 20px); left:50%; transform:translateX(-50%);
            background:${type === 'error' ? '#c62828' : type === 'warning' ? '#e65100' : '#2D6A4F'};
            color:#fff; padding:12px 22px; border-radius:24px; font-size:14px; font-weight:700;
            z-index:9999; box-shadow:0 4px 20px rgba(0,0,0,.2); font-family:'Nunito',sans-serif;
            animation:slideUp .3s ease; white-space:nowrap;
        `;
        toast.textContent = msg;
        document.body.appendChild(toast);

        if (!document.getElementById('toast-anim')) {
            const style = document.createElement('style');
            style.id = 'toast-anim';
            style.textContent = '@keyframes slideUp{from{opacity:0;transform:translate(-50%,20px)}to{opacity:1;transform:translate(-50%,0)}}';
            document.head.appendChild(style);
        }

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity .3s';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // --- Auto-dismiss flash messages ---
    document.querySelectorAll('.flash-msg').forEach(el => {
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transition = 'opacity .5s, max-height .5s';
            el.style.maxHeight = '0';
            setTimeout(() => el.remove(), 500);
        }, 4000);
    });

    // --- Confirm dialogs for delete buttons ---
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const msg = this.dataset.confirm || 'Yakin mau dihapus?';
            if (!confirm(msg)) e.preventDefault();
        });
    });

    // --- Warga search filter ---
    const wargaSearch = document.getElementById('warga-search');
    if (wargaSearch) {
        wargaSearch.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.warga-item').forEach(item => {
                const nama = item.querySelector('.warga-nama')?.textContent.toLowerCase() || '';
                const rumah = item.querySelector('.warga-rumah')?.textContent.toLowerCase() || '';
                item.style.display = (nama.includes(term) || rumah.includes(term)) ? '' : 'none';
            });
        });
    }

    // --- Number animation for saldo ---
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count);
        let current = 0;
        const step = target / 50;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            el.textContent = 'Rp ' + Math.floor(current).toLocaleString('id-ID');
        }, 30);
    });

}); // end DOMContentLoaded
