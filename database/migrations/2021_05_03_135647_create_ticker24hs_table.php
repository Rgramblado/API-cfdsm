<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTicker24hsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ticker24hs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id')->unique();
            $table->decimal('last_price', 30, 10);
            $table->decimal('high', 30, 10);
            $table->decimal('low', 30, 10);
            $table->decimal('open', 30, 10);
            $table->decimal('price_change', 20, 10);
            $table->timestamps();

            $table->foreign('market_id')->references('id')->on('markets');
        });

        DB::unprepared('CREATE TRIGGER `Insert_ticker_24h_trigger` BEFORE INSERT ON `ticker24hs`
        FOR EACH ROW BEGIN
            SET NEW.created_at = CURRENT_TIMESTAMP();
            SET NEW.updated_at = CURRENT_TIMESTAMP();
        END');
        


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ticker24hs');
    }
}
