<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_courier', 20)->nullable()->after('shipping_service');
            $table->timestamp('paid_at')->nullable()->after('transaction_number');
            $table->timestamp('shipped_at')->nullable()->after('paid_at');
            $table->unique('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['transaction_number']);
            $table->dropColumn(['shipping_courier', 'paid_at', 'shipped_at']);
        });
    }
};
