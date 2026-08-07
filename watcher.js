const chokidar = require('chokidar');
const { exec } = require('child_process');

const watchDirs = [
    'app/**/*',
    'routes/**/*',
    'config/**/*',
    'resources/**/*',
];

const watcher = chokidar.watch(watchDirs, {
    ignored: /node_modules|\.git/, // Abaikan direktori tertentu
    usePolling: true, // Mode polling
    interval: 100, // Poll setiap 500ms
    persistent: true,
    followSymlinks: true, // Ikuti symlink jika ada
    ignoreInitial: false, // Jangan abaikan pemindaian awal
});


watcher
    .on('add', (path) => console.log(`File ${path} added`))
    .on('change', (path) => console.log(`File ${path} changed`))
    .on('unlink', (path) => console.log(`File ${path} removed`));

watcher
    .on('ready', () => console.log('Watcher is ready'))
    .on('change', (path) => {
        console.log(`File ${path} has changed. Restarting Octane workers...`);
        exec('php artisan octane:reload', (err, stdout, stderr) => {
            if (err) {
                console.error(`Error reloading Octane workers: ${err.message}`);
                return;
            }
            console.log(`Octane workers reloaded successfully: ${stdout}`);
        });
    })
    .on('error', (error) => console.error(`Watcher error: ${error}`));
