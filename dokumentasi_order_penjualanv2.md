# Dokumentasi Order Penjualan v2

## 1. Struktur Tabel Order Penjualan (`order_penjualans`)

- Menyimpan data utama order penjualan.
- Kolom penting:
  - `noorder` (unik, format: yymd-XXXXXX-OPJ)
  - `tglorder`
  - `pelanggan_id` (relasi ke users)
  - `sales_id` (relasi ke users)
  - `total_harga`
  - `status_order` (status proses order)
  - `status_pembayaran` (status pembayaran)
  - `alasan_batal` (nullable, alasan pembatalan)
  - `tanggal_kirim` (nullable, waktu barang dikirim)
  - `tanggal_terima` (nullable, waktu barang diterima pelanggan)
  - Timestamps

### Status Order (`status_order`)
- 1: draft
- 2: menunggu persetujuan
- 3: disetujui
- 4: diproses
- 5: dikirim
- 6: diterima
- 9: dibatalkan

### Status Pembayaran (`status_pembayaran`)
- 1: hutang
- 2: lunas

---

## 2. Struktur Tabel Rincian Order Penjualan (`order_penjualan_rincians`)

- Menyimpan detail produk pada setiap order.
- Kolom:
  - `order_penjualan_id` (relasi ke order_penjualans)
  - `produk_id` (relasi ke products)
  - `qty`
  - `harga`
  - `subtotal`

---

## 3. Perubahan & Penambahan Kolom

- **Penambahan status pembatalan dan alasan batal:**
  - Kolom `status_order` diperluas opsinya (termasuk 9: dibatalkan).
  - Kolom `alasan_batal` ditambahkan (nullable).
- **Tracking pengiriman:**
  - Kolom `tanggal_kirim` dan `tanggal_terima` ditambahkan untuk tracking proses pengiriman dan penerimaan barang.

---

## 4. Proses Bisnis Status Order

- **Admin**:
  - Mengubah status order dari draft → menunggu persetujuan → disetujui → diproses → dikirim.
  - Bisa membatalkan order (status 9) dan mengisi alasan pembatalan.
- **User/Pelanggan**:
  - Melihat status order.
  - Konfirmasi penerimaan barang (mengubah status menjadi 6: diterima).
- **Tracking**:
  - Tanggal kirim dan terima dicatat otomatis/manual sesuai proses.

---

## 5. Implementasi Kode

- **Generate No Order Unik:**
  - Format: `yymd-XXXXXX-OPJ`
  - Menggunakan kombinasi tanggal dan karakter acak, dicek ke database agar benar-benar unik.

- **Transaksi Database:**
  - Proses pembuatan order dan rincian menggunakan DB Transaction.
  - Jika ada error, semua data di-rollback.

- **Relasi Model:**
  - `OrderPenjualan` memiliki banyak `OrderPenjualanRincian`.
  - Relasi ke user (pelanggan & sales) dan produk.

---

## 6. Migrasi Penambahan Kolom

**Contoh migrasi penambahan status batal dan alasan batal:**
```php
$table->string('status_order', 2)
    ->default('1')
    ->comment('1: draft, 2: menunggu persetujuan, 3: disetujui, 4: diproses, 5: dikirim, 6: diterima, 9: dibatalkan')
    ->change();
$table->string('alasan_batal')->nullable()->after('status_order')->comment('Alasan pembatalan order');
```

**Contoh migrasi tracking pengiriman:**
```php
$table->timestamp('tanggal_kirim')->nullable()->after('status_order')->comment('Tanggal order dikirim ke pelanggan');
$table->timestamp('tanggal_terima')->nullable()->after('tanggal_kirim')->comment('Tanggal order diterima pelanggan');
```

---

## 7. Catatan

- Semua perubahan dilakukan dengan migrasi baru agar histori database tetap rapi.
- Status order dan pembayaran dipisah agar proses bisnis lebih fleksibel.
- Tracking pengiriman dan penerimaan barang sudah disiapkan, tinggal diintegrasikan ke proses bisnis.

---

_Dokumentasi ini bisa digunakan sebagai referensi pengembangan, diskusi, dan onboarding tim developer._