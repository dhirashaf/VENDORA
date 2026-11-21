<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreScale extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = 'score';

    protected $fillable = ['score', 'label', 'definition'];
}
