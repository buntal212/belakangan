## TransientToken dalam Laravel Sanctum

`TransientToken` adalah kelas yang digunakan oleh Laravel Sanctum untuk merepresentasikan token yang tidak disimpan dalam database. Ini adalah konsep penting dalam Sanctum yang memungkinkan dua mode autentikasi berbeda:

### 1. Token Berbasis Database (Personal Access Tokens)

Ini adalah token API tradisional yang:
- Disimpan dalam tabel `personal_access_tokens`
- Dibuat dengan metode `createToken()`
- Digunakan untuk autentikasi API dengan header `Authorization: Bearer {token}`
- Memiliki metode seperti `delete()`, `can()`, dll.

### 2. Token Sementara (TransientToken)

`TransientToken` adalah objek yang:
- **Tidak disimpan dalam database**
- Dibuat secara otomatis oleh Sanctum saat autentikasi berbasis cookie/session
- Digunakan saat SPA (Single Page Application) mengakses API melalui cookie
- **Tidak memiliki metode `delete()`** (penyebab error yang Anda alami)

### Kapan TransientToken Digunakan?

TransientToken digunakan dalam skenario berikut:

1. Saat menggunakan fitur SPA Authentication dari Sanctum
2. Ketika request API berasal dari domain yang sama (stateful domain)
3. Ketika autentikasi dilakukan melalui cookie Laravel, bukan token API

Dalam kasus ini, Sanctum secara otomatis membuat `TransientToken` untuk request tersebut, yang memungkinkan aplikasi SPA mengakses API tanpa perlu mengirim token API dalam setiap request.

### Perbedaan Utama

| Personal Access Token | TransientToken |
|-----------------------|----------------|
| Disimpan di database | Tidak disimpan di database |
| Perlu dikirim dalam header | Menggunakan cookie session |
| Memiliki metode `delete()` | Tidak memiliki metode `delete()` |
| Dapat memiliki abilities/scopes | Memiliki semua abilities |
| Dapat kedaluwarsa | Mengikuti sesi Laravel |

### Implikasi untuk Logout

Saat melakukan logout:
- Untuk Personal Access Token: Perlu menghapus token dari database
- Untuk TransientToken: Cukup menghapus session Laravel (tidak perlu menghapus token)

Itulah mengapa error terjadi saat mencoba memanggil `delete()` pada `TransientToken` - karena tidak ada yang perlu dihapus dari database.