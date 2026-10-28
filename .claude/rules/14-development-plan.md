# Development Plan — Pasar Live (backend)

**Kondisi awal (28 Okt 2026):** Laravel 11.9, branch `master` (Sep 2024). Tanpa seeder demo, test bawaan saja.
Semua rute seller/buyer hanya butuh login (tanpa cek peran/pemilik); harga & ongkir order dari app; stok tidak
berkurang; callback Midtrans tanpa verifikasi signature; ongkir lewat RajaOngkir Pro dari app dengan key tertanam
(API lama sudah mati); FCM membaca kolom `fcm_id` yang tidak ada; `.env.example` berisi placeholder yang terbaca
sebagai kunci aktif; nama file `CreateVaService.php` ≠ kelas (gagal di Linux).

Setiap phase ditutup: `php artisan test` hijau · Pint · `migrate:fresh --seed` MySQL · uji kedua app di emulator.

## Phase A — Hidup, aman, alur belanja lengkap

- [x] Laravel 13.39, sanctum 4, tinker 3, phpunit 12, boost 2; kreait/laravel-firebase 7
- [x] Middleware `role:` + cek pemilik produk/alamat/order (404), registrasi dengan email unik & sandi ≥ 8
- [x] Order: harga & ongkir server, stok terkunci & berkurang, nomor transaksi unik, VA Midtrans/simulasi
- [x] Callback Midtrans: signature SHA-512, nominal cocok, transisi idempoten; order kedaluwarsa mengembalikan stok
- [x] Ongkir/wilayah/lacak resi simulasi (bentuk RajaOngkir), wilayah publik untuk form daftar penjual
- [x] Resi hanya penjual pemilik & order lunas; FCM opsional (`PushNotifier`); token Agora 503 tanpa kunci
- [x] Ringkasan dashboard penjual `/seller/summary` (pengganti angka statis di app)
- [x] Seeder demo: 4 toko, 4 pembeli, 24 produk berfoto, ±48 order 30 hari
- [x] `MarketplaceApiTest` (17 test bersama bawaan)
- [x] Rules `.claude`

**Status Phase A:** SELESAI 28 Okt 2026 — 17 test hijau.

## Backlog

- Vendor ongkir nyata (Komerce/Biteship) — keputusan pemilik; adapter cukup mengganti `ShippingSimulator`.
- Uji Midtrans sandbox sungguhan (kunci SB) + webhook publik.
- Live streaming (Agora) & chat live (Firestore) — butuh kunci pemilik.
- Konfirmasi pesanan diterima oleh pembeli (status `completed`) dan ulasan produk.
- Berat produk per item (saat ini 1 kg per paket, sama dengan app).
