# Social Login API Documentation

## Overview

API ini menyediakan endpoint untuk melakukan autentikasi menggunakan provider OAuth seperti Google dan GitHub, serta autentikasi menggunakan email dan password.

## Base URL

```
http://localhost:8000/api/v2
```

## Endpoints

### Email Authentication

#### Login dengan Email

Melakukan login menggunakan email dan password.

```
POST /auth/email/login
```

##### Request Body

| Field    | Type   | Description                |
|----------|--------|----------------------------|
| email    | string | Email pengguna             |
| password | string | Password pengguna          |

##### Response

```json
{
  "success": true,
  "user": {
    "id": 1,
    "nama": "John Doe",
    "email": "john@example.com",
    "username": "johndoe",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
}
```

##### Response (Jika User Terdaftar dengan Social Provider)

```json
{
  "success": false,
  "message": "Akun Anda terdaftar menggunakan Google. Silakan login menggunakan Google atau set password terlebih dahulu.",
  "provider": "google"
}
```

#### Register dengan Email

Mendaftarkan pengguna baru menggunakan email dan password.

```
POST /auth/email/register
```

##### Request Body

| Field                | Type   | Description                                |
|----------------------|--------|--------------------------------------------|
| nama                 | string | Nama lengkap pengguna                      |
| email                | string | Email pengguna (harus unik)                |
| username             | string | Username pengguna (harus unik)             |
| password             | string | Password pengguna (minimal 6 karakter)     |
| password_confirmation| string | Konfirmasi password (harus sama)           |

##### Response

```json
{
  "success": true,
  "message": "Registrasi berhasil",
  "user": {
    "id": 1,
    "nama": "John Doe",
    "email": "john@example.com",
    "username": "johndoe",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
}
```

#### Set Password untuk Akun Social

Mengatur password untuk akun yang terdaftar menggunakan social provider.

```
POST /auth/email/set-password
```

##### Request Body

| Field                | Type   | Description                                |
|----------------------|--------|--------------------------------------------|
| email                | string | Email pengguna                             |
| password             | string | Password baru (minimal 6 karakter)         |
| password_confirmation| string | Konfirmasi password (harus sama)           |

##### Response

```json
{
  "success": true,
  "message": "Password berhasil diatur",
  "user": {
    "id": 1,
    "nama": "John Doe",
    "email": "john@example.com",
    "username": "johndoe",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  },
  "token": "1|abcdefghijklmnopqrstuvwxyz1234567890"
}
```

#### Logout

Menghapus token autentikasi pengguna.

```
POST /auth/email/logout
```

##### Headers

| Header        | Value                                |
|---------------|--------------------------------------|
| Authorization | Bearer {token}                       |

##### Response

```json
{
  "success": true,
  "message": "Successfully logged out"
}
```

#### Get User Data

Mendapatkan data pengguna yang sedang login.

```
GET /auth/me
```

##### Headers

| Header        | Value                                |
|---------------|--------------------------------------|
| Authorization | Bearer {token}                       |

##### Response

```json
{
  "success": true,
  "user": {
    "id": 1,
    "nama": "John Doe",
    "email": "john@example.com",
    "username": "johndoe",
    "created_at": "2023-01-01T00:00:00.000000Z",
    "updated_at": "2023-01-01T00:00:00.000000Z"
  }
}
```

### Social Authentication

#### Get OAuth Redirect URL

Mendapatkan URL untuk redirect ke provider OAuth.

```
GET /auth/{provider}/redirect-url
```

##### Parameters

| Parameter | Type   | Description                                |
|-----------|--------|--------------------------------------------|
| provider  | string | Provider OAuth (google, github)            |

##### Response

```json
{
  "status": "success",
  "redirect_url": "https://accounts.google.com/o/oauth2/auth?client_id=..."
}
```

#### OAuth Redirect

Redirect langsung ke provider OAuth.

```
GET /auth/{provider}/redirect
```

##### Parameters

| Parameter | Type   | Description                                |
|-----------|--------|--------------------------------------------|
| provider  | string | Provider OAuth (google, github)            |

##### Response

Redirect ke halaman login provider OAuth.

#### OAuth Callback

Callback dari provider OAuth setelah user berhasil login.

```
GET /auth/{provider}/callback
```

##### Parameters

| Parameter | Type   | Description                                |
|-----------|--------|--------------------------------------------|
| provider  | string | Provider OAuth (google, github)            |

##### Response

Redirect ke frontend dengan token dan data user:

```
{frontend_url}/auth/social-callback?token={token}&user={user_data}
```

## Contoh Penggunaan

### Frontend (React)

#### Login dengan Email

```javascript
// Fungsi untuk login dengan email
const loginWithEmail = async (email, password) => {
  try {
    const response = await axios.post('/api/v2/auth/email/login', {
      email,
      password
    });
    
    // Simpan token dan data user
    localStorage.setItem('token', response.data.token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    
    // Redirect ke dashboard
    navigate('/dashboard');
  } catch (error) {
    console.error('Error:', error.response?.data?.message || error.message);
  }
};

// Fungsi untuk registrasi dengan email
const registerWithEmail = async (userData) => {
  try {
    const response = await axios.post('/api/v2/auth/email/register', userData);
    
    // Simpan token dan data user
    localStorage.setItem('token', response.data.token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    
    // Redirect ke dashboard
    navigate('/dashboard');
  } catch (error) {
    console.error('Error:', error.response?.data?.message || error.message);
  }
};
```

#### Login dengan Google

```javascript
// Fungsi untuk login dengan Google
const loginWithGoogle = async () => {
  try {
    // Dapatkan URL redirect
    const response = await axios.get('/api/v2/auth/google/redirect-url');
    
    // Redirect ke URL provider OAuth
    window.location.href = response.data.redirect_url;
  } catch (error) {
    console.error('Error:', error);
  }
};

// Halaman callback untuk menerima token setelah login
const SocialCallback = () => {
  useEffect(() => {
    // Ambil token dan data user dari URL
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    const userData = JSON.parse(decodeURIComponent(params.get('user')));
    
    if (token) {
      // Simpan token dan data user
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(userData));
      
      // Redirect ke dashboard
      navigate('/dashboard');
    }
  }, []);
  
  return <div>Loading...</div>;
};
```

### Frontend (Vue)

#### Login dengan Email

```javascript
// Fungsi untuk login dengan email
const loginWithEmail = async (email, password) => {
  try {
    const response = await axios.post('/api/v2/auth/email/login', {
      email,
      password
    });
    
    // Simpan token dan data user
    localStorage.setItem('token', response.data.token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    
    // Redirect ke dashboard
    router.push('/dashboard');
  } catch (error) {
    console.error('Error:', error.response?.data?.message || error.message);
  }
};

// Fungsi untuk registrasi dengan email
const registerWithEmail = async (userData) => {
  try {
    const response = await axios.post('/api/v2/auth/email/register', userData);
    
    // Simpan token dan data user
    localStorage.setItem('token', response.data.token);
    localStorage.setItem('user', JSON.stringify(response.data.user));
    
    // Redirect ke dashboard
    router.push('/dashboard');
  } catch (error) {
    console.error('Error:', error.response?.data?.message || error.message);
  }
};
```

#### Login dengan Google

```javascript
// Fungsi untuk login dengan Google
const loginWithGoogle = async () => {
  try {
    // Dapatkan URL redirect
    const response = await axios.get('/api/v2/auth/google/redirect-url');
    
    // Redirect ke URL provider OAuth
    window.location.href = response.data.redirect_url;
  } catch (error) {
    console.error('Error:', error);
  }
};

// Halaman callback untuk menerima token setelah login
// Di dalam setup() atau mounted()
onMounted(() => {
  // Ambil token dan data user dari URL
  const params = new URLSearchParams(window.location.search);
  const token = params.get('token');
  const userData = JSON.parse(decodeURIComponent(params.get('user')));
  
  if (token) {
    // Simpan token dan data user
    localStorage.setItem('token', token);
    localStorage.setItem('user', JSON.stringify(userData));
    
    // Redirect ke dashboard
    router.push('/dashboard');
  }
});
```
