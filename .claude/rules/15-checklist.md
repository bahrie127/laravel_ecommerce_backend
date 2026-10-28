# Checklist Sebelum Selesai — Pasar Live (backend)

## Setiap fitur
- [ ] Rute di grup peran yang benar (`role:seller` / `role:user`) + cek pemilik data (404)
- [ ] Nilai uang/status dihitung server; transisi lewat method model
- [ ] Bentuk JSON lama tetap (cek model Dart di kedua app); perubahan diikuti app
- [ ] Integrasi luar lewat service dengan mode simulasi; tanpa kunci di kode
- [ ] Test ditambah (happy path, 403/404 peran & pemilik, validasi)
- [ ] `vendor/bin/pint --dirty --format agent`

## Setiap phase
- [ ] `php artisan migrate:fresh --seed` hijau di MySQL
- [ ] `php artisan test --compact` hijau (DB `ecommerce_test`)
- [ ] Uji alur pembeli & penjual di emulator via proxy 8370
- [ ] Centang `14-development-plan.md`, laporan + langkah deploy (migrate, cron `schedule:run`, `.env`)
