<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CerQuiz;
use App\Models\Material;
use App\Models\QuizAttempt;
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
        ]);

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
     * Update aktivitas CER.
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
        ]);

        $material = Material::query()
            ->where('id', $validated['material_id'])
            ->where('teacher_id', $request->user()->id)
            ->first();

        if (!$material) {
            return response()->json([
                'message' => 'Materi tidak dapat digunakan.',
            ], 403);
        }

        $quiz->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'material_id' => $material->id,
            'duration_minutes' => $validated['duration_minutes'],
        ]);

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

        $quiz->delete();

        return response()->json([
            'message' => 'Aktivitas CER berhasil dihapus.',
        ]);
    }

    /**
     * Mengubah status aktivitas CER.
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

        return response()->json([
            'message' => 'Status aktivitas berhasil diperbarui.',
            'data' => $cerQuiz->fresh(),
        ]);
    }

    public function grades(CerQuiz $cerQuiz)
    {
        $attempts = QuizAttempt::query()
            ->where('quiz_id', $cerQuiz->id)
            ->where('status', 'completed')
            ->with('student:id,name,username,email,class_name')
            ->latest('completed_at')
            ->get();

        $data = $attempts->map(function ($attempt) {
            return [
                'id' => $attempt->id,

                'student_id' => $attempt->student_id,

                'name' => $attempt->student->name,

                'username' => $attempt->student->username,

                'class_name' => $attempt->student->class_name,

                'score' => $attempt->score,

                'duration_seconds' => $attempt->duration_seconds,

                'duration_formatted' => gmdate(
                    'H:i:s',
                    $attempt->duration_seconds
                ),

                'status' => $attempt->status,

                'completed_at' => $attempt->completed_at,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }
}