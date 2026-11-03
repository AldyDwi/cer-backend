<?php

namespace App\Exports;

use App\Models\QuizAttempt;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StudentActivityLogExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents, WithStrictNullComparison
{
    protected int $quizId;

    public function __construct(int $quizId)
    {
        $this->quizId = $quizId;
    }

    // Implementasi method
    public function collection(): Collection
    {
        return QuizAttempt::with(['student', 'activityLog'])
            ->where('quiz_id', $this->quizId)
            ->get();
    }

    // Helper konversi detik ke HH:MM:SS
    private function formatSecondsToTime(int|float|null $totalSeconds): string
    {
        $seconds = max(0, (int) $totalSeconds);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    // Helper format tanggal (DD/MM/YYYY, HH.mm)
    private function formatDateTime(?DateTimeInterface $dateTime): string
    {
        if (!$dateTime) {
            return '-';
        }

        return $dateTime->format('d/m/Y, H.i');
    }

    // Implementasi method headings
    public function headings(): array
    {
        return [
            // Baris 1: Header Utama (18 Kolom)
            [
                'MAHASISWA',      // A
                'USERNAME',       // B
                'KELAS',          // C
                'NILAI',          // D
                'DURASI',         // E
                'WAKTU MULAI',    // F
                'WAKTU SELESAI',  // G
                'PLACE (PASANG)', // H
                '',               // I
                '',               // J
                'DELETE (HAPUS)', // K
                '',               // L
                '',               // M
                'REPLACE (GANTI)',// N
                '',               // O
                '',               // P
                'AKTIVITAS MATERI',// Q
                '',               // R
            ],
            // Baris 2: Sub-Header (18 Kolom)
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'CLAIM',     // H
                'EVIDENCE',  // I
                'REASONING', // J
                'CLAIM',     // K
                'EVIDENCE',  // L
                'REASONING', // M
                'CLAIM',     // N
                'EVIDENCE',  // O
                'REASONING', // P
                'COUNT',     // Q
                'TOTAL TIME',// R
            ],
        ];
    }

    public function map($attempt): array
    {
        $log = $attempt->activityLog;

        $row = [
            $attempt->student->name ?? '-',
            $attempt->student->username ?? '-',
            $attempt->student->class_name ?? '-',
            $attempt->score ?? 0,
            $this->formatSecondsToTime($attempt->duration_seconds ?? 0),
            $this->formatDateTime($attempt->started_at),
            $this->formatDateTime($attempt->completed_at),

            (int) ($log?->place_claim ?? 0),
            (int) ($log?->place_evidence ?? 0),
            (int) ($log?->place_reasoning ?? 0),

            (int) ($log?->delete_claim ?? 0),
            (int) ($log?->delete_evidence ?? 0),
            (int) ($log?->delete_reasoning ?? 0),

            (int) ($log?->replace_claim ?? 0),
            (int) ($log?->replace_evidence ?? 0),
            (int) ($log?->replace_reasoning ?? 0),

            (int) ($log?->read_material_count ?? 0),
            $this->formatSecondsToTime($log?->read_material_time ?? 0),
        ];

        logger()->info('HASIL MAPPING EXPORT', [
            'attempt_id' => $attempt->id,
            'row' => $row,
        ]);

        return $row;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Merge Vertikal Kolom Utama (A-G)
                $sheet->mergeCells('A1:A2');
                $sheet->mergeCells('B1:B2');
                $sheet->mergeCells('C1:C2');
                $sheet->mergeCells('D1:D2');
                $sheet->mergeCells('E1:E2');
                $sheet->mergeCells('F1:F2');
                $sheet->mergeCells('G1:G2');

                // 2. Merge Horizontal Grup Log (H-R)
                $sheet->mergeCells('H1:J1'); // PLACE
                $sheet->mergeCells('K1:M1'); // DELETE
                $sheet->mergeCells('N1:P1'); // REPLACE
                $sheet->mergeCells('Q1:R1'); // MATERI

                // 3. Style & Format Header
                $sheet->getStyle('A1:R2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A1:R2')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A1:R2')->getFont()->setBold(true);

                // 4. Pewarnaan Pastel Header
                $sheet->getStyle('H1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('F3E8FF');
                $sheet->getStyle('K1:M1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE4E6');
                $sheet->getStyle('N1:P1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FEF3C7');
                $sheet->getStyle('Q1:R1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E0F2FE');
            },
        ];
    }
}