<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'market_id',
        'is_long',
        'weight',
        'entry_price',
        'exit_price',
        'PNL'
    ];

    public function market(){
        return $this->belongsTo(Market::class);
    }
}
