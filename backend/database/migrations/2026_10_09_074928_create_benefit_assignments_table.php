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
        Schema::create('benefit_assignments', function (Blueprint $table) {
        $table->id();

        $table->foreignId('user_id')
            ->constrained()
            ->restrictOnDelete();

        $table->foreignId('benefit_plan_id')
            ->constrained()
            ->restrictOnDelete();

        $table->unsignedSmallInteger('year');

        $table->unique(['user_id', 'year']); // each employee can only have 1 plan on each year

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benefit_assignments');
    }
};
