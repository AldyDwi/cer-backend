<?php

namespace App\Services;

use App\Models\CerItem;
use App\Models\CerQuiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CerItemService
{
    public function create(
        CerQuiz $quiz,
        array $data
    ): CerItem {
        return DB::transaction(function () use ($quiz, $data) {
            $nextOrder = ((int) $quiz->items()->max('page_order')) + 1;

            $item = $quiz->items()->create([
                'page_order' => $nextOrder,
            ]);

            $cards = [
                [
                    'card_type' => 'claim',
                    'content' => $data['claim'],
                ],
                [
                    'card_type' => 'evidence',
                    'content' => $data['evidence'],
                ],
                [
                    'card_type' => 'reasoning',
                    'content' => $data['reasoning'],
                ],
                [
                    'card_type' => 'distractor_evidence',
                    'content' => $data['distractor_evidence'],
                ],
                [
                    'card_type' => 'distractor_reasoning',
                    'content' => $data['distractor_reasoning'],
                ],
            ];

            $item->optionCards()->createMany($cards);

            return $item->load('optionCards');
        });
    }

    public function update(
        CerItem $item,
        array $data
    ): CerItem {
        return DB::transaction(function () use ($item, $data) {
            $cardContents = [
                'claim' => $data['claim'],
                'evidence' => $data['evidence'],
                'reasoning' => $data['reasoning'],
                'distractor_evidence' => $data['distractor_evidence'],
                'distractor_reasoning' => $data['distractor_reasoning'],
            ];

            foreach ($cardContents as $cardType => $content) {
                $item->optionCards()
                    ->where('card_type', $cardType)
                    ->update([
                        'content' => $content,
                    ]);
            }

            return $item->load('optionCards');
        });
    }

    public function delete(CerItem $item): void
    {
        DB::transaction(function () use ($item) {
            $item->optionCards()->delete();
            $item->delete();
        });
    }

    public function validateCardComposition(
        CerItem $item
    ): bool {
        $requiredTypes = [
            'claim',
            'evidence',
            'reasoning',
            'distractor_evidence',
            'distractor_reasoning',
        ];

        $actualTypes = $item
            ->optionCards()
            ->pluck('card_type')
            ->sort()
            ->values()
            ->toArray();

        $expectedTypes = collect($requiredTypes)
            ->sort()
            ->values()
            ->toArray();

        return $actualTypes === $expectedTypes;
    }
}