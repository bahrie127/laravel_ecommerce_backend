<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Nama provinsi/kota/kecamatan untuk model yang menyimpan ID wilayah di kolom `province`, `city`, `district`.
 */
trait HasRegionNames
{
    /** @var array<string, array<int, string|null>> */
    private static array $regionNameCache = [];

    public function getProvinceNameAttribute(): ?string
    {
        return self::regionName('provinces', 'name', $this->province);
    }

    public function getCityNameAttribute(): ?string
    {
        $type = self::regionName('cities', 'type', $this->city);
        $name = self::regionName('cities', 'name', $this->city);

        return $name ? trim(($type === 'Kabupaten' ? 'Kab. ' : 'Kota ').$name) : null;
    }

    public function getDistrictNameAttribute(): ?string
    {
        return self::regionName('subdistricts', 'name', $this->district);
    }

    private static function regionName(string $table, string $column, mixed $id): ?string
    {
        if (! is_numeric($id)) {
            return null;
        }

        return self::$regionNameCache["{$table}.{$column}"][(int) $id]
            ??= DB::table($table)->where('id', (int) $id)->value($column);
    }
}
