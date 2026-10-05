<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'title',
        'content',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function cerQuizzes()
    {
        return $this->hasMany(CerQuiz::class, 'material_id');
    }
}