<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data wilayah untuk ongkir & alamat. Dulu app memanggil RajaOngkir Pro langsung (key di app, API lama
 * sudah tidak aktif); ID mengikuti pola RajaOngkir supaya alamat lama tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provinces', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('province_id')->index();
            $table->string('type', 20);
            $table->string('name');
            $table->string('postal_code', 10);
            // Titik kasar untuk hitung jarak ongkir simulasi.
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
        });

        Schema::create('subdistricts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedInteger('city_id')->index();
            $table->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subdistricts');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
