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
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->index(
                ['quiz_id', 'student_id', 'status'],
                'quiz_attempts_lookup_idx'
            );

            $table->index(
                ['status', 'deadline_at'],
                'quiz_attempts_deadline_idx'
            );
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->unique(
                ['attempt_id', 'item_id'],
                'student_answers_attempt_item_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropIndex('quiz_attempts_lookup_idx');
            $table->dropIndex('quiz_attempts_deadline_idx');
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropUnique('student_answers_attempt_item_unique');
        });
    }
};
