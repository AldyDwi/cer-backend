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
        Schema::create('cer_quizzes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('teacher_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('material_id')
                ->constrained('materials')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedInteger('duration_minutes')
                ->default(15);

            $table->enum('status', [
                'draft',
                'published',
            ])->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cer_quizzes');
    }
};
