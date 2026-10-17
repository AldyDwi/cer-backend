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
        Schema::create('student_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained('cer_quizzes')->cascadeOnDelete();

            // Tracking Log Komponen CER
            $table->unsignedInteger('place_claim')->default(0);
            $table->unsignedInteger('place_evidence')->default(0);
            $table->unsignedInteger('place_reasoning')->default(0);

            $table->unsignedInteger('delete_claim')->default(0);
            $table->unsignedInteger('delete_evidence')->default(0);
            $table->unsignedInteger('delete_reasoning')->default(0);

            $table->unsignedInteger('replace_claim')->default(0);
            $table->unsignedInteger('replace_evidence')->default(0);
            $table->unsignedInteger('replace_reasoning')->default(0);

            // Tracking Log Baca Materi
            $table->unsignedInteger('read_material_count')->default(0);
            $table->unsignedInteger('read_material_time')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_activity_logs');
    }
};
