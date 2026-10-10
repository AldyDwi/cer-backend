<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\CerQuizController;
use App\Http\Controllers\Api\CerItemController;
use App\Http\Controllers\Api\UserController;


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication & Profile
    |--------------------------------------------------------------------------
    */

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    Route::patch(
        '/profile',
        [UserController::class, 'updateProfile']
    );

    Route::patch(
        '/profile/password',
        [UserController::class, 'updatePassword']
    );


    /*
    |--------------------------------------------------------------------------
    | Materials
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/materials/options',
        [MaterialController::class, 'options']
    );

    Route::apiResource(
        'materials',
        MaterialController::class
    );


    /*
    |--------------------------------------------------------------------------
    | Students
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'students',
        StudentController::class
    );


    /*
    |--------------------------------------------------------------------------
    | CER Quizzes
    |--------------------------------------------------------------------------
    */

    Route::apiResource(
        'cer-quizzes',
        CerQuizController::class
    )->only([
        'index',
        'store',
        'show',
        'update',
        'destroy',
    ]);


    /*
    |--------------------------------------------------------------------------
    | CER Quiz Status
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/cer-quizzes/{cerQuiz}/status',
        [CerQuizController::class, 'updateStatus']
    );


    /*
    |--------------------------------------------------------------------------
    | CER Quiz Items
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cer-quizzes/{cerQuiz}/items',
        [CerItemController::class, 'index']
    );

    Route::post(
        '/cer-quizzes/{cerQuiz}/items',
        [CerItemController::class, 'store']
    );

    Route::put(
        '/cer-items/{cerItem}',
        [CerItemController::class, 'update']
    );

    Route::delete(
        '/cer-items/{cerItem}',
        [CerItemController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | CER Distractor Generation
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/cer-quizzes/{cerQuiz}/generate-distractors',
        [CerItemController::class, 'generateDistractors']
    );


    /*
    |--------------------------------------------------------------------------
    | CER Grades
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/cer-quizzes/{cerQuiz}/grades',
        [CerQuizController::class, 'grades']
    );


    /*
    |--------------------------------------------------------------------------
    | Student Activities
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/student/cer-quizzes',
        [CerQuizController::class, 'publishedForStudent']
    );
});