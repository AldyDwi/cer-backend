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

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class, 'item_id');
    }
}