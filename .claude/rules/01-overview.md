# Pasar Live — Marketplace Live Shopping (backend)

API untuk dua app Flutter kelas FIC 19: `flutter_ecommerce_user_app` (pembeli) dan `flutter_ecommerce_seller_app`
(penjual/toko). Pembeli belanja per toko, bayar lewat virtual account, melacak paket; penjual mengelola produk,
memproses pesanan (input resi), dan bisa siaran live (Agora). Tidak ada panel web.

## SSOT

| Sumber | Lokasi |
|---|---|
| Kontrak API | `routes/api.php`, `app/Http/Controllers/Api/*` (bentuk JSON lama dipertahankan untuk app) |
| Plan | `.claude/rules/14-development-plan.md` |
| Portofolio | `jagoflutter/.claude/services/portfolio-projects.md` |

## Stack

PHP 8.3 · Laravel 13 · MySQL 8 (Laragon 3307, DB `ecommerce`, test `ecommerce_test`, demo `ecommerce_portfolio_demo`) ·
Sanctum · midtrans-php (Core API VA) · kreait/laravel-firebase (FCM, opsional) · token builder Agora (bawaan repo)

## Lokal

`php -S 0.0.0.0:8379` dari `public/` + proxy `node ../.claude/playbook/tools/emulator-proxy.mjs 8370 8379`,
app emulator `http://10.0.2.2:8370` · `SESSION_COOKIE=ecommerce_session` · Composer butuh
`--ignore-platform-req=ext-sodium` (Laragon tanpa sodium; hanya untuk EdDSA di lcobucci/jwt).

Akun demo (sandi `password`):

| Peran (`users.roles`) | Email |
|---|---|
| Penjual | `batiklestari@`, `gadgetnusantara@` (sedang live), `dapurrumah@`, `kopikintamani@pasarlive.test` |
| Pembeli (`user`) | `andi@`, `sinta@`, `rizky@`, `dewi@pasarlive.test` |

## Modul

| Area | Route | Peran |
|---|---|---|
| Auth, FCM token | `/login`, `/logout`, `/buyer/register`, `/seller/register`, `/update-fcm-token` | semua |
| Wilayah (publik) | `/shipping/province|city|subdistrict` | publik, throttle |
| Ongkir & lacak resi | `/shipping/cost`, `/shipping/waybill` | login (resi: pembeli/penjual order) |
| Produk, kategori, pesanan masuk, resi, ringkasan, status live | `/seller/*` | `role:seller` |
| Toko, alamat, order, simulasi bayar, riwayat | `/buyer/*` | `role:user` |
| Detail order, token live | `/orders/{order}`, `/agora/token` | login (pemilik/peserta) |
| Callback Midtrans | `/midtrans/callback` | publik, signature SHA-512 |

## Status order

`pending` (VA aktif 24 jam, stok sudah dipesan) → `paid` (`paid_at`) → `shipping` (`shipping_number`, `shipped_at`);
`pending` → `expired`/`cancelled` mengembalikan stok (`Order::closeUnpaid`, command `orders:expire-unpaid` tiap 15 menit).

## Integrasi luar (semua opsional, mode simulasi default)

- **Ongkir/wilayah/resi:** `App\Services\Shipping\ShippingSimulator` — tarif JNE OKE/REG/YES dari jarak antarkota
  (haversine), manifest resi bertahap dari `shipped_at`. Bentuk respons = RajaOngkir Pro (app memakai model lama).
  RajaOngkir Pro lama sudah tidak aktif; vendor nyata (Komerce/Biteship) = keputusan pemilik.
- **Pembayaran:** `MIDTRANS_SERVER_KEY` kosong → VA dibuat lokal + `POST /buyer/orders/{id}/simulate-payment`.
- **FCM:** `FIREBASE_CREDENTIALS` kosong → notifikasi dilewati (`PushNotifier`).
- **Agora:** `AGORA_APP_ID`/`AGORA_APP_CERTIFICATE` kosong → token 503.
