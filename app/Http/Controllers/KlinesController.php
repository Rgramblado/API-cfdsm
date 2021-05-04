<?php

namespace App\Http\Controllers;

use App\Models\Klines15m;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class KlinesController extends Controller
{
    public function index(Request $request){
        $request->validate([
            'symbol' => 'required',
            'interval' => ['required', Rule::in(['15m', '1h', '4h'])]
        ]);

        if($request->interval == '15m'){
            $result = Klines15m::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time')->get()->last();
        }

        return json_encode($result);
    }
}
