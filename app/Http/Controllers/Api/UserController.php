<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

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
                'unique:users,username,' . $user->id,
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],

            'class_name' => [
                'nullable',
                'string',
                'max:100',
            ],

            'password' => [
                'nullable',
                'string',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];

        /*
         * class_name hanya digunakan mahasiswa.
         * Teacher tetap NULL.
         */
        if ($user->role === 'student') {
            $user->class_name =
                $validated['class_name'] ?? null;
        } else {
            $user->class_name = null;
        }

        /*
         * Password hanya diubah jika user mengisinya.
         */
        if (!empty($validated['password'])) {
            $user->password = Hash::make(
                $validated['password']
            );
        }

        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }
}