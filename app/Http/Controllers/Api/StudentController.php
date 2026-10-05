<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * Menampilkan daftar student.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50'],
        ]);

        $perPage = $validated['per_page'] ?? 10;

        $query = User::query()
            ->where('role', 'student');

        /*
         * Search berdasarkan:
         * - nama
         * - username
         * - email
         */
        if (!empty($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        /*
         * Filter kelas
         */
        if (!empty($validated['class_name'])) {
            $query->where(
                'class_name',
                $validated['class_name']
            );
        }

        $students = $query
            ->select([
                'id',
                'name',
                'username',
                'email',
                'class_name',
                'created_at',
                'updated_at',
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        /*
         * Daftar kelas untuk filter.
         */
        $classes = User::query()
            ->where('role', 'student')
            ->whereNotNull('class_name')
            ->where('class_name', '!=', '')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name');

        return response()->json([
            'data' => $students->items(),
            'current_page' => $students->currentPage(),
            'last_page' => $students->lastPage(),
            'per_page' => $students->perPage(),
            'total' => $students->total(),
            'classes' => $classes,
        ]);
    }

    /**
     * Menambahkan student.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                'unique:users,username',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
            ],

            'class_name' => [
                'required',
                'string',
                'max:100',
            ],
        ], [
            'name.required' => 'Nama wajib diisi.',

            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',

            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',

            'class_name.required' => 'Kelas wajib diisi.',
        ]);

        $student = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
            'class_name' => $validated['class_name'],
        ]);

        return response()->json([
            'message' => 'Student berhasil ditambahkan.',
            'data' => [
                'id' => $student->id,
                'name' => $student->name,
                'username' => $student->username,
                'email' => $student->email,
                'class_name' => $student->class_name,
            ],
        ], 201);
    }

    /**
     * Menampilkan detail student.
     */
    public function show(Request $request, string $id)
    {
        $student = User::query()
            ->where('role', 'student')
            ->find($id);

        if (!$student) {
            return response()->json([
                'message' => 'Student tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $student->id,
                'name' => $student->name,
                'username' => $student->username,
                'email' => $student->email,
                'class_name' => $student->class_name,
            ],
        ]);
    }

    /**
     * Mengubah student.
     */
    public function update(Request $request, string $id)
    {
        $student = User::query()
            ->where('role', 'student')
            ->find($id);

        if (!$student) {
            return response()->json([
                'message' => 'Student tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],

            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')
                    ->ignore($student->id),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($student->id),
            ],

            /*
             * Password tidak wajib ketika update.
             */
            'password' => [
                'nullable',
                'string',
                'min:8',
            ],

            'class_name' => [
                'required',
                'string',
                'max:100',
            ],
        ], [
            'name.required' => 'Nama wajib diisi.',

            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan.',

            'password.min' => 'Password minimal 8 karakter.',

            'class_name.required' => 'Kelas wajib diisi.',
        ]);

        $student->name = $validated['name'];
        $student->username = $validated['username'];
        $student->email = $validated['email'];
        $student->class_name = $validated['class_name'];

        if (!empty($validated['password'])) {
            $student->password = Hash::make(
                $validated['password']
            );
        }

        /*
         * Jangan biarkan role berubah.
         */
        $student->role = 'student';

        $student->save();

        return response()->json([
            'message' => 'Student berhasil diperbarui.',
            'data' => [
                'id' => $student->id,
                'name' => $student->name,
                'username' => $student->username,
                'email' => $student->email,
                'class_name' => $student->class_name,
            ],
        ]);
    }

    /**
     * Menghapus student.
     */
    public function destroy(Request $request, string $id)
    {
        $student = User::query()
            ->where('role', 'student')
            ->find($id);

        if (!$student) {
            return response()->json([
                'message' => 'Student tidak ditemukan.',
            ], 404);
        }

        $student->delete();

        return response()->json([
            'message' => 'Student berhasil dihapus.',
        ]);
    }
}