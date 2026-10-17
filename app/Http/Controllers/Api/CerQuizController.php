<?php

namespace App\Http\Controllers\Api;

use App\Events\CerActivityUpdated;
use App\Http\Controllers\Controller;
use App\Models\CerQuiz;
use App\Models\Material;
use App\Models\QuizAttempt;
use App\Models\StudentActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CerQuizController extends Controller
{
    /**
     * Menampilkan aktivitas CER milik teacher yang sedang login.
     */
    public function index(Request $request)
    {
        $quizzes = CerQuiz::query()
            ->where('teacher_id', $request->user()->id)
            ->with('material:id,title')
            ->latest()
            ->get();

        return response()->json([
            'data' => $quizzes,
        ]);
    }

    /**
     * Membuat aktivitas CER baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'material_id' => [
                'required',
                'integer',
                'exists:materials,id',
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:180',
            ],

            'show_keys' => [
                'nullable',
                'boolean',
            ],
        ], [
            'title.required' => 'Judul aktivitas wajib diisi.',
            'description.required' => 'Deskripsi aktivitas wajib diisi.',
            'material_id.required' => 'Materi wajib dipilih.',
            'material_id.exists' => 'Materi tidak ditemukan.',
            'duration_minutes.required' => 'Durasi pengerjaan wajib diisi.',
            'duration_minutes.min' => 'Durasi minimal 1 menit.',
            'duration_minutes.max' => 'Durasi maksimal 180 menit.',
        ]);

        /*
         * Pastikan materi memang milik teacher
         * yang sedang login.
         */
        $material = Material::query()
            ->where('id', $validated['material_id'])
            ->where('teacher_id', $request->user()->id)
            ->first();

        if (!$material) {
            return response()->json([
                'message' => 'Materi tidak dapat digunakan.',
            ], 403);
        }

        $quiz = CerQuiz::create([
            'teacher_id' => $request->user()->id,
            'material_id' => $material->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'duration_minutes' => $validated['duration_minutes'],
            'status' => 'draft',
            'show_keys' => $validated['show_keys'] ?? true,
        ]);

        // Broadcast Event Reverb: Aktivitas Baru Dibuat
        CerActivityUpdated::dispatch($quiz, 'created');

        return response()->json([
            'message' => 'Aktivitas CER berhasil dibuat.',
            'data' => $quiz->load('material:id,title'),
        ], 201);
    }

    /**
     * Menampilkan detail aktivitas CER.
     */
    public function show(Request $request, string $id)
    {
        $quiz = CerQuiz::query()
            ->where('teacher_id', $request->user()->id)
            ->with('material:id,title')
            ->find($id);

        if (!$quiz) {
            return response()->json([
                'message' => 'Aktivitas CER tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => $quiz,
        ]);
    }

    /**
     * Update aktivitas CER (termasuk toggle show_keys).
     */
    public function update(Request $request, string $id)
    {
        $quiz = CerQuiz::query()
            ->where('teacher_id', $request->user()->id)
            ->find($id);

        if (!$quiz) {
            return response()->json([
                'message' => 'Aktivitas CER tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'required',
                'string',
            ],

            'material_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:materials,id',
            ],

            'duration_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:180',
            ],

            'show_keys' => [
                'sometimes',
                'boolean',
            ],
            'show_correct_answers' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $updateData = [];

        if (array_key_exists('title', $validated)) {
            $updateData['title'] = $validated['title'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (array_key_exists('duration_minutes', $validated)) {
            $updateData['duration_minutes'] = $validated['duration_minutes'];
        }
        if (array_key_exists('show_keys', $validated)) {
            $updateData['show_keys'] = $validated['show_keys'];
        } elseif (array_key_exists('show_correct_answers', $validated)) {
            $updateData['show_keys'] = $validated['show_correct_answers'];
        }

        if (array_key_exists('material_id', $validated)) {
            $material = Material::query()
                ->where('id', $validated['material_id'])
                ->where('teacher_id', $request->user()->id)
                ->first();

            if (!$material) {
                return response()->json([
                    'message' => 'Materi tidak dapat digunakan.',
                ], 403);
            }

            $updateData['material_id'] = $material->id;
        }

        $quiz->update($updateData);

        // Broadcast Event Reverb: Judul / Data Aktivitas Diperbarui
        CerActivityUpdated::dispatch($quiz->fresh(), 'updated');

        return response()->json([
            'message' => 'Aktivitas CER berhasil diperbarui.',
            'data' => $quiz->load('material:id,title'),
        ]);
    }

    /**
     * Menghapus aktivitas CER.
     */
    public function destroy(Request $request, string $id)
    {
        $quiz = CerQuiz::query()
            ->where('teacher_id', $request->user()->id)
            ->find($id);

        if (!$quiz) {
            return response()->json([
                'message' => 'Aktivitas CER tidak ditemukan.',
            ], 404);
        }

        $deletedQuiz = clone $quiz;
        $quiz->delete();

        // Broadcast Event Reverb: Aktivitas Dihapus
        CerActivityUpdated::dispatch($deletedQuiz, 'deleted');

        return response()->json([
            'message' => 'Aktivitas CER berhasil dihapus.',
        ]);
    }

    /**
     * Mengubah status aktivitas CER (Draft / Published).
     */
    public function updateStatus(
        Request $request,
        CerQuiz $cerQuiz
    ) {
        if ($cerQuiz->teacher_id !== $request->user()->id) {
            abort(403, 'Anda tidak memiliki akses ke aktivitas ini.');
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'in:draft,published',
            ],
        ]);

        $cerQuiz->update([
            'status' => $validated['status'],
        ]);

        // Broadcast Event Reverb: Status Berubah
        CerActivityUpdated::dispatch($cerQuiz->fresh(), 'status_changed');

        return response()->json([
            'message' => 'Status aktivitas berhasil diperbarui.',
            'data' => $cerQuiz->fresh(),
        ]);
    }

    /**
     * Menampilkan daftar nilai mahasiswa untuk aktivitas CER.
     */
    public function grades(CerQuiz $cerQuiz)
    {
        $attempts = QuizAttempt::query()
            ->where('quiz_id', $cerQuiz->id)
            ->where('status', 'completed')
            ->whereHas('student', function ($query) {
                $query->where('role', 'student');
            })
            ->with('student:id,name,username,email,class_name')
            ->latest('completed_at')
            ->get()
            ->unique('student_id');

        $data = $attempts->map(function ($attempt) {
            return [
                'id' => $attempt->student_id,
                'student_id' => $attempt->student_id,
                'name' => $attempt->student?->name ?? 'Mahasiswa',
                'username' => $attempt->student?->username ?? '-',
                'class_name' => $attempt->student?->class_name ?? '-',
                'score' => $attempt->score,
                'duration_seconds' => $attempt->duration_seconds,
                'duration_formatted' => $attempt->duration_seconds 
                    ? gmdate('H:i:s', $attempt->duration_seconds) 
                    : null,
                'status' => $attempt->status,
                'completed_at' => $attempt->completed_at,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Menampilkan aktivitas CER yang sudah dipublikasikan untuk mahasiswa.
     */
    public function publishedForStudent(Request $request)
    {
        $student = $request->user();

        abort_unless(
            $student->role === 'student',
            403,
            'Akses hanya untuk mahasiswa.'
        );

        $activities = CerQuiz::query()
            ->with([
                'teacher:id,name',
                'material:id,title',
            ])
            ->where('status', 'published')
            ->latest()
            ->get();

        $quizIds = $activities->pluck('id');

        $attempts = QuizAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('quiz_id', $quizIds)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->get()
            ->unique('quiz_id')
            ->keyBy('quiz_id');

        $data = $activities->map(function ($activity) use ($attempts) {
            $attempt = $attempts->get($activity->id);

            return [
                'id' => $activity->id,
                'title' => $activity->title,
                'description' => $activity->description,
                'duration_minutes' => $activity->duration_minutes,
                'status' => $activity->status,
                'show_keys' => $activity->show_keys ?? true,

                'teacher' => [
                    'id' => $activity->teacher?->id,
                    'name' => $activity->teacher?->name ?? 'Dosen',
                ],

                'material' => [
                    'id' => $activity->material?->id,
                    'title' => $activity->material?->title ?? '-',
                ],

                'completed' => $attempt !== null,
                'score' => $attempt?->score,
                'completed_at' => $attempt?->completed_at,
            ];
        })->values();

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Menampilkan log aktivitas mahasiswa untuk aktivitas CER tertentu.
     */
    public function getActivityLogs(Request $request, CerQuiz $cerQuiz)
    {
        abort_unless($request->user()->role === 'teacher', 403, 'Akses khusus guru/dosen.');
        abort_unless($cerQuiz->teacher_id === $request->user()->id, 403, 'Anda tidak memiliki akses ke kuis ini.');

        $logs = StudentActivityLog::query()
            ->where('quiz_id', $cerQuiz->id)
            ->with(['student' => function ($query) {
                $query->select('id', 'name', 'username', 'class_name');
            }])
            ->latest('updated_at')
            ->get();

        return response()->json([
            'quiz' => [
                'id' => $cerQuiz->id,
                'title' => $cerQuiz->title,
            ],
            'logs' => $logs,
        ]);
    }
}