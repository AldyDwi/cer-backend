<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CerItem\StoreCerItemRequest;
use App\Http\Requests\CerItem\UpdateCerItemRequest;
use App\Models\CerItem;
use App\Models\CerQuiz;
use App\Services\CerItemService;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CerItemController extends Controller
{
    public function __construct(
        private CerItemService $cerItemService
    ) {
    }

    /**
     * Menampilkan seluruh triplet dari satu aktivitas CER.
     */
    public function index(CerQuiz $cerQuiz): JsonResponse
    {
        $this->authorizeTeacher($cerQuiz);

        $items = $cerQuiz->items()
            ->with('optionCards')
            ->orderBy('page_order')
            ->get();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Menambahkan triplet baru.
     */
    public function store(StoreCerItemRequest $request, CerQuiz $cerQuiz): JsonResponse {
        $this->authorizeTeacher($cerQuiz);
        $this->ensureDraft($cerQuiz);

        $item = $this->cerItemService->create(
            $cerQuiz,
            $request->validated()
        );

        return response()->json([
            'message' => 'Triplet CER berhasil ditambahkan.',
            'data' => $item,
        ], 201);
    }

    /**
     * Mengubah triplet.
     */
    public function update(UpdateCerItemRequest $request, CerItem $cerItem): JsonResponse {
        $this->authorizeTeacher($cerItem->quiz);
        $this->ensureDraft($cerItem->quiz);

        $item = $this->cerItemService->update(
            $cerItem,
            $request->validated()
        );

        return response()->json([
            'message' => 'Triplet CER berhasil diperbarui.',
            'data' => $item,
        ]);
    }

    /**
     * Menghapus triplet.
     */
    public function destroy(CerItem $cerItem): JsonResponse {
        $this->authorizeTeacher($cerItem->quiz);
        $this->ensureDraft($cerItem->quiz);

        $this->cerItemService->delete($cerItem);

        return response()->json([
            'message' => 'Triplet CER berhasil dihapus.',
        ]);
    }

    /**
     * Memastikan teacher hanya dapat mengakses
     * aktivitas miliknya sendiri.
     */
    private function authorizeTeacher(CerQuiz $cerQuiz): void
    {
        $user = Auth::user();

        if (!$user || $user->role !== 'teacher') {
            abort(403, 'Anda tidak memiliki akses ke aktivitas ini.');
        }

        if ($cerQuiz->teacher_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses ke aktivitas ini.');
        }
    }

    /**
     * Menghasilkan distractor untuk triplet CER.
     */
    public function generateDistractors(Request $request, CerQuiz $cerQuiz) {
        $this->authorizeTeacher($cerQuiz);

        $validated = $request->validate([
            'claim' => [
                'required',
                'string',
                'min:1',
            ],

            'evidence' => [
                'required',
                'string',
                'min:1',
            ],

            'reasoning' => [
                'required',
                'string',
                'min:1',
            ],
        ], [
            'claim.required' =>
                'Claim wajib diisi terlebih dahulu.',

            'evidence.required' =>
                'Evidence wajib diisi terlebih dahulu.',

            'reasoning.required' =>
                'Reasoning wajib diisi terlebih dahulu.',
        ]);

        $cerQuiz->load('material');

        $geminiService = app(GeminiService::class);

        try {
            $result = $geminiService->generateDistractors(
                $cerQuiz->material->content,
                $validated['claim'],
                $validated['evidence'],
                $validated['reasoning']
            );

            return response()->json([
                'message' =>
                    'Distractor berhasil dibuat.',

                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' =>
                    'Gagal membuat distractor. Silakan coba lagi.',
            ], 500);
        }
    }

    /**
     * Memastikan triplet CER hanya dapat diubah jika status aktivitas masih draft.
     */
    private function ensureDraft(CerQuiz $cerQuiz): void
    {
        if ($cerQuiz->status !== 'draft') {
            abort(
                403,
                'Triplet CER tidak dapat diubah karena aktivitas sudah dipublikasikan.'
            );
        }
    }
}