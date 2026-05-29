<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link a user to the OPD they operate for. Kept as a dedicated pivot
     * (not a column on `users`) so the hibah package stays self-contained
     * and doesn't migrate the core users table. One operator → one OPD;
     * admin-hibah users simply have no row here and bypass the OpdScope.
     */
    public function up(): void
    {
        Schema::create('nawasara_hibah_operator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('opd_id')->constrained('nawasara_registry_opd')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique('user_id'); // one OPD per operator
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nawasara_hibah_operator');
    }
};
