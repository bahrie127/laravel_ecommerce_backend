# Konvensi Wajib — Pasar Live (ringkas)

`_umum/` berlaku, tetapi repo ini API-only lama: tidak memakai spatie/permission, komponen Blade, atau `/api/v1`.
**Rules proyek menang** — jangan memaksakan pola web `_umum` ke sini.

1. **Peran lewat middleware `role:`** (`seller` / `user`). Setiap aksi memeriksa pemilik data (produk, alamat, order)
   dan membalas **404** untuk data milik orang lain.
2. **Harga, ongkir, total, dan status order dihitung server.** App hanya mengirim produk, jumlah, alamat, layanan
   kirim (`"JNE REG"`), dan bank VA. `shipping_cost` dari app diabaikan.
3. **Stok** dikurangi saat order dibuat (dalam transaksi + `lockForUpdate`), dikembalikan saat order kedaluwarsa/batal.
4. **Transisi status hanya lewat method model** (`markPaid`, `closeUnpaid`, `ship`) — idempoten.
5. **Bentuk JSON lama dipertahankan** (app membaca `{status, message, data}`, harga `decimal` sebagai string,
   flag `is_*` sebagai int). Respons buat-order mengirim angka (int). Ubah bentuk → ubah app + test.
6. **Galat API selalu JSON**, walau app lupa header `Accept` (`bootstrap/app.php`).
7. **Rahasia hanya di `.env`**; placeholder kosong, bukan `####` (placeholder dianggap kunci aktif).
8. **Integrasi luar dengan mode simulasi**; test tidak memanggil Midtrans/Firebase/Agora.
9. **Wilayah = ID RajaOngkir** di kolom `province/city/district`; nama ikut dikirim (`*_name`) lewat `HasRegionNames`.
10. **Seeder demo realistis**, relatif hari ini; foto Unsplash di `database/seeders/images` disalin ke storage.
11. **Test + Pint + `migrate:fresh --seed` (MySQL) + uji kedua app di emulator** sebelum phase selesai.
12. **Lokal** — PHP Laragon 8.3, `PHP_INI_SCAN_DIR="D:\.php-ini-hermes"` untuk test, suite memakai DB MySQL
    `ecommerce_test` (phpunit.xml) — jangan pernah arahkan ke DB dev.
