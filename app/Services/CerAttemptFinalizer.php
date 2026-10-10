<?php

namespace App\Services;

use App\Models\CerQuiz;
use App\Models\QuizAttempt;
use App\Models\StudentAnswer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CerAttemptFinalizer
{
    public function finalizeLocked(
        QuizAttempt $attempt,
        CerQuiz $quiz,
        ?Carbon $finishedAt = null
    ): QuizAttempt {
        // Pemanggil harus sudah berada di dalam transaksi dan
        // mengunci attempt menggunakan lockForUpdate().
        if ($attempt->status === 'completed') {
            return $attempt;
        }

        $timezone = config('app.timezone', 'Asia/Jakarta');

        $startedAt = $attempt->started_at
            ? Carbon::parse($attempt->started_at, $timezone)
            : Carbon::now($timezone);

        $deadlineAt = $attempt->deadline_at
            ? Carbon::parse($attempt->deadline_at, $timezone)
            : null;

        $now = Carbon::now($timezone);

        // Jika waktu selesai tidak ditentukan, gunakan waktu saat ini.
        $finishedAt ??= $now;

        // Jika sudah melewati deadline, waktu selesai adalah deadline.
        if ($deadlineAt && $finishedAt->greaterThan($deadlineAt)) {
            $finishedAt = $deadlineAt->copy();
        }

        $items = $quiz->items()
            ->select(['id', 'quiz_id', 'page_order'])
            ->orderBy('page_order')
            ->get();

        $itemIds = $items->pluck('id')->all();

        $answers = StudentAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->whereIn('item_id', $itemIds)
            ->get()
            ->keyBy('item_id');

        // Pastikan setiap item mempunyai satu baris jawaban,
        // termasuk jika mahasiswa tidak mengisi item tersebut.
        $missingRows = [];

        foreach ($items as $item) {
            if (! $answers->has($item->id)) {
                $missingRows[] = [
                    'attempt_id' => $attempt->id,
                    'item_id' => $item->id,
                    'claim_card_id' => null,
                    'evidence_card_id' => null,
                    'reasoning_card_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($missingRows !== []) {
            DB::table('student_answers')->insertOrIgnore($missingRows);

            $answers = StudentAnswer::query()
                ->where('attempt_id', $attempt->id)
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id');
        }

        // Ambil seluruh kartu yang dipilih dalam satu query.
        $selectedCardIds = $answers
            ->flatMap(fn ($answer) => [
                $answer->claim_card_id,
                $answer->evidence_card_id,
                $answer->reasoning_card_id,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $cardsById = DB::table('option_cards')
            ->whereIn('id', $selectedCardIds)
            ->get(['id', 'item_id', 'card_type'])
            ->keyBy('id');

        $answerUpdates = [];
        $correctComponents = 0;
        $totalComponents = $items->count() * 3;

        foreach ($items as $item) {
            $answer = $answers->get($item->id);

            $claimCard = $answer->claim_card_id
                ? $cardsById->get($answer->claim_card_id)
                : null;

            $evidenceCard = $answer->evidence_card_id
                ? $cardsById->get($answer->evidence_card_id)
                : null;

            $reasoningCard = $answer->reasoning_card_id
                ? $cardsById->get($answer->reasoning_card_id)
                : null;

            // Cocokkan juga item_id untuk memastikan kartu berasal
            // dari item yang sedang dinilai.
            $claimCorrect = $claimCard
                && (int) $claimCard->item_id === (int) $item->id
                && $claimCard->card_type === 'claim';

            $evidenceCorrect = $evidenceCard
                && (int) $evidenceCard->item_id === (int) $item->id
                && $evidenceCard->card_type === 'evidence';

            $reasoningCorrect = $reasoningCard
                && (int) $reasoningCard->item_id === (int) $item->id
                && $reasoningCard->card_type === 'reasoning';

            $correctComponents += (int) (bool) $claimCorrect;
            $correctComponents += (int) (bool) $evidenceCorrect;
            $correctComponents += (int) (bool) $reasoningCorrect;

            $answerUpdates[] = [
                'attempt_id' => $attempt->id,
                'item_id' => $item->id,
                'claim_card_id' => $answer->claim_card_id,
                'evidence_card_id' => $answer->evidence_card_id,
                'reasoning_card_id' => $answer->reasoning_card_id,
                'is_claim_correct' => (bool) $claimCorrect,
                'is_evidence_correct' => (bool) $evidenceCorrect,
                'is_reasoning_correct' => (bool) $reasoningCorrect,
                'created_at' => $answer->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        if ($answerUpdates !== []) {
            DB::table('student_answers')->upsert(
                $answerUpdates,
                ['attempt_id', 'item_id'],
                [
                    'claim_card_id',
                    'evidence_card_id',
                    'reasoning_card_id',
                    'is_claim_correct',
                    'is_evidence_correct',
                    'is_reasoning_correct',
                    'updated_at',
                ]
            );
        }

        $score = $totalComponents > 0
            ? round(($correctComponents / $totalComponents) * 100, 2)
            : 0;

        $durationSeconds = max(
            0,
            (int) $startedAt->diffInSeconds($finishedAt, false)
        );

        $attempt->score = $score;
        $attempt->duration_seconds = $durationSeconds;
        $attempt->status = 'completed';
        $attempt->completed_at = $finishedAt;
        $attempt->save();

        return $attempt->refresh();
    }
}
