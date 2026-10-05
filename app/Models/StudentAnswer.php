<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'attempt_id',
        'item_id',
        'claim_card_id',
        'evidence_card_id',
        'reasoning_card_id',
        'is_claim_correct',
        'is_evidence_correct',
        'is_reasoning_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_claim_correct' => 'boolean',
            'is_evidence_correct' => 'boolean',
            'is_reasoning_correct' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function attempt()
    {
        return $this->belongsTo(
            QuizAttempt::class,
            'attempt_id'
        );
    }

    public function item()
    {
        return $this->belongsTo(
            CerItem::class,
            'item_id'
        );
    }

    public function claimCard()
    {
        return $this->belongsTo(
            OptionCard::class,
            'claim_card_id'
        );
    }

    public function evidenceCard()
    {
        return $this->belongsTo(
            OptionCard::class,
            'evidence_card_id'
        );
    }

    public function reasoningCard()
    {
        return $this->belongsTo(
            OptionCard::class,
            'reasoning_card_id'
        );
    }
}