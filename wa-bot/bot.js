// ============================================================
// bot.js — WhatsApp Bot RT 005 GMR 8
// Powered by Baileys (@whiskeysockets/baileys)
// ============================================================

import 'dotenv/config';
import {
    makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} from '@whiskeysockets/baileys';
import NodeCache from 'node-cache';
import pino from 'pino';
import qrcode from 'qrcode-terminal';

// ============================================================
// KONFIGURASI
// ============================================================
const SITE_URL = process.env.SITE_URL || 'http://localhost:8000';
const API_URL = `${SITE_URL}/api/wa-webhook.php`;
const API_KEY = process.env.WA_API_KEY || '';
const GROUP_ID = process.env.GROUP_ID || '';   // ID grup WA
const BOT_NAME = process.env.BOT_NAME || 'Pak Robot GMR 8';
const LOG_LEVEL = process.env.LOG_LEVEL || 'silent';

// Cache session percakapan (tiap sender, TTL 5 menit)
const sessionCache = new NodeCache({ stdTTL: 300, checkperiod: 60 });

// Logger minimal
const logger = pino({ level: LOG_LEVEL });

// ===========================================================
// HELPER: Call PHP API
// ===========================================================
async function apiCall(action, params = {}, body = null) {
    const qs = new URLSearchParams({ action, ...params }).toString();
    const url = `${API_URL}?${qs}`;
    const opts = {
        method: body ? 'POST' : 'GET',
        headers: {
            'X-Api-Key': API_KEY,
            'Content-Type': 'application/json',
        },
    };
    if (body) opts.body = JSON.stringify(body);

    try {
        const res = await fetch(url, opts);
        const data = await res.json();
        return data;
    } catch (err) {
        console.error('[API ERROR]', err.message);
        return { ok: false, error: err.message };
    }
}

// ===========================================================
// HELPER: Format Rupiah
// ===========================================================
function fRp(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}

function bulanNama(n) {
    const b = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return b[n] || '';
}

// ===========================================================
// PESAN HANDLER
// ===========================================================
async function handleMessage(sock, msg) {
    const msgContent = msg.message?.conversation
        || msg.message?.extendedTextMessage?.text
        || '';

    if (!msgContent) return;

    const from = msg.key.remoteJid;
    const isGroup = from.endsWith('@g.us');
    const sender = isGroup ? msg.key.participant : from;
    const senderNum = sender?.replace('@s.whatsapp.net', '');

    // ── Hanya proses pesan dari grup yang dikonfigurasi (jika GROUP_ID diset)
    if (GROUP_ID && isGroup && from !== GROUP_ID) return;
    // ── Atau proses semua grup (jika GROUP_ID belum diset) + chat personal
    if (GROUP_ID === '' && isGroup) return; // Jika belum set GROUP_ID, abaikan grup

    const text = msgContent.trim();
    const textLower = text.toLowerCase();

    // Session state user
    const sessionKey = `${from}:${sender}`;
    const session = sessionCache.get(sessionKey) || { state: 'idle' };

    // ============================================================
    // FLOW PERCAKAPAN MULTI-STEP (untuk !bayar)
    // ============================================================
    if (session.state === 'pilih_warga') {
        const choice = parseInt(text);
        if (!isNaN(choice) && choice >= 1 && choice <= (session.list?.length || 0)) {
            const chosen = session.list[choice - 1];
            sessionCache.set(sessionKey, {
                state: 'konfirmasi_bayar',
                tagihan: chosen,
                jenis: session.jenis,
            });

            const konfirmMsg =
                `✅ *Konfirmasi Pembayaran*\n\n` +
                `Nama       : ${chosen.nama}\n` +
                `No. Rumah  : ${chosen.nomor_rumah}\n` +
                `Jenis      : ${chosen.jenis_nama || session.jenis}\n` +
                `Nominal    : ${fRp(chosen.nominal)}\n\n` +
                `Balas *ya* untuk catat, atau *batal* untuk membatalkan.`;

            await sock.sendMessage(from, { text: konfirmMsg }, { quoted: msg });
        } else if (textLower === 'batal') {
            sessionCache.del(sessionKey);
            await sock.sendMessage(from, { text: '❌ Dibatalkan. Ketik *gmr8 bantuan* untuk daftar perintah.' }, { quoted: msg });
        } else {
            await sock.sendMessage(from, { text: `Pilihan tidak valid. Ketik angka 1-${session.list.length} atau *batal*.` }, { quoted: msg });
        }
        return;
    }

    if (session.state === 'konfirmasi_bayar') {
        if (textLower === 'ya') {
            const t = session.tagihan;
            const res = await apiCall('catat_bayar', {}, {
                tagihan_id: t.tagihan_id,
                catatan_wa: `Konfirmasi via WhatsApp oleh ${senderNum}`,
                nama_pengirim: senderNum,
            });

            sessionCache.del(sessionKey);

            if (res.ok) {
                const replyMsg =
                    `🎉 *Dicatat!*\n\n` +
                    `*${res.warga}* (${res.nomor_rumah})\n` +
                    `${session.jenis} — ${fRp(res.nominal)}\n\n` +
                    `Status: ⏳ Menunggu verifikasi bendahara\n\n` +
                    `📸 *PENTING:* Mohon upload bukti transfer di:\n` +
                    `${SITE_URL}/iuran.php\n\n` +
                    `Terima kasih sudah bayar! 💚`;
                await sock.sendMessage(from, { text: replyMsg }, { quoted: msg });
            } else {
                await sock.sendMessage(from, {
                    text: `❌ Gagal catat: ${res.error || 'Error tidak dikenal'}`,
                }, { quoted: msg });
            }
        } else if (textLower === 'batal') {
            sessionCache.del(sessionKey);
            await sock.sendMessage(from, { text: '❌ Dibatalkan.' }, { quoted: msg });
        } else {
            await sock.sendMessage(from, { text: 'Balas *ya* untuk konfirmasi atau *batal* untuk membatalkan.' }, { quoted: msg });
        }
        return;
    }

    if (session.state === 'cari_warga_bayar') {
        // User mengetikkan nama untuk dicari
        const nama = text;
        const bulan = new Date().getMonth() + 1;
        const tahun = new Date().getFullYear();

        const res = await apiCall('cari_warga', {
            nama,
            bulan,
            tahun,
            jenis_id: session.jenis_id || '',
        });

        if (!res.ok || res.count === 0) {
            await sock.sendMessage(from, {
                text: `😕 Tidak ditemukan warga dengan nama *"${nama}"* yang belum bayar.\n\nCoba ketik nama yang lebih lengkap.`,
            }, { quoted: msg });
            return;
        }

        const belumBayar = res.data.filter(d => d.status === 'belum_bayar');
        if (belumBayar.length === 0) {
            sessionCache.del(sessionKey);
            const found = res.data[0];
            await sock.sendMessage(from, {
                text: `✅ *${found.nama}* sudah berstatus *${found.status}*. Tidak perlu dicatat lagi.`,
            }, { quoted: msg });
            return;
        }

        if (belumBayar.length === 1) {
            const chosen = belumBayar[0];
            sessionCache.set(sessionKey, {
                state: 'konfirmasi_bayar',
                tagihan: chosen,
                jenis: chosen.jenis_nama || session.jenis,
            });

            await sock.sendMessage(from, {
                text: `✅ Ditemukan: *${chosen.nama}* (${chosen.nomor_rumah})\n${chosen.jenis_nama} — ${fRp(chosen.nominal)}\n\nBalas *ya* untuk konfirmasi atau *batal*.`,
            }, { quoted: msg });
        } else {
            let list = `🔍 Ditemukan *${belumBayar.length}* hasil untuk "${nama}":\n\n`;
            belumBayar.forEach((w, i) => {
                list += `${i + 1}. ${w.nama} (${w.nomor_rumah}) — ${w.jenis_nama || '-'} ${fRp(w.nominal)}\n`;
            });
            list += `\nBalas angka nomor urut untuk memilih, atau *batal*.`;

            sessionCache.set(sessionKey, {
                state: 'pilih_warga',
                list: belumBayar,
                jenis: session.jenis || 'IPL',
            });

            await sock.sendMessage(from, { text: list }, { quoted: msg });
        }
        return;
    }

    // ============================================================
    // COMMAND HANDLER (dimulai dengan gmr8)
    // ============================================================
    if (!text.startsWith('gmr8')) return;

    const parts = text.split(' ');
    // Extract the first two words for the command (e.g., 'gmr8 bantuan')
    const command = parts.slice(0, 2).join(' ').toLowerCase();
    // The rest is arguments
    const args = parts.slice(2).join(' ').trim();

    switch (command) {

        // ── gmr8 bantuan ─────────────────────────────────────────
        case 'gmr8 bantuan':
        case 'gmr8 help':
        case 'gmr8 menu': {
            const help =
                `🌿 *${BOT_NAME} — RT 005 GMR 8*\n\n` +
                `Perintah yang tersedia:\n\n` +
                `📋 *gmr8 cek [jenis]* — Cek siapa belum bayar\n` +
                `    Contoh: gmr8 cek IPL\n\n` +
                `💰 *gmr8 bayar [nama]* — Catat pembayaran\n` +
                `    Contoh: gmr8 bayar Ahmad Fauzi\n\n` +
                `💚 *gmr8 saldo* — Cek saldo kas RT\n\n` +
                `📅 *gmr8 kegiatan* — Info kegiatan mendatang\n\n` +
                `📊 *gmr8 laporan* — Rangkuman laporan bulan ini\n\n` +
                `ℹ️ *gmr8 iuran* — Lihat jenis & nominal iuran\n\n` +
                `_${BOT_NAME} — Melayani dengan sepenuh hati 💚_`;
            await sock.sendMessage(from, { text: help }, { quoted: msg });
            break;
        }

        // ── gmr8 saldo ───────────────────────────────────────────
        case 'gmr8 saldo': {
            const res = await apiCall('saldo');
            if (!res.ok) {
                await sock.sendMessage(from, { text: `❌ Gagal ambil data: ${res.error}` }, { quoted: msg });
                break;
            }
            const saldoMsg =
                `💰 *Saldo Kas RT 005 GMR 8*\n\n` +
                `Saldo Saat Ini : *${fRp(res.saldo)}*\n\n` +
                `📅 ${res.bulan}:\n` +
                `Pemasukan : ${fRp(res.masuk_bulan_ini)}\n` +
                `Pengeluaran: ${fRp(res.keluar_bulan_ini)}\n\n` +
                `_Update: ${new Date().toLocaleString('id-ID')} WIB_`;
            await sock.sendMessage(from, { text: saldoMsg }, { quoted: msg });
            break;
        }

        // ── gmr8 kegiatan ────────────────────────────────────────
        case 'gmr8 kegiatan': {
            const res = await apiCall('kegiatan', { limit: 3 });
            if (!res.ok || res.data.length === 0) {
                await sock.sendMessage(from, {
                    text: '📅 Belum ada kegiatan terjadwal nih. Nantikan info selanjutnya! 😊',
                }, { quoted: msg });
                break;
            }
            let txt = `📅 *Kegiatan Mendatang RT 005 GMR 8*\n\n`;
            res.data.forEach((k, i) => {
                const tgl = new Date(k.tanggal);
                const tglStr = tgl.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                txt += `${i + 1}. *${k.judul}*\n`;
                txt += `   📅 ${tglStr}\n`;
                if (k.waktu) txt += `   ⏰ ${k.waktu.substring(0, 5)} WIB\n`;
                if (k.lokasi) txt += `   📍 ${k.lokasi}\n`;
                if (k.agenda) txt += `   📋 ${k.agenda.substring(0, 100)}...\n`;
                txt += '\n';
            });
            txt += `_Catat di kalender ya! 💚_`;
            await sock.sendMessage(from, { text: txt }, { quoted: msg });
            break;
        }

        // ── gmr8 iuran ───────────────────────────────────────────
        case 'gmr8 iuran': {
            const res = await apiCall('jenis_iuran');
            if (!res.ok || res.data.length === 0) {
                await sock.sendMessage(from, { text: 'Belum ada data iuran.' }, { quoted: msg });
                break;
            }
            let txt = `🏷️ *Jenis Iuran RT 005 GMR 8*\n\n`;
            res.data.forEach((j, i) => {
                txt += `${i + 1}. *${j.nama}*\n`;
                txt += `   Nominal: ${fRp(j.nominal)} / ${j.periode}\n\n`;
            });
            txt += `_Pembayaran via transfer BCA. Info: ${SITE_URL}/iuran.php_`;
            await sock.sendMessage(from, { text: txt }, { quoted: msg });
            break;
        }

        // ── gmr8 cek [jenis] ─────────────────────────────────────
        case 'gmr8 cek': {
            const bulan = new Date().getMonth() + 1;
            const tahun = new Date().getFullYear();

            // Cari jenis iuran jika ada argumen
            let jenisId = 0;
            let jenisNama = args || 'Semua';

            if (args) {
                const jenisRes = await apiCall('jenis_iuran');
                if (jenisRes.ok) {
                    const found = jenisRes.data.find(j =>
                        j.nama.toLowerCase().includes(args.toLowerCase())
                    );
                    if (found) {
                        jenisId = found.id;
                        jenisNama = found.nama;
                    }
                }
            }

            const res = await apiCall('belum_bayar', { jenis_id: jenisId, bulan, tahun });
            if (!res.ok) {
                await sock.sendMessage(from, { text: `❌ Error: ${res.error}` }, { quoted: msg });
                break;
            }

            const bln = `${bulanNama(bulan)} ${tahun}`;

            if (res.count === 0) {
                await sock.sendMessage(from, {
                    text: `🎉 *${jenisNama}* — Semua sudah bayar di ${bln}! Warga GMR 8 kompak! 💚`,
                }, { quoted: msg });
                break;
            }

            let txt = `📋 *${jenisNama} — Belum Bayar ${bln}*\n`;
            txt += `Total: *${res.count} warga*\n\n`;

            // Grup per jenis jika tidak filter
            if (!jenisId) {
                const grouped = {};
                res.data.forEach(w => {
                    const key = w.jenis_nama || 'Lainnya';
                    if (!grouped[key]) grouped[key] = [];
                    grouped[key].push(w);
                });
                for (const [jenis, list] of Object.entries(grouped)) {
                    txt += `🏷️ *${jenis}* (${list.length} warga):\n`;
                    list.forEach((w, i) => {
                        txt += `  ${i + 1}. ${w.nama} (${w.nomor_rumah})\n`;
                    });
                    txt += '\n';
                }
            } else {
                res.data.forEach((w, i) => {
                    txt += `${i + 1}. ${w.nama} (${w.nomor_rumah}) — ${fRp(w.nominal)}\n`;
                });
            }

            txt += `\n_Yuk segera bayar! 💚 ${SITE_URL}/iuran.php_`;
            await sock.sendMessage(from, { text: txt }, { quoted: msg });
            break;
        }

        // ── gmr8 laporan ─────────────────────────────────────────
        case 'gmr8 laporan': {
            const bulan = new Date().getMonth() + 1;
            const tahun = new Date().getFullYear();
            const res = await apiCall('laporan_wa', { bulan, tahun });
            if (!res.ok) {
                await sock.sendMessage(from, { text: `❌ Error: ${res.error}` }, { quoted: msg });
                break;
            }
            await sock.sendMessage(from, { text: res.text }, { quoted: msg });
            break;
        }

        // ── gmr8 statistik ───────────────────────────────────────
        case 'gmr8 statistik':
        case 'gmr8 stat': {
            const bulan = new Date().getMonth() + 1;
            const tahun = new Date().getFullYear();
            const res = await apiCall('statistik', { bulan, tahun });
            if (!res.ok || res.data.length === 0) {
                await sock.sendMessage(from, { text: 'Belum ada data tagihan bulan ini.' }, { quoted: msg });
                break;
            }
            let txt = `📊 *Statistik Iuran — ${res.bulan}*\n\n`;
            res.data.forEach(d => {
                const pct = d.total > 0 ? Math.round(d.lunas / d.total * 100) : 0;
                const bar = '█'.repeat(Math.round(pct / 10)) + '░'.repeat(10 - Math.round(pct / 10));
                txt += `🏷️ *${d.jenis}*\n`;
                txt += `${bar} ${pct}%\n`;
                txt += `✅ Lunas: ${d.lunas}  ⏳ Proses: ${d.pending}  ❌ Belum: ${d.belum}\n`;
                txt += `(Total: ${d.total} warga)\n\n`;
            });
            await sock.sendMessage(from, { text: txt }, { quoted: msg });
            break;
        }

        // ── gmr8 bayar [nama warga] ───────────────────────────────
        case 'gmr8 bayar': {
            if (!args) {
                // Minta nama
                sessionCache.set(sessionKey, { state: 'cari_warga_bayar', jenis: 'IPL' });
                await sock.sendMessage(from, {
                    text: `💚 *Catat Pembayaran Iuran*\n\nKetik nama warga yang ingin dicatat pembayarannya:\n(Contoh: Ahmad Fauzi)`,
                }, { quoted: msg });
                break;
            }

            // Langsung cari nama dari argumen
            const bulan = new Date().getMonth() + 1;
            const tahun = new Date().getFullYear();

            const res = await apiCall('cari_warga', { nama: args, bulan, tahun });
            if (!res.ok) {
                await sock.sendMessage(from, { text: `❌ Error: ${res.error}` }, { quoted: msg });
                break;
            }

            const belumBayar = res.data.filter(d => d.status === 'belum_bayar');

            if (belumBayar.length === 0) {
                if (res.count > 0) {
                    await sock.sendMessage(from, {
                        text: `✅ *${res.data[0].nama}* sudah berstatus *${res.data[0].status}*. Terima kasih! 💚`,
                    }, { quoted: msg });
                } else {
                    await sock.sendMessage(from, {
                        text: `😕 Warga *"${args}"* tidak ditemukan di daftar tagihan bulan ini.\n\nCoba cek nama di: ${SITE_URL}/iuran.php`,
                    }, { quoted: msg });
                }
                break;
            }

            if (belumBayar.length === 1) {
                const chosen = belumBayar[0];
                sessionCache.set(sessionKey, {
                    state: 'konfirmasi_bayar',
                    tagihan: chosen,
                    jenis: chosen.jenis_nama,
                });
                await sock.sendMessage(from, {
                    text: `✅ Ditemukan:\n*${chosen.nama}* (${chosen.nomor_rumah})\n${chosen.jenis_nama} — ${fRp(chosen.nominal)}\n\nBalas *ya* untuk konfirmasi atau *batal*.`,
                }, { quoted: msg });
            } else {
                let listMsg = `🔍 Ditemukan *${belumBayar.length}* tagihan untuk "*${args}*":\n\n`;
                belumBayar.forEach((w, i) => {
                    listMsg += `${i + 1}. ${w.nama} (${w.nomor_rumah}) — ${w.jenis_nama} ${fRp(w.nominal)}\n`;
                });
                listMsg += `\nBalas *angka* untuk memilih atau *batal*.`;
                sessionCache.set(sessionKey, { state: 'pilih_warga', list: belumBayar });
                await sock.sendMessage(from, { text: listMsg }, { quoted: msg });
            }
            break;
        }

        // ── Command tidak dikenal ─────────────────────────────
        default: {
            await sock.sendMessage(from, {
                text: `❓ Perintah tidak dikenal. Ketik *gmr8 bantuan* untuk daftar perintah.`,
            }, { quoted: msg });
        }
    }
}

// ===========================================================
// KONEKSI WhatsApp (Baileys)
// ===========================================================
async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState('./auth_info');
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger: pino({ level: LOG_LEVEL }),
        printQRInTerminal: false,
        auth: state,
        msgRetryCounterCache: new NodeCache(),
        generateHighQualityLinkPreview: false,
    });

    // Simpan credentials setiap berubah
    sock.ev.on('creds.update', saveCreds);

    // Handle connection update
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n========================================');
            console.log('  SCAN QR CODE INI DENGAN WhatsApp:');
            console.log('========================================\n');
            qrcode.generate(qr, { small: true });
            console.log('\n========================================\n');
        }

        if (connection === 'close') {
            const shouldReconnect = lastDisconnect?.error?.output?.statusCode !== DisconnectReason.loggedOut;
            console.log(`[BOT] Koneksi terputus. Reconnect: ${shouldReconnect}`);
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 5000); // Tunggu 5 detik sebelum reconnect
            } else {
                console.log('[BOT] Logged out. Hapus folder auth_info dan restart bot.');
                process.exit(0);
            }
        }

        if (connection === 'open') {
            console.log(`\n✅ [BOT] ${BOT_NAME} berhasil terhubung ke WhatsApp!`);
            console.log(`[BOT] API URL : ${API_URL}`);
            if (GROUP_ID) {
                console.log(`[BOT] Grup ID : ${GROUP_ID}`);
            } else {
                console.log('[BOT] ⚠️  GROUP_ID belum diset di .env!');
                console.log('[BOT]     Cek log di bawah untuk menemukan ID grup...');
            }
        }
    });

    // Handle pesan masuk
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (msg.key.fromMe) continue;     // Abaikan pesan dari bot sendiri
            if (!msg.message) continue;        // Abaikan kosong

            // Log semua grup untuk membantu setup GROUP_ID
            if (msg.key.remoteJid?.endsWith('@g.us') && !GROUP_ID) {
                console.log(`[BOT] Pesan dari grup: ${msg.key.remoteJid}`);
            }

            try {
                await handleMessage(sock, msg);
            } catch (err) {
                console.error('[BOT] Error handle message:', err);
            }
        }
    });

    // Handle masuk grup — log group ID baru
    sock.ev.on('group-participants.update', async ({ id }) => {
        if (!GROUP_ID) {
            console.log(`[BOT] Bot masuk/ada update di grup: ${id}`);
        }
    });

    return sock;
}

// ===========================================================
// MULAI BOT
// ===========================================================
console.log(`\n🌿 ${BOT_NAME} — Memulai...`);
console.log(`[BOT] API URL : ${API_URL}`);
connectToWhatsApp();
