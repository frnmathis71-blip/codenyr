<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEvent extends Model
{
    protected $guarded = ['id'];

    public static function record(int $projectId, string $description): void
    {
        static::create(['client_project_id' => $projectId, 'user_id' => auth()->id(), 'description' => $description]);
    }
}
