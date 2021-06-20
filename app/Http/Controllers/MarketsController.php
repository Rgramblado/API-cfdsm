<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\Ticker24h;
use Illuminate\Http\Request;

class MarketsController extends Controller
{
    /**
     * @OA\Get(
     *  path="/api/markets",
     *  summary="Datos de los mercados",
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve los datos de los mercados"
     *  ),
     * )
     */
    public function getMarkets(){
        return (json_encode(Market::all()));
    }

    /**
     * @OA\Get(
     *  path="/api/market",
     *  summary="Datos de un mercado",
     *  @OA\Parameter(
     *      name = "market",
     *      description = "Nombre del mercado",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="String"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve los datos del mercado seleccionado"
     *  ),
     *  @OA\Response(
     *      response=429,
     *      description="Alguno de los datos introducidos no es correcto"
     *  ),
     * )
     */
    public function getMarket(Request $request){
        $request->validate([
            'market' => 'required|string'
        ]);
        return (json_encode(Market::where('name', '=', $request->market)->get()->first()));
    }

    /**
     * @OA\Get(
     *  path="/api/ticker/24h",
     *  summary="Datos de los precios de mercados",
     *  @OA\Response(
     *      response=200,
     *      description="Devuelve los precios de los mercados actualizados."
     *  ),
     * )
     */
    public function getTicker(){
        return json_encode(Ticker24h::with('market')->get());
    }

}
