<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateKlines4hsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('klines4hs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->timestamp('open_time')->nullable();
            $table->timestamp('close_time')->nullable();
            $table->unsignedDecimal('open', 20, 10);
            $table->unsignedDecimal('close', 20, 10);
            $table->unsignedDecimal('high', 20, 10);
            $table->unsignedDecimal('low', 20, 10);
            $table->timestamps();

            $table->foreign('market_id')->references('id')->on('markets');
            $table->index('market_id');
            $table->unique(['market_id', 'open_time']);
        });

        DB::unprepared('CREATE TRIGGER `Update_kline_4h_trigger` BEFORE UPDATE ON `klines4hs`
        FOR EACH ROW BEGIN
               IF NEW.close > OLD.high THEN
                   SET NEW.high = NEW.close;
               END IF;
               IF NEW.close < OLD.low THEN
                   SET NEW.low = NEW.close;
               END IF;
               SET NEW.updated_at = CURRENT_TIMESTAMP();
           END');
        DB::unprepared('CREATE TRIGGER `Insert_kline_4h_trigger` BEFORE INSERT ON `klines4hs`
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
        Schema::dropIfExists('klines4hs');
        DB::unprepared('DROP TRIGGER `Update_kline_4h_trigger`');
    }
}
