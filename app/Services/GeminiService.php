<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use JsonException;

class GeminiService
{
    public function generateDistractors(
        string $material,
        string $claim,
        string $evidence,
        string $reasoning
    ): array {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (!$apiKey || !$model) {
            throw new RuntimeException(
                'GEMINI_API_KEY atau GEMINI_MODEL belum dikonfigurasi.'
            );
        }

        $prompt = <<<PROMPT
Buat satu distractor Evidence dan satu distractor Reasoning untuk aktivitas rekonstruksi Claim, Evidence, and Reasoning (CER).

MATERI:
{$material}

CLAIM UTAMA:
{$claim}

EVIDENCE UTAMA:
{$evidence}

REASONING UTAMA:
{$reasoning}

ATURAN:
1. Evidence distractor harus berupa fakta benar yang terdapat dalam materi, tetapi menggunakan konsep atau mekanisme berbeda dari evidence utama.
2. Pilih fakta lain yang masih berkaitan dengan topik dan sekilas tampak mendukung claim, tetapi hubungan CER-nya sebenarnya tidak tepat.
3. Reasoning distractor harus menjelaskan evidence tersebut dan membangun hubungan yang tampak masuk akal, tetapi keliru karena salah menghubungkan fungsi atau peran konsep.
4. Jangan mengarang fakta, mengulang evidence utama, atau menyatakan secara eksplisit bahwa distractor salah, tidak relevan, atau tidak mendukung claim.
5. Hindari mengulang kata kunci claim serta menyebutkan konsep inti evidence utama dalam reasoning distractor.
6. Gunakan bahasa Indonesia yang jelas, alami, dan mudah dipahami mahasiswa. Jangan memberi petunjuk bahwa jawaban tersebut distractor.
7. Panjang distractor_evidence maksimal 50 kata.
8. Panjang distractor_reasoning maksimal 70 kata.
9. Pastikan kedua field lengkap dan JSON valid.
10. Jangan menghasilkan teks di luar objek JSON.

KELUARAN:
Kembalikan hanya JSON valid dengan dua field string:
{
  "distractor_evidence": "...",
  "distractor_reasoning": "..."
}
PROMPT;

        $response = Http::timeout(60)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
            ])
            ->post(
                "https://gemini-proxy.ardiansyahaldy4.workers.dev/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.5,
                        'maxOutputTokens' => 2048,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'distractor_evidence' => [
                                    'type' => 'STRING',
                                ],
                                'distractor_reasoning' => [
                                    'type' => 'STRING',
                                ],
                            ],
                            'required' => [
                                'distractor_evidence',
                                'distractor_reasoning',
                            ],
                        ],
                    ],
                ]
            );

        if ($response->failed()) {
            $message = $response->json('error.message')
                ?? $response->body();

            throw new RuntimeException(
                'Gagal menghubungi Gemini API: ' . $message
            );
        }

        $data = $response->json();

        // Periksa apakah Gemini menghasilkan kandidat jawaban.
        $candidate = data_get($data, 'candidates.0');

        if (!$candidate) {
            $blockReason = data_get(
                $data,
                'promptFeedback.blockReason'
            );

            throw new RuntimeException(
                'Gemini tidak menghasilkan kandidat jawaban.'
                . ($blockReason ? ' Alasan: ' . $blockReason : '')
            );
        }

        // Gabungkan seluruh bagian teks, bukan hanya parts.0.text.
        $parts = data_get($candidate, 'content.parts', []);
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);

        if ($text === '') {
            $finishReason = data_get($candidate, 'finishReason');

            throw new RuntimeException(
                'Respons Gemini kosong.'
                . ($finishReason
                    ? ' Status: ' . $finishReason
                    : '')
            );
        }

        // Antisipasi apabila respons dibungkus markdown fence.
        $text = preg_replace(
            '/^\s*```(?:json)?\s*|\s*```\s*$/i',
            '',
            $text
        );

        try {
            $result = json_decode(
                trim($text),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            $finishReason = data_get($candidate, 'finishReason');

            throw new RuntimeException(
                'JSON dari Gemini tidak valid: '
                . $e->getMessage()
                . ($finishReason
                    ? ' Status: ' . $finishReason
                    : '')
            );
        }

        if (
            !is_array($result) ||
            !isset(
                $result['distractor_evidence'],
                $result['distractor_reasoning']
            ) ||
            !is_string($result['distractor_evidence']) ||
            !is_string($result['distractor_reasoning']) ||
            trim($result['distractor_evidence']) === '' ||
            trim($result['distractor_reasoning']) === ''
        ) {
            throw new RuntimeException(
                'JSON Gemini tidak memiliki dua field string '
                . 'yang valid.'
            );
        }

        return [
            'distractor_evidence' => trim(
                $result['distractor_evidence']
            ),
            'distractor_reasoning' => trim(
                $result['distractor_reasoning']
            ),
        ];
    }
}

