<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Wilayah ringkas (Jawa & Bali) untuk alamat dan ongkir simulasi. ID provinsi/kota mengikuti RajaOngkir,
 * ID kecamatan = id kota * 100 + urutan.
 */
class RegionSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, cities: array<int, array{0: string, 1: string, 2: string, 3: float, 4: float, 5: list<string>}>}>
     */
    public const REGIONS = [
        6 => ['name' => 'DKI Jakarta', 'cities' => [
            153 => ['Kota', 'Jakarta Selatan', '12230', -6.2615, 106.8106, ['Kebayoran Baru', 'Tebet', 'Cilandak', 'Pasar Minggu', 'Setiabudi']],
            152 => ['Kota', 'Jakarta Pusat', '10540', -6.1865, 106.8341, ['Menteng', 'Gambir', 'Tanah Abang', 'Senen']],
            151 => ['Kota', 'Jakarta Barat', '11220', -6.1674, 106.7637, ['Grogol Petamburan', 'Kebon Jeruk', 'Palmerah', 'Cengkareng']],
        ]],
        9 => ['name' => 'Jawa Barat', 'cities' => [
            23 => ['Kota', 'Bandung', '40111', -6.9175, 107.6191, ['Coblong', 'Sukajadi', 'Lengkong', 'Buahbatu', 'Cicendo']],
            55 => ['Kota', 'Bekasi', '17121', -6.2383, 106.9756, ['Bekasi Selatan', 'Bekasi Barat', 'Rawalumbu']],
            79 => ['Kota', 'Bogor', '16119', -6.5971, 106.8060, ['Bogor Tengah', 'Bogor Utara', 'Tanah Sareal']],
        ]],
        10 => ['name' => 'Jawa Tengah', 'cities' => [
            445 => ['Kota', 'Surakarta (Solo)', '57113', -7.5755, 110.8243, ['Laweyan', 'Banjarsari', 'Jebres', 'Pasar Kliwon', 'Serengan']],
            399 => ['Kota', 'Semarang', '50135', -6.9667, 110.4167, ['Semarang Tengah', 'Candisari', 'Tembalang', 'Banyumanik']],
        ]],
        5 => ['name' => 'DI Yogyakarta', 'cities' => [
            501 => ['Kota', 'Yogyakarta', '55111', -7.7956, 110.3695, ['Gondokusuman', 'Mergangsan', 'Kraton', 'Umbulharjo']],
            419 => ['Kabupaten', 'Sleman', '55513', -7.7167, 110.3550, ['Depok', 'Mlati', 'Ngaglik', 'Gamping']],
            39 => ['Kabupaten', 'Bantul', '55715', -7.8881, 110.3289, ['Kasihan', 'Sewon', 'Banguntapan']],
        ]],
        11 => ['name' => 'Jawa Timur', 'cities' => [
            444 => ['Kota', 'Surabaya', '60119', -7.2575, 112.7521, ['Gubeng', 'Wonokromo', 'Tegalsari', 'Sukolilo', 'Rungkut']],
            256 => ['Kota', 'Malang', '65112', -7.9666, 112.6326, ['Klojen', 'Lowokwaru', 'Blimbing']],
        ]],
        1 => ['name' => 'Bali', 'cities' => [
            114 => ['Kota', 'Denpasar', '80227', -8.6705, 115.2126, ['Denpasar Selatan', 'Denpasar Barat', 'Denpasar Timur', 'Denpasar Utara']],
            17 => ['Kabupaten', 'Badung', '80351', -8.5819, 115.1771, ['Kuta', 'Kuta Utara', 'Mengwi']],
            32 => ['Kabupaten', 'Bangli', '80619', -8.2970, 115.3549, ['Kintamani', 'Bangli', 'Susut']],
        ]],
    ];

    public function run(): void
    {
        DB::table('subdistricts')->delete();
        DB::table('cities')->delete();
        DB::table('provinces')->delete();

        foreach (self::REGIONS as $provinceId => $province) {
            DB::table('provinces')->insert(['id' => $provinceId, 'name' => $province['name']]);

            foreach ($province['cities'] as $cityId => [$type, $name, $postalCode, $latitude, $longitude, $subdistricts]) {
                DB::table('cities')->insert([
                    'id' => $cityId,
                    'province_id' => $provinceId,
                    'type' => $type,
                    'name' => $name,
                    'postal_code' => $postalCode,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);

                foreach ($subdistricts as $index => $subdistrict) {
                    DB::table('subdistricts')->insert([
                        'id' => $cityId * 100 + $index + 1,
                        'city_id' => $cityId,
                        'name' => $subdistrict,
                    ]);
                }
            }
        }
    }

    /**
     * ID kecamatan dari nama kota & kecamatan (dipakai seeder demo).
     */
    public static function subdistrictId(int $cityId, string $subdistrict): int
    {
        foreach (self::REGIONS as $province) {
            if (isset($province['cities'][$cityId])) {
                $index = array_search($subdistrict, $province['cities'][$cityId][5], true);

                return $cityId * 100 + $index + 1;
            }
        }

        throw new \InvalidArgumentException("Kecamatan {$subdistrict} tidak ada di kota {$cityId}.");
    }
}
