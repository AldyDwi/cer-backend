<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'content',
        'card_type',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function item()
    {
        return $this->belongsTo(CerItem::class, 'item_id');
    }

    public function claimAnswers()
    {
        return $this->hasMany(
            StudentAnswer::class,
            'claim_card_id'
        );
    }

    public function evidenceAnswers()
    {
        return $this->hasMany(
            StudentAnswer::class,
            'evidence_card_id'
        );
    }

    public function reasoningAnswers()
    {
        return $this->hasMany(
            StudentAnswer::class,
            'reasoning_card_id'
        );
    }
}