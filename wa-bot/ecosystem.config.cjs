// ecosystem.config.cjs — PM2 Config untuk DirectAdmin Hosting
// Jalankan dengan: pm2 start ecosystem.config.cjs

module.exports = {
    apps: [
        {
            name: 'gmr8-wa-bot',
            script: 'bot.js',
            interpreter: 'node',
            interpreter_args: '--experimental-vm-modules',
            watch: false,
            max_memory_restart: '200M',
            restart_delay: 5000,
            env: {
                NODE_ENV: 'production',
            },
            log_date_format: 'YYYY-MM-DD HH:mm:ss',
            error_file: './logs/error.log',
            out_file: './logs/out.log',
            merge_logs: true,
        },
    ],
};
