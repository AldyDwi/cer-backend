<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MaterialController extends Controller
{
    /**
     * Menampilkan seluruh materi milik guru yang sedang login.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $query = Material::query()
            ->where('teacher_id', $request->user()->id);

        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $materials = $query
            ->latest()
            ->paginate($perPage);

        return response()->json($materials);
    }

    /**
     * Menambahkan materi baru.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ], [
            'title.required' => 'Judul materi wajib diisi.',
            'title.max' => 'Judul materi maksimal 255 karakter.',
            'content.required' => 'Isi materi wajib diisi.',
        ]);

        $material = Material::create([
            'teacher_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Materi berhasil ditambahkan.',
            'data' => $material,
        ], 201);
    }

    /**
     * Menampilkan detail materi.
     */
    public function show(Request $request, string $id)
    {
        $material = Material::query()
            ->where('teacher_id', $request->user()->id)
            ->find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Materi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => $material,
        ]);
    }

    /**
     * Mengubah materi.
     */
    public function update(Request $request, string $id)
    {
        $material = Material::query()
            ->where('teacher_id', $request->user()->id)
            ->find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Materi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ], [
            'title.required' => 'Judul materi wajib diisi.',
            'title.max' => 'Judul materi maksimal 255 karakter.',
            'content.required' => 'Isi materi wajib diisi.',
        ]);

        $material->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Materi berhasil diperbarui.',
            'data' => $material->fresh(),
        ]);
    }

    /**
     * Menghapus materi.
     */
    public function destroy(Request $request, string $id)
    {
        $material = Material::query()
            ->where('teacher_id', $request->user()->id)
            ->find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Materi tidak ditemukan.',
            ], 404);
        }

        $material->delete();

        return response()->json([
            'message' => 'Materi berhasil dihapus.',
        ]);
    }

    /**
     * Menampilkan opsi materi milik guru yang sedang login.
     */
    public function options(Request $request)
    {
        $materials = Material::query()
            ->where('teacher_id', $request->user()->id)
            ->select([
                'id',
                'title',
            ])
            ->latest('created_at')
            ->get();

        return response()->json([
            'data' => $materials,
        ]);
    }
}