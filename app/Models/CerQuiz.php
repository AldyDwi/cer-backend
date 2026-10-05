<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CerQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'material_id',
        'title',
        'description',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function items()
    {
        return $this->hasMany(CerItem::class, 'quiz_id')
            ->orderBy('page_order');
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class, 'quiz_id');
    }
}