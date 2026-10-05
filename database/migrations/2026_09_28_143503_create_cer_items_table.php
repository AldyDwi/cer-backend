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
        Schema::create('cer_items', function (Blueprint $table) {
           $table->id();

            $table->foreignId('quiz_id')
                ->constrained('cer_quizzes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->unsignedInteger('page_order');

            $table->timestamps();

            $table->unique([
                'quiz_id',
                'page_order',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cer_items');
    }
};
