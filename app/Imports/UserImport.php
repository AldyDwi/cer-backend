<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;

class UserImport implements ToModel, WithHeadingRow, WithValidation, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    public function model(array $row): Model|null
    {

        $roleInput = $row['role'] ?? 'student';
        $role = strtolower(trim((string) $roleInput));

        return new User([
            'username'   => trim((string) $row['username']),
            'email'      => trim((string) $row['email']),
            'password'   => Hash::make($row['password'] ?? '12345678'),
            'name'       => trim((string) ($row['nama_lengkap'] ?? $row['nama'] ?? $row['name'])),
            'class_name' => $role === 'teacher' ? null : (isset($row['kelas']) ? trim((string) $row['kelas']) : null),
            'role'       => $role,
        ]);
    }

    public function rules(): array
    {
        return [
            'username'     => 'required|string|unique:users,username',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required',
            'nama_lengkap' => 'required|string|max:255',
            'role'         => 'required|in:teacher,student,Teacher,Student',
            'kelas'        => 'nullable|string|max:50',
        ];
    }

    public function customValidationMessages()
    {
        return [
            'username.required'     => 'Kolom username wajib diisi.',
            'username.unique'       => 'Username sudah terdaftar di sistem.',
            'email.required'        => 'Kolom email wajib diisi.',
            'email.unique'          => 'Email sudah terdaftar di sistem.',
            'nama_lengkap.required' => 'Kolom nama_lengkap wajib diisi.',
            'role.required'         => 'Kolom role wajib diisi (student/teacher).',
            'kelas.required'        => 'Kolom kelas wajib diisi.',
        ];
    }
}