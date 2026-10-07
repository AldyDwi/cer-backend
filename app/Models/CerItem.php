<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CerItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'page_order',
    ];

    protected function casts(): array
    {
        return [
            'page_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function quiz()
    {
        return $this->belongsTo(CerQuiz::class, 'quiz_id');
    }

    public function optionCards()
    {
        return $this->hasMany(OptionCard::class, 'item_id');
    }

    public function claimCard()
    {
        return $this->hasOne(OptionCard::class, 'item_id')
            ->where('card_type', 'claim');
    }

    public function evidenceCard()
    {
        return $this->hasOne(OptionCard::class, 'item_id')
            ->where('card_type', 'evidence');
    }

    public function reasoningCard()
    {
        return $this->hasOne(OptionCard::class, 'item_id')
            ->where('card_type', 'reasoning');
    }

    public function distractorEvidenceCard()
    {
        return $this->hasOne(OptionCard::class, 'item_id')
            ->where('card_type', 'distractor_evidence');
    }

    public function distractorReasoningCard()
    {
        return $this->hasOne(OptionCard::class, 'item_id')
            ->where('card_type', 'distractor_reasoning');
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class, 'item_id');
    }
}