// ============================================================
// list-groups.js — Tampilkan semua Grup WhatsApp yang diikuti bot
// Jalankan terpisah setelah bot berhasil login (auth_info sudah ada)
// Cara: node list-groups.js
// ============================================================

import 'dotenv/config';
import { makeWASocket, useMultiFileAuthState, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import pino from 'pino';

async function listGroups() {
    const { state, saveCreds } = await useMultiFileAuthState('./auth_info');
    const { version } = await fetchLatestBaileysVersion();

    const sock = makeWASocket({
        version,
        logger: pino({ level: 'silent' }),
        auth: state,
        printQRInTerminal: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async ({ connection }) => {
        if (connection === 'open') {
            console.log('\n✅ Terhubung ke WhatsApp!\n');
            console.log('📋 Daftar Grup yang diikuti bot:\n');
            console.log('='.repeat(60));

            try {
                // Ambil semua chat
                const chats = await sock.groupFetchAllParticipating();
                const groups = Object.values(chats);

                if (groups.length === 0) {
                    console.log('⚠️  Bot belum bergabung ke grup manapun!');
                    console.log('   Tambahkan nomor bot ke grup WhatsApp RT terlebih dahulu.');
                } else {
                    groups.forEach((g, i) => {
                        console.log(`\n[${i + 1}] ${g.subject}`);
                        console.log(`    ID      : ${g.id}`);
                        console.log(`    Anggota : ${g.participants?.length || 0} orang`);
                        console.log(`    Dibuat  : ${new Date(g.creation * 1000).toLocaleDateString('id-ID')}`);
                    });
                }

                console.log('\n' + '='.repeat(60));
                console.log('\n📝 CARA PAKAI:');
                console.log('   1. Salin ID grup yang sesuai (format: 628xxx@g.us)');
                console.log('   2. Paste ke .env: GROUP_ID=628xxx@g.us');
                console.log('   3. Restart bot: npm run dev\n');
            } catch (err) {
                console.error('Error ambil daftar grup:', err.message);
            }

            setTimeout(() => process.exit(0), 2000);
        }

        if (connection === 'close') {
            console.log('\n❌ Koneksi gagal — pastikan auth_info sudah ada (bot sudah pernah login)');
            process.exit(1);
        }
    });
}

listGroups();
