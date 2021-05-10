<?php

namespace App\Http\Controllers;

use App\Models\Klines15m;
use App\Models\Klines1h;
use App\Models\Klines4h;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KlinesController extends Controller
{
    public function getLastKline(Request $request){
        $request->validate([
            'symbol' => 'required|exists:markets,name',
            'interval' => ['required', Rule::in(['15m', '1h', '4h'])]
        ]);

        if($request->interval == '15m'){
            $result = Klines15m::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time')->get()->last();
        }
        if($request->interval == '1h'){
            $result = Klines1h::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time')->get()->last();
        }
        if($request->interval == '4h'){
            $result = Klines4h::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time')->get()->last();
        }

        return json_encode($result);
    }

    public function getMarketsUTCData(){
        $result = DB::raw('SELECT m.name name, m.icon icon, MAX(k.high) high, MIN(k.low) low, t.last_price last_price
                            FROM klines4hs k JOIN markets m ON (k.market_id = m.id)
                                JOIN ticker24hs t on (m.id = t.market_id)
                            WHERE (k.open_time >= CURDATE())');
        return json_encode($result);
    }
}
