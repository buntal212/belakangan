# Panduan Implementasi Typesense untuk Laravel

## Daftar Isi
- [Instalasi Typesense Server](#instalasi-typesense-server)
- [Instalasi Laravel Scout dengan Typesense](#instalasi-laravel-scout-dengan-typesense)
- [Konfigurasi Laravel](#konfigurasi-laravel)
- [Membuat Model Searchable](#membuat-model-searchable)
- [Menggunakan Typesense di Frontend](#menggunakan-typesense-di-frontend)
- [Optimasi Typesense](#optimasi-typesense)
- [Troubleshooting](#troubleshooting)

## Instalasi Typesense Server

### Menggunakan Docker (Opsi 1)

```bash
docker run -p 8108:8108 -v /www/server/typesense/data:/data \
  typesense/typesense:0.25.0 \
  --data-dir /data \
  --api-key=your-secret-api-key \
  --enable-cors
```

### Instalasi Langsung di Server (Opsi 2)

```bash
# Buat direktori untuk Typesense
mkdir -p /www/server/typesense/{data,logs}

# Download Typesense
curl -O https://dl.typesense.org/releases/0.25.0/typesense-server-0.25.0-linux-amd64.tar.gz

# Extract
tar -xzf typesense-server-0.25.0-linux-amd64.tar.gz -C /www/server/typesense/

# Buat file konfigurasi
cat > /www/server/typesense/config.ini << EOF
api-key = your-secret-api-key
data-dir = /www/server/typesense/data
log-dir = /www/server/typesense/logs
api-address = 0.0.0.0
api-port = 8108
enable-cors = true
memory-limit-mb = 300
num-threads = 2
cache-num-entries = 10000
EOF

# Buat service systemd
cat > /etc/systemd/system/typesense.service << EOF
[Unit]
Description=Typesense Search Engine
After=network.target

[Service]
User=www-data
Group=www-data
ExecStart=/www/server/typesense/typesense-server --config=/www/server/typesense/config.ini
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

# Set permission
chown -R www-data:www-data /www/server/typesense

# Enable dan start service
systemctl daemon-reload
systemctl enable typesense
systemctl start typesense
```

## Instalasi Laravel Scout dengan Typesense

### 1. Install Laravel Scout dan Typesense Adapter

```bash
composer require laravel/scout typesense/laravel-scout-typesense-driver
```

### 2. Publish Konfigurasi Scout

```bash
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

## Konfigurasi Laravel

### 1. Edit file `.env`

```
SCOUT_DRIVER=typesense
SCOUT_QUEUE=true

TYPESENSE_API_KEY=your-secret-api-key
TYPESENSE_HOST=localhost
TYPESENSE_PORT=8108
TYPESENSE_PROTOCOL=http
TYPESENSE_NEAREST_NODE=localhost:8108
```

### 2. Edit file `config/scout.php`

```php
'typesense' => [
    'api_key' => env('TYPESENSE_API_KEY', ''),
    'nodes' => [
        [
            'host' => env('TYPESENSE_HOST', 'localhost'),
            'port' => env('TYPESENSE_PORT', '8108'),
            'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
            'path' => env('TYPESENSE_PATH', ''),
        ],
    ],
    'nearest_node' => [
        'host' => env('TYPESENSE_HOST', 'localhost'),
        'port' => env('TYPESENSE_PORT', '8108'),
        'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
        'path' => env('TYPESENSE_PATH', ''),
    ],
    'connection_timeout_seconds' => env('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
    'healthcheck_interval_seconds' => env('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
    'num_retries' => env('TYPESENSE_NUM_RETRIES', 3),
    'retry_interval_seconds' => env('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
],

'queue' => [
    'queue' => 'low',
    'delay' => 5,
],

'chunk' => [
    'searchable' => 100,
    'unsearchable' => 100,
],
```

## Membuat Model Searchable

### 1. Contoh Model Barang

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Barang extends Model
{
    use HasFactory, Searchable;
    
    protected $guarded = ['id'];
    
    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'kodebarang' => $this->kodebarang,
            'namabarang' => $this->namabarang,
            'harga' => (float) $this->harga,
            'stok' => (int) $this->stok,
            'kategori_id' => $this->kategori_id,
            'brand_id' => $this->brand_id,
            'created_at' => $this->created_at->timestamp,
        ];
    }
    
    /**
     * Get the name of the index associated with the model.
     *
     * @return string
     */
    public function searchableAs()
    {
        return 'barang_index';
    }
    
    /**
     * Define Typesense schema for this model
     */
    public static function typesenseSchema()
    {
        return [
            'name' => 'barang_index',
            'fields' => [
                [
                    'name' => 'id',
                    'type' => 'int32',
                ],
                [
                    'name' => 'kodebarang',
                    'type' => 'string',
                ],
                [
                    'name' => 'namabarang',
                    'type' => 'string',
                ],
                [
                    'name' => 'harga',
                    'type' => 'float',
                ],
                [
                    'name' => 'stok',
                    'type' => 'int32',
                ],
                [
                    'name' => 'kategori_id',
                    'type' => 'int32',
                    'optional' => true,
                ],
                [
                    'name' => 'brand_id',
                    'type' => 'int32',
                    'optional' => true,
                ],
                [
                    'name' => 'created_at',
                    'type' => 'int64',
                ],
            ],
            'default_sorting_field' => 'created_at',
        ];
    }
}
```

### 2. Membuat Command untuk Membuat Schema

```php
<?php

namespace App\Console\Commands;

use App\Models\Barang;
use Illuminate\Console\Command;
use Typesense\LaravelTypesense\Typesense;

class CreateTypesenseSchema extends Command
{
    protected $signature = 'typesense:create-schema';
    protected $description = 'Create Typesense schema for models';

    public function handle()
    {
        $this->info('Creating Typesense schema...');
        
        // Hapus collection jika sudah ada
        try {
            Typesense::client()->collections['barang_index']->delete();
            $this->info('Existing collection deleted.');
        } catch (\Exception $e) {
            $this->info('No existing collection found or error: ' . $e->getMessage());
        }
        
        // Buat schema baru
        try {
            $schema = Barang::typesenseSchema();
            Typesense::client()->collections->create($schema);
            $this->info('Schema created successfully!');
        } catch (\Exception $e) {
            $this->error('Failed to create schema: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
```

### 3. Import Data ke Typesense

```bash
# Buat schema terlebih dahulu
php artisan typesense:create-schema

# Import data
php artisan scout:import "App\Models\Barang"
```

## Menggunakan Typesense di Controller

### 1. Contoh Controller Search

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);
        
        $searchParams = [
            'q' => $query,
            'query_by' => 'namabarang, kodebarang',
            'sort_by' => 'created_at:desc',
            'per_page' => $perPage,
            'page' => $page,
        ];
        
        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_id')) {
            $searchParams['filter_by'] = 'kategori_id:=' . $request->input('kategori_id');
        }
        
        // Filter berdasarkan brand jika ada
        if ($request->has('brand_id')) {
            $filterBy = isset($searchParams['filter_by']) 
                ? $searchParams['filter_by'] . ' && brand_id:=' . $request->input('brand_id')
                : 'brand_id:=' . $request->input('brand_id');
            
            $searchParams['filter_by'] = $filterBy;
        }
        
        // Filter berdasarkan range harga
        if ($request->has('min_price') && $request->has('max_price')) {
            $filterBy = isset($searchParams['filter_by']) 
                ? $searchParams['filter_by'] . ' && harga:>=' . $request->input('min_price') . ' && harga:<=' . $request->input('max_price')
                : 'harga:>=' . $request->input('min_price') . ' && harga:<=' . $request->input('max_price');
            
            $searchParams['filter_by'] = $filterBy;
        }
        
        $results = Barang::search($query, function ($typesense, $query, $options) use ($searchParams) {
            return $typesense->search($searchParams);
        });
        
        return new JsonResponse([
            'data' => $results->items(),
            'meta' => [
                'total' => $results->total(),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($results->total() / $perPage),
            ]
        ]);
    }
}
```

### 2. Tambahkan Route

```php
// routes/api.php
Route::get('search', [App\Http\Controllers\Api\SearchController::class, 'search']);
```

## Menggunakan Typesense di Frontend (Quasar Vue)

### 1. Install Typesense Instantsearch

```bash
npm install typesense-instantsearch-adapter instantsearch.js vue-instantsearch
```

### 2. Contoh Komponen Search di Vue

```vue
<template>
  <div class="search-container">
    <ais-instant-search
      :search-client="searchClient"
      index-name="barang_index"
    >
      <ais-search-box placeholder="Cari barang..." />
      
      <div class="search-filters">
        <ais-refinement-list attribute="kategori_id" />
        <ais-range-input attribute="harga" />
      </div>
      
      <ais-hits>
        <template v-slot:item="{ item }">
          <div class="search-item">
            <h3>{{ item.namabarang }}</h3>
            <p>Kode: {{ item.kodebarang }}</p>
            <p>Harga: Rp {{ item.harga.toLocaleString() }}</p>
            <p>Stok: {{ item.stok }}</p>
          </div>
        </template>
      </ais-hits>
      
      <ais-pagination />
    </ais-instant-search>
  </div>
</template>

<script>
import { TypesenseInstantsearchAdapter } from 'typesense-instantsearch-adapter';
import {
  AisInstantSearch,
  AisSearchBox,
  AisHits,
  AisRefinementList,
  AisRangeInput,
  AisPagination
} from 'vue-instantsearch';

export default {
  components: {
    AisInstantSearch,
    AisSearchBox,
    AisHits,
    AisRefinementList,
    AisRangeInput,
    AisPagination
  },
  data() {
    return {
      searchClient: null
    };
  },
  created() {
    const typesenseInstantsearchAdapter = new TypesenseInstantsearchAdapter({
      server: {
        apiKey: process.env.VUE_APP_TYPESENSE_SEARCH_KEY,
        nodes: [
          {
            host: process.env.VUE_APP_TYPESENSE_HOST || 'localhost',
            port: process.env.VUE_APP_TYPESENSE_PORT || 8108,
            protocol: process.env.VUE_APP_TYPESENSE_PROTOCOL || 'http'
          }
        ]
      },
      additionalSearchParameters: {
        query_by: 'namabarang, kodebarang'
      }
    });
    
    this.searchClient = typesenseInstantsearchAdapter.searchClient;
  }
};
</script>

<style>
.search-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.search-filters {
  display: flex;
  margin: 20px 0;
}

.search-item {
  border: 1px solid #eee;
  padding: 15px;
  margin-bottom: 15px;
  border-radius: 4px;
}
</style>
```

### 3. Konfigurasi Environment di Quasar

```js
// quasar.conf.js
build: {
  env: {
    VUE_APP_TYPESENSE_SEARCH_KEY: process.env.TYPESENSE_SEARCH_KEY || 'your-search-only-key',
    VUE_APP_TYPESENSE_HOST: process.env.TYPESENSE_HOST || 'localhost',
    VUE_APP_TYPESENSE_PORT: process.env.TYPESENSE_PORT || '8108',
    VUE_APP_TYPESENSE_PROTOCOL: process.env.TYPESENSE_PROTOCOL || 'http'
  }
}
```

## Optimasi Typesense

### 1. Membuat API Key dengan Batasan

```php
// Buat command untuk membuat API key
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Typesense\LaravelTypesense\Typesense;

class CreateTypesenseApiKey extends Command
{
    protected $signature = 'typesense:create-api-key {description}';
    protected $description = 'Create a search-only API key for Typesense';

    public function handle()
    {
        $description = $this->argument('description');
        
        try {
            $response = Typesense::client()->keys->create([
                'description' => $description,
                'actions' => ['documents:search'],
                'collections' => ['barang_index'],
            ]);
            
            $this->info('API Key created successfully!');
            $this->info('Key: ' . $response['value']);
            $this->warn('Save this key, it will not be shown again!');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create API key: ' . $e->getMessage());
            return 1;
        }
    }
}
```

### 2. Menggunakan Queue untuk Indexing

Pastikan queue worker berjalan:

```bash
php artisan queue:work --queue=high,default,low
```

### 3. Mengoptimalkan Schema

```php
public static function typesenseSchema()
{
    return [
        'name' => 'barang_index',
        'fields' => [
            [
                'name' => 'id',
                'type' => 'int32',
            ],
            [
                'name' => 'kodebarang',
                'type' => 'string',
                'facet' => false,
            ],
            [
                'name' => 'namabarang',
                'type' => 'string',
                'facet' => false,
                'infix' => true,  // Pencarian substring
            ],
            [
                'name' => 'harga',
                'type' => 'float',
                'facet' => true,  // Untuk filter range
            ],
            [
                'name' => 'stok',
                'type' => 'int32',
                'facet' => false,
            ],
            [
                'name' => 'kategori_id',
                'type' => 'int32',
                'facet' => true,  // Untuk filter kategori
                'optional' => true,
            ],
            [
                'name' => 'brand_id',
                'type' => 'int32',
                'facet' => true,  // Untuk filter brand
                'optional' => true,
            ],
            [
                'name' => 'created_at',
                'type' => 'int64',
                'facet' => false,
            ],
        ],
        'default_sorting_field' => 'created_at',
        'token_separators' => ['-', '_'],  // Untuk kode barang yang menggunakan separator
    ];
}
```

### 4. Mengoptimalkan Penggunaan Memory

Edit file konfigurasi Typesense (`/www/server/typesense/config.ini`):

```ini
memory-limit-mb = 300
cache-num-entries = 10000
log-slow-requests-time-ms = 1000
num-threads = 2
```

## Troubleshooting

### 1. Typesense Tidak Dapat Diakses

```bash
# Periksa status service
systemctl status typesense

# Periksa log
tail -f /www/server/typesense/logs/typesense.log

# Periksa firewall
ufw status
```

### 2. Data Tidak Terindeks

```bash
# Periksa status queue
php artisan queue:monitor

# Jalankan indexing secara manual
php artisan scout:import "App\Models\Barang"

# Periksa log Laravel
tail -f storage/logs/laravel.log
```

### 3. Pencarian Tidak Mengembalikan Hasil yang Diharapkan

```bash
# Periksa schema yang terdaftar
curl http://localhost:8108/collections/barang_index \
  -H "X-TYPESENSE-API-KEY: your-secret-api-key"

# Periksa dokumen yang terindeks
curl http://localhost:8108/collections/barang_index/documents/search \
  -H "X-TYPESENSE-API-KEY: your-secret-api-key" \
  -d '{
    "q": "*",
    "per_page": 5
  }'
```

### 4. Performa Lambat

```bash
# Periksa penggunaan CPU dan Memory
top -c

# Periksa penggunaan disk
df -h

# Periksa slow queries
grep "slow" /www/server/typesense/logs/typesense.log
```

## Integrasi dengan Laravel Horizon

Jika Anda menggunakan Laravel Horizon untuk mengelola queue, pastikan konfigurasi berikut di `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high', 'low'],
            'balance' => 'auto',
            'processes' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
    
    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high', 'low'],
            'balance' => 'auto',
            'processes' => 2,
            'tries' => 3,
        ],
    ],
],
```

## Contoh Implementasi Lengkap

### 1. Membuat Service Provider untuk Typesense

```php
<?php

namespace App\Providers;

use App\Models\Barang;
use Illuminate\Support\ServiceProvider;
use Typesense\LaravelTypesense\Typesense;

class TypesenseServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Register model schemas
        if ($this->app->runningInConsole()) {
            $this->registerModelSchemas();
        }
    }

    protected function registerModelSchemas()
    {
        // Daftarkan semua model yang menggunakan Typesense
        $models = [
            Barang::class,
        ];

        foreach ($models as $model) {
            if (method_exists($model, 'typesenseSchema')) {
                $this->registerSchema($model);
            }
        }
    }

    protected function registerSchema($model)
    {
        try {
            $schema = $model::typesenseSchema();
            $collectionName = $schema['name'];

            // Cek apakah collection sudah ada
            try {
                Typesense::client()->collections[$collectionName]->retrieve();
                // Collection sudah ada, tidak perlu dibuat lagi
            } catch (\Exception $e) {
                // Collection belum ada, buat baru
                Typesense::client()->collections->create($schema);
            }
        } catch (\Exception $e) {
            // Log error
            \Log::error("Failed to register Typesense schema for {$model}: " . $e->getMessage());
        }
    }
}
```

### 2. Membuat Observer untuk Sinkronisasi Data

```php
<?php

namespace App\Observers;

use App\Models\Barang;

class BarangObserver
{
    /**
     * Handle the Barang "created" event.
     */
    public function created(Barang $barang)
    {
        // Barang akan otomatis diindeks oleh Scout
    }

    /**
     * Handle the Barang "updated" event.
     */
    public function updated(Barang $barang)
    {
        // Barang akan otomatis diupdate di indeks oleh Scout
    }

    /**
     * Handle the Barang "deleted" event.
     */
    public function deleted(Barang $barang)
    {
        // Barang akan otomatis dihapus dari indeks oleh Scout
    }

    /**
     * Handle the Barang "restored" event.
     */
    public function restored(Barang $barang)
    {
        $barang->searchable();
    }

    /**
     * Handle the Barang "force deleted" event.
     */
    public function forceDeleted(Barang $barang)
    {
        $barang->unsearchable();
    }
}
```

### 3. Mendaftarkan Observer di AppServiceProvider

```php
<?php

namespace App\Providers;

use App\Models\Barang;
use App\Observers\BarangObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        Barang::observe(BarangObserver::class);
    }
}
```

### 4. Membuat Command untuk Reindex Semua Data

```php
<?php

namespace App\Console\Commands;

use App\Models\Barang;
use Illuminate\Console\Command;
use Typesense\LaravelTypesense\Typesense;

class ReindexTypesense extends Command
{
    protected $signature = 'typesense:reindex';
    protected $description = 'Recreate Typesense schema and reindex all data';

    public function handle()
    {
        $this->info('Starting Typesense reindexing...');
        
        // Recreate schema
        $this->call('typesense:create-schema');
        
        // Reindex data
        $this->info('Reindexing Barang model...');
        $this->call('scout:import', [
            'model' => Barang::class,
        ]);
        
        $this->info('Reindexing completed successfully!');
        
        return 0;
    }
}
```

## Monitoring dan Maintenance

### 1. Monitoring Typesense

Buat script monitoring untuk Typesense:

```bash
#!/bin/bash
# /www/server/scripts/monitor-typesense.sh

DATE=$(date +"%Y-%m-%d %H:%M:%S")
TYPESENSE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8108/health -H "X-TYPESENSE-API-KEY: your-secret-api-key")
TYPESENSE_MEMORY=$(curl -s http://localhost:8108/metrics.json -H "X-TYPESENSE-API-KEY: your-secret-api-key" | grep memory_usage | awk '{print $2}')

echo "$DATE - Typesense Status: $TYPESENSE_STATUS, Memory Usage: $TYPESENSE_MEMORY" >> /www/server/logs/typesense_monitor.log

# Kirim alert jika Typesense tidak berjalan
if [ "$TYPESENSE_STATUS" != "200" ]; then
  echo "ALERT: Typesense is not responding properly. Status code: $TYPESENSE_STATUS" | mail -s "Typesense Alert" your-email@example.com
fi

# Kirim alert jika penggunaan memory terlalu tinggi
if (( $(echo "$TYPESENSE_MEMORY > 250" | bc -l) )); then
  echo "ALERT: Typesense memory usage is high: $TYPESENSE_MEMORY MB" | mail -s "Typesense Memory Alert" your-email@example.com
fi
```

Jadwalkan dengan cron:

```
*/15 * * * * /www/server/scripts/monitor-typesense.sh
```

### 2. Backup Typesense Data

Buat script backup untuk Typesense:

```bash
#!/bin/bash
# /www/server/scripts/backup-typesense.sh

DATE=$(date +"%Y-%m-%d")
BACKUP_DIR="/www/server/backups/typesense"
TYPESENSE_DATA_DIR="/www/server/typesense/data"

# Buat direktori backup jika belum ada
mkdir -p $BACKUP_DIR

# Stop Typesense service
systemctl stop typesense

# Backup data
tar -czf $BACKUP_DIR/typesense-data-$DATE.tar.gz $TYPESENSE_DATA_DIR

# Start Typesense service
systemctl start typesense

# Hapus backup yang lebih dari 7 hari
find $BACKUP_DIR -name "typesense-data-*.tar.gz" -type f -mtime +7 -delete

# Log hasil
echo "Typesense backup completed at $(date)" >> /www/server/logs/backup.log
```

Jadwalkan dengan cron untuk berjalan setiap minggu:

```
0 2 * * 0 /www/server/scripts/backup-typesense.sh
```

## Kesimpulan

Dengan mengikuti panduan ini, Anda telah berhasil mengimplementasikan Typesense sebagai mesin pencarian untuk aplikasi Laravel Anda. Typesense menawarkan performa pencarian yang cepat dengan penggunaan resource yang minimal, cocok untuk server dengan spesifikasi 2 CPU dan 2GB RAM.

Beberapa keuntungan menggunakan Typesense:

1. **Performa Tinggi**: Pencarian instan dengan typo-tolerance
2. **Resource Rendah**: Hanya membutuhkan ~300MB RAM
3. **Mudah Digunakan**: API yang sederhana dan dokumentasi yang lengkap
4. **Integrasi Mudah**: Terintegrasi dengan baik dengan Laravel melalui Scout

Untuk informasi lebih lanjut, kunjungi [dokumentasi resmi Typesense](https://typesense.org/docs/).

