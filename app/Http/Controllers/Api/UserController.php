<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->ignore($user->id),
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],

            'class_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];

        // Kelas hanya berlaku untuk mahasiswa.
        if ($user->role === 'student') {
            // Pertahankan kelas lama jika class_name tidak dikirim.
            if (array_key_exists('class_name', $validated)) {
                $user->class_name = $validated['class_name'];
            }
        } else {
            $user->class_name = null;
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => [
                'required',
                'current_password',
            ],

            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8),
            ],
        ]);

        // Jangan izinkan password baru sama dengan password lama.
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [
                    'Password baru harus berbeda dari password saat ini.',
                ],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'message' => 'Password berhasil diperbarui.',
        ]);
    }
}