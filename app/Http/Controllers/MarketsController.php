<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\Ticker24h;
use Illuminate\Http\Request;

class MarketsController extends Controller
{
    public function getMarkets(){
        return (json_encode(Market::all()));
    }

    public function getTicker(){
        return json_encode(Ticker24h::with('market')->get());
    }

}
