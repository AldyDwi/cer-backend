<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CerQuiz;
use App\Models\QuizAttempt;
use App\Models\StudentAnswer;
use App\Models\StudentActivityLog;
use App\Services\CerAttemptFinalizer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentCerAttemptController extends Controller
{
    public function __construct(
        private readonly CerAttemptFinalizer $finalizer
    ) {}

    /**
     * Memulai atau melanjutkan aktivitas CER.
     */
    public function start(Request $request, CerQuiz $cerQuiz)
    {
        abort_unless(
            $request->user()->role === 'student',
            403,
            'Hanya mahasiswa yang dapat mengerjakan aktivitas.'
        );

        $result = DB::transaction(function () use ($request, $cerQuiz) {
            $quiz = CerQuiz::query()
                ->whereKey($cerQuiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $quiz->status === 'published',
                404,
                'Aktivitas tidak tersedia.'
            );

            $attempt = $this->findAttempt(
                $quiz->id,
                $request->user()->id
            );

            $now = $this->now();

            if ($attempt && $attempt->status === 'in_progress') {
                if (! $attempt->started_at) {
                    $attempt->started_at = $now;
                }

                if (! $attempt->deadline_at) {
                    $startedAt = Carbon::parse(
                        $attempt->started_at,
                        $this->timezone()
                    );

                    $attempt->deadline_at = $startedAt->copy()->addMinutes(
                        (int) $quiz->duration_minutes
                    );
                }

                $attempt->save();

                if (
                    $attempt->deadline_at &&
                    $now->greaterThanOrEqualTo(
                        Carbon::parse(
                            $attempt->deadline_at,
                            $this->timezone()
                        )
                    )
                ) {
                    $attempt = $this->finalizeExpired(
                        $attempt,
                        $quiz
                    );
                }
            }

            if (! $attempt) {
                $attempt = QuizAttempt::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('student_id', $request->user()->id)
                    ->where('status', 'completed')
                    ->latest('id')
                    ->first();

                if (! $attempt) {
                    $startedAt = $now->copy();

                    $attempt = QuizAttempt::create([
                        'quiz_id' => $quiz->id,
                        'student_id' => $request->user()->id,
                        'score' => 0,
                        'duration_seconds' => 0,
                        'status' => 'in_progress',
                        'started_at' => $startedAt,
                        'deadline_at' => $startedAt->copy()->addMinutes(
                            (int) $quiz->duration_minutes
                        ),
                        'completed_at' => null,
                    ]);
                }
            }

            return [
                'quiz' => $quiz,
                'attempt' => $attempt,
            ];
        }, 3);

        return response()->json(
            $this->attemptPayload(
                $result['quiz'],
                $result['attempt']
            )
        );
    }

    /**
     * Autosave sekumpulan jawaban beserta log aktivitas.
     */
    public function saveAnswers(
        Request $request,
        CerQuiz $cerQuiz
    ) {
        abort_unless(
            $request->user()->role === 'student',
            403
        );

        $result = DB::transaction(function () use (
            $request,
            $cerQuiz
        ) {
            $quiz = CerQuiz::query()
                ->whereKey($cerQuiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = $this->findAttempt(
                $quiz->id,
                $request->user()->id
            );

            abort_unless($attempt, 404, 'Attempt tidak ditemukan.');

            if ($attempt->status === 'completed') {
                return [
                    'kind' => 'completed',
                    'attempt' => $attempt,
                ];
            }

            if ($this->isExpired($attempt)) {
                $attempt = $this->finalizeExpired($attempt, $quiz);

                return [
                    'kind' => 'expired',
                    'attempt' => $attempt,
                ];
            }

            $validated = $this->validateAnswers($request, $quiz);

            $this->persistAnswers(
                $attempt,
                $validated['answers']
            );

            // Simpan atau update log aktivitas saat autosave
            $logsData = $request->input('logs', []);
            if (! empty($logsData)) {
                $this->persistLogs($attempt, $request->user()->id, $quiz->id, $logsData);
            }

            return [
                'kind' => 'saved',
                'attempt' => $attempt,
            ];
        }, 3);

        if ($result['kind'] === 'expired' || $result['kind'] === 'completed') {
            return response()->json([
                'message' => $result['kind'] === 'expired' 
                    ? 'Waktu pengerjaan telah habis.' 
                    : 'Attempt telah selesai dan tidak dapat diubah.',
                'status' => 'completed',
                'attempt' => $result['attempt'],
            ], 409);
        }

        return response()->json([
            'message' => 'Jawaban berhasil disimpan.',
            'status' => 'in_progress',
        ]);
    }

    /**
     * Submit akhir pengerjaan.
     */
    public function submit(Request $request, CerQuiz $cerQuiz)
    {
        abort_unless(
            $request->user()->role === 'student',
            403
        );

        $logsData = $request->input('logs', []);

        $result = DB::transaction(function () use (
            $request,
            $cerQuiz,
            $logsData
        ) {
            $quiz = CerQuiz::query()
                ->whereKey($cerQuiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = $this->findAttempt(
                $quiz->id,
                $request->user()->id
            );

            if (! $attempt) {
                $completedAttempt = QuizAttempt::query()
                    ->where('quiz_id', $quiz->id)
                    ->where('student_id', $request->user()->id)
                    ->where('status', 'completed')
                    ->latest('id')
                    ->first();

                if ($completedAttempt) {
                    return $completedAttempt;
                }

                abort(404, 'Attempt tidak ditemukan.');
            }

            if ($attempt->status === 'completed') {
                return $attempt;
            }

            if ($this->isExpired($attempt)) {
                return $this->finalizeExpired($attempt, $quiz);
            }

            $validated = $this->validateAnswers($request, $quiz);

            $this->persistAnswers(
                $attempt,
                $validated['answers']
            );

            if (! empty($logsData)) {
                $this->persistLogs($attempt, $request->user()->id, $quiz->id, $logsData);
            }

            $attempt->refresh();

            return $this->finalizer->finalizeLocked(
                $attempt,
                $quiz,
                $this->now()
            );
        }, 3);

        \App\Events\QuizAttemptSubmitted::dispatch($result);

        return response()->json([
            'message' => 'Aktivitas berhasil difinalisasi.',
            'status' => $result->status,
            'attempt' => [
                'id' => $result->id,
                'score' => $result->score,
                'duration_seconds' => $result->duration_seconds,
                'completed_at' => $result->completed_at,
            ],
            'redirect_to' => "/student/cer/{$cerQuiz->id}/review",
        ]);
    }

    /**
     * Melihat hasil review setelah aktivitas selesai.
     */
    public function review(Request $request, CerQuiz $cerQuiz)
    {
        $user = $request->user();

        abort_unless(
            in_array($user->role, ['student', 'teacher']),
            403,
            'Anda tidak memiliki akses untuk melihat review pengerjaan ini.'
        );

        $studentId = $user->id;

        if ($user->role === 'teacher') {
            $request->validate([
                'student_id' => ['required', 'integer', 'exists:users,id'],
            ]);

            abort_unless(
                $cerQuiz->teacher_id === $user->id,
                403,
                'Anda tidak memiliki akses ke kuis ini.'
            );

            $studentId = (int) $request->query('student_id');
        }

        $attempt = QuizAttempt::query()
            ->where('quiz_id', $cerQuiz->id)
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->latest('id')
            ->firstOrFail();

        $quiz = CerQuiz::query()
            ->with([
                'material:id,title',
                'items:id,quiz_id,page_order',
                'items.optionCards:id,item_id,content,card_type',
            ])
            ->findOrFail($cerQuiz->id);

        $answers = StudentAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('item_id');
        
        $items = $quiz->items()->with([
            'optionCards:id,item_id,content,card_type',
        ])->orderBy('page_order')->get();

        $items = $items->map(function ($item) use ($answers) {
            $answer = $answers->get($item->id);

            return [
                'id' => $item->id,
                'page_order' => $item->page_order,
                'cards' => $item->optionCards->map(function ($card) {
                    return [
                        'id' => $card->id,
                        'content' => $card->content,
                        'card_type' => $card->card_type,
                    ];
                })->values(),

                'answer' => $answer ? [
                    'claim_card_id' => $answer->claim_card_id,
                    'evidence_card_id' => $answer->evidence_card_id,
                    'reasoning_card_id' => $answer->reasoning_card_id,
                    'is_claim_correct' => (bool) $answer->is_claim_correct,
                    'is_evidence_correct' => (bool) $answer->is_evidence_correct,
                    'is_reasoning_correct' => (bool) $answer->is_reasoning_correct,
                ] : null,
            ];
        })->values();

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'show_keys' => $user->role === 'teacher' ? true : (bool) $quiz->show_keys,
                'material' => $quiz->material,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'duration_seconds' => $attempt->duration_seconds,
                'started_at' => $attempt->started_at,
                'deadline_at' => $attempt->deadline_at,
                'completed_at' => $attempt->completed_at,
            ],
            'items' => $items,
        ]);
    }

    private function persistLogs(QuizAttempt $attempt, int $studentId, int $quizId, array $logsData): void
    {
        if (empty($logsData)) {
            return;
        }

        \App\Models\StudentActivityLog::updateOrCreate(
            [
                'attempt_id' => $attempt->id,
            ],
            [
                'student_id' => $studentId,
                'quiz_id' => $quizId,
                'place_claim' => (int) ($logsData['place_claim'] ?? 0),
                'place_evidence' => (int) ($logsData['place_evidence'] ?? 0),
                'place_reasoning' => (int) ($logsData['place_reasoning'] ?? 0),
                'delete_claim' => (int) ($logsData['delete_claim'] ?? 0),
                'delete_evidence' => (int) ($logsData['delete_evidence'] ?? 0),
                'delete_reasoning' => (int) ($logsData['delete_reasoning'] ?? 0),
                'replace_claim' => (int) ($logsData['replace_claim'] ?? 0),
                'replace_evidence' => (int) ($logsData['replace_evidence'] ?? 0),
                'replace_reasoning' => (int) ($logsData['replace_reasoning'] ?? 0),
                'read_material_count' => (int) ($logsData['read_material_count'] ?? 0),
                'read_material_time' => (int) ($logsData['read_material_time'] ?? 0),
            ]
        );
    }

    private function findAttempt(int $quizId, int $studentId): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->lockForUpdate()
            ->first();
    }

    private function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    private function timezone(): string
    {
        return config('app.timezone', 'Asia/Jakarta');
    }

    private function isExpired(QuizAttempt $attempt): bool
    {
        if (! $attempt->deadline_at) {
            return false;
        }

        return $this->now()->greaterThanOrEqualTo(
            Carbon::parse($attempt->deadline_at, $this->timezone())
        );
    }

    private function finalizeExpired(QuizAttempt $attempt, CerQuiz $quiz): QuizAttempt
    {
        if ($attempt->status === 'completed') {
            return $attempt;
        }

        $deadline = Carbon::parse($attempt->deadline_at, $this->timezone());

        return $this->finalizer->finalizeLocked($attempt, $quiz, $deadline);
    }

    private function validateAnswers(Request $request, CerQuiz $quiz): array
    {
        $validated = Validator::make(
            $request->all(),
            [
                'answers' => ['required', 'array'],
                'answers.*.item_id' => ['required', 'integer', 'distinct'],
                'answers.*.claim_card_id' => ['nullable', 'integer'],
                'answers.*.evidence_card_id' => ['nullable', 'integer'],
                'answers.*.reasoning_card_id' => ['nullable', 'integer'],
            ]
        )->validate();

        $answers = collect($validated['answers']);
        $itemIds = $answers->pluck('item_id')->unique()->values();

        $validItemIds = $quiz->items()
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validItemIds) !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'answers' => 'Terdapat item yang bukan bagian dari aktivitas ini.',
            ]);
        }

        $cardIds = $answers
            ->flatMap(fn ($answer) => [
                $answer['claim_card_id'] ?? null,
                $answer['evidence_card_id'] ?? null,
                $answer['reasoning_card_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $cards = DB::table('option_cards')
            ->whereIn('id', $cardIds)
            ->get(['id', 'item_id'])
            ->keyBy('id');

        foreach ($answers as $answer) {
            foreach (['claim_card_id', 'evidence_card_id', 'reasoning_card_id'] as $field) {
                $cardId = $answer[$field] ?? null;

                if ($cardId === null) {
                    continue;
                }

                $card = $cards->get($cardId);

                if (! $card || (int) $card->item_id !== (int) $answer['item_id']) {
                    throw ValidationException::withMessages([
                        'answers' => 'Kartu tidak sesuai dengan item CER.',
                    ]);
                }
            }
        }

        return ['answers' => $answers->all()];
    }

    private function persistAnswers(QuizAttempt $attempt, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        $now = $this->now();

        $rows = collect($answers)->map(fn ($answer) => [
            'attempt_id' => $attempt->id,
            'item_id' => $answer['item_id'],
            'claim_card_id' => $answer['claim_card_id'] ?? null,
            'evidence_card_id' => $answer['evidence_card_id'] ?? null,
            'reasoning_card_id' => $answer['reasoning_card_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('student_answers')->upsert(
            $rows,
            ['attempt_id', 'item_id'],
            [
                'claim_card_id',
                'evidence_card_id',
                'reasoning_card_id',
                'updated_at',
            ]
        );
    }

    private function attemptPayload(CerQuiz $quiz, QuizAttempt $attempt): array
    {
        $quiz->load([
            'material:id,title,content',
            'teacher:id,name',
            'items:id,quiz_id,page_order',
            'items.optionCards:id,item_id,content',
        ]);

        $answers = StudentAnswer::query()
            ->where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('item_id');

        return [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'duration_minutes' => $quiz->duration_minutes,
                'material' => $quiz->material,
                'teacher' => $quiz->teacher,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'score' => $attempt->score,
                'started_at' => $attempt->started_at,
                'deadline_at' => $attempt->deadline_at,
                'completed_at' => $attempt->completed_at,
                'duration_seconds' => $attempt->duration_seconds,
            ],
            'items' => $quiz->items->map(function ($item) use ($answers) {
                $answer = $answers->get($item->id);

                return [
                    'id' => $item->id,
                    'page_order' => $item->page_order,
                    'cards' => $item->optionCards->map(fn ($card) => [
                        'id' => $card->id,
                        'content' => $card->content,
                    ])->values(),
                    'answer' => $answer ? [
                        'claim_card_id' => $answer->claim_card_id,
                        'evidence_card_id' => $answer->evidence_card_id,
                        'reasoning_card_id' => $answer->reasoning_card_id,
                    ] : null,
                ];
            })->values(),
        ];
    }
}