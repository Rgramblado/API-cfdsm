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
    /**
     * @OA\Get(
     *  path="/klines/last",
     *  summary="Velas japonesas",
     *  @OA\Parameter(
     *      name = "symbol",
     *      description = "Nombre del mercado",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "interval",
     *      description = "Intervalo deseado (15m, 1h o 4h)",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve la última vela japonesa del mercado seleccionado, en el intervalo seleccionado"
     *  ),
     *  @OA\Response(
     *      response=429,
     *      description="Alguno de los datos introducidos no es correcto"
     *  )
     * )
     */
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
    /**
     * @OA\Get(
     *  path="/klines/",
     *  summary="Velas japonesas",
     *  @OA\Parameter(
     *      name = "symbol",
     *      description = "Nombre del mercado",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "interval",
     *      description = "Intervalo deseado (15m, 1h o 4h)",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve las velas japonesas del mercado seleccionado, en el intervalo seleccionado"
     *  ),
     *  @OA\Response(
     *      response=429,
     *      description="Alguno de los datos introducidos no es correcto"
     *  )
     * )
     */
    public function getKlines(Request $request){
        $request->validate([
            'symbol' => 'required|exists:markets,name',
            'interval' => ['required', Rule::in(['15m', '1h', '4h'])]
        ]);

        if($request->interval == '15m'){
            $result = Klines15m::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time', 'desc')->take(500)->get();
        }
        if($request->interval == '1h'){
            $result = Klines1h::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time', 'desc')->take(500)->get();
        }
        if($request->interval == '4h'){
            $result = Klines4h::whereRaw('market_id = (SELECT id FROM markets WHERE name=\'' . $request->symbol . '\')')
            ->orderBy('open_time', 'desc')->take(500)->get();
        }

        return json_encode($result);
    }
}
