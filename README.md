# Lumbung Hijau - Backend

Backend untuk aplikasi Lumbung Hijau menggunakan Laravel 11 dan MySQL.

## Struktur Folder

```
backend/
├── app/                    # Logika utama aplikasi
│   ├── Http/              # Controllers, Middleware, Requests
│   │   ├── Controllers/   # Controller untuk menangani request
│   │   │   └── Api/       # Controller untuk API
│   │   ├── Middleware/    # Middleware untuk autentikasi dan validasi
│   │   └── Requests/      # Form request validation
│   ├── Models/            # Model Eloquent untuk database
│   ├── Filament/          # Admin panel menggunakan Filament
│   └── Providers/         # Service providers
│
├── database/              # Database migrations dan seeders
│   ├── migrations/        # File migrasi database
│   ├── seeders/          # Seeder untuk data awal
│   └── factories/         # Factory untuk testing
│
├── routes/                # Definisi route
│   ├── api.php           # Route untuk API
│   ├── web.php           # Route untuk web
│   └── auth.php          # Route untuk autentikasi
│
├── storage/              # File yang di-upload
│   └── app/public/      # File yang bisa diakses publik
│
└── config/              # File konfigurasi
```

## API Endpoints

### Autentikasi
- POST `/api/auth/register` - Registrasi user baru
- POST `/api/auth/login` - Login user
- POST `/api/auth/logout` - Logout user (perlu token)
- GET `/api/auth/user` - Get data user yang login (perlu token)

### User & Profile
- GET `/api/users/{id}` - Get detail user berdasarkan ID
- GET `/api/profile` - Get profile user yang login
- PATCH `/api/profile/update` - Update profile user

### OTP
- POST `/api/otp/send` - Kirim kode OTP
- POST `/api/otp/verify` - Verifikasi kode OTP
- POST `/api/otp/resend` - Kirim ulang kode OTP

### Kategori Sampah
- GET `/api/waste-categories` - List semua kategori sampah
- POST `/api/waste-categories` - Tambah kategori baru (admin)
- POST `/api/waste-categories/{id}` - Update kategori (admin)
- DELETE `/api/waste-categories/{id}` - Hapus kategori (admin)

### Transaksi
- GET `/api/transactions/user` - List transaksi user
- GET `/api/transactions/user/{id}` - Detail transaksi user
- GET `/api/transactions/pending` - List transaksi pending (collector/admin)
- GET `/api/transactions/verified` - List transaksi terverifikasi (collector/admin)
- GET `/api/transactions/search` - Cari transaksi (collector/admin)
- GET `/api/transactions/{id}` - Detail transaksi (collector/admin)
- POST `/api/transactions/verify/{id}` - Verifikasi transaksi (collector)
- POST `/api/transactions/verify/{id}/submit` - Submit verifikasi (collector)
- POST `/api/transactions/{id}/admin-action` - Approve/reject transaksi (admin)

### Cart
- GET `/api/cart` - Get cart user
- POST `/api/cart/add` - Tambah item ke cart
- POST `/api/cart/remove` - Hapus item dari cart
- POST `/api/cart/update-item` - Update item di cart
- POST `/api/cart/submit` - Submit cart jadi transaksi

### Penarikan Saldo
- GET `/api/withdrawals` - List penarikan user
- POST `/api/withdrawals` - Request penarikan baru
- GET `/api/withdrawals/success` - List penarikan sukses
- GET `/api/withdrawals/check-expired` - Cek penarikan yang expired (admin)
- GET `/api/withdrawals/{id}` - Detail penarikan
- POST `/api/withdrawals/{id}/status` - Update status penarikan (admin)

## Middleware

- `auth:sanctum` - Memastikan request memiliki token yang valid
- `role:collector,admin` - Memastikan user memiliki role yang sesuai

## Models

- User
- WasteCategory
- Transaction
- TransactionDetail
- BalanceHistory
- Withdrawal

## Setup Development

1. Install dependencies:
```bash
composer install
```

2. Copy .env.example ke .env dan sesuaikan konfigurasi database

3. Generate app key:
```bash
php artisan key:generate
```

4. Jalankan migrasi dan seeder:
```bash
php artisan migrate --seed
```

5. Link storage:
```bash
php artisan storage:link
```

6. Jalankan server:
```bash
php artisan serve
```

## Testing

Jalankan test dengan:
```bash
php artisan test
```
