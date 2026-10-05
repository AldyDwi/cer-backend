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
        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')
                ->constrained('quiz_attempts')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('item_id')
                ->constrained('cer_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('claim_card_id')
                ->nullable()
                ->constrained('option_cards')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('evidence_card_id')
                ->nullable()
                ->constrained('option_cards')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('reasoning_card_id')
                ->nullable()
                ->constrained('option_cards')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->boolean('is_claim_correct')->nullable();
            $table->boolean('is_evidence_correct')->nullable();
            $table->boolean('is_reasoning_correct')->nullable();

            $table->timestamps();

            $table->unique([
                'attempt_id',
                'item_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_answers');
    }
};
