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
        Schema::table('visits', function (Blueprint $table) {
            // Preserve existing visits when time or doctor details are unknown.
            $table->time('visited_time')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('doctor_title')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Remove only the columns introduced by this migration.
            $table->dropColumn([
                'visited_time',
                'doctor_name',
                'doctor_title',
            ]);
        });
    }
};
