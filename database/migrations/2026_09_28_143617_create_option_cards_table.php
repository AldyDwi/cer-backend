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
        Schema::create('option_cards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('item_id')
                ->constrained('cer_items')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->text('content');

            $table->enum('card_type', [
                'claim',
                'evidence',
                'reasoning',
                'distractor_evidence',
                'distractor_reasoning',
            ]);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_cards');
    }
};
