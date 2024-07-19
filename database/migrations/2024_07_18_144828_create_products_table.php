<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            //seller_id
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            //category_id
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            //name
            $table->string('name');
            //description
            $table->text('description')->nullable();
            //price
            $table->decimal('price', 15, 2);
            //stock
            $table->integer('stock');
            //image
            $table->string('image')->nullable();
            //is_active
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
