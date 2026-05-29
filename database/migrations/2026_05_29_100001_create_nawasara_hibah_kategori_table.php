<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nawasara_hibah_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique(); // HIBAH UANG, HIBAH BARANG, BANSOS, BK
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_kategori');
    }
};
