<?php

namespace App\Http\Requests\CerItem;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCerItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'claim' => [
                'required',
                'string',
                'max:5000',
            ],

            'evidence' => [
                'required',
                'string',
                'max:5000',
            ],

            'reasoning' => [
                'required',
                'string',
                'max:5000',
            ],

            'distractor_evidence' => [
                'required',
                'string',
                'max:5000',
            ],

            'distractor_reasoning' => [
                'required',
                'string',
                'max:5000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'Semua komponen CER wajib diisi.',
            '*.string' => 'Data harus berupa teks.',
            '*.max' => 'Isi terlalu panjang.',
        ];
    }
}