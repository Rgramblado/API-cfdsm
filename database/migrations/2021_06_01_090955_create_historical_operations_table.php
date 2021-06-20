<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateHistoricalOperationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('historical_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('market_id');
            $table->boolean('is_long');
            $table->float('weight');
            $table->float('entry_price');
            $table->float('exit_price');
            $table->float('PNL');
            $table->timestamps();
        });

        DB::unprepared('CREATE TRIGGER `liquidations_limits_trigger` BEFORE UPDATE ON `ticker24hs`
        FOR EACH ROW BEGIN
        /*DECLARE n_id_operation DECIMAL(20,0);
        DECLARE n_user_id DECIMAL(20,0);
        DECLARE n_entry_price DECIMAL(30,10);
        DECLARE n_weight DECIMAL(30,10);
        DECLARE n_market_id DECIMAL(20,0);
        DECLARE n_is_long boolean;
        DECLARE c CURSOR FOR
        SELECT id, user_id, market_id, entry_price, is_long, weight
        FROM current_operations
        WHERE market_id = NEW.market_id AND init_time IS NOT null
        AND ((is_long=1 AND NEW.last_price <= liquidation_price)
        OR (is_long=0 AND NEW.last_price >= liquidation_price)
        );*/

        SET NEW.updated_at = CURRENT_TIMESTAMP(); /* Updated at */
        /* Liquidaciones */
        /*OPEN c;
        BEGIN

        LOOP
        FETCH c INTO n_id_operation, n_user_id, n_market_id, n_entry_price, n_is_long, n_weight;

        INSERT INTO historical_operations (user_id, market_id, is_long, entry_price, exit_price, PNL)
        VALUES (n_user_id, n_market_id, n_is_long, n_entry_price, NEW.last_price, -ABS(NEW.last_price-n_entry_price)*n_weight );

        DELETE FROM current_operations WHERE id = n_id_operacion;

        END LOOP;
        END;
        CLOSE c;*/
        END;

        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('historical_operations');
    }
}
