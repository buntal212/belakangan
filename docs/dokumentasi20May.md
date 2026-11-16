# Dokumentasi Meeting 20 May 2025

## 1. Manajemen User & Pegawai

- [🟢] Hidden pelanggan dari user
- [🟢] Input pegawai harus ada default pegawai
- [ ] Akun pelanggan (pending - menunggu verifikasi fisik)

## 2. Master Barang

- [🟢] Input non-keramik: field jenis keramik tidak muncul
- [🟢] Filter master barang (low stock dll)
- [🟢] Format tampilan satuan:
  - Satuan besar tanpa kurung
  - Satuan kecil (dalam kurung)
- [🟢] Kesepakatan pemecahan satuan besar ke kecil
- [🟢] Pencarian by nama barang dan kode barang

## 3. Stok & Inventory

- [ ] Stok Opname:
  - Perbaikan warna label nama barang
  - Highlight content
  - 🟢 Penyesuaian content stok sesuai kesepakatan
- [🟢] Kartu stok barang (fix bug) (Permasalahan Karena tanggal di transaksi tersimpan 0000-00-00 jadi jumlah stok langsung masuk ke sado awal bukan menambah list sebgai transaksi)
- [ ] Pencarian di menu stok
- [ ] Stok satuan_b dihitung otomatis oleh sistem
- [ ] Tampilan stok saat login sebagai sales

## 4. Penerimaan Barang

- [🟢] Bug di transaksi penerimaan
- [🟢] Bug di tanggal penerimaan, ketika tanggal tidak dipilih ulang tanggalnya akan tersimpan 0000-00-00
- [🟢] Aktivasi delete header
- [ ] Perbaikan print transaksi
- [ ] Tambah master kebijakan supplier

## 5. Penjualan

### 5.1 Form Penjualan

- [🟢] Hapus field sales
- [🟢] Validasi data pelanggan tidak boleh kosong
- [🟢] Rapikan tampilan form
- [🟢] Reset form di order barang
- [ ] Satuan di keranjang belanja

### 5.2 List Penjualan

- [🟢] Tampilkan list di awal, form setelahnya
- [🟢] Tambah filter list penjualan (filter flag:semua, lunas, terhutang, dan masing2 flag)
- [ ] Print: tambah kolom satuan

### 5.3 Pembayaran Cicilan & Retur

- [🟢] Fix bug DP di pembayaran cicilan (pembayaran DP harus lunas, boleh ga lunas sih kalo pelanggan, tapi harus punya pelanggan id)
- [🟢] Validasi pembayaran <= total hutang (cek di backend untuk semua hutang dan retur)
- [🟢] Cicilan: kurangi dengan nilai return (ini kalo ada retur jumlah cicilan nya ga ngurangi retur)
- [🟢] Fix bug jumlah return
- [ ] Implementasi retur penjualan

## 6. Penyesuaian Stok

- [ ] Tambah informasi stok
- [ ] Validasi pengurangan stok
- [ ] Tambah filter tanggal

## 7. E-commerce & Frontend

- [ ] Tombol kembali -> redirect ke home
- [ ] Hapus fitur ulasan di halaman tentang
- [ ] Notifikasi berhasil checkout
- [ ] Persetujuan dari sales
- [ ] Status order sales langsung ke-3

## 8. UI/UX

- [ ] Standarisasi warna:
  - Label nama: putih
  - Stok: kuning di semua section
- [ ] Pewarnaan tombol dan pemilihan icon (sesuai arahan mas Vigar)

## 9. Fitur Admin

- [ ] Pengembangan fitur admin untuk order

## Status Pengembangan

- 🔴 Bug/Critical
- 🟡 In Progress
- 🟢 Completed

## Tim Pengembang

- Frontend:
- Backend:
- UI/UX:
- QA:

## Timeline

- Target Completion:
- Next Review:
