<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Klines15m extends Model
{
    use HasFactory;

    public function market(){
        return $this->belongsTo(Market::class);
    }
}
