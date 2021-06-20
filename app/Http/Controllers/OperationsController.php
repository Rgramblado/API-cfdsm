<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CurrentOperation;
use App\Models\HistoricalOperation;
use App\Models\Market;
use App\Models\Ticker24h;
use App\Models\User;
use App\Traits\ApiResponser;
use Carbon\Carbon;

class OperationsController extends Controller
{
    use ApiResponser;

    /**
     * @OA\Get(
     *  path="/api/user/operations/current",
     *  summary="Datos de las operaciones actuales del usuario",
     *  @OA\Response(
     *      response=200,
     *      description="Se ofrecen los datos de las operaciones actuales del usuario, de tipo market y limit ejecutadas."
     *  )
     * 
     * )
     */
    public function currentIndex(){
        return json_encode(CurrentOperation::with('market')
            ->where('user_id', '=', auth()->user()->id)
            ->whereNotNull('init_time')
            ->get());
    }

    /**
     * @OA\Get(
     *  path="/api/user/operations/pending",
     *  summary="Datos de las operaciones pendientes del usuario",
     *  @OA\Response(
     *      response=200,
     *      description="Se ofrecen los datos de las operaciones actuales del usuario, de tipo limit
     *      aun no ejecutadas."
     *  )
     * 
     * )
     */
    public function pendingIndex(){
        return json_encode(CurrentOperation::with('market')
            ->where('user_id', '=', auth()->user()->id)
            ->whereNull('init_time')
            ->get());
    }

    /**
     * @OA\Get(
     *  path="/api/user/operations/historical",
     *  summary="Datos de las operaciones históricas del usuario",
     *  
     *  @OA\Response(
     *      response=200,
     *      description="Se ofrecen los datos del histórico
     *      de operaciones del usuario."
     *  )
     * 
     * )
     */

    public function historicalIndex(){
        return json_encode(HistoricalOperation::with('market')
            ->where('user_id', '=', auth()->user()->id)
            ->orderBy('created_at', 'desc')
            ->get());
    }
    /**
     * @OA\Put(
     *  path="/api/user/operation/",
     *  summary="Creación de una nueva operación",
     *  @OA\Parameter(
     *      name = "symbol",
     *      description = "Mercado sobre el que realizar la operación",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="Number"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "is_long",
     *      description = "Operación de tipo long (true)/short (false)",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="Boolean"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "leverage",
     *      description = "Apalancamiento de la operación.",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="Number"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "margin",
     *      description = "Garantía ofrecida por el usuario en la operación.",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="Number"
     *      )
     *  ),
     *  @OA\Parameter(
     *      name = "limit_price",
     *      description = "Precio límite de la operación, en caso de ser una operación de tipo limit.",
     *      required = false,  
     *      in="path",
     *      @OA\Schema(
     *          type="Number"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="La operación se ha creado correctamente."
     *  ),
     *  @OA\Response(
     *      response=403,
     *      description="La garantía ofrecida (margin) es superior a la cantidad disponible de fondos."
     *  ),
     *  @OA\Response(
     *      response=429,
     *      description="Alguno de los parámetros no es del tipo correcto."
     *  )
     * 
     * )
     */
    public function addOperation(Request $request){
        $request->validate([
            'symbol' => 'required|exists:markets,name',
            'is_long' => 'required|boolean',
            'leverage' => 'required|integer',
            'margin' => 'required|numeric',
            'limit_price' => 'numeric'
        ]);

        if($request->margin > User::find(auth()->user()->id)->wallet){
            return $this->error("Cantidad excedida", 403);
        }

        $marketId = Market::where('name', '=', $request->symbol)->get()->first()->id;
        $currentPrice = Ticker24h::where('market_id', '=', $marketId)
                        ->get()                
                        ->first()
                        ->last_price;

        
        $operation = new CurrentOperation();
        $operation->user_id = auth()->user()->id;
        $operation->market_id = $marketId;
        $operation->init_time = empty($request->limit_price) ? Carbon::now() : null;
        $operation->is_long = $request->is_long;
        $operation->leverage = $request->leverage;
        $operation->margin = $request->margin;
        $operation->weight = $request->margin * $request->leverage / (empty($request->limit_price) ? $currentPrice : $request->limit_price);
        $operation->entry_price = empty($request->limit_price) ? $currentPrice : $request->limit_price;
        if($request->is_long){
            $operation->liquidation_price = (empty($request->limit_price) ? $currentPrice : $request->limit_price) * (1 - 1/$request->leverage);
        }else{
            $operation->liquidation_price = (empty($request->limit_price) ? $currentPrice : $request->limit_price) * (1 + 1/$request->leverage);
        }
        $operation->limit_price = empty($request->limit_price) ? null : $request->limit_price;
        
        $operation->save();

        $user = User::find(auth()->user()->id);
        $user->wallet = $user->wallet - $request->margin;
        $user->save();

        return $this->success(null, "Operación completada con éxito");
    }
    /**
     * @OA\Delete(
     *  path="/api/user/operation/",
     *  summary="Creación de una nueva operación",
     *  @OA\Parameter(
     *      name = "op_id",
     *      description = "Mercado sobre el que realizar la operación (id)",
     *      required = true,  
     *      in="path",
     *      @OA\Schema(
     *          type="Integer"
     *      )
     *  ),
     *  @OA\Response(
     *      response=200,
     *      description="La operación se ha creado correctamente."
     *  ),
     *  @OA\Response(
     *      response=403,
     *      description="La operación no existe, o el usuario no está autorizado a eliminarla."
     *  ),
     *  @OA\Response(
     *      response=429,
     *      description="Alguno de los parámetros no es del tipo correcto."
     *  )
     * 
     * )
     */
    public function closeOperation(Request $request){
        $request->validate([
            'op_id' => 'integer'
        ]);

        $operation = CurrentOperation::where('id', '=', $request->op_id)
            ->where('user_id', '=', auth()->user()->id)
            ->get()
            ->first();
        
        if(!$operation){
            return $this->error("Error en la ejecución de la orden", 403);
        }
        //Eliminamos la operación 
        CurrentOperation::find($request->op_id)->delete();
        
        $currentPrice = Ticker24h::find($operation->market_id)->last_price;
        $pnl = ($operation->is_long == 1 ? 1 : -1)*$operation->weight*($currentPrice-$operation->entry_price);
        HistoricalOperation::create([
            'user_id' => auth()->user()->id,
            'market_id' => $operation->market_id,
            'is_long' => $operation->is_long,
            'weight' => $operation->weight,
            'entry_price' => $operation->entry_price,
            'exit_price' => $currentPrice,
            'PNL' => $pnl,
        ]);

        $user = User::find(auth()->user()->id);
        $user->wallet = $user->wallet + $pnl + $operation->margin;
        $user->save();
    }
     
}
