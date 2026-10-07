<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

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

        if (!$apiKey) {
            throw new RuntimeException(
                'GEMINI_API_KEY belum dikonfigurasi.'
            );
        }

        if (!$model) {
            throw new RuntimeException(
                'GEMINI_MODEL belum dikonfigurasi.'
            );
        }

        $prompt = <<<PROMPT
Anda adalah asisten pembelajaran untuk membuat distractor pada aktivitas rekonstruksi Claim, Evidence, and Reasoning (CER).

Tugas Anda adalah membuat dua distractor berdasarkan materi dan CER yang diberikan.

MATERI:
{$material}

CLAIM:
{$claim}

EVIDENCE:
{$evidence}

REASONING:
{$reasoning}

Buat dua komponen distractor berikut.

1. Distractor Evidence

- Ambil fakta yang benar-benar terdapat dalam materi.
- Evidence harus benar secara faktual.
- Evidence harus berasal dari konsep atau bagian materi yang berbeda
  dari evidence utama.
- Evidence tidak boleh menggunakan konsep inti yang sama dengan
  evidence utama jika konsep tersebut merupakan dasar langsung
  dari claim.
- Hindari menggunakan kembali objek, mekanisme, atau istilah utama
  yang menjadi dasar evidence utama.
- Pilih konsep lain dalam materi yang masih berhubungan dengan topik
  tetapi memiliki fungsi atau peran berbeda.
- Evidence harus tetap memiliki kemungkinan terlihat relevan
  terhadap claim bagi mahasiswa yang kurang teliti.
- Utamakan fakta konkret yang dapat berdiri sendiri sebagai evidence.
- Jangan membuat fakta baru yang tidak terdapat dalam materi.
- Jangan memberikan petunjuk bahwa evidence tersebut merupakan
  distractor.

2. Distractor Reasoning

- Buat reasoning yang terlihat ilmiah, logis, dan meyakinkan.
- Reasoning harus menjelaskan distractor evidence yang diberikan.
- Reasoning harus membentuk hubungan antara distractor evidence
  dan claim yang tampak masuk akal, tetapi sebenarnya tidak tepat.
- Kesalahan harus muncul karena salah menghubungkan fungsi atau
  peran konsep, bukan karena fakta dalam evidence salah.
- Jangan menyatakan secara eksplisit bahwa distractor evidence
  salah atau tidak tepat.
- Jangan menggunakan frasa:
  "tidak mendukung",
  "tidak menjelaskan",
  "tidak relevan",
  "tidak berkaitan",
  "tanpa bergantung pada",
  "tanpa menggunakan",
  atau frasa lain yang secara langsung menunjukkan bahwa
  distractor bukan jawaban yang benar.
- Jangan mengulang kata atau frasa utama dari claim.
- Jangan menggunakan sinonim yang terlalu dekat dengan kata kunci
  claim.
- Jangan menyebutkan kembali konsep yang menjadi inti evidence utama.
- Jangan menyebutkan "aturan berbasis teks", "aturan berbasis teks
  statis", "klausul teks", atau istilah yang memiliki makna sama
  dengan konsep tersebut.
- Distractor Reasoning harus tetap menjelaskan distractor evidence
  dan tidak boleh mengubahnya menjadi evidence utama.

ATURAN PEMILIHAN DISTRACTOR:
Pakai bahasa Indonesia yang jelas dan mudah dipahami.

Identifikasi terlebih dahulu konsep utama yang digunakan oleh
evidence utama untuk mendukung claim.

Kemudian pilih konsep lain dari materi yang:
1. benar secara faktual,
2. berbeda dari konsep utama evidence,
3. masih berhubungan dengan topik,
4. dapat terlihat relevan dengan claim,
5. tetapi tidak memiliki hubungan CER yang tepat dengan claim.

Jangan memilih distractor yang menggunakan konsep inti yang sama
dengan evidence utama.
PROMPT;

        $response = Http::timeout(60)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],

                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 500,

                        // Paksa Gemini menghasilkan JSON.
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
            $errorBody = $response->json();

            throw new RuntimeException(
                'Gagal menghubungi Gemini API: ' .
                ($errorBody['error']['message'] ?? $response->body())
            );
        }

        $responseData = $response->json();

        $text = data_get(
            $responseData,
            'candidates.0.content.parts.0.text'
        );

        if (!$text) {
            throw new RuntimeException(
                'Gemini tidak menghasilkan jawaban.'
            );
        }

        $text = trim($text);

        $result = json_decode($text, true);

        if (
            !is_array($result) ||
            empty($result['distractor_evidence']) ||
            empty($result['distractor_reasoning'])
        ) {
            throw new RuntimeException(
                'Format hasil Gemini tidak sesuai.'
            );
        }

        return [
            'distractor_evidence' =>
                trim($result['distractor_evidence']),

            'distractor_reasoning' =>
                trim($result['distractor_reasoning']),
        ];
    }
}