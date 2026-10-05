<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Material;
use App\Models\CerQuiz;
use App\Models\CerItem;
use App\Models\OptionCard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. TEACHER
        |--------------------------------------------------------------------------
        */

        $teacher = User::create([
            'name' => 'Guru Demo',
            'username' => 'guru',
            'email' => 'guru@gmail.com',
            'password' => Hash::make('guru123'),
            'role' => 'teacher',
            'class_name' => null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. STUDENTS
        |--------------------------------------------------------------------------
        */

        $students = [
            [
                'name' => 'Budi Santoso',
                'username' => 'budi',
                'email' => 'budi@gmail.com',
                'class_name' => 'TI-4A',
            ],
            [
                'name' => 'Siti Aminah',
                'username' => 'siti',
                'email' => 'siti@gmail.com',
                'class_name' => 'TI-4A',
            ],
            [
                'name' => 'Andi Pratama',
                'username' => 'andi',
                'email' => 'andi@gmail.com',
                'class_name' => 'TI-4B',
            ],
        ];

        foreach ($students as $student) {
            User::create([
                'name' => $student['name'],
                'username' => $student['username'],
                'email' => $student['email'],
                'password' => Hash::make('siswa123'),
                'role' => 'student',
                'class_name' => $student['class_name'],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 3. MATERIAL
        |--------------------------------------------------------------------------
        */

        $material = Material::create([
            'teacher_id' => $teacher->id,
            'title' => 'Deforestasi dan Dampaknya terhadap Lingkungan',
            'content' => <<<TEXT
Deforestasi mengurangi populasi burung. Jumlah spesies burung di area hutan yang ditebang turun 40% dalam waktu satu tahun. Burung kehilangan habitat untuk bersarang dan sumber makanan utamanya akibat pohon-pohon yang ditebang.

Suhu udara lokal meningkat akibat penebangan hutan. Data termometer menunjukkan kenaikan rata-rata 2°C di area yang baru saja ditebang. Pohon menyerap panas dan memberikan keteduhan. Tanpa kanopi pohon, sinar matahari langsung memanaskan tanah.

Kualitas air sungai di sekitar hutan memburuk. Tingkat kekeruhan air sungai meningkat 3x lipat terutama saat hujan lebat. Akar pohon berfungsi menahan tanah. Tanpa pohon, tanah mudah tergerus erosi dan masuk ke aliran sungai.
TEXT,
        ]);

        /*
        |--------------------------------------------------------------------------
        | 4. CER QUIZ / AKTIVITAS
        |--------------------------------------------------------------------------
        */

        $quiz = CerQuiz::create([
            'teacher_id' => $teacher->id,
            'material_id' => $material->id,
            'title' => 'Dampak Deforestasi terhadap Lingkungan',
            'description' => 'Rekonstruksi hubungan Claim, Evidence, dan Reasoning berdasarkan materi tentang dampak deforestasi.',
            'duration_minutes' => 15,
            'status' => 'published',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 5. CER ITEMS
        |--------------------------------------------------------------------------
        */

        /*
        |--------------------------------------------------------------------------
        | TRIPLET 1
        | Deforestasi → Populasi burung
        |--------------------------------------------------------------------------
        */

        $item1 = CerItem::create([
            'quiz_id' => $quiz->id,
            'page_order' => 1,
        ]);

        $this->createCards($item1, [
            [
                'content' => 'Deforestasi mengurangi populasi burung.',
                'card_type' => 'claim',
            ],
            [
                'content' => 'Jumlah spesies burung di area hutan yang ditebang turun 40% dalam waktu satu tahun.',
                'card_type' => 'evidence',
            ],
            [
                'content' => 'Burung kehilangan habitat untuk bersarang dan sumber makanan utamanya akibat pohon-pohon yang ditebang.',
                'card_type' => 'reasoning',
            ],
            [
                'content' => 'Jumlah spesies burung di area hutan yang ditebang meningkat 40% dalam waktu satu tahun.',
                'card_type' => 'distractor_evidence',
            ],
            [
                'content' => 'Pohon yang ditebang membuat burung memiliki lebih banyak tempat untuk bersarang dan mendapatkan makanan.',
                'card_type' => 'distractor_reasoning',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | TRIPLET 2
        | Deforestasi → Suhu udara
        |--------------------------------------------------------------------------
        */

        $item2 = CerItem::create([
            'quiz_id' => $quiz->id,
            'page_order' => 2,
        ]);

        $this->createCards($item2, [
            [
                'content' => 'Penebangan hutan menyebabkan suhu udara lokal meningkat.',
                'card_type' => 'claim',
            ],
            [
                'content' => 'Data termometer menunjukkan kenaikan rata-rata 2°C di area yang baru saja ditebang.',
                'card_type' => 'evidence',
            ],
            [
                'content' => 'Tanpa kanopi pohon, sinar matahari langsung memanaskan tanah karena pohon yang sebelumnya menyerap panas dan memberikan keteduhan telah ditebang.',
                'card_type' => 'reasoning',
            ],
            [
                'content' => 'Data termometer menunjukkan suhu udara lokal menurun rata-rata 2°C di area yang baru saja ditebang.',
                'card_type' => 'distractor_evidence',
            ],
            [
                'content' => 'Penebangan pohon membuat area menjadi lebih teduh sehingga sinar matahari yang mencapai tanah berkurang.',
                'card_type' => 'distractor_reasoning',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | TRIPLET 3
        | Deforestasi → Kualitas air
        |--------------------------------------------------------------------------
        */

        $item3 = CerItem::create([
            'quiz_id' => $quiz->id,
            'page_order' => 3,
        ]);

        $this->createCards($item3, [
            [
                'content' => 'Penebangan hutan menyebabkan kualitas air sungai di sekitar hutan memburuk.',
                'card_type' => 'claim',
            ],
            [
                'content' => 'Tingkat kekeruhan air sungai meningkat 3x lipat terutama saat hujan lebat.',
                'card_type' => 'evidence',
            ],
            [
                'content' => 'Tanpa pohon, tanah lebih mudah tergerus oleh erosi dan masuk ke aliran sungai karena akar pohon yang sebelumnya menahan tanah telah hilang.',
                'card_type' => 'reasoning',
            ],
            [
                'content' => 'Tingkat kekeruhan air sungai menurun 3x lipat terutama saat hujan lebat.',
                'card_type' => 'distractor_evidence',
            ],
            [
                'content' => 'Akar pohon yang hilang membuat tanah semakin kuat menahan erosi sehingga lebih sedikit tanah yang masuk ke sungai.',
                'card_type' => 'distractor_reasoning',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | SELESAI
        |--------------------------------------------------------------------------
        */

        $this->command->info('REKA-CER dummy data berhasil dibuat.');
        $this->command->info('Teacher: guru / password');
        $this->command->info('Student: budi / password');
        $this->command->info('Student: siti / password');
        $this->command->info('Student: andi / password');
    }

    /**
     * Create the five cards for a CER item.
     */
    private function createCards(
        CerItem $item,
        array $cards
    ): void {
        foreach ($cards as $card) {
            OptionCard::create([
                'item_id' => $item->id,
                'content' => $card['content'],
                'card_type' => $card['card_type'],
            ]);
        }
    }
}
