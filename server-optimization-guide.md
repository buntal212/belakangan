# Panduan Optimasi Server Produksi (2 CPU, 2GB RAM)

## Daftar Isi
- [Optimasi MySQL 5.7](#optimasi-mysql-57)
- [Optimasi PHP-FPM](#optimasi-php-fpm)
- [Optimasi Typesense](#optimasi-typesense)
- [Optimasi Laravel](#optimasi-laravel)
- [Optimasi AA Panel](#optimasi-aa-panel)
- [Optimasi Nginx](#optimasi-nginx)
- [Optimasi Sistem](#optimasi-sistem)
- [Monitoring](#monitoring)

## Optimasi MySQL 5.7

Edit file konfigurasi MySQL (`/etc/mysql/my.cnf` atau `/etc/mysql/mysql.conf.d/mysqld.cnf`):

```ini
[mysqld]
# Pengaturan Buffer Pool (RAM untuk MySQL)
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_log_buffer_size = 8M

# Pengaturan Koneksi
max_connections = 50
thread_cache_size = 8
max_allowed_packet = 16M

# Query Cache (masih berguna di MySQL 5.7)
query_cache_type = 1
query_cache_size = 32M
query_cache_limit = 1M

# Table Cache
table_open_cache = 400
table_definition_cache = 400

# Temporary Tables
tmp_table_size = 32M
max_heap_table_size = 32M

# Pengaturan Lainnya
join_buffer_size = 256K
sort_buffer_size = 256K
read_buffer_size = 256K
read_rnd_buffer_size = 512K
```

## Optimasi PHP-FPM

Edit file konfigurasi PHP-FPM (`/etc/php/7.4/fpm/pool.d/www.conf` atau sesuai versi PHP):

```ini
[www]
user = www-data
group = www-data

# Pengaturan Process Manager
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

# Pengaturan Timeout
request_terminate_timeout = 60s
```

Edit file konfigurasi PHP (`/etc/php/7.4/fpm/php.ini` atau sesuai versi PHP):

```ini
; Batas Memory
memory_limit = 128M

; Pengaturan OPcache
opcache.enable=1
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=0

; Pengaturan Upload
upload_max_filesize = 8M
post_max_size = 8M

; Pengaturan Session
session.gc_maxlifetime = 1440
```

## Optimasi Typesense

Buat atau edit file konfigurasi Typesense (`/www/server/typesense/config.ini`):

```ini
api-key = your-secret-api-key
data-dir = /www/server/typesense/data
log-dir = /www/server/typesense/logs
api-address = 0.0.0.0
api-port = 8108

# Batasi penggunaan memory
memory-limit-mb = 300

# Pengaturan thread
num-threads = 2

# Pengaturan cache
cache-num-entries = 10000
```

## Optimasi Laravel

Edit file `.env` di project Laravel:

```
# Driver Cache dan Session
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Pengaturan Log
LOG_CHANNEL=daily
LOG_LEVEL=warning

# Pengaturan Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Pengaturan Scout (Typesense)
SCOUT_DRIVER=typesense
SCOUT_QUEUE=true
TYPESENSE_API_KEY=your-secret-api-key
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
```

Edit file `config/scout.php`:

```php
'queue' => [
    'queue' => 'low',
    'delay' => 5,
],
'chunk' => [
    'searchable' => 100,
    'unsearchable' => 100,
],
```

## Optimasi AA Panel

Melalui interface AA Panel:

1. **Nonaktifkan Service yang Tidak Digunakan**:
   - Buka "Service Management"
   - Nonaktifkan service yang tidak diperlukan (FTP, Email, dll)

2. **Kurangi Interval Monitoring**:
   - Buka "Settings" > "Panel Settings"
   - Atur "Monitoring Interval" ke 60 detik atau lebih

3. **Batasi Log Retention**:
   - Buka "Logs"
   - Atur "Log Retention" ke 7 hari

## Optimasi Nginx

Edit file konfigurasi Nginx (`/www/server/nginx/conf/nginx.conf`):

```nginx
user www-data;
worker_processes 2;
worker_rlimit_nofile 20000;

events {
    worker_connections 1024;
    multi_accept on;
    use epoll;
}

http {
    # Basic Settings
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;
    server_tokens off;

    # Buffer Size
    client_body_buffer_size 10K;
    client_header_buffer_size 1k;
    client_max_body_size 8m;
    large_client_header_buffers 2 1k;

    # Timeouts
    client_body_timeout 12;
    client_header_timeout 12;
    send_timeout 10;

    # Gzip Settings
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_buffers 16 8k;
    gzip_http_version 1.1;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # Cache Settings
    open_file_cache max=1000 inactive=20s;
    open_file_cache_valid 30s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;
}
```

Untuk virtual host Laravel (`/www/server/nginx/conf.d/your-site.conf`):

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /www/wwwroot/your-laravel-project/public;
    index index.php;

    # Caching Static Files
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # Laravel Routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM Configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_intercept_errors on;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    # Deny Access to Sensitive Files
    location ~ /\.ht {
        deny all;
    }
}
```

## Optimasi Sistem

### Swap Management

```bash
# Buat swap file 1GB
sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# Tambahkan ke /etc/fstab
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# Atur swappiness lebih rendah
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### Limit Proses

Edit `/etc/security/limits.conf`:

```
www-data soft nproc 1000
www-data hard nproc 1500
www-data soft nofile 10000
www-data hard nofile 15000
```

## Monitoring

### Setup Monitoring Alert

1. **Memory Usage**:
   - Set alert jika penggunaan RAM > 85%

2. **CPU Load**:
   - Set alert jika CPU load > 90% selama > 5 menit

3. **Disk Space**:
   - Set alert jika disk space < 15%

4. **MySQL Connections**:
   - Set alert jika koneksi aktif > 40

### Perintah Monitoring Berguna

```bash
# Monitor penggunaan RAM
free -m

# Monitor proses yang menggunakan banyak resource
top -c

# Monitor koneksi MySQL
mysqladmin -u root -p processlist

# Monitor koneksi Nginx
netstat -anp | grep nginx | wc -l

# Monitor log PHP-FPM
tail -f /var/log/php7.4-fpm.log

# Monitor log Typesense
tail -f /www/server/typesense/logs/typesense.log
```

### Script Monitoring Sederhana

Buat file `/www/server/scripts/monitor.sh`:

```bash
#!/bin/bash
DATE=$(date +"%Y-%m-%d %H:%M:%S")
MEMORY=$(free -m | awk 'NR==2{printf "%.2f%%", $3*100/$2 }')
CPU=$(top -bn1 | grep load | awk '{printf "%.2f%%", $(NF-2)}')
DISK=$(df -h | awk '$NF=="/"{printf "%s", $5}')

echo "$DATE - Memory: $MEMORY, CPU: $CPU, Disk: $DISK" >> /www/server/logs/system_monitor.log

# Kirim alert jika memory usage > 85%
if (( $(echo "$MEMORY > 85" | bc -l) )); then
  echo "HIGH MEMORY ALERT: $MEMORY used" | mail -s "Server Memory Alert" your-email@example.com
fi
```

Jadwalkan dengan cron:

```
*/10 * * * * /www/server/scripts/monitor.sh
```


## Optimasi OPcache

OPcache meningkatkan performa PHP dengan menyimpan script yang sudah dikompilasi di memory, menghilangkan kebutuhan untuk membaca dan parsing script PHP setiap request.

### Konfigurasi OPcache

Edit file konfigurasi PHP (`/etc/php/7.4/fpm/php.ini` atau sesuai versi PHP):

```ini
; Aktifkan OPcache
opcache.enable=1
opcache.enable_cli=0

; Pengaturan Memory
opcache.memory_consumption=64        ; Alokasi memory untuk OPcache (MB)
opcache.interned_strings_buffer=8    ; Memory untuk string interned (MB)
opcache.max_accelerated_files=4000   ; Jumlah maksimum file yang di-cache
opcache.max_wasted_percentage=10     ; Persentase memory terbuang sebelum restart

; Pengaturan Validasi
opcache.revalidate_freq=60           ; Frekuensi validasi ulang (detik)
opcache.validate_timestamps=1        ; Validasi timestamp (nonaktifkan di prod dengan hati-hati)
opcache.revalidate_path=0            ; Tidak perlu validasi path

; Pengaturan Optimasi
opcache.optimization_level=0x7FFFBFFF ; Level optimasi maksimum
opcache.save_comments=1              ; Simpan komentar (diperlukan untuk beberapa framework)
opcache.fast_shutdown=1              ; Percepat shutdown

; Pengaturan Lainnya
opcache.file_cache=/var/www/opcache  ; Lokasi file cache (opsional)
opcache.file_cache_only=0            ; Gunakan hanya file cache (tidak)
opcache.file_cache_consistency_checks=1 ; Periksa konsistensi file cache
```

### Membuat Direktori File Cache (Opsional)

```bash
mkdir -p /var/www/opcache
chown www-data:www-data /var/www/opcache
chmod 755 /var/www/opcache
```

### Script Monitoring OPcache

Buat file `/www/wwwroot/your-laravel-project/public/opcache-status.php`:

```php
<?php
// Batasi akses berdasarkan IP
$allowed_ips = ['127.0.0.1', '::1', '192.168.1.x']; // Ganti dengan IP Anda
if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
    header('HTTP/1.1 403 Forbidden');
    exit('Forbidden');
}

// Tampilkan status OPcache
opcache_reset();
echo '<pre>';
print_r(opcache_get_status());
echo '</pre>';
```

### Script Reset OPcache

Buat file `/www/server/scripts/reset-opcache.php`:

```php
<?php
// Script untuk reset OPcache setelah deployment
if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo "OPcache reset: " . ($result ? "success" : "failed") . "\n";
} else {
    echo "OPcache not available\n";
}
```

### Integrasi dengan Deployment

Tambahkan ke script deployment Anda:

```bash
# Setelah deploy kode baru
php /www/server/scripts/reset-opcache.php
```

### Optimasi OPcache untuk Laravel

Tambahkan ke `.env`:

```
OPCACHE_ENABLE=true
```

### Manfaat OPcache pada Server 2 CPU, 2GB RAM

1. **Peningkatan Performa**: 2-5x lebih cepat untuk request PHP
2. **Pengurangan CPU Usage**: 30-50% lebih rendah untuk beban yang sama
3. **Pengurangan I/O Disk**: Mengurangi pembacaan file PHP dari disk
4. **Konsistensi Performa**: Mengurangi variasi waktu respons

### Catatan Penting

- Alokasikan 64-128MB untuk OPcache (sesuai dengan ukuran aplikasi)
- Setelah deployment, selalu reset OPcache
- Jika menggunakan `validate_timestamps=0` di production, Anda HARUS reset OPcache setelah update kode
- Monitor penggunaan memory OPcache untuk memastikan tidak terlalu besar atau terlalu kecil

## Reload OPcache Otomatis Setelah Deployment

### 1. Integrasi dengan GitHub Actions

Jika Anda menggunakan GitHub Actions untuk deployment, tambahkan langkah reset OPcache di file workflow Anda:

```yaml
# .github/workflows/deploy.yaml
jobs:
  deploy:
    # ... langkah deployment lainnya ...
    
    - name: Deploy to server
      # ... perintah deployment ...
    
    - name: Reset OPcache
      run: |
        ssh user@your-server "php /www/server/scripts/reset-opcache.php"
```

### 2. Script Post-Deployment

Buat script post-deployment yang akan dijalankan setelah kode baru di-deploy:

```bash
#!/bin/bash
# /www/server/scripts/post-deploy.sh

# Update kode dari repository
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Migrasi database jika diperlukan
php artisan migrate --force

# Clear cache Laravel
php artisan optimize:clear
php artisan optimize

# Reset OPcache
php /www/server/scripts/reset-opcache.php

# Log hasil
echo "Deployment completed at $(date)" >> /www/server/logs/deployment.log
```

Jadikan script executable:
```bash
chmod +x /www/server/scripts/post-deploy.sh
```

### 3. Webhook untuk Reset OPcache

Buat endpoint webhook di Laravel yang dapat dipanggil setelah deployment:

```php
// routes/api.php
Route::post('deployment-webhook', function (Request $request) {
    // Verifikasi secret token untuk keamanan
    if ($request->header('X-Deployment-Token') !== env('DEPLOYMENT_SECRET_TOKEN')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    // Reset OPcache
    if (function_exists('opcache_reset')) {
        opcache_reset();
        Log::info('OPcache reset via webhook');
        return response()->json(['status' => 'OPcache reset successful']);
    }
    
    return response()->json(['status' => 'OPcache not available'], 500);
})->middleware('throttle:10,1');
```

Kemudian panggil webhook ini dari sistem CI/CD Anda:
```bash
curl -X POST https://your-domain.com/api/deployment-webhook \
  -H "X-Deployment-Token: your-secret-token"
```

### 4. Integrasi dengan AA Panel

Jika Anda menggunakan AA Panel untuk deployment, tambahkan perintah reset OPcache ke script deployment di AA Panel:

1. Buka AA Panel > Website > your-site > Settings > Deployment
2. Tambahkan perintah berikut ke "Post-deployment commands":
   ```bash
   php /www/server/scripts/reset-opcache.php
   ```

### 5. File Watcher untuk Development

Untuk lingkungan development, Anda bisa menggunakan file watcher untuk reset OPcache otomatis saat file berubah:

```javascript
// watcher.js
const chokidar = require('chokidar');
const { exec } = require('child_process');

// Pantau perubahan file PHP
const watcher = chokidar.watch([
    './app/**/*.php',
    './config/**/*.php',
    './routes/**/*.php',
    './resources/views/**/*.php'
], {
    ignored: /node_modules/,
    persistent: true
});

console.log('OPcache watcher started...');

watcher
    .on('change', (path) => {
        console.log(`File ${path} changed. Resetting OPcache...`);
        exec('php /www/server/scripts/reset-opcache.php', (err, stdout, stderr) => {
            if (err) {
                console.error(`Error resetting OPcache: ${err.message}`);
                return;
            }
            console.log(`OPcache reset: ${stdout}`);
        });
    })
    .on('error', (error) => console.error(`Watcher error: ${error}`));
```

### 6. Cron Job untuk Reset Periodik

Jika Anda ingin memastikan OPcache selalu segar, tambahkan cron job untuk reset periodik:

```bash
# Reset OPcache setiap jam
0 * * * * php /www/server/scripts/reset-opcache.php >> /www/server/logs/opcache-reset.log 2>&1
```

### 7. Integrasi dengan Supervisor

Jika Anda menggunakan Supervisor untuk mengelola proses Laravel Octane atau Queue Worker, Anda bisa menambahkan reset OPcache saat restart proses:

```ini
# /etc/supervisor/conf.d/laravel-octane.conf
[program:laravel-octane]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan octane:start --server=swoole --host=0.0.0.0 --port=8000
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/octane.log
stopwaitsecs=3600
stopsignal=QUIT
startretries=3
startsecs=10

# Tambahkan perintah ini untuk reset OPcache saat restart
stopasgroup=true
killasgroup=true
```

Tambahkan ke file `/etc/supervisor/conf.d/supervisor-event-listeners.conf`:

```ini
[eventlistener:process_restart]
command=php /www/server/scripts/supervisor_event_listener.php
events=PROCESS_STATE_EXITED,PROCESS_STATE_STOPPED,PROCESS_STATE_STARTING
```

Dan buat file listener:

```php
<?php
// /www/server/scripts/supervisor_event_listener.php
while (true) {
    $line = fgets(STDIN);
    if (strpos($line, 'PROCESS_STATE_STARTING') !== false && strpos($line, 'laravel-octane') !== false) {
        // Reset OPcache saat proses Octane dimulai ulang
        if (function_exists('opcache_reset')) {
            opcache_reset();
            file_put_contents('/www/server/logs/opcache-reset.log', 
                date('Y-m-d H:i:s') . " - OPcache reset triggered by Supervisor restart\n", 
                FILE_APPEND);
        }
    }
    echo "READY\n";
}
```
