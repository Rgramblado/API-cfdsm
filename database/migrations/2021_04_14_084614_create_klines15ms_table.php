<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKlines15msTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('klines15ms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->timestamp('open_time')->nullable();
            $table->timestamp('close_time')->nullable();
            $table->unsignedDecimal('open', 20, 10);
            $table->unsignedDecimal('close', 20, 10);
            $table->unsignedDecimal('high', 20, 10);
            $table->unsignedDecimal('low', 20, 10);
            $table->unsignedDecimal('base_volume', 30, 10);
            $table->unsignedDecimal('taker_volume', 30, 10);
            $table->timestamps();

            $table->foreign('market_id')->references('id')->on('markets');
            $table->index('market_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('klines15ms');
    }
}
