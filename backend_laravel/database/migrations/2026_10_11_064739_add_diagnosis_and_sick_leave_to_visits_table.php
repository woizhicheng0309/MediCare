<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Preserve existing records without inventing historical medical data.
            $table->string('diagnosis')->nullable();
            $table->date('sick_leave_started_on')->nullable();
            $table->date('sick_leave_ended_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            // Remove the fields introduced by this migration.
            $table->dropColumn([
                'diagnosis',
                'sick_leave_started_on',
                'sick_leave_ended_on',
            ]);
        });
    }
};